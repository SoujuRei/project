<?php

// Only execute Web/Browser headers and Sessions if NOT running via CLI
if (php_sapi_name() !== 'cli') {

    // 1. Determine and sanitize Allowed Origin
    $rawOrigin = getenv('FRONTEND_ORIGIN') ?: 'https://mercury.swin.edu.au';
    $parsedScheme = parse_url($rawOrigin, PHP_URL_SCHEME) ?: 'https';
    $parsedHost   = parse_url($rawOrigin, PHP_URL_HOST)   ?: 'mercury.swin.edu.au';
    $allowedOrigin = "{$parsedScheme}://{$parsedHost}";

    // 2. Set CORS Headers
    header("Access-Control-Allow-Origin: {$allowedOrigin}");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Credentials: true");

    // 3. Handle browser preflight OPTIONS requests
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit();
    }

    // 4. Configure cross-domain session cookies
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 86400 * 7, // 7 days
            'path'     => '/',
            'domain'   => '',        // Uses current Render host domain
            'secure'   => true,      // Required for HTTPS on Render
            'httponly' => true,      // Prevents XSS script access
            'samesite' => 'None'     // Required for Mercury -> Render cross-domain sessions
        ]);
        session_start();
    }
}

// 5. Load Helpers & Configs
$appConfig = require __DIR__ . '/config/app.php';

require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/helpers/request.php';
require_once __DIR__ . '/helpers/auth.php';

// 6. Database Connection
$config = require __DIR__ . '/config/db.php';

try {
    $db = getDbConnection();
} catch (PDOException $e) {
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Database connection failed: ' . $e->getMessage()
        ]);
    } else {
        echo "Database connection failed: " . $e->getMessage() . "\n";
    }

    exit(1);
}