<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\StorageFactory;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$requestedFile = $_GET['file'] ?? '';

if (empty($requestedFile)) {
    http_response_code(404);
    die('File not found');
}

// Only handle local storage access control
$storageType = $_ENV['STORAGE_TYPE'] ?? 'aws';

if ($storageType !== 'local') {
    http_response_code(404);
    die('Not found');
}

$config = [
    'storage_type' => 'local',
    'local_storage_path' => $_ENV['LOCAL_STORAGE_PATH'] ?? './storage',
    'local_base_url' => $_ENV['LOCAL_BASE_URL'] ?? 'http://localhost:8000'
];

$storage = StorageFactory::create($config);

// Get file metadata
$metadata = $storage->getMetadata($requestedFile);

if (!$metadata) {
    http_response_code(404);
    die('File not found');
}

// Check access permissions
if ($metadata['access_type'] === 'private') {
    http_response_code(403);
    die('Access denied - file is private');
}

// For public files, serve the content
$content = $storage->getFileContent($requestedFile);

if ($content === false) {
    http_response_code(404);
    die('File not found');
}

// Set appropriate headers
header('Content-Type: ' . ($metadata['content_type'] ?? 'application/octet-stream'));
header('Content-Length: ' . strlen($content));
header('Content-Disposition: inline; filename="' . basename($requestedFile) . '"');

// Add cache headers for public files
if ($metadata['access_type'] === 'public-read') {
    header('Cache-Control: public, max-age=3600');
    header('ETag: "' . md5($content) . '"');
}

// Output file content
echo $content;