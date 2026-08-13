<?php
// index.php

// Load all configuration & CORS from bootstrap
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

// Health check response for Render and direct root browser visits
echo json_encode([
    'status'  => 'success',
    'message' => 'Backend API on Render is running successfully.'
]);