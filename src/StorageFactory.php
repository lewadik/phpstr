<?php

namespace App;

use App\S3StorageManager;
use App\LocalS3Storage;
use App\SftpS3Storage;

class StorageFactory
{
    public static function create($config)
    {
        $storageType = $config['storage_type'] ?? 'aws';

        switch ($storageType) {
            case 'local':
                return new LocalS3Storage([
                    'storage_path' => $config['local_storage_path'] ?? './storage',
                    'base_url' => $config['local_base_url'] ?? 'http://localhost:8000'
                ]);

            case 'sftp':
                return new SftpS3Storage([
                    'sftp_host' => $config['sftp_host'],
                    'sftp_port' => $config['sftp_port'] ?? 22,
                    'sftp_username' => $config['sftp_username'],
                    'sftp_password' => $config['sftp_password'] ?? null,
                    'sftp_private_key' => $config['sftp_private_key'] ?? null,
                    'sftp_private_key_password' => $config['sftp_private_key_password'] ?? null,
                    'sftp_path' => $config['sftp_path'] ?? '/storage',
                    'base_url' => $config['sftp_base_url'] ?? 'http://localhost:8000'
                ]);

            case 'aws':
            default:
                return new S3StorageManager([
                    'key' => $config['aws_key'],
                    'secret' => $config['aws_secret'],
                    'region' => $config['aws_region'],
                    'bucket' => $config['aws_bucket'],
                    'endpoint' => $config['aws_endpoint'] ?? null
                ]);
        }
    }
}