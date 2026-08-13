<?php

require_once __DIR__ . '/../bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

/*
 * POST /votes.php
 * Body: { "log_id": 123 }
 *
 * Toggles a vote for the authenticated user:
 * - If voted: removes vote and decrements count.
 * - If not voted: adds vote and increments count.
 */
if ($method === 'POST') {
    $userId = requireAuth();
    $body = getJsonBody();

    $logId = (int) ($body['log_id'] ?? $body['logId'] ?? 0);

    if (!$logId) {
        jsonError('Missing log id.');
    }

    // Check log existence
    $stmt = $db->prepare('SELECT id FROM logs WHERE id = ?');
    $stmt->execute([$logId]);

    if (!$stmt->fetch()) {
        jsonError('Log not found.', 404);
    }

    try {
        $db->beginTransaction();

        // Check current vote status
        $stmt = $db->prepare('SELECT id FROM votes WHERE log_id = ? AND user_id = ?');
        $stmt->execute([$logId, $userId]);
        $existingVote = $stmt->fetch();

        if ($existingVote) {
            // UNVOTE
            $stmt = $db->prepare('DELETE FROM votes WHERE log_id = ? AND user_id = ?');
            $stmt->execute([$logId, $userId]);

            $stmt = $db->prepare('UPDATE logs SET vote_count = GREATEST(vote_count - 1, 0) WHERE id = ?');
            $stmt->execute([$logId]);

            $voted = false;
        } else {
            // UPVOTE
            $stmt = $db->prepare('INSERT INTO votes (log_id, user_id) VALUES (?, ?)');
            $stmt->execute([$logId, $userId]);

            $stmt = $db->prepare('UPDATE logs SET vote_count = vote_count + 1 WHERE id = ?');
            $stmt->execute([$logId]);

            $voted = true;
        }

        // Fetch fresh vote count
        $stmt = $db->prepare('SELECT vote_count FROM logs WHERE id = ?');
        $stmt->execute([$logId]);
        $votes = (int) $stmt->fetchColumn();

        $db->commit();

        jsonResponse([
            'success' => true,
            'voted' => $voted,
            'votes' => $votes
        ]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        jsonError('Unable to process vote toggle.', 500);
    }
}

/*
 * DELETE /votes.php?log_id=123
 *
 * Explicit unvote handler if invoked with DELETE method.
 */
if ($method === 'DELETE') {
    $userId = requireAuth();

    $logId = (int) ($_GET['log_id'] ?? 0);

    if (!$logId) {
        jsonError('Missing log id.');
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare('DELETE FROM votes WHERE log_id = ? AND user_id = ?');
        $stmt->execute([$logId, $userId]);

        if ($stmt->rowCount() > 0) {
            $stmt = $db->prepare('UPDATE logs SET vote_count = GREATEST(vote_count - 1, 0) WHERE id = ?');
            $stmt->execute([$logId]);
        }

        $stmt = $db->prepare('SELECT vote_count FROM logs WHERE id = ?');
        $stmt->execute([$logId]);
        $votes = (int) $stmt->fetchColumn();

        $db->commit();

        jsonResponse([
            'success' => true,
            'voted' => false,
            'votes' => $votes
        ]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        jsonError('Unable to remove vote.', 500);
    }
}

jsonError('Method not allowed.', 405);