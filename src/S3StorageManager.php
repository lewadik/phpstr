<?php

namespace App;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class S3StorageManager
{
    private $s3Client;
    private $bucket;

    public const ACCESS_PRIVATE = 'private';
    public const ACCESS_PUBLIC_READ = 'public-read';
    public const ACCESS_PUBLIC_READ_WRITE = 'public-read-write';

    public function __construct($config)
    {
        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => $config['region'],
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret']
            ],
            'endpoint' => $config['endpoint'] ?? null,
            'use_path_style_endpoint' => !empty($config['endpoint'])
        ]);
        
        $this->bucket = $config['bucket'];
    }

    /**
     * Upload file to S3 with specified access level
     */
    public function uploadFile($filePath, $key, $accessType = self::ACCESS_PRIVATE, $metadata = [])
    {
        try {
            $params = [
                'Bucket' => $this->bucket,
                'Key' => $key,
                'SourceFile' => $filePath,
                'ACL' => $accessType,
                'Metadata' => $metadata
            ];

            $result = $this->s3Client->putObject($params);
            
            return [
                'success' => true,
                'url' => $result['ObjectURL'],
                'etag' => $result['ETag'],
                'access_type' => $accessType
            ];
        } catch (AwsException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload content directly to S3
     */
    public function uploadContent($content, $key, $accessType = self::ACCESS_PRIVATE, $contentType = 'text/plain')
    {
        try {
            $params = [
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => $content,
                'ACL' => $accessType,
                'ContentType' => $contentType
            ];

            $result = $this->s3Client->putObject($params);
            
            return [
                'success' => true,
                'url' => $result['ObjectURL'],
                'etag' => $result['ETag'],
                'access_type' => $accessType
            ];
        } catch (AwsException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }    /*
*
     * Change access level of existing object
     */
    public function changeAccessLevel($key, $newAccessType)
    {
        try {
            $this->s3Client->putObjectAcl([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'ACL' => $newAccessType
            ]);

            return [
                'success' => true,
                'message' => "Access level changed to {$newAccessType}"
            ];
        } catch (AwsException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate presigned URL for private files
     */
    public function generatePresignedUrl($key, $expiration = '+1 hour')
    {
        try {
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $key
            ]);

            $request = $this->s3Client->createPresignedRequest($cmd, $expiration);
            
            return [
                'success' => true,
                'url' => (string) $request->getUri(),
                'expires' => $expiration
            ];
        } catch (AwsException $e) {
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
            $params = [
                'Bucket' => $this->bucket,
                'Prefix' => $prefix
            ];

            $result = $this->s3Client->listObjectsV2($params);
            
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $acl = $this->getObjectAcl($object['Key']);
                    $objects[] = [
                        'key' => $object['Key'],
                        'size' => $object['Size'],
                        'last_modified' => $object['LastModified'],
                        'access_level' => $acl['access_level'] ?? 'unknown'
                    ];
                }
            }

            return [
                'success' => true,
                'objects' => $objects
            ];
        } catch (AwsException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get object ACL information
     */
    private function getObjectAcl($key)
    {
        try {
            $result = $this->s3Client->getObjectAcl([
                'Bucket' => $this->bucket,
                'Key' => $key
            ]);

            $accessLevel = 'private';
            foreach ($result['Grants'] as $grant) {
                if (isset($grant['Grantee']['URI']) && 
                    $grant['Grantee']['URI'] === 'http://acs.amazonaws.com/groups/global/AllUsers') {
                    if ($grant['Permission'] === 'READ') {
                        $accessLevel = 'public-read';
                    } elseif ($grant['Permission'] === 'WRITE') {
                        $accessLevel = 'public-read-write';
                    }
                }
            }

            return ['access_level' => $accessLevel];
        } catch (AwsException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Delete object from S3
     */
    public function deleteObject($key)
    {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key
            ]);

            return [
                'success' => true,
                'message' => 'Object deleted successfully'
            ];
        } catch (AwsException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}