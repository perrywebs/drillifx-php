<?php
// Central MySQL configuration.
//
// Priority: environment variables (recommended on cPanel) -> defaults.
// On cPanel shared hosting, create the database + user in "MySQL Databases"
// (names usually look like cpuser_drillifx) and either set these env vars
// (via .htaccess SetEnv or MultiPHP settings) or edit the defaults below.
//
//   DB_HOST    default 'localhost'   (cPanel almost always uses localhost)
//   DB_PORT    default 3306
//   DB_NAME    e.g. 'cpuser_drillifx'
//   DB_USER    e.g. 'cpuser_drillifx'
//   DB_PASS    the database user password
//   DB_CHARSET default 'utf8mb4'
//
// PHP 8.1 compatible. Keep this file outside public access (.htaccess blocks it).
$dbHost = getenv('DB_HOST');
$dbPort = getenv('DB_PORT');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbCharset = getenv('DB_CHARSET');

return [
    'host' => (is_string($dbHost) && trim($dbHost) !== '') ? trim($dbHost) : 'localhost',
    'port' => (is_string($dbPort) && (int)$dbPort > 0) ? (int)$dbPort : 3308,
    'db'   => (is_string($dbName) && trim($dbName) !== '') ? trim($dbName) : 'drillifx',
    'user' => (is_string($dbUser) && trim($dbUser) !== '') ? trim($dbUser) : 'root',
    'pass' => is_string($dbPass) ? $dbPass : 'secret',
    'charset' => (is_string($dbCharset) && trim($dbCharset) !== '') ? trim($dbCharset) : 'utf8mb4',
];
