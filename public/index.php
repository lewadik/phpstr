<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\StorageFactory;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Storage Configuration
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

// Handle different actions
$action = $_GET['action'] ?? 'dashboard';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S3 Storage Manager</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; padding: 8px 16px; background: #007cba; color: white; text-decoration: none; border-radius: 4px; }
        .nav a:hover { background: #005a87; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #218838; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .alert { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .file-list { margin-top: 20px; }
        .file-item { padding: 10px; border: 1px solid #ddd; margin-bottom: 10px; border-radius: 4px; }
        .access-badge { padding: 2px 8px; border-radius: 12px; font-size: 12px; color: white; }
        .access-private { background: #6c757d; }
        .access-public-read { background: #17a2b8; }
        .access-public-read-write { background: #ffc107; color: #212529; }
    </style>
</head>
<body>
    <div class="container">
        <h1>S3 Storage Manager</h1>
        <div style="background: #e3f2fd; padding: 10px; border-radius: 4px; margin-bottom: 20px;">
            <strong>Storage Type:</strong> <?php echo strtoupper($storageType); ?>
            <?php if ($storageType === 'local'): ?>
                <span style="color: #1976d2;">(Local S3-Compatible Storage)</span>
            <?php else: ?>
                <span style="color: #1976d2;">(AWS S3)</span>
            <?php endif; ?>
        </div>
        
        <div class="nav">
            <a href="?action=dashboard">Dashboard</a>
            <a href="?action=upload">Upload File</a>
            <a href="?action=list">List Files</a>
        </div>

        <?php
        switch ($action) {
            case 'upload':
                include 'upload.php';
                break;
            case 'list':
                include 'list.php';
                break;
            case 'change_access':
                include 'change_access.php';
                break;
            default:
                include 'dashboard.php';
                break;
        }
        ?>
    </div>
</body>
</html>