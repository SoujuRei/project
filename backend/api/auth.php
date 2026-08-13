<?php

require_once __DIR__ . '/../bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($action === 'me') {
    if ($method !== 'GET') {
        jsonError('Method not allowed.', 405);
    }

    if (empty($_SESSION['user_id'])) {
        jsonError('Not authenticated.', 401);
    }

    $stmt = $db->prepare(
        'SELECT id, username, email, role
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([(int) $_SESSION['user_id']]);

    $user = $stmt->fetch();

    if (!$user) {
        logoutUser();
        jsonError('User not found.', 401);
    }

    $user['id'] = (int) $user['id'];

    jsonResponse([
        'user' => $user
    ]);
}


if ($action === 'login') {
    if ($method !== 'POST') {
        jsonError('Method not allowed.', 405);
    }

    $body = getJsonBody();

    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if ($username === '' || $password === '') {
        jsonError('Username and password are required.');
    }

    $stmt = $db->prepare(
        'SELECT id, username, email, password_hash, role
         FROM users
         WHERE username = ? OR email = ?
         LIMIT 1'
    );

    $stmt->execute([$username, $username]);

    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonError('Invalid credentials.', 401);
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];

    jsonResponse([
        'user' => [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
        ]
    ]);
}


if ($action === 'register') {
    if ($method !== 'POST') {
        jsonError('Method not allowed.', 405);
    }

    $body = getJsonBody();

    $username = trim($body['username'] ?? '');
    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
        jsonError('Username, email and password are required.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Invalid email address.');
    }

    if (strlen($password) < 8) {
        jsonError('Password must be at least 8 characters.');
    }

    $stmt = $db->prepare(
        'SELECT id
         FROM users
         WHERE username = ? OR email = ?
         LIMIT 1'
    );

    $stmt->execute([$username, $email]);

    if ($stmt->fetch()) {
        jsonError('Username or email is already registered.', 409);
    }

    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    $stmt = $db->prepare(
        'INSERT INTO users
            (username, email, password_hash)
         VALUES (?, ?, ?)'
    );

    $stmt->execute([
        $username,
        $email,
        $passwordHash
    ]);

    $userId = (int) $db->lastInsertId();

    $_SESSION['user_id'] = $userId;

    jsonResponse([
        'user' => [
            'id' => $userId,
            'username' => $username,
            'email' => $email,
            'role' => 'member',
        ]
    ], 201);
}


if ($action === 'logout') {
    if ($method !== 'POST') {
        jsonError('Method not allowed.', 405);
    }

    logoutUser();

    jsonResponse([
        'success' => true
    ]);
}


jsonError('Unknown authentication action.', 404);