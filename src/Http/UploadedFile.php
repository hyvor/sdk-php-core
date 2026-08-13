<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Http;

/**
 * A file to be sent as part of a multipart/form-data request (e.g. uploading
 * an image, or importing a comments export file).
 */
final class UploadedFile
{
    public function __construct(
        public readonly string $filename,
        public readonly string $contents,
        public readonly string $contentType = 'application/octet-stream',
    ) {
    }
}
