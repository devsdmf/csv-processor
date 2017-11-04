# Improved PHP CSV Processor

This is a simple project to illustrate the daemons usage using PHP.

## Requirements

- PHP 5.x or 7.x
- PCNTL extension
- `exec` function enabled
- MySQL 5.6+

## Installation

### Downloading

There is no any kind of installation script, just clone this repository using the following command:

```
$ git clone https://github.com/devsdmf/campus-party-csv-processor
```

### Database

Create a database that will be used to save the processed data and to store the process queue:

```sql
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

DROP TABLE IF EXISTS `ceps`;

CREATE TABLE `ceps` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cep` varchar(8) NOT NULL DEFAULT '',
  `street` varchar(1024) NOT NULL DEFAULT '',
  `neighborhood` varchar(1024) NOT NULL DEFAULT '',
  `city` varchar(1024) NOT NULL DEFAULT '',
  `state` varchar(1024) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `queue`;

CREATE TABLE `queue` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent` int(11) DEFAULT NULL,
  `file` varchar(1024) NOT NULL DEFAULT '',
  `file_size` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `attempts` int(1) NOT NULL DEFAULT '0',
  `pid` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

```

### Configuring

In the root of the project, there is a file called `config.php` that store the configurations of the application, the available settings are:

- DB_HOST: The database host
- DB_USER: The database user
- DB_PASS: The database password
- DB_NAME: The database name
- DATA_DIR: The directory where the CSV files are located
- PHP_BIN: The path to the PHP binary (auto-detected using `which` command)
- PROCESSOR_SCRIPT: The path to the CSV processor script
- PROCESS_UID: The user that will be used to run the daemon and the processor script (1)
- PROCESS_GID: The group that will be used to run the daemon and the processor script (1)
- ENABLE_FILE_CHUNKS: Turn on/off the chunk of large CSV files (enabled by default)
- MAX_LINES_PER_CHUNK: The maximum number of lines per chunked file (default is 10000 lines per chunk)
- STATUS_PENDING: The status constant for pending processes in queue
- STATUS_CHUNKED: The status constant for chunked processes in queue
- STATUS_QUEUED: The status constant for queued processes in queue
- STATUS_RUNNING: The status constant for running processes in queue
- STATUS_FINISHED: The status constant for finished processes in queue
- STATUS_FAILED: The status constant for failed processes in queue 

(1) - To get the uid and gid of your current user, just type the `id` command in your terminal app.

### Running

To start the daemon just execute it using the php command:

```
$ php csvd.php
```

To check if is it running, use the following command:

```
$ ps aux | grep csvd.php
```

To check if there is any processor running, use the following command:

```
$ ps aux | grep processor.php
```

### Adding a CSV to be processed in the queue

To add a CSV to be processed, just add it to the queue's table in the database with the status pending:

```sql
INSERT INTO `queue` (`file`,`status`,`created_at`) VALUES ('sample.csv','pending',NOW());
```

(1) - Make sure that the daemon is running using the command above

(2) - You can check the status of the process directly in the status column in the database

(3) - You can download a [sample.csv]() file in the following link

## Credit

I would like to thank you the [CEP Aberto](http://cepaberto.com/) project to make available the seed that was used to make this project.

# License

This project is licensed under the [MIT license](LICENSE).

