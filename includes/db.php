<?php
/**
 * PDO bootstrap. Exposes $pdo.
 */
if (!defined('DB_HOST')) {
    require_once dirname(__DIR__) . '/config.php';
}

$dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
    // Align MySQL's session time zone with PHP so NOW() matches app time.
    $offset = (new DateTime('now', new DateTimeZone(date_default_timezone_get())))->format('P');
    $pdo->exec('SET time_zone = ' . $pdo->quote($offset));
} catch (PDOException $e) {
    throw new RuntimeException('Database connection failed: ' . $e->getMessage());
}
