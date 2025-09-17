<?php

namespace App;

class LocalS3Storage
{
    private $basePath;
    private $baseUrl;
    private $metadataPath;

    public const ACCESS_PRIVATE = 'private';
    public const ACCESS_PUBLIC_READ = 'public-read';
    public const ACCESS_PUBLIC_READ_WRITE = 'public-read-write';

    public function __construct($config)
    {
        $this->basePath = rtrim($config['storage_path'], '/');
        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->metadataPath = $this->basePath . '/.metadata';
        
        // Create directories if they don't exist
        $this->ensureDirectoryExists($this->basePath);
        $this->ensureDirectoryExists($this->metadataPath);
    }

    /**
     * Upload file to local storage
     */
    public function uploadFile($filePath, $key, $accessType = self::ACCESS_PRIVATE, $metadata = [])
    {
        try {
            $targetPath = $this->getFilePath($key);
            $this->ensureDirectoryExists(dirname($targetPath));

            if (!copy($filePath, $targetPath)) {
                return [
                    'success' => false,
                    'error' => 'Failed to copy file to storage'
                ];
            }

            // Store metadata
            $this->saveMetadata($key, [
                'access_type' => $accessType,
                'content_type' => mime_content_type($targetPath),
                'size' => filesize($targetPath),
                'created' => date('c'),
                'metadata' => $metadata
            ]);

            return [
                'success' => true,
                'url' => $this->getPublicUrl($key),
                'etag' => md5_file($targetPath),
                'access_type' => $accessType
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload content directly to local storage
     */
    public function uploadContent($content, $key, $accessType = self::ACCESS_PRIVATE, $contentType = 'text/plain')
    {
        try {
            $targetPath = $this->getFilePath($key);
            $this->ensureDirectoryExists(dirname($targetPath));

            if (file_put_contents($targetPath, $content) === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to write content to storage'
                ];
            }

            // Store metadata
            $this->saveMetadata($key, [
                'access_type' => $accessType,
                'content_type' => $contentType,
                'size' => strlen($content),
                'created' => date('c'),
                'metadata' => []
            ]);

            return [
                'success' => true,
                'url' => $this->getPublicUrl($key),
                'etag' => md5($content),
                'access_type' => $accessType
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Change access level of existing object
     */
    public function changeAccessLevel($key, $newAccessType)
    {
        try {
            $metadata = $this->getMetadata($key);
            if (!$metadata) {
                return [
                    'success' => false,
                    'error' => 'File not found'
                ];
            }

            $metadata['access_type'] = $newAccessType;
            $this->saveMetadata($key, $metadata);

            return [
                'success' => true,
                'message' => "Access level changed to {$newAccessType}"
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }    /*
*
     * Generate presigned URL for private files (token-based access)
     */
    public function generatePresignedUrl($key, $expiration = '+1 hour')
    {
        try {
            $metadata = $this->getMetadata($key);
            if (!$metadata) {
                return [
                    'success' => false,
                    'error' => 'File not found'
                ];
            }

            $expirationTime = strtotime($expiration);
            $token = $this->generateAccessToken($key, $expirationTime);
            
            $url = $this->baseUrl . '/download.php?key=' . urlencode($key) . '&token=' . $token . '&expires=' . $expirationTime;

            return [
                'success' => true,
                'url' => $url,
                'expires' => $expiration
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * List objects with their access levels
     */
    public function listObjects($prefix = '')
    {
        try {
            $objects = [];
            $metadataFiles = glob($this->metadataPath . '/*.json');

            foreach ($metadataFiles as $metadataFile) {
                $key = basename($metadataFile, '.json');
                $key = str_replace('___', '/', $key); // Convert back from safe filename
                
                if ($prefix && strpos($key, $prefix) !== 0) {
                    continue;
                }

                $metadata = $this->getMetadata($key);
                if ($metadata) {
                    $filePath = $this->getFilePath($key);
                    $objects[] = [
                        'key' => $key,
                        'size' => $metadata['size'],
                        'last_modified' => new \DateTime($metadata['created']),
                        'access_level' => $metadata['access_type']
                    ];
                }
            }

            return [
                'success' => true,
                'objects' => $objects
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete object from local storage
     */
    public function deleteObject($key)
    {
        try {
            $filePath = $this->getFilePath($key);
            $metadataFile = $this->getMetadataFile($key);

            $success = true;
            if (file_exists($filePath)) {
                $success = unlink($filePath);
            }

            if (file_exists($metadataFile)) {
                unlink($metadataFile);
            }

            if (!$success) {
                return [
                    'success' => false,
                    'error' => 'Failed to delete file'
                ];
            }

            return [
                'success' => true,
                'message' => 'Object deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get file content (for download functionality)
     */
    public function getFileContent($key)
    {
        $filePath = $this->getFilePath($key);
        if (!file_exists($filePath)) {
            return false;
        }

        return file_get_contents($filePath);
    }

    /**
     * Check if file exists
     */
    public function fileExists($key)
    {
        return file_exists($this->getFilePath($key));
    }

    /**
     * Validate access token for presigned URLs
     */
    public function validateAccessToken($key, $token, $expires)
    {
        if (time() > $expires) {
            return false;
        }

        $expectedToken = $this->generateAccessToken($key, $expires);
        return hash_equals($expectedToken, $token);
    }

    // Private helper methods

    private function getFilePath($key)
    {
        return $this->basePath . '/files/' . $key;
    }

    private function getMetadataFile($key)
    {
        $safeKey = str_replace('/', '___', $key); // Make filename safe
        return $this->metadataPath . '/' . $safeKey . '.json';
    }

    private function getPublicUrl($key)
    {
        return $this->baseUrl . '/files/' . $key;
    }

    private function saveMetadata($key, $metadata)
    {
        $metadataFile = $this->getMetadataFile($key);
        file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));
    }

    private function getMetadata($key)
    {
        $metadataFile = $this->getMetadataFile($key);
        if (!file_exists($metadataFile)) {
            return null;
        }

        $content = file_get_contents($metadataFile);
        return json_decode($content, true);
    }

    private function ensureDirectoryExists($path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    private function generateAccessToken($key, $expires)
    {
        $secret = 'your-secret-key-change-this'; // Should be in config
        return hash_hmac('sha256', $key . $expires, $secret);
    }
}