<?php

namespace App\Firebase;

class StorageClient
{
    private string $bucket;

    public function __construct(string $bucket)
    {
        $this->bucket = $bucket;
    }

    /**
     * Génère l’URL publique Firebase Storage pour une photo
     */
    public function generatePublicUrl(string $path): string
    {
        return sprintf(
            'https://firebasestorage.googleapis.com/v0/b/%s/o/%s?alt=media',
            $this->bucket,
            rawurlencode($path)
        );
    }
}
