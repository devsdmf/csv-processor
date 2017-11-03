<?php

// setting up constants and configurations
define('DATA_DIR',realpath(__DIR__ . '/data'));
define('PROCESSOR',realpath(__DIR__) . '/processor.php');
define('ENABLE_CHUNK',true);
define('CHUNK_MAX_LINES',100000);

// the signal handler
function sig_handler($signo) {
    echo "caugh a signal $signo" . PHP_EOL;
}

// setting up signals
pcntl_signal(SIGTERM, 'sig_handler');
pcntl_signal(SIGINT, 'sig_handler');

// forking to child
$pid = pcntl_fork();

if ($pid == -1) {
    die('could not fork');
} else if ($pid) {
    pcntl_wait($status, WNOHANG);
    exit(0);
} else {
    // here start the child
    posix_setsid();
    posix_setuid(501);
    posix_setgid(20);
}

// getting the child pid
$pid = posix_getpid();

// getting the database connection
$db = new PDO('mysql:host=localhost;dbname=csv_processor','root',null);

while (true) {
    // fetching pending process from the queue
    $stmt = $db->query("SELECT * FROM `queue` WHERE `status`='pending'");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // files to be processed in queue
    $queue = new SplQueue();
    $queue->setIteratorMode(SplQueue::IT_MODE_DELETE);

    // looping into results
    foreach($data as $row) {
        if (ENABLE_CHUNK) {
            // checking the number of lines in file
            preg_match('/[0-9]+/', exec('wc -l ' . DATA_DIR . '/' . $row['file']), $result);
            if (intval($result[0]) > CHUNK_MAX_LINES) {
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
                    $stmt->execute([$row['id'],$file,'queued']);

                    // adding next process to be initiated
                    $queue->push($db->lastInsertId());
                }

                // updating the status of the main queue to chunked
                $stmt = $db->prepare('UPDATE `queue` SET `status`=? WHERE `id`=?');
                $stmt->execute(['chunked',$row['id']]);
            } else {
                // adding the process to be initiated
                $queue->push($row['id']);

                // updating the status to queued
                $stmt = $db->prepare('UPDATE `queue` SET `status`=? WHERE `id`=?');
                $stmt->execute(['queued',$row['id']]);
            }
        } else {
            // adding the process to be initiated
            $queue->push(intval($row['id']));

            // updating the status to queued
            $stmt = $db->prepare('UPDATE `queue` SET `status`=? WHERE `id`=?');
            $stmt->execute(['queued',$row['id']]);
        }
    }

    // initializing the processes
    $queue->rewind();
    while (!$queue->isEmpty()) {
        // getting the next process
        $queueId = $queue->pop();

        // initializng the processor
        exec("/usr/local/opt/php71/bin/php " . PROCESSOR . ' ' . $queueId . ' > /dev/null 2>&1');
    }

    // checking for chunked process state
    $stmt = $db->query("SELECT * FROM `queue` WHERE `status`='chunked'");
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
            if ($sub['status'] === 'running') {
                $isRunning = true;
            }
        }

        // checking before update
        if (!$isRunning) {
            // updating the parent process to finished when all processes finished
            $stmt = $db->prepare("UPDATE `queue` SET `status`=?,`finished_at`=NOW() WHERE `id`=?");
            $stmt->execute(['finished',$row['id']]);
        }
    }
    
    sleep(10);
}

