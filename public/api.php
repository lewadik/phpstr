<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\StorageFactory;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Simple API key authentication (optional)
$apiKey = $_ENV['API_KEY'] ?? null;
if ($apiKey) {
    $providedKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
    if ($providedKey !== $apiKey) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or missing API key']);
        exit;
    }
}

// Create storage instance
$storageType = $_ENV['STORAGE_TYPE'] ?? 'aws';

if ($storageType === 'local') {
    $config = [
        'storage_type' => 'local',
        'local_storage_path' => $_ENV['LOCAL_STORAGE_PATH'] ?? './storage',
        'local_base_url' => $_ENV['LOCAL_BASE_URL'] ?? 'http://localhost:8000'
    ];
} elseif ($storageType === 'sftp') {
    $config = [
        'storage_type' => 'sftp',
        'sftp_host' => $_ENV['SFTP_HOST'],
        'sftp_port' => $_ENV['SFTP_PORT'] ?? 22,
        'sftp_username' => $_ENV['SFTP_USERNAME'],
        'sftp_password' => $_ENV['SFTP_PASSWORD'] ?? null,
        'sftp_private_key' => $_ENV['SFTP_PRIVATE_KEY'] ?? null,
        'sftp_private_key_password' => $_ENV['SFTP_PRIVATE_KEY_PASSWORD'] ?? null,
        'sftp_path' => $_ENV['SFTP_PATH'] ?? '/storage',
        'sftp_base_url' => $_ENV['SFTP_BASE_URL'] ?? 'http://localhost:8000'
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

try {
    $storage = StorageFactory::create($config);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Storage initialization failed: ' . $e->getMessage()]);
    exit;
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Remove 'api.php' from path parts if present
if (end($pathParts) === 'api.php') {
    array_pop($pathParts);
}

// Route requests
switch ($method) {
    case 'POST':
        handleUpload($storage);
        break;
    case 'GET':
        if (isset($pathParts[1]) && $pathParts[1] === 'files') {
            handleList($storage);
        } else {
            handleInfo();
        }
        break;
    case 'PUT':
        if (isset($pathParts[1]) && $pathParts[1] === 'access') {
            handleAccessChange($storage);
        } else {
            handleContentUpload($storage);
        }
        break;
    case 'DELETE':
        handleDelete($storage);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleUpload($storage)
{
    try {
        // Check if file was uploaded
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'No file uploaded or upload error']);
            return;
        }

        $file = $_FILES['file'];
        $key = $_POST['key'] ?? $file['name'];
        $accessType = $_POST['access_type'] ?? 'private';
        
        // Validate access type
        $validAccessTypes = ['private', 'public-read', 'public-read-write'];
        if (!in_array($accessType, $validAccessTypes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid access_type. Must be: ' . implode(', ', $validAccessTypes)]);
            return;
        }

        // Parse metadata if provided
        $metadata = [];
        if (isset($_POST['metadata'])) {
            $metadata = json_decode($_POST['metadata'], true) ?? [];
        }

        $result = $storage->uploadFile($file['tmp_name'], $key, $accessType, $metadata);

        if ($result['success']) {
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => [
                    'key' => $key,
                    'url' => $result['url'],
                    'etag' => $result['etag'],
                    'access_type' => $result['access_type'],
                    'size' => $file['size']
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $result['error']]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Upload failed: ' . $e->getMessage()]);
    }
}

function handleContentUpload($storage)
{
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['key']) || !isset($input['content'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields: key, content']);
            return;
        }

        $key = $input['key'];
        $content = $input['content'];
        $accessType = $input['access_type'] ?? 'private';
        $contentType = $input['content_type'] ?? 'text/plain';

        // Validate access type
        $validAccessTypes = ['private', 'public-read', 'public-read-write'];
        if (!in_array($accessType, $validAccessTypes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid access_type. Must be: ' . implode(', ', $validAccessTypes)]);
            return;
        }

        $result = $storage->uploadContent($content, $key, $accessType, $contentType);

        if ($result['success']) {
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Content uploaded successfully',
                'data' => [
                    'key' => $key,
                    'url' => $result['url'],
                    'etag' => $result['etag'],
                    'access_type' => $result['access_type'],
                    'size' => strlen($content)
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $result['error']]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Content upload failed: ' . $e->getMessage()]);
    }
}functio
n handleList($storage)
{
    try {
        $prefix = $_GET['prefix'] ?? '';
        $result = $storage->listObjects($prefix);

        if ($result['success']) {
            $files = array_map(function($file) {
                return [
                    'key' => $file['key'],
                    'size' => $file['size'],
                    'last_modified' => $file['last_modified']->format('c'),
                    'access_level' => $file['access_level']
                ];
            }, $result['objects']);

            echo json_encode([
                'success' => true,
                'data' => [
                    'files' => $files,
                    'count' => count($files)
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $result['error']]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'List failed: ' . $e->getMessage()]);
    }
}

function handleAccessChange($storage)
{
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['key']) || !isset($input['access_type'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields: key, access_type']);
            return;
        }

        $key = $input['key'];
        $accessType = $input['access_type'];

        // Validate access type
        $validAccessTypes = ['private', 'public-read', 'public-read-write'];
        if (!in_array($accessType, $validAccessTypes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid access_type. Must be: ' . implode(', ', $validAccessTypes)]);
            return;
        }

        $result = $storage->changeAccessLevel($key, $accessType);

        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => $result['message']
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $result['error']]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Access change failed: ' . $e->getMessage()]);
    }
}

function handleDelete($storage)
{
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['key'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required field: key']);
            return;
        }

        $key = $input['key'];
        $result = $storage->deleteObject($key);

        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => $result['message']
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $result['error']]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Delete failed: ' . $e->getMessage()]);
    }
}

function handleInfo()
{
    global $storageType;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'service' => 'S3 Storage API',
            'version' => '1.0.0',
            'storage_type' => $storageType,
            'endpoints' => [
                'POST /api.php' => 'Upload file (multipart/form-data)',
                'PUT /api.php' => 'Upload content (JSON)',
                'GET /api.php/files' => 'List files',
                'PUT /api.php/access' => 'Change file access level',
                'DELETE /api.php' => 'Delete file'
            ],
            'access_types' => ['private', 'public-read', 'public-read-write']
        ]
    ]);
}