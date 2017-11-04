<?php

// requiring the configurations file
require_once('config.php');

// the signal handler
function sig_terminate($signo) {
    global $db, $stmt;
    unset($stmt);
    unset($db);
}

// setting up signals
pcntl_signal(SIGTERM, 'sig_terminate');
pcntl_signal(SIGINT, 'sig_terminate');

// forking to child
$pid = pcntl_fork();

if ($pid == -1) {
    die('An error occurred at try to fork the process to a child');
} else if ($pid) {
    // waiting for the child initializing
    pcntl_wait($status, WNOHANG);
    exit(0);
} else {
    // here start the child
    posix_setsid();
    posix_setuid(PROCESS_UID);
    posix_setgid(PROCESS_GID);

    // closing descriptors
    fclose(STDIN);
    fclose(STDOUT);
    fclose(STDERR);
}

// getting the child pid
$pid = posix_getpid();

// getting the database connection
$db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,DB_USER,DB_PASS,[PDO::ATTR_PERSISTENT => true]);

while (true) {
    // fetching pending process from the queue
    $stmt = $db->query("SELECT * FROM `queue` WHERE `status`='" . STATUS_PENDING . "'");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // files to be processed in queue
    $queue = new SplQueue();
    $queue->setIteratorMode(SplQueue::IT_MODE_DELETE);

    // looping into results
    foreach($data as $row) {
        if (ENABLE_FILE_CHUNKS) {
            // checking the number of lines in file
            preg_match('/[0-9]+/', exec('wc -l ' . DATA_DIR . '/' . $row['file']), $result);
            if (intval($result[0]) > MAX_LINES_PER_CHUNK) {
                // generating a prefix to prepend the new generated files
                $prefix = uniqid();

                // changing the directory to split the files
                chdir(DATA_DIR);

                // splitting the csv file into chunks
                exec("split -l 100000 " . $row['file'] . ' ' . $prefix . '-');
                exec('for f in ' . $prefix . '*; do mv "$f" "$f.csv"; done');

                // creating the new process in the queue
                foreach (glob($prefix . '*.csv') as $file) {
                    $stmt = $db->prepare('INSERT INTO `queue` (`parent`,`file`,`status`,`created_at`) VALUES (?,?,?,NOW())');
                    $stmt->execute([$row['id'],$file,STATUS_QUEUED]);

                    // adding next process to be initiated
                    $queue->push($db->lastInsertId());
                }

                // updating the status of the main queue to chunked
                $stmt = $db->prepare('UPDATE `queue` SET `status`=? WHERE `id`=?');
                $stmt->execute([STATUS_CHUNKED,$row['id']]);
            } else {
                // adding the process to be initiated
                $queue->push($row['id']);

                // updating the status to queued
                $stmt = $db->prepare('UPDATE `queue` SET `status`=? WHERE `id`=?');
                $stmt->execute([STATUS_QUEUED,$row['id']]);
            }
        } else {
            // adding the process to be initiated
            $queue->push(intval($row['id']));

            // updating the status to queued
            $stmt = $db->prepare('UPDATE `queue` SET `status`=? WHERE `id`=?');
            $stmt->execute([STATUS_QUEUED,$row['id']]);
        }
    }

    // initializing the processes
    $queue->rewind();
    while (!$queue->isEmpty()) {
        // getting the next process
        $queueId = $queue->pop();

        // initializng the processor
        exec(implode(' ',[PHP_BIN,PROCESSOR_SCRIPT,$queueId,' > /dev/null 2>&1']));
    }

    // checking for chunked process state
    $stmt = $db->query("SELECT * FROM `queue` WHERE `status`='" . STATUS_CHUNKED ."'");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // looping into results
    foreach ($data as $row) {
        // fetching subprocesses
        $stmt = $db->query("SELECT * FROM `queue` WHERE `parent`=" . $row['id']);
        $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // control var for running processes
        $isRunning = false;

        // checking if all process has finished
        foreach ($subs as $sub) {
            if ($sub['status'] === STATUS_RUNNING) {
                $isRunning = true;
            }
        }

        // checking before update
        if (!$isRunning) {
            // updating the parent process to finished when all processes finished
            $stmt = $db->prepare("UPDATE `queue` SET `status`=?,`finished_at`=NOW() WHERE `id`=?");
            $stmt->execute([STATUS_FINISHED,$row['id']]);
        }
    }
    
    sleep(10);
}

