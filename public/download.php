<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\StorageFactory;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Get parameters
$key = $_GET['key'] ?? '';
$token = $_GET['token'] ?? '';
$expires = $_GET['expires'] ?? 0;

if (empty($key)) {
    http_response_code(400);
    die('Missing key parameter');
}

// Create storage instance
$storageType = $_ENV['STORAGE_TYPE'] ?? 'aws';

if ($storageType === 'local') {
    $config = [
        'storage_type' => 'local',
        'local_storage_path' => $_ENV['LOCAL_STORAGE_PATH'] ?? './storage',
        'local_base_url' => $_ENV['LOCAL_BASE_URL'] ?? 'http://localhost:8000'
    ];
} else {
    $config = [
        'storage_type' => 'aws',
        'aws_key' => $_ENV['AWS_ACCESS_KEY_ID'],
        'aws_secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
        'aws_region' => $_ENV['AWS_DEFAULT_REGION'],
        'aws_bucket' => $_ENV['AWS_BUCKET'],
        'aws_endpoint' => $_ENV['AWS_ENDPOINT'] ?? null
    ];
}

$storage = StorageFactory::create($config);

// For local storage, handle presigned URL validation
if ($storageType === 'local') {
    use App\LocalS3StorageHelper;
    
    $helper = new LocalS3StorageHelper($config['local_storage_path']);
    
    if (!empty($token) && !empty($expires)) {
        // Validate presigned URL
        if (!$helper->validateAccessToken($key, $token, $expires)) {
            http_response_code(403);
            die('Invalid or expired token');
        }
    } else {
        // Check if file is publicly accessible
        $metadata = $helper->getMetadata($key);
        if (!$metadata || $metadata['access_type'] === 'private') {
            http_response_code(403);
            die('Access denied');
        }
    }

    // Get file path and content
    $filePath = $helper->getFilePath($key, $config['local_storage_path']);
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        die('File not found');
    }

    $content = file_get_contents($filePath);
    $metadata = $helper->getMetadata($key);
    
    // Set appropriate headers
    header('Content-Type: ' . ($metadata['content_type'] ?? 'application/octet-stream'));
    header('Content-Length: ' . strlen($content));
    header('Content-Disposition: inline; filename="' . basename($key) . '"');
    
    // Add cache headers for public files
    if ($metadata['access_type'] === 'public-read') {
        header('Cache-Control: public, max-age=3600');
        header('ETag: "' . md5($content) . '"');
    }
    
    // Output file content
    echo $content;
} else {
    // For AWS S3, redirect to the actual S3 URL
    // This is a simplified approach - in production you might want to proxy the content
    http_response_code(404);
    die('Direct download not supported for AWS S3 storage');
}