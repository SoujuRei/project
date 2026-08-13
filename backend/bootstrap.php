<?php

session_start();

$appConfig = require __DIR__ . '/config/app.php';

$frontendOrigin = $appConfig['frontend_origin'];

header("Access-Control-Allow-Origin: {$frontendOrigin}");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/helpers/request.php';
require_once __DIR__ . '/helpers/auth.php';

$config = require __DIR__ . '/config/db.php';

try {
    $db = getDbConnection();
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');

    echo json_encode([
        'error' => 'Database connection failed.'
    ]);

    exit;
}