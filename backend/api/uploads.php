<?php

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed.', 405);
}

$userId = requireAuth();

if (!isset($_FILES['file'])) {
    jsonError('No file uploaded.');
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    jsonError('File upload failed.');
}

/*
 * Limits
 *
 * Images: 10 MB
 * Videos: 100 MB
 */
$maxImageSize = 10 * 1024 * 1024;
$maxVideoSize = 100 * 1024 * 1024;

$allowedTypes = [
    'image/jpeg' => [
        'extension' => 'jpg',
        'maxSize' => $maxImageSize,
    ],
    'image/png' => [
        'extension' => 'png',
        'maxSize' => $maxImageSize,
    ],
    'image/webp' => [
        'extension' => 'webp',
        'maxSize' => $maxImageSize,
    ],
    'video/mp4' => [
        'extension' => 'mp4',
        'maxSize' => $maxVideoSize,
    ],
    'video/webm' => [
        'extension' => 'webm',
        'maxSize' => $maxVideoSize,
    ],
];

if ($file['size'] <= 0) {
    jsonError('Uploaded file is empty.');
}

if (!is_uploaded_file($file['tmp_name'])) {
    jsonError('Invalid upload.');
}

/*
 * Detect MIME type from the actual file contents rather than
 * trusting the browser-provided MIME type.
 */
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

if (!isset($allowedTypes[$mime])) {
    jsonError('Unsupported file type.');
}

$typeInfo = $allowedTypes[$mime];

if ($file['size'] > $typeInfo['maxSize']) {
    jsonError(
        $mime === 'video/mp4' || $mime === 'video/webm'
            ? 'Video exceeds the 100 MB size limit.'
            : 'Image exceeds the 10 MB size limit.'
    );
}

/*
 * Store files outside the API directory.
 *
 * __DIR__ = backend/api
 * ../uploads/logs = backend/uploads/logs
 */
$uploadDir = __DIR__ . '/../uploads/logs';

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        jsonError('Unable to create upload directory.', 500);
    }
}

if (!is_writable($uploadDir)) {
    jsonError('Upload directory is not writable.', 500);
}

/*
 * Generate a collision-resistant filename.
 */
$filename =
    bin2hex(random_bytes(16))
    . '_'
    . time()
    . '.'
    . $typeInfo['extension'];

$destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    jsonError('Unable to save uploaded file.', 500);
}

/*
 * URL is relative to the backend.
 *
 * Example:
 * /cos30043/s105550047/project/backend/uploads/logs/abc123.jpg
 *
 * The frontend can prepend its API/backend base URL as needed.
 */
$relativeUrl = '/uploads/logs/'  . $filename;

$type = str_starts_with($mime, 'video/')
    ? 'video'
    : 'image';

jsonResponse([
    'success' => true,
    'type' => $type,
    'url' => $relativeUrl,
    'filename' => $filename,
    'size' => $file['size'],
    'mime' => $mime,
], 201);