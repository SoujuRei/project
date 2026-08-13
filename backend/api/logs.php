<?php
require_once __DIR__ . '/../bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$jsonFields = ['party_members', 'shadows', 'gatekeeper', 'treasure', 'shuffle_time', 'discoveries', 'custom_info', 'blocks_covered', 'co_authors'];

function decodeLogRow(array $row, array $jsonFields): array {
    foreach ($jsonFields as $field) {
        $camel = lcfirst(str_replace('_', '', ucwords($field, '_')));
        $row[$camel] = $row[$field] !== null ? json_decode($row[$field], true) : null;
        if ($camel !== $field) unset($row[$field]);
    }
    $row['id'] = (int) $row['id'];
    $row['floor'] = (int) $row['floor'];
    $row['startFloor'] = $row['start_floor'] !== null ? (int) $row['start_floor'] : null;
    $row['endFloor'] = $row['end_floor'] !== null ? (int) $row['end_floor'] : null;
    unset($row['start_floor'], $row['end_floor']);
    $row['votes'] = (int) $row['vote_count'];
    unset($row['vote_count']);
    if (isset($row['exploration_goal'])) { $row['explorationGoal'] = $row['exploration_goal']; unset($row['exploration_goal']); }
    if (isset($row['strategy_notes'])) { $row['strategyNotes'] = $row['strategy_notes']; unset($row['strategy_notes']); }
    if (isset($row['overall_notes'])) { $row['overallNotes'] = $row['overall_notes']; unset($row['overall_notes']); }
    if (isset($row['run_number'])) { $row['runNumber'] = (int) $row['run_number']; unset($row['run_number']); }
    if (isset($row['has_voted'])) { $row['hasVoted'] = (bool) $row['has_voted']; unset($row['has_voted']); }
    return $row;
}


// Normalize media URLs for database comparison/storage.
function sanitizeMediaUrl(string $url): string {
    $url = trim($url);

    if ($url === '') {
        return '';
    }

    $parsed = parse_url($url);

    // If this is an absolute URL, keep only its path.
    if ($parsed !== false && isset($parsed['path'])) {
        $url = $parsed['path'];
    }

    // Support both the old format:
    //   /backend/uploads/logs/file.jpg
    //
    // and the current format:
    //   /uploads/logs/file.jpg
    //
    // Internally, always use:
    //   /uploads/logs/file.jpg
    $backendPrefix = '/backend/uploads/logs/';

    if (str_starts_with($url, $backendPrefix)) {
        $url = substr($url, strlen('/backend'));
    }

    return '/' . ltrim($url, '/');
}


// Delete a physical media file from backend/uploads/logs.
function deletePhysicalMediaFile(string $relativeUrl): void {
    $cleanPath = sanitizeMediaUrl($relativeUrl);

    if ($cleanPath === '') {
        return;
    }

    // Only allow files from the log upload directory.
    if (!str_starts_with($cleanPath, '/uploads/logs/')) {
        return;
    }

    $filename = basename($cleanPath);

    if ($filename === '' || $filename === '.' || $filename === '..') {
        return;
    }

    $file = __DIR__ . '/../uploads/logs/' . $filename;

    if (is_file($file)) {
        if (!unlink($file)) {
            error_log("Unable to delete media file: {$file}");
        }
    }
}



if ($method === 'GET' && isset($_GET['meta'])) {
    $authors = $db->query('SELECT DISTINCT u.username FROM logs JOIN users u ON u.id = logs.author_id ORDER BY u.username')->fetchAll(PDO::FETCH_COLUMN);
    $goals = $db->query("SELECT DISTINCT exploration_goal FROM logs WHERE exploration_goal IS NOT NULL AND exploration_goal != '' ORDER BY exploration_goal")->fetchAll(PDO::FETCH_COLUMN);

    $shadowSet = [];
    $shadowRows = $db->query('SELECT shadows FROM logs WHERE shadows IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($shadowRows as $raw) {
        foreach ((json_decode($raw, true) ?: []) as $shadow) {
            if (!empty($shadow['name'])) $shadowSet[$shadow['name']] = true;
        }
    }

    jsonResponse([
        'authors' => $authors,
        'goals' => $goals,
        'shadowNames' => array_values(array_keys($shadowSet)),
    ]);
}

if ($method === 'GET' && !isset($_GET['id'])) {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int) ($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $where = [];
    $params = [];

    foreach (['block' => 'block', 'difficulty' => 'difficulty', 'outcome' => 'outcome', 'goal' => 'exploration_goal'] as $qs => $col) {
        if (!empty($_GET[$qs])) {
            $where[] = "logs.{$col} = ?";
            $params[] = $_GET[$qs];
        }
    }

    if (!empty($_GET['author'])) {
        $where[] = 'u.username = ?';
        $params[] = $_GET['author'];
    }

    if (!empty($_GET['party'])) {
        $where[] = 'JSON_CONTAINS(party_members, JSON_QUOTE(?))';
        $params[] = $_GET['party'];
    }

    if (!empty($_GET['shadow'])) {
        $where[] = "JSON_SEARCH(shadows, 'one', ?, NULL, '\$[*].name') IS NOT NULL";
        $params[] = $_GET['shadow'];
    }

    if (isset($_GET['gatekeeper']) && $_GET['gatekeeper'] !== '') {
        $where[] = $_GET['gatekeeper'] === 'yes'
            ? "gatekeeper->>'\$.encountered' = 'true'"
            : "(gatekeeper->>'\$.encountered' = 'false' OR gatekeeper IS NULL)";
    }
    foreach (['treasure' => 'treasure', 'shuffle' => 'shuffle_time', 'discovery' => 'discoveries'] as $qs => $col) {
        if (isset($_GET[$qs]) && $_GET[$qs] !== '') {
            $where[] = $_GET[$qs] === 'yes' ? "JSON_LENGTH({$col}) > 0" : "(JSON_LENGTH({$col}) = 0 OR {$col} IS NULL)";
        }
    }

    if (!empty($_GET['search'])) {
        $where[] = '(strategy_notes LIKE ? OR overall_notes LIKE ? OR block LIKE ? OR title LIKE ?)';
        $like = '%' . $_GET['search'] . '%';
        array_push($params, $like, $like, $like, $like);
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sort = ($_GET['sort'] ?? 'date') === 'votes' ? 'vote_count DESC' : 'date DESC';

    $countStmt = $db->prepare("SELECT COUNT(*) FROM logs JOIN users u ON u.id = logs.author_id {$whereSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $currentUserId = $_SESSION['user_id'] ?? 0;
    $sql = "SELECT logs.*, u.username AS author,
                   EXISTS(SELECT 1 FROM votes v WHERE v.log_id = logs.id AND v.user_id = ?) AS has_voted,
                   (SELECT COUNT(*) FROM logs l2 WHERE l2.author_id = logs.author_id AND l2.created_at <= logs.created_at) AS run_number
            FROM logs
            JOIN users u ON u.id = logs.author_id
            {$whereSql}
            ORDER BY {$sort}
            LIMIT {$limit} OFFSET {$offset}";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge([$currentUserId], $params));
    $rawRows = $stmt->fetchAll();

    // Fetch media for all returned logs in one query
    $mediaMap = [];
    if (!empty($rawRows)) {
        $logIds = array_column($rawRows, 'id');
        $inClause = implode(',', array_fill(0, count($logIds), '?'));
        $mediaStmt = $db->prepare("SELECT id, log_id, type, url, caption, position FROM log_media WHERE log_id IN ({$inClause}) ORDER BY position");
        $mediaStmt->execute($logIds);
        foreach ($mediaStmt->fetchAll() as $media) {
            $mediaMap[$media['log_id']][] = $media;
        }
    }

    // Decode logs and attach media array directly inside the map return
    $rows = array_map(function($r) use ($jsonFields, $mediaMap) {
        $decoded = decodeLogRow($r, $jsonFields);
        $decoded['media'] = $mediaMap[$decoded['id']] ?? [];
        return $decoded;
    }, $rawRows);

    jsonResponse(['data' => $rows, 'total' => $total, 'page' => $page, 'limit' => $limit]);
}

if ($method === 'GET' && isset($_GET['id'])) {
    $currentUserId = $_SESSION['user_id'] ?? 0;
    $stmt = $db->prepare("SELECT logs.*, u.username AS author,
                                  EXISTS(SELECT 1 FROM votes v WHERE v.log_id = logs.id AND v.user_id = ?) AS has_voted
                           FROM logs JOIN users u ON u.id = logs.author_id WHERE logs.id = ?");
    $stmt->execute([$currentUserId, $_GET['id']]);
    $row = $stmt->fetch();
    if (!$row) jsonError('Log not found.', 404);

    $log = decodeLogRow($row, $jsonFields);

    $mediaStmt = $db->prepare('SELECT id, type, url, caption, position FROM log_media WHERE log_id = ? ORDER BY position');
    $mediaStmt->execute([$_GET['id']]);
    $log['media'] = $mediaStmt->fetchAll();

    jsonResponse(['data' => $log]);
}

function saveLog(PDO $db, int $userId, array $body, ?int $existingId = null): int {
    $columns = [
        'author_id' => $userId,
        'title' => $body['title'] ?? null,
        'date' => $body['date'],
        'block' => $body['block'] ?? '',
        'floor' => $body['floor'] ?? ($body['startFloor'] ?? 0),
        'start_floor' => $body['startFloor'] ?? null,
        'end_floor' => $body['endFloor'] ?? null,
        'blocks_covered' => json_encode($body['blocksCovered'] ?? []),
        'co_authors' => json_encode($body['coAuthors'] ?? []),
        'party_members' => json_encode($body['partyMembers'] ?? []),
        'difficulty' => $body['difficulty'] ?? null,
        'exploration_goal' => $body['explorationGoal'] ?? null,
        'outcome' => $body['outcome'] ?? null,
        'strategy_notes' => $body['strategyNotes'],
        'overall_notes' => $body['overallNotes'] ?? null,
        'shadows' => json_encode($body['shadows'] ?? []),
        'gatekeeper' => json_encode($body['gatekeeper'] ?? ['encountered' => false]),
        'treasure' => json_encode($body['treasure'] ?? []),
        'shuffle_time' => json_encode($body['shuffleTime'] ?? []),
        'discoveries' => json_encode($body['discoveries'] ?? []),
        'custom_info' => json_encode($body['customInfo'] ?? []),
    ];

    if ($existingId === null) {
        $cols = array_keys($columns);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $stmt = $db->prepare('INSERT INTO logs (' . implode(', ', $cols) . ") VALUES ({$placeholders})");
        $stmt->execute(array_values($columns));
        $logId = (int) $db->lastInsertId();
    } else {
        unset($columns['author_id']);
        $setSql = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($columns)));
        $stmt = $db->prepare("UPDATE logs SET {$setSql} WHERE id = ?");
        $stmt->execute([...array_values($columns), $existingId]);
        $logId = $existingId;

        // Fetch existing media to compare against incoming media payload
        $existingMediaStmt = $db->prepare('SELECT url FROM log_media WHERE log_id = ?');
        $existingMediaStmt->execute([$logId]);
        $existingUrls = $existingMediaStmt->fetchAll(PDO::FETCH_COLUMN);

        $incomingUrls = array_map(fn($m) => sanitizeMediaUrl($m['url'] ?? ''), $body['media'] ?? []);

        // Only delete physical files that were explicitly removed by the user in the edit UI
        foreach ($existingUrls as $oldUrl) {
            $cleanOldUrl = sanitizeMediaUrl($oldUrl);
            if (!in_array($cleanOldUrl, $incomingUrls, true)) {
                deletePhysicalMediaFile($cleanOldUrl);
            }
        }

        $db->prepare('DELETE FROM log_media WHERE log_id = ?')->execute([$logId]);
    }

    if (!empty($body['media']) && is_array($body['media'])) {
        $mediaStmt = $db->prepare('INSERT INTO log_media (log_id, type, url, caption, position) VALUES (?, ?, ?, ?, ?)');
        foreach ($body['media'] as $i => $item) {
            if (empty($item['url'])) continue;
            $cleanUrl = sanitizeMediaUrl($item['url']);
            $mediaStmt->execute([$logId, $item['type'] ?? 'image', $cleanUrl, $item['caption'] ?? null, $i]);
        }
    }

    return $logId;
}

if ($method === 'POST') {
    $userId = requireAuth();
    $body = getJsonBody();

    if (empty($body['date']) || empty($body['strategyNotes'])) {
        jsonError('Missing required fields.');
    }

    jsonResponse(['id' => saveLog($db, $userId, $body)], 201);
}

if ($method === 'PUT') {
    $userId = requireAuth();
    $logId = (int) ($_GET['id'] ?? 0);
    if (!$logId) jsonError('Missing log id.');

    $stmt = $db->prepare('SELECT author_id FROM logs WHERE id = ?');
    $stmt->execute([$logId]);
    $existing = $stmt->fetch();
    if (!$existing) jsonError('Log not found.', 404);

    $roleStmt = $db->prepare('SELECT role FROM users WHERE id = ?');
    $roleStmt->execute([$userId]);
    $isAdmin = ($roleStmt->fetchColumn() === 'admin');

    if ((int) $existing['author_id'] !== $userId && !$isAdmin) {
        jsonError('You can only edit your own log entries.', 403);
    }

    $body = getJsonBody();
    saveLog($db, $userId, $body, $logId);
    jsonResponse(['success' => true]);
}

if ($method === 'DELETE') {
    $userId = requireAuth();
    $logId = (int) ($_GET['id'] ?? 0);
    if (!$logId) jsonError('Missing log id.');

    $stmt = $db->prepare('SELECT author_id FROM logs WHERE id = ?');
    $stmt->execute([$logId]);
    $existing = $stmt->fetch();
    if (!$existing) jsonError('Log not found.', 404);

    $roleStmt = $db->prepare('SELECT role FROM users WHERE id = ?');
    $roleStmt->execute([$userId]);
    $isAdmin = ($roleStmt->fetchColumn() === 'admin');

    if ((int) $existing['author_id'] !== $userId && !$isAdmin) {
        jsonError('You can only delete your own log entries.', 403);
    }

    // Clean up physical media files on disk
    $mediaStmt = $db->prepare('SELECT url FROM log_media WHERE log_id = ?');
    $mediaStmt->execute([$logId]);
    foreach ($mediaStmt->fetchAll(PDO::FETCH_COLUMN) as $url) {
        deletePhysicalMediaFile($url);
    }

    $db->prepare('DELETE FROM log_media WHERE log_id = ?')->execute([$logId]);
    $db->prepare('DELETE FROM logs WHERE id = ?')->execute([$logId]);

    jsonResponse(['success' => true]);
}

jsonError('Method not allowed.', 405);