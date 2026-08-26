<?php

declare (strict_types=1);
namespace JooosiFon\Api\Support;

use JooosiFon\Utils\Common;
use JooosiFon\Utils\Upload;
/**
 * Creates font attachments as a unit and can compensate for a later database
 * failure by deleting attachments created during the current operation.
 */
final class MediaImportService
{
    /**
     * @var int[]
     */
    private array $createdAttachmentIds = [];
    /**
     * @param string[] $allowedHosts
     * @return array<string, mixed>
     */
    public function remote(string $url, string $fileName, string $extension, array $allowedHosts = []): array
    {
        $this->assertRemoteUrl($url, $allowedHosts);
        $mime = self::mimeForExtension($extension);
        $attachmentId = Upload::remote_upload_media($url, $fileName, $mime);
        return $this->attachmentData($attachmentId, $fileName, $extension, $mime);
    }
    /**
     * @return array<string, mixed>
     */
    public function binary(string $binary, string $fileName, string $extension): array
    {
        $mime = self::mimeForExtension($extension);
        $attachmentId = Upload::binary_upload_media($binary, $fileName, $mime);
        return $this->attachmentData($attachmentId, $fileName, $extension, $mime);
    }
    /**
     * @return array<string, mixed>
     */
    public function local(string $path, string $fileName, string $extension): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('The source font file is not readable.');
        }
        $binary = file_get_contents($path);
        if (!is_string($binary) || $binary === '') {
            throw new \RuntimeException('The source font file is empty.');
        }
        return $this->binary($binary, $fileName, $extension);
    }
    public function commit(): void
    {
        $this->createdAttachmentIds = [];
    }
    public function rollback(): void
    {
        foreach (array_unique($this->createdAttachmentIds) as $attachmentId) {
            wp_delete_attachment($attachmentId, \true);
        }
        $this->createdAttachmentIds = [];
    }
    /**
     * @param array|object $fontFaces
     * @return int[]
     */
    public static function attachmentIds($fontFaces): array
    {
        $ids = [];
        foreach ((array) $fontFaces as $fontFace) {
            $files = is_array($fontFace) ? $fontFace['files'] ?? [] : $fontFace->files ?? [];
            foreach ((array) $files as $file) {
                $attachmentId = is_array($file) ? $file['attachment_id'] ?? 0 : $file->attachment_id ?? 0;
                if (is_numeric($attachmentId) && (int) $attachmentId > 0) {
                    $ids[] = (int) $attachmentId;
                }
            }
        }
        return array_values(array_unique($ids));
    }
    /**
     * @param int[] $attachmentIds
     */
    public static function deleteAttachments(array $attachmentIds): void
    {
        foreach (array_unique(array_map('intval', $attachmentIds)) as $attachmentId) {
            if ($attachmentId > 0) {
                wp_delete_attachment($attachmentId, \true);
            }
        }
    }
    public static function mimeForExtension(string $extension): string
    {
        $mimes = ['woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf', 'otf' => 'font/otf', 'eot' => 'font/eot'];
        $extension = strtolower($extension);
        if (!isset($mimes[$extension])) {
            throw new \InvalidArgumentException('Unsupported font file extension.');
        }
        return $mimes[$extension];
    }
    /**
     * @param mixed $attachmentId
     * @return array<string, mixed>
     */
    private function attachmentData($attachmentId, string $fileName, string $extension, string $mime): array
    {
        if (is_wp_error($attachmentId)) {
            throw new \RuntimeException($attachmentId->get_error_message());
        }
        if (!is_int($attachmentId) || $attachmentId < 1) {
            throw new \RuntimeException('The font attachment could not be created.');
        }
        $path = get_attached_file($attachmentId);
        if (!is_string($path) || !is_readable($path)) {
            wp_delete_attachment($attachmentId, \true);
            throw new \RuntimeException('The uploaded font attachment is not readable.');
        }
        $this->createdAttachmentIds[] = $attachmentId;
        return ['uid' => Common::random_slug(10), 'attachment_id' => $attachmentId, 'attachment_url' => wp_get_attachment_url($attachmentId), 'extension' => strtolower($extension), 'mime' => $mime, 'file_size' => (int) filesize($path), 'name' => pathinfo($fileName, \PATHINFO_FILENAME)];
    }
    /**
     * @param string[] $allowedHosts
     */
    private function assertRemoteUrl(string $url, array $allowedHosts): void
    {
        if (!wp_http_validate_url($url)) {
            throw new \InvalidArgumentException('The remote font URL is not safe.');
        }
        if ($allowedHosts === []) {
            return;
        }
        $host = strtolower((string) wp_parse_url($url, \PHP_URL_HOST));
        $allowedHosts = array_map('strtolower', $allowedHosts);
        if (!in_array($host, $allowedHosts, \true)) {
            throw new \InvalidArgumentException('The remote font URL uses an unapproved host.');
        }
    }
}
