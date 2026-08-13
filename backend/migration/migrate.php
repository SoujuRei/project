<?php
/**
 * One-off migration: imports the old localStorage-era JSON fixtures into
 * MySQL. Run from the command line, NOT as a web request:
 *
 *   php migrate.php /path/to/users.json /path/to/logs.json /path/to/comments.json
 *
 * All three args are optional — pass '-' to skip one (e.g. if you're
 * starting logs empty but still want to import users).
 *
 * KNOWN LIMITATION: the old app only ever stored a vote *count* per log,
 * never who voted. This script sets logs.vote_count directly from that
 * number, but cannot create matching rows in the `votes` table (there's no
 * source data for who cast them). Practical effect: vote counts display
 * correctly, but the one-vote-per-user constraint has no history to
 * enforce against for pre-migration votes — anyone can vote once more on
 * migrated entries even if they'd already "voted" in the old system.
 */
require_once __DIR__ . '/../config/db.php';

$db = getDbConnection();

$usersPath = $argv[1] ?? '-';
$logsPath = $argv[2] ?? '-';
$commentsPath = $argv[3] ?? '-';

function readJson(string $path): ?array {
    if ($path === '-' || !file_exists($path)) return null;
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

// ---- Users ----
$usernameToId = [];

$users = readJson($usersPath);
if ($users !== null) {
    echo "Importing " . count($users) . " users...\n";
    $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
    foreach ($users as $u) {
        // Old fixtures stored plaintext passwords (acceptable for the
        // no-backend prototype) — hash properly now, on the way in
        $hash = password_hash($u['password'] ?? bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $stmt->execute([
            $u['username'],
            $u['email'] ?? ($u['username'] . '@migrated.local'),
            $hash,
            $u['role'] ?? 'member',
        ]);
        $usernameToId[$u['username']] = (int) $db->lastInsertId();
    }
    echo "  Done. " . count($usernameToId) . " accounts ready (passwords re-hashed, originals not preserved).\n";
} else {
    echo "Skipping users (no file given or not found).\n";
    // Still need id lookups for logs/comments below, so pull any existing users
    foreach ($db->query('SELECT id, username FROM users') as $row) {
        $usernameToId[$row['username']] = (int) $row['id'];
    }
}

function getOrCreateUserId(PDO $db, array &$map, string $username): int {
    if (isset($map[$username])) return $map[$username];

    // Referenced by a log/comment but never appeared in users.json —
    // create a minimal placeholder account rather than dropping their data
    $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$username, "{$username}@migrated.local", password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT), 'member']);
    $id = (int) $db->lastInsertId();
    $map[$username] = $id;
    echo "  Created placeholder account for referenced-but-missing user \"{$username}\".\n";
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
            $log['date'],
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
        $oldIdToNewId[$log['id']] = $newId; // old string UUID -> new int id

        foreach ($log['media'] ?? [] as $i => $item) {
            if (empty($item['url'])) continue;
            $mediaStmt->execute([$newId, $item['type'] ?? 'image', $item['url'], $item['caption'] ?? null, $i]);
        }
    }
    echo "  Done. " . count($oldIdToNewId) . " logs imported.\n";
} else {
    echo "Skipping logs (no file given or not found).\n";
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
            continue; // comments for a log that wasn't imported (or logs.json was skipped)
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
    echo "  Done. {$imported} comments imported" . ($skipped ? ", {$skipped} skipped (log not found)" : '') . ".\n";
} else {
    echo "Skipping comments (no file given or not found).\n";
}

echo "\nMigration complete.\n";
if ($users !== null) {
    echo "IMPORTANT: original plaintext passwords were not preserved (they were hashed on import).\n";
    echo "Migrated users will need to reset their password, or you'll need to tell them their old password still works (it does — same value, just now hashed).\n";
}