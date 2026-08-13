<?php

$settings = require __DIR__ . '/settings.php';

$environment = $settings['environment'];

if (!isset($settings['db'][$environment])) {
    die('Invalid application environment.');
}

$config = $settings['db'][$environment];

function getDbConnection(): PDO
{
    global $config;

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['name']
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (!empty($config['ssl_ca'])) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $config['ssl_ca'];
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }

    return new PDO(
        $dsn,
        $config['user'],
        $config['pass'],
        $options
    );
}

return $config;