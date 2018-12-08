<?php

// requiring the configurations file
require_once('config.php');

// checking if the queue id was provided
if (!isset($argv[1])) die('You must pass the queue id as argument to the script');

// forking the process to run in background
$pid = pcntl_fork();

if ($pid == -1) {
    die('could not fork');
} else if ($pid) {
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

// getting child pid 
$pid = posix_getpid();

// getting argument
$queueId = intval($argv[1]);

// initializing the database connection
$db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,DB_USER,DB_PASS,[PDO::ATTR_PERSISTENT => true]);

// getting the queued process from the database
$stmt = $db->query("SELECT * FROM `queue` WHERE `id`=$queueId");
$process = $stmt->fetch(PDO::FETCH_ASSOC);

// checking if the process exists
if (!$process) die('The specified queue id was not found');

// setting up custom error handler to nicely update the process status in the db
set_error_handler(function ($errno, $errstr) use ($process, $db) {
    $stmt = $db->prepare('UPDATE `queue` SET `status`=?, `pid`=? WHERE `id`=?');
    $stmt->execute([STATUS_FAILED,null,$process['id']]);
    $db->commit();

    exit(1);
});

// updating the process status to running
$stmt = $db->prepare('UPDATE `queue` SET `status`=?,`pid`=? WHERE `id`=?');
$stmt->execute([STATUS_RUNNING,$pid,$process['id']]);

// opening the file
$handler = fopen(DATA_DIR . '/' . $process['file'],'r');

// initializing the database transaction
$db->beginTransaction();

// registering signal handler to nicely stop the database transactions
function sig_terminate($signo) {
    global $db;
    $db->rollBack();
    unset($db);
    exit(0);
}

// setting up signals
pcntl_signal(SIGTERM, 'sig_terminate');
pcntl_signal(SIGINT, 'sig_terminate');

// iterating over the files
while (!feof($handler)) {
    // fetching the row from the CSV file
    $data = fgetcsv($handler);

    // preparing the statement
    $stmt = $db->prepare('INSERT INTO `ceps` (`cep`,`street`,`neighborhood`,`city`,`state`) VALUES (?,?,?,?,?)');

    // inserting
    $stmt->execute([$data[1],$data[2],$data[3],$data[4],$data[5]]);
}

// commiting the transaction
$db->commit();

// updating the status
$stmt = $db->prepare('UPDATE `queue` SET `status`=?,`finished_at`=NOW() WHERE `id`=?');
$stmt->execute([STATUS_FINISHED,$process['id']]);

// realeasing resources
unset($stmt);
unset($db);
