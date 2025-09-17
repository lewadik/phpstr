<?php
// Dashboard content
$stats = $storage->listObjects();
$totalFiles = $stats['success'] ? count($stats['objects']) : 0;

$accessCounts = [
    'private' => 0,
    'public-read' => 0,
    'public-read-write' => 0
];

if ($stats['success']) {
    foreach ($stats['objects'] as $object) {
        $accessLevel = $object['access_level'];
        if (isset($accessCounts[$accessLevel])) {
            $accessCounts[$accessLevel]++;
        }
    }
}
?>

<div class="dashboard">
    <h2>Storage Dashboard</h2>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: #e3f2fd; padding: 20px; border-radius: 8px; text-align: center;">
            <h3 style="margin: 0; color: #1976d2;">Total Files</h3>
            <p style="font-size: 24px; margin: 10px 0; font-weight: bold;"><?php echo $totalFiles; ?></p>
        </div>
        
        <div style="background: #f3e5f5; padding: 20px; border-radius: 8px; text-align: center;">
            <h3 style="margin: 0; color: #7b1fa2;">Private Files</h3>
            <p style="font-size: 24px; margin: 10px 0; font-weight: bold;"><?php echo $accessCounts['private']; ?></p>
        </div>
        
        <div style="background: #e8f5e8; padding: 20px; border-radius: 8px; text-align: center;">
            <h3 style="margin: 0; color: #388e3c;">Public Read</h3>
            <p style="font-size: 24px; margin: 10px 0; font-weight: bold;"><?php echo $accessCounts['public-read']; ?></p>
        </div>
        
        <div style="background: #fff3e0; padding: 20px; border-radius: 8px; text-align: center;">
            <h3 style="margin: 0; color: #f57c00;">Public Read/Write</h3>
            <p style="font-size: 24px; margin: 10px 0; font-weight: bold;"><?php echo $accessCounts['public-read-write']; ?></p>
        </div>
    </div>

    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
        <h3>Access Types Explained</h3>
        <ul>
            <li><strong>Private:</strong> Only accessible with proper authentication or presigned URLs</li>
            <li><strong>Public Read:</strong> Anyone can download/view the file via direct URL</li>
            <li><strong>Public Read/Write:</strong> Anyone can read and modify the file (use with caution)</li>
        </ul>
    </div>

    <div style="margin-top: 20px;">
        <h3>Quick Actions</h3>
        <a href="?action=upload" class="btn">Upload New File</a>
        <a href="?action=list" class="btn" style="background: #17a2b8; margin-left: 10px;">Manage Files</a>
    </div>
</div>