<?php

namespace App;

use App\S3StorageManager;
use App\LocalS3Storage;

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