<?php
// This script creates the database and tables needed for the canonical PHP application.
// It assumes you have a configuration file "canoniconfig.php" with the following constants:
// - $CANON_DB_PREFIX: Prefix for the database name (e.g., '')
// - $CANON_DB_USER: Database username with CREATE privileges
// - $CANON_DB_PASSWORD: Database password
// - It connects to 'localhost' by default.
// If the database already exists, it will skip creation but attempt to create tables (which may fail if they exist).
// Run this script once to set up the database.
// WARNING: This script drops the database if it exists (comment out if unwanted).
// Adjust as needed.

require "canoniconfig.php"; // Include your config file with DB credentials

$host = $CANON_DB_HOST;
$dbname = $CANON_DB_PREFIX . 'canonical';

try {
    $pdo = new PDO("mysql:host=$host", $CANON_DB_USER, $CANON_DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if database exists
    $stmt = $pdo->query("SHOW DATABASES LIKE '$dbname'");
    if ($stmt->rowCount() > 0) 
    {
        echo "Database '$dbname' already exists. Exiting.\n";
        exit(0);
    }
    // $pdo->exec("DROP DATABASE IF EXISTS `$dbname`");

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Switch to the new database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $CANON_DB_USER, $CANON_DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Database '$dbname' created or already exists.\n";

    // Create tables (without IF NOT EXISTS to error if they exist; add IF NOT EXISTS if preferred)

    // TABLE: PHOTOS
    $pdo->exec("
        CREATE TABLE PHOTOS (
            MODEL VARCHAR(255) NOT NULL,
            VARI INT NOT NULL DEFAULT 0,
            APPROVED TINYINT(1) NOT NULL DEFAULT 0,
            DESCRIP TEXT,
            PHOTOURL VARCHAR(255),
            PHOTONAME VARCHAR(255),
            PHOTO MEDIUMBLOB,
            NAME VARCHAR(255),
            POSTDATE INT,
            ORDINAL INT NOT NULL DEFAULT 0,
            WIDTH INT NOT NULL DEFAULT 0,
            PRIMARY KEY (MODEL, VARI, PHOTONAME, WIDTH)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table PHOTOS created.\n";

    // TABLE: VISLOG
    $pdo->exec("
        CREATE TABLE VISLOG (
            ORDINAL INT AUTO_INCREMENT PRIMARY KEY,
            NAME VARCHAR(255),
            EDATE INT,
            EVENT TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table VISLOG created.\n";

    // TABLE: LASTUPDATE (single row table)
    $pdo->exec("
        CREATE TABLE LASTUPDATE (
            DATE VARCHAR(50)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    // Insert initial row
    $pdo->exec("INSERT INTO LASTUPDATE (DATE) VALUES ('')");
    echo "Table LASTUPDATE created.\n";

    // TABLE: NOTE
    $pdo->exec("
        CREATE TABLE NOTE (
            HEADER VARCHAR(255) PRIMARY KEY,
            DESCRIP TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table NOTE created.\n";

    // TABLE: CAT
    $pdo->exec("
        CREATE TABLE CAT (
            CATNAME VARCHAR(255) NOT NULL,
            PRODUCED TINYINT(1) NOT NULL,
            NOTE TEXT,
            ORDINAL INT NOT NULL DEFAULT 0,
            PRIMARY KEY (CATNAME, PRODUCED)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table CAT created.\n";

    // TABLE: ENTRY
    $pdo->exec("
        CREATE TABLE ENTRY (
            MODEL VARCHAR(255) NOT NULL,
            VARI INT NOT NULL DEFAULT 0,
            CATNAME VARCHAR(255) NOT NULL,
            PRODUCED TINYINT(1) NOT NULL,
            VERIFIED TINYINT(1) NOT NULL DEFAULT 0,
            SHORTBLURB TEXT,
            ALIASOF VARCHAR(255),
            ALIASOFV INT DEFAULT 0,
            PRIMARY KEY (MODEL, VARI, PRODUCED)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table ENTRY created.\n";

    // TABLE: OWNER
    $pdo->exec("
        CREATE TABLE OWNER (
            NAME VARCHAR(255) PRIMARY KEY,
            EMAIL VARCHAR(255) UNIQUE,
            PASSWORD VARCHAR(255),
            ACCESS INT NOT NULL DEFAULT 0,
            PRIVATE TINYINT(1) NOT NULL DEFAULT 0,
            CODE VARCHAR(10)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table OWNER created.\n";

    // TABLE: OWNERS
    $pdo->exec("
        CREATE TABLE OWNERS (
            NAME VARCHAR(255) NOT NULL,
            MODEL VARCHAR(255) NOT NULL,
            VARI INT NOT NULL DEFAULT 0,
            SERIAL VARCHAR(255),
            SERIALPHOTONAME VARCHAR(255),
            SERIALPHOTO MEDIUMBLOB,
            APPROVED TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (NAME, MODEL, VARI)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table OWNERS created.\n";

    // TABLE: TOKEN
    $pdo->exec("
        CREATE TABLE TOKEN (
            TOKEN VARCHAR(255) PRIMARY KEY,
            NAME VARCHAR(255) NOT NULL,
            EXPIRE INT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table TOKEN created.\n";
    
    // TABLE: THROTTLE
    $pdo->exec("
        CREATE TABLE THROTTLE (
            TYPE INT NOT NULL,
            IP VARCHAR(255) NOT NULL,
            EMAIL VARCHAR(255) NOT NULL,
            EXPIRE INT NOT NULL,
            INDEX idx_expire (EXPIRE),
            INDEX idx_type (TYPE)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table TOKEN created.\n";
    
    echo "All tables created successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}