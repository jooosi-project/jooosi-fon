<?php

/*
 * This file is part of the Jooosi Fon package.
 *
 * (c) Joshua Gugun Siagian <suabahasa@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare (strict_types=1);
namespace JooosiFon\Upgrade;

use JooosiFonDeps\JOOOSI_FON;
/**
 * One-time filesystem and attachment migration for the Jooosi Fon rebrand.
 *
 * @todo Remove this class and its call site completely in Jooosi Fon 3.0.0.
 */
final class LegacyRebrandUpgrade
{
    public const CACHE_REBUILD_OPTION = 'jooosi_fon_legacy_cache_rebuild_required';
    private const COMPLETION_OPTION = 'jooosi_fon_legacy_rebrand_upgraded';
    private const LEGACY_UPLOAD_DIRECTORY = 'yabe-webfont';
    private const UPLOAD_DIRECTORY = 'jooosi-fon';
    private const FONT_DIRECTORY = 'fonts';
    private const CACHE_DIRECTORY = 'cache';
    public function run(): void
    {
        if (get_option(self::COMPLETION_OPTION, \false) !== \false) {
            return;
        }
        $uploads = wp_upload_dir();
        $baseDirectory = is_array($uploads) ? rtrim((string) ($uploads['basedir'] ?? ''), '/\\') : '';
        if ($baseDirectory === '' || !is_dir($baseDirectory)) {
            return;
        }
        $legacyRoot = $baseDirectory . '/' . self::LEGACY_UPLOAD_DIRECTORY;
        $root = $baseDirectory . '/' . self::UPLOAD_DIRECTORY;
        $fontChanged = \false;
        $cacheChanged = \false;
        $otherChanged = \false;
        $attachmentsComplete = $this->migrateAttachmentPaths($baseDirectory, $fontChanged);
        $fontsComplete = $attachmentsComplete && $this->migrateDirectory($legacyRoot . '/' . self::FONT_DIRECTORY, $root . '/' . self::FONT_DIRECTORY, \false, $fontChanged);
        $cacheComplete = $this->migrateDirectory($legacyRoot . '/' . self::CACHE_DIRECTORY, $root . '/' . self::CACHE_DIRECTORY, \true, $cacheChanged);
        $rootComplete = $attachmentsComplete && $fontsComplete && $cacheComplete && $this->migrateDirectory($legacyRoot, $root, \false, $otherChanged);
        if ($fontChanged || $cacheChanged) {
            update_option(self::CACHE_REBUILD_OPTION, \true, \false);
        }
        if ($attachmentsComplete && $fontsComplete && $cacheComplete && $rootComplete) {
            update_option(self::COMPLETION_OPTION, JOOOSI_FON::VERSION, \false);
        }
    }
    private function migrateAttachmentPaths(string $baseDirectory, bool &$changed): bool
    {
        global $wpdb;
        $legacyPrefix = self::LEGACY_UPLOAD_DIRECTORY . '/' . self::FONT_DIRECTORY . '/';
        $prefix = self::UPLOAD_DIRECTORY . '/' . self::FONT_DIRECTORY . '/';
        $like = $wpdb->esc_like($legacyPrefix) . '%';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT `post_id`, `meta_value` FROM `{$wpdb->postmeta}` WHERE `meta_key` = %s AND `meta_value` LIKE %s", '_wp_attached_file', $like));
        if (!is_array($rows)) {
            return \false;
        }
        $complete = \true;
        foreach ($rows as $row) {
            $attachmentId = (int) (is_object($row) ? $row->post_id ?? 0 : $row['post_id'] ?? 0);
            $legacyRelativePath = (string) (is_object($row) ? $row->meta_value ?? '' : $row['meta_value'] ?? '');
            $legacyRelativePath = ltrim(str_replace('\\', '/', $legacyRelativePath), '/');
            if ($attachmentId < 1 || strpos($legacyRelativePath, $legacyPrefix) !== 0) {
                continue;
            }
            $relativePath = $prefix . substr($legacyRelativePath, strlen($legacyPrefix));
            $legacyPath = $baseDirectory . '/' . $legacyRelativePath;
            $path = $baseDirectory . '/' . $relativePath;
            if (!$this->prepareAttachmentFile($legacyPath, $path)) {
                $complete = \false;
                continue;
            }
            $metadata = wp_get_attachment_metadata($attachmentId);
            if (is_array($metadata) && ($metadata['file'] ?? null) === $legacyRelativePath) {
                $metadata['file'] = $relativePath;
                if (wp_update_attachment_metadata($attachmentId, $metadata) === \false) {
                    $complete = \false;
                    continue;
                }
            }
            $updated = update_post_meta($attachmentId, '_wp_attached_file', $relativePath, $legacyRelativePath);
            if ($updated === \false && get_post_meta($attachmentId, '_wp_attached_file', \true) !== $relativePath) {
                $complete = \false;
                continue;
            }
            $changed = \true;
            if (is_file($legacyPath)) {
                wp_delete_file($legacyPath);
                if (file_exists($legacyPath)) {
                    $complete = \false;
                }
            }
        }
        return $complete;
    }
    /**
     * Copy first so the stored legacy path remains valid until every metadata
     * update has succeeded. A later directory pass removes duplicate sources.
     */
    private function prepareAttachmentFile(string $legacyPath, string $path): bool
    {
        if (is_file($path)) {
            return !is_file($legacyPath) || $this->filesMatch($legacyPath, $path);
        }
        if (!is_file($legacyPath) || is_link($legacyPath)) {
            return \false;
        }
        $directory = dirname($path);
        if (!is_dir($directory) && !wp_mkdir_p($directory)) {
            return \false;
        }
        if (copy($legacyPath, $path) && $this->filesMatch($legacyPath, $path)) {
            return \true;
        }
        if (file_exists($path)) {
            wp_delete_file($path);
        }
        return \false;
    }
    private function migrateDirectory(string $legacyDirectory, string $directory, bool $discardLegacyConflicts, bool &$changed): bool
    {
        if (!is_dir($legacyDirectory)) {
            return \true;
        }
        if (!is_dir($directory) && !wp_mkdir_p($directory)) {
            return \false;
        }
        $entries = scandir($legacyDirectory);
        if (!is_array($entries)) {
            return \false;
        }
        $complete = \true;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $legacyPath = $legacyDirectory . '/' . $entry;
            $path = $directory . '/' . $entry;
            if (is_link($legacyPath)) {
                $complete = \false;
                continue;
            }
            if (is_dir($legacyPath)) {
                if (!$this->migrateDirectory($legacyPath, $path, $discardLegacyConflicts, $changed)) {
                    $complete = \false;
                }
                continue;
            }
            if (!is_file($legacyPath) || !$this->migrateFile($legacyPath, $path, $discardLegacyConflicts, $changed)) {
                $complete = \false;
            }
        }
        $remaining = scandir($legacyDirectory);
        if (is_array($remaining) && array_values(array_diff($remaining, ['.', '..'])) === []) {
            if (!rmdir($legacyDirectory)) {
                $complete = \false;
            }
        } else {
            $complete = \false;
        }
        return $complete;
    }
    private function migrateFile(string $legacyPath, string $path, bool $discardLegacyConflict, bool &$changed): bool
    {
        if (is_file($path)) {
            if (!is_file($legacyPath)) {
                return \true;
            }
            if ($discardLegacyConflict || $this->filesMatch($legacyPath, $path)) {
                wp_delete_file($legacyPath);
                $changed = \true;
                return !file_exists($legacyPath);
            }
            return \false;
        }
        if (!is_file($legacyPath) || is_link($legacyPath)) {
            return \false;
        }
        $directory = dirname($path);
        if (!is_dir($directory) && !wp_mkdir_p($directory)) {
            return \false;
        }
        if (rename($legacyPath, $path)) {
            $changed = \true;
            return \true;
        }
        if (!copy($legacyPath, $path) || !$this->filesMatch($legacyPath, $path)) {
            if (file_exists($path)) {
                wp_delete_file($path);
            }
            return \false;
        }
        wp_delete_file($legacyPath);
        $changed = \true;
        return !file_exists($legacyPath);
    }
    private function filesMatch(string $legacyPath, string $path): bool
    {
        $legacySize = filesize($legacyPath);
        $size = filesize($path);
        if (!is_int($legacySize) || !is_int($size) || $legacySize !== $size) {
            return \false;
        }
        $legacyHash = hash_file('sha256', $legacyPath);
        $hash = hash_file('sha256', $path);
        return is_string($legacyHash) && is_string($hash) && hash_equals($legacyHash, $hash);
    }
}
