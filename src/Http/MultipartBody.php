<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Http;

/**
 * Builds a `multipart/form-data` request body for endpoints that accept file
 * uploads (e.g. `POST /media/image`, `POST /data/import/comments`).
 *
 * @internal
 */
final class MultipartBody
{
    /**
     * @param array<string, scalar|null> $fields
     * @param array<string, UploadedFile> $files
     * @return array{0: string, 1: string} [boundary, body]
     */
    public static function build(array $fields, array $files): array
    {
        $boundary = 'HyvorSdkBoundary' . bin2hex(random_bytes(16));
        $body = '';

        foreach ($fields as $name => $value) {
            if ($value === null) {
                continue;
            }

            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= (string) $value . "\r\n";
        }

        foreach ($files as $name => $file) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$file->filename}\"\r\n";
            $body .= "Content-Type: {$file->contentType}\r\n\r\n";
            $body .= $file->contents . "\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        return [$boundary, $body];
    }
}
