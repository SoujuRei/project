<?php

require_once __DIR__ . '/../bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];


if ($method === 'GET') {

    $logId = (int) (
        $_GET['log_id']
        ?? $_GET['logId']
        ?? 0
    );

    if (!$logId) {
        jsonError('Missing log id.');
    }

    $stmt = $db->prepare(
        'SELECT
            c.id,
            c.log_id,
            c.author_id,
            c.text,
            c.created_at AS date,
            u.username AS author
         FROM comments c
         JOIN users u ON u.id = c.author_id
         WHERE c.log_id = ?
         ORDER BY c.created_at ASC'
    );

    $stmt->execute([$logId]);

    $comments = $stmt->fetchAll();

    foreach ($comments as &$comment) {
        $comment['id'] = (int) $comment['id'];
        $comment['log_id'] = (int) $comment['log_id'];
        $comment['author_id'] = (int) $comment['author_id'];
    }

    jsonResponse([
        'data' => $comments
    ]);
}


if ($method === 'POST') {
    $userId = requireAuth();
    $body = getJsonBody();

    $logId = (int) (
    $body['log_id']
    ?? $body['logId']
    ?? 0
);
    $text = trim($body['text'] ?? '');

    if (!$logId) {
        jsonError('Missing log id.');
    }

    if ($text === '') {
        jsonError('Comment cannot be empty.');
    }

    if (mb_strlen($text) > 5000) {
        jsonError('Comment is too long.');
    }

    // Verify log exists.
    $stmt = $db->prepare(
        'SELECT id FROM logs WHERE id = ?'
    );
    $stmt->execute([$logId]);

    if (!$stmt->fetch()) {
        jsonError('Log not found.', 404);
    }

    $stmt = $db->prepare(
        'INSERT INTO comments (log_id, author_id, text)
         VALUES (?, ?, ?)'
    );

    $stmt->execute([
        $logId,
        $userId,
        $text
    ]);

    $commentId = (int) $db->lastInsertId();

    $stmt = $db->prepare(
        'SELECT
            c.id,
            c.log_id,
            c.author_id,
            c.text,
            c.created_at,
            u.username AS author
         FROM comments c
         JOIN users u ON u.id = c.author_id
         WHERE c.id = ?'
    );

    $stmt->execute([$commentId]);

    $comment = $stmt->fetch();

    jsonResponse([
        'data' => $comment
    ], 201);
}


if ($method === 'DELETE') {
    $userId = requireAuth();

    $commentId = (int) ($_GET['id'] ?? 0);

    if (!$commentId) {
        jsonError('Missing comment id.');
    }

    $stmt = $db->prepare(
        'SELECT c.author_id, u.role
         FROM comments c
         JOIN users u ON u.id = c.author_id
         WHERE c.id = ?'
    );

    $stmt->execute([$commentId]);

    $comment = $stmt->fetch();

    if (!$comment) {
        jsonError('Comment not found.', 404);
    }

    if (
        (int) $comment['author_id'] !== $userId &&
        $comment['role'] !== 'admin'
    ) {
        jsonError(
            'You can only delete your own comments.',
            403
        );
    }

    $stmt = $db->prepare(
        'DELETE FROM comments WHERE id = ?'
    );

    $stmt->execute([$commentId]);

    jsonResponse([
        'success' => true
    ]);
}


jsonError('Method not allowed.', 405);