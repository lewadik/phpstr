<?php
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload_type']) && $_POST['upload_type'] === 'file') {
        // File upload
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['file'];
            $key = $_POST['key'] ?: $uploadedFile['name'];
            $accessType = $_POST['access_type'];
            
            $result = $storage->uploadFile($uploadedFile['tmp_name'], $key, $accessType);
            
            if ($result['success']) {
                $message = "File uploaded successfully! Access type: {$result['access_type']}";
                $messageType = 'success';
            } else {
                $message = "Upload failed: {$result['error']}";
                $messageType = 'error';
            }
        } else {
            $message = "Please select a file to upload.";
            $messageType = 'error';
        }
    } elseif (isset($_POST['upload_type']) && $_POST['upload_type'] === 'content') {
        // Content upload
        $content = $_POST['content'];
        $key = $_POST['key'];
        $accessType = $_POST['access_type'];
        $contentType = $_POST['content_type'] ?: 'text/plain';
        
        if (!empty($content) && !empty($key)) {
            $result = $storage->uploadContent($content, $key, $accessType, $contentType);
            
            if ($result['success']) {
                $message = "Content uploaded successfully! Access type: {$result['access_type']}";
                $messageType = 'success';
            } else {
                $message = "Upload failed: {$result['error']}";
                $messageType = 'error';
            }
        } else {
            $message = "Please provide both content and key.";
            $messageType = 'error';
        }
    }
}
?>

<h2>Upload to S3</h2>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
    <!-- File Upload -->
    <div>
        <h3>Upload File</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="upload_type" value="file">
            
            <div class="form-group">
                <label for="file">Select File:</label>
                <input type="file" id="file" name="file" required>
            </div>
            
            <div class="form-group">
                <label for="key">S3 Key (optional - uses filename if empty):</label>
                <input type="text" id="key" name="key" placeholder="folder/filename.ext">
            </div>
            
            <div class="form-group">
                <label for="access_type">Access Type:</label>
                <select id="access_type" name="access_type" required>
                    <option value="private">Private</option>
                    <option value="public-read">Public Read</option>
                    <option value="public-read-write">Public Read/Write</option>
                </select>
            </div>
            
            <button type="submit" class="btn">Upload File</button>
        </form>
    </div>
    
    <!-- Content Upload -->
    <div>
        <h3>Upload Content</h3>
        <form method="POST">
            <input type="hidden" name="upload_type" value="content">
            
            <div class="form-group">
                <label for="content_key">S3 Key:</label>
                <input type="text" id="content_key" name="key" placeholder="folder/filename.txt" required>
            </div>
            
            <div class="form-group">
                <label for="content_type">Content Type:</label>
                <select id="content_type" name="content_type">
                    <option value="text/plain">Text</option>
                    <option value="text/html">HTML</option>
                    <option value="application/json">JSON</option>
                    <option value="text/css">CSS</option>
                    <option value="application/javascript">JavaScript</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="content_access_type">Access Type:</label>
                <select id="content_access_type" name="access_type" required>
                    <option value="private">Private</option>
                    <option value="public-read">Public Read</option>
                    <option value="public-read-write">Public Read/Write</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="content">Content:</label>
                <textarea id="content" name="content" rows="8" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Enter your content here..." required></textarea>
            </div>
            
            <button type="submit" class="btn">Upload Content</button>
        </form>
    </div>
</div>