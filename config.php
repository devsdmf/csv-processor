<?php

// This is the project configuration file to easily get it up and running

// Database connection settings
define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS',null);
define('DB_NAME','csv_processor');

// Directories and files
define('DATA_DIR',realpath(__DIR__) . '/data');
define('PHP_BIN',trim(exec('which php')));
define('PROCESSOR_SCRIPT',realpath(__DIR__) . '/processor.php');

// Runtime configurations
define('PROCESS_UID',501);
define('PROCESS_GID',20);
define('ENABLE_FILE_CHUNKS',true);
define('MAX_LINES_PER_CHUNK',10000);

// Process statuses
define('STATUS_PENDING','pending');
define('STATUS_CHUNKED','chunked');
define('STATUS_QUEUED','queued');
define('STATUS_RUNNING','running');
define('STATUS_FINISHED','finished');
define('STATUS_FAILED','failed');
