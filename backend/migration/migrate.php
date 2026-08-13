<?php
/**
 * One-off migration: imports localStorage JSON fixtures into MySQL / TiDB.
 *
 * CLI Usage:
 *   php migrate.php /path/to/users.json /path/to/logs.json /path/to/comments.json
 *
 * Web Usage (Fallback):
 *   https://your-app.onrender.com/migrate.php?key=YOUR_SECRET_KEY
 */

require_once __DIR__ . '/../bootstrap.php';

// If run from web browser, require a simple secret key check
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain');
    $secret = getenv('MIGRATE_SECRET') ?: 'sees-migration-2026';
    if (($_GET['key'] ?? '') !== $secret) {
        http_response_code(403);
        die("Access denied. Invalid migration key.\n");
    }
}

$db = getDbConnection();

$usersPath    = $argv[1] ?? __DIR__ . '/fixtures/users.json';
$logsPath     = $argv[2] ?? __DIR__ . '/fixtures/logs.json';
$commentsPath = $argv[3] ?? __DIR__ . '/fixtures/comments.json';

function readJson(string $path): ?array {
    if ($path === '-' || !file_exists($path)) return null;
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

// ---- Users ----
$usernameToId = [];

// Pre-load all existing users from database into memory
foreach ($db->query('SELECT id, username FROM users') as $row) {
    $usernameToId[$row['username']] = (int) $row['id'];
}

$users = readJson($usersPath);
if ($users !== null) {
    echo "Importing " . count($users) . " users...\n";
    
    $checkStmt  = $db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $insertStmt = $db->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)');

    foreach ($users as $u) {
        $username = $u['username'];
        
        // Check if user already exists in DB
        $checkStmt->execute([$username]);
        $existingId = $checkStmt->fetchColumn();

        if ($existingId) {
            $usernameToId[$username] = (int) $existingId;
            continue;
        }

        $hash = password_hash($u['password'] ?? bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $insertStmt->execute([
            $username,
            $u['email'] ?? ($username . '@migrated.local'),
            $hash,
            $u['role'] ?? 'member',
        ]);
        $usernameToId[$username] = (int) $db->lastInsertId();
    }
    echo "  Done. " . count($usernameToId) . " user accounts active.\n";
} else {
    echo "Skipping users JSON (file not found or skipped).\n";
}

function getOrCreateUserId(PDO $db, array &$map, string $username): int {
    if (isset($map[$username])) return $map[$username];

    $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        $username, 
        "{$username}@migrated.local", 
        password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT), 
        'member'
    ]);
    
    $id = (int) $db->lastInsertId();
    $map[$username] = $id;
    echo "  Created placeholder account for user \"{$username}\".\n";
    return $id;
}

// ---- Logs ----
$oldIdToNewId = [];

$logs = readJson($logsPath);
if ($logs !== null) {
    echo "Importing " . count($logs) . " logs...\n";
    $insertStmt = $db->prepare('INSERT INTO logs
        (author_id, title, date, block, floor, start_floor, end_floor, blocks_covered, co_authors,
         party_members, difficulty, exploration_goal, outcome, strategy_notes, overall_notes,
         shadows, gatekeeper, treasure, shuffle_time, discoveries, custom_info, vote_count)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    
    $mediaStmt = $db->prepare('INSERT INTO log_media (log_id, type, url, caption, position) VALUES (?, ?, ?, ?, ?)');

    foreach ($logs as $log) {
        $authorId = getOrCreateUserId($db, $usernameToId, $log['author'] ?? 'unknown');

        $insertStmt->execute([
            $authorId,
            $log['title'] ?? null,
            $log['date'] ?? date('Y-m-d'),
            $log['block'] ?? '',
            $log['floor'] ?? ($log['startFloor'] ?? 0),
            $log['startFloor'] ?? null,
            $log['endFloor'] ?? null,
            json_encode($log['blocksCovered'] ?? []),
            json_encode($log['coAuthors'] ?? []),
            json_encode($log['partyMembers'] ?? []),
            $log['difficulty'] ?? null,
            $log['explorationGoal'] ?? null,
            $log['outcome'] ?? null,
            $log['strategyNotes'] ?? ($log['strategyNote'] ?? ''),
            $log['overallNotes'] ?? null,
            json_encode($log['shadows'] ?? []),
            json_encode($log['gatekeeper'] ?? ['encountered' => false]),
            json_encode($log['treasure'] ?? []),
            json_encode($log['shuffleTime'] ?? []),
            json_encode($log['discoveries'] ?? []),
            json_encode($log['customInfo'] ?? []),
            (int) ($log['votes'] ?? 0),
        ]);

        $newId = (int) $db->lastInsertId();
        if (isset($log['id'])) {
            $oldIdToNewId[$log['id']] = $newId;
        }

        foreach ($log['media'] ?? [] as $i => $item) {
            if (empty($item['url'])) continue;
            $mediaStmt->execute([$newId, $item['type'] ?? 'image', $item['url'], $item['caption'] ?? null, $i]);
        }
    }
    echo "  Done. " . count($oldIdToNewId) . " logs imported.\n";
} else {
    echo "Skipping logs JSON (file not found or skipped).\n";
}

// ---- Comments ----
$comments = readJson($commentsPath);
if ($comments !== null) {
    echo "Importing comments...\n";
    $stmt = $db->prepare('INSERT INTO comments (log_id, author_id, text, created_at) VALUES (?, ?, ?, ?)');
    $imported = 0;
    $skipped = 0;

    foreach ($comments as $oldLogId => $commentList) {
        if (!isset($oldIdToNewId[$oldLogId])) {
            $skipped += count($commentList);
            continue;
        }
        $newLogId = $oldIdToNewId[$oldLogId];

        foreach ($commentList as $comment) {
            $authorId = getOrCreateUserId($db, $usernameToId, $comment['author'] ?? 'unknown');
            $stmt->execute([
                $newLogId,
                $authorId,
                $comment['text'],
                $comment['date'] ?? date('Y-m-d H:i:s'),
            ]);
            $imported++;
        }
    }
    echo "  Done. {$imported} comments imported" . ($skipped ? ", {$skipped} skipped" : '') . ".\n";
} else {
    echo "Skipping comments JSON (file not found or skipped).\n";
}

echo "\nMigration complete.\n";