<?php
const DB_HOST = 'sql104.infinityfree.com';
const DB_PORT = 3306;
// Neu biet ten database chinh xac tren InfinityFree, thay XXX bang phan duoi that.
// Vi du: if0_42307724_eduquest
const DB_NAME = 'if0_42307724_edusystem_php';
const DB_USER = 'if0_42307724';
const DB_PASS = '161194Asd';
const DB_CHARSET = 'utf8mb4';

function resolved_db_name(): string {
    static $name = null;
    if ($name !== null) return $name;

    $configured = trim(DB_NAME);
    if ($configured !== '' && substr($configured, -4) !== '_XXX') {
        return $name = $configured;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=' . DB_CHARSET;
    $server = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $prefix = 'if0_42307724_%';
    $st = $server->prepare('SHOW DATABASES LIKE ?');
    $st->execute([$prefix]);
    $found = $st->fetchColumn();
    if (!$found) {
        throw new RuntimeException('Khong tim thay database InfinityFree co prefix if0_42307724_. Hay cap nhat DB_NAME trong config/database.php.');
    }
    return $name = (string)$found;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . resolved_db_name() . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
    ]);
    return $pdo;
}
