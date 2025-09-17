<?php

namespace App;

/**
 * Helper methods for LocalS3Storage that need to be accessible from download scripts
 */
class LocalS3StorageHelper
{
    private $metadataPath;

    public function __construct($storagePath)
    {
        $this->metadataPath = rtrim($storagePath, '/') . '/.metadata';
    }

    public function getMetadata($key)
    {
        $safeKey = str_replace('/', '___', $key);
        $metadataFile = $this->metadataPath . '/' . $safeKey . '.json';
        
        if (!file_exists($metadataFile)) {
            return null;
        }

        $content = file_get_contents($metadataFile);
        return json_decode($content, true);
    }

    public function getFilePath($key, $basePath)
    {
        return rtrim($basePath, '/') . '/files/' . $key;
    }

    public function validateAccessToken($key, $token, $expires)
    {
        if (time() > $expires) {
            return false;
        }

        $secret = 'your-secret-key-change-this'; // Should match LocalS3Storage
        $expectedToken = hash_hmac('sha256', $key . $expires, $secret);
        return hash_equals($expectedToken, $token);
    }
}