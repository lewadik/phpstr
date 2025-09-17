<?php

namespace App;

use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;

class SftpS3Storage
{
    private $sftp;
    private $basePath;
    private $baseUrl;
    private $metadataPath;
    private $connected = false;

    public const ACCESS_PRIVATE = 'private';
    public const ACCESS_PUBLIC_READ = 'public-read';
    public const ACCESS_PUBLIC_READ_WRITE = 'public-read-write';

    public function __construct($config)
    {
        $this->basePath = rtrim($config['sftp_path'], '/');
        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->metadataPath = $this->basePath . '/.metadata';
        
        $this->connect($config);
    }

    private function connect($config)
    {
        try {
            $this->sftp = new SFTP($config['sftp_host'], $config['sftp_port'] ?? 22);
            
            if (isset($config['sftp_private_key']) && !empty($config['sftp_private_key'])) {
                // Key-based authentication
                $key = PublicKeyLoader::load(file_get_contents($config['sftp_private_key']));
                if (isset($config['sftp_private_key_password'])) {
                    $key = $key->withPassword($config['sftp_private_key_password']);
                }
                $this->connected = $this->sftp->login($config['sftp_username'], $key);
            } else {
                // Password-based authentication
                $this->connected = $this->sftp->login($config['sftp_username'], $config['sftp_password']);
            }

            if (!$this->connected) {
                throw new \Exception('SFTP authentication failed');
            }

            // Create directories if they don't exist
            $this->ensureDirectoryExists($this->basePath);
            $this->ensureDirectoryExists($this->metadataPath);

        } catch (\Exception $e) {
            throw new \Exception('SFTP connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Upload file to SFTP storage
     */
    public function uploadFile($filePath, $key, $accessType = self::ACCESS_PRIVATE, $metadata = [])
    {
        if (!$this->connected) {
            return [
                'success' => false,
                'error' => 'SFTP not connected'
            ];
        }

        try {
            $targetPath = $this->getFilePath($key);
            $this->ensureDirectoryExists(dirname($targetPath));

            if (!$this->sftp->put($targetPath, $filePath, SFTP::SOURCE_LOCAL_FILE)) {
                return [
                    'success' => false,
                    'error' => 'Failed to upload file to SFTP server'
                ];
            }

            // Store metadata
            $this->saveMetadata($key, [
                'access_type' => $accessType,
                'content_type' => mime_content_type($filePath),
                'size' => filesize($filePath),
                'created' => date('c'),
                'metadata' => $metadata
            ]);

            return [
                'success' => true,
                'url' => $this->getPublicUrl($key),
                'etag' => md5_file($filePath),
                'access_type' => $accessType
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload content directly to SFTP storage
     */
    public function uploadContent($content, $key, $accessType = self::ACCESS_PRIVATE, $contentType = 'text/plain')
    {
        if (!$this->connected) {
            return [
                'success' => false,
                'error' => 'SFTP not connected'
            ];
        }

        try {
            $targetPath = $this->getFilePath($key);
            $this->ensureDirectoryExists(dirname($targetPath));

            if (!$this->sftp->put($targetPath, $content)) {
                return [
                    'success' => false,
                    'error' => 'Failed to upload content to SFTP server'
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    } 
   /**
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
        } catch (\Exception $e) {
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
        if (!$this->connected) {
            return [
                'success' => false,
                'error' => 'SFTP not connected'
            ];
        }

        try {
            $objects = [];
            $metadataFiles = $this->sftp->nlist($this->metadataPath);

            if ($metadataFiles === false) {
                return [
                    'success' => true,
                    'objects' => []
                ];
            }

            foreach ($metadataFiles as $file) {
                if (substr($file, -5) !== '.json') {
                    continue;
                }

                $key = basename($file, '.json');
                $key = str_replace('___', '/', $key); // Convert back from safe filename
                
                if ($prefix && strpos($key, $prefix) !== 0) {
                    continue;
                }

                $metadata = $this->getMetadata($key);
                if ($metadata) {
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
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete object from SFTP storage
     */
    public function deleteObject($key)
    {
        if (!$this->connected) {
            return [
                'success' => false,
                'error' => 'SFTP not connected'
            ];
        }

        try {
            $filePath = $this->getFilePath($key);
            $metadataFile = $this->getMetadataFile($key);

            $success = true;
            if ($this->sftp->file_exists($filePath)) {
                $success = $this->sftp->delete($filePath);
            }

            if ($this->sftp->file_exists($metadataFile)) {
                $this->sftp->delete($metadataFile);
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
        } catch (\Exception $e) {
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
        if (!$this->connected) {
            return false;
        }

        $filePath = $this->getFilePath($key);
        if (!$this->sftp->file_exists($filePath)) {
            return false;
        }

        return $this->sftp->get($filePath);
    }

    /**
     * Check if file exists
     */
    public function fileExists($key)
    {
        if (!$this->connected) {
            return false;
        }

        return $this->sftp->file_exists($this->getFilePath($key));
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

    /**
     * Get metadata for external access
     */
    public function getMetadata($key)
    {
        $metadataFile = $this->getMetadataFile($key);
        if (!$this->sftp->file_exists($metadataFile)) {
            return null;
        }

        $content = $this->sftp->get($metadataFile);
        return json_decode($content, true);
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
        $this->sftp->put($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));
    }

    private function ensureDirectoryExists($path)
    {
        if (!$this->sftp->file_exists($path)) {
            $this->sftp->mkdir($path, -1, true);
        }
    }

    private function generateAccessToken($key, $expires)
    {
        $secret = 'your-secret-key-change-this'; // Should be in config
        return hash_hmac('sha256', $key . $expires, $secret);
    }

    /**
     * Get SFTP connection status
     */
    public function isConnected()
    {
        return $this->connected;
    }

    /**
     * Disconnect SFTP connection
     */
    public function disconnect()
    {
        if ($this->sftp) {
            $this->sftp->disconnect();
            $this->connected = false;
        }
    }

    /**
     * Reconnect SFTP if connection is lost
     */
    public function reconnect($config)
    {
        $this->disconnect();
        $this->connect($config);
    }
}