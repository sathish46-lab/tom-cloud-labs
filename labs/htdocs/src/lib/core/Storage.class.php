<?php
use Aws\S3\S3Client;

class Storage {
    private static $client = null;

    public static function getClient() {
        if (self::$client === null) {
            $config = get_config('s3');
            self::$client = new S3Client([
                'version'     => 'latest',
                'region'      => $config['region'],
                'endpoint'    => $config['endpoint'],
                'use_path_style_endpoint' => $config['use_path_style'],
                'signature_version' => 'v4',
                'use_aws_shared_config_files' => false,
                'credentials' => [
                    'key'    => $config['access_key'],
                    'secret' => $config['secret_key'],
                ],
            ]);
        }
        return self::$client;
    }
    /**
     * Upload a file to MinIO
     * @param string $localFilePath Full path to the file on your server
     * @param string $s3Path The destination path inside the bucket (e.g., 'avatars/user1.png')
     */
    public static function upload($localFilePath, $s3Path) {
        $client = self::getClient();
        $config = get_config('s3');

        try {
            $result = $client->putObject([
                'Bucket' => $config['bucket'],
                'Key'    => ltrim($s3Path, '/'),
                'SourceFile' => $localFilePath,
                'ACL'    => 'public-read', // Ensures the image is viewable via URL
                'ContentType' => mime_content_type($localFilePath) // Important for browser rendering
            ]);
            return $result;
        } catch (Aws\S3\Exception\S3Exception $e) {
            error_log("MinIO Upload Error: " . $e->getMessage());
            return false;
        }
    }
}