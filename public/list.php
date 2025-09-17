<?php
$message = '';
$messageType = '';

// Handle access level changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_access'])) {
    $key = $_POST['key'];
    $newAccessType = $_POST['new_access_type'];
    
    $result = $storage->changeAccessLevel($key, $newAccessType);
    
    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
    } else {
        $message = "Failed to change access: {$result['error']}";
        $messageType = 'error';
    }
}

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $key = $_POST['key'];
    
    $result = $storage->deleteObject($key);
    
    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
    } else {
        $message = "Failed to delete file: {$result['error']}";
        $messageType = 'error';
    }
}

// Handle presigned URL generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_url'])) {
    $key = $_POST['key'];
    $expiration = $_POST['expiration'] ?: '+1 hour';
    
    $result = $storage->generatePresignedUrl($key, $expiration);
    
    if ($result['success']) {
        $message = "Presigned URL generated (expires {$result['expires']}): <br><a href='{$result['url']}' target='_blank'>{$result['url']}</a>";
        $messageType = 'success';
    } else {
        $message = "Failed to generate URL: {$result['error']}";
        $messageType = 'error';
    }
}

// Get file list
$fileList = $storage->listObjects();
?>

<h2>File Management</h2>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($fileList['success']): ?>
    <div class="file-list">
        <h3>Files in S3 Bucket</h3>
        
        <?php if (empty($fileList['objects'])): ?>
            <p>No files found in the bucket.</p>
        <?php else: ?>
            <?php foreach ($fileList['objects'] as $file): ?>
                <div class="file-item">
                    <div style="display: flex; justify-content: between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div style="flex: 1; min-width: 200px;">
                            <strong><?php echo htmlspecialchars($file['key']); ?></strong>
                            <br>
                            <small>Size: <?php echo number_format($file['size']); ?> bytes | 
                            Modified: <?php echo $file['last_modified']->format('Y-m-d H:i:s'); ?></small>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <span class="access-badge access-<?php echo str_replace('-', '-', $file['access_level']); ?>">
                                <?php echo strtoupper(str_replace('-', ' ', $file['access_level'])); ?>
                            </span>
                            
                            <!-- Change Access Form -->
                            <form method="POST" style="display: inline-block;">
                                <input type="hidden" name="key" value="<?php echo htmlspecialchars($file['key']); ?>">
                                <select name="new_access_type" style="padding: 4px; font-size: 12px;">
                                    <option value="private" <?php echo $file['access_level'] === 'private' ? 'selected' : ''; ?>>Private</option>
                                    <option value="public-read" <?php echo $file['access_level'] === 'public-read' ? 'selected' : ''; ?>>Public Read</option>
                                    <option value="public-read-write" <?php echo $file['access_level'] === 'public-read-write' ? 'selected' : ''; ?>>Public Read/Write</option>
                                </select>
                                <button type="submit" name="change_access" class="btn" style="padding: 4px 8px; font-size: 12px;">Change</button>
                            </form>
                            
                            <!-- Generate Presigned URL Form -->
                            <form method="POST" style="display: inline-block;">
                                <input type="hidden" name="key" value="<?php echo htmlspecialchars($file['key']); ?>">
                                <select name="expiration" style="padding: 4px; font-size: 12px;">
                                    <option value="+1 hour">1 Hour</option>
                                    <option value="+1 day">1 Day</option>
                                    <option value="+1 week">1 Week</option>
                                </select>
                                <button type="submit" name="generate_url" class="btn" style="padding: 4px 8px; font-size: 12px; background: #17a2b8;">Get URL</button>
                            </form>
                            
                            <!-- Delete Form -->
                            <form method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this file?');">
                                <input type="hidden" name="key" value="<?php echo htmlspecialchars($file['key']); ?>">
                                <button type="submit" name="delete_file" class="btn btn-danger" style="padding: 4px 8px; font-size: 12px;">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="alert alert-error">
        Failed to list files: <?php echo htmlspecialchars($fileList['error']); ?>
    </div>
<?php endif; ?>