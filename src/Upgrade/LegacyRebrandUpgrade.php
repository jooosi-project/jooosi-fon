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
use JooosiFon\Database\FontTable;
/**
 * One-time filesystem and attachment migration for the Jooosi Fon rebrand.
 *
 * @todo Remove this class and its call site completely in Jooosi Fon 3.0.0.
 */
final class LegacyRebrandUpgrade
{
    public const CACHE_REBUILD_OPTION = 'jooosi_fon_legacy_cache_rebuild_required';
    public const DELETE_LEGACY_DATA_OPTION = 'misc.delete_legacy_data';
    private const COMPLETION_OPTION = 'jooosi_fon_legacy_rebrand_upgraded';
    private const CLEANUP_OPTION = 'jooosi_fon_legacy_data_cleaned';
    private const LEGACY_UPLOAD_DIRECTORY = 'yabe-webfont';
    private const UPLOAD_DIRECTORY = 'jooosi-fon';
    private const FONT_DIRECTORY = 'fonts';
    private const CACHE_DIRECTORY = 'cache';
    /**
     * @return array{complete: bool, filesystem_complete: bool, font_table_available: bool, legacy_data_available: bool, current_data_available: bool, cleanup_requested: bool, cleanup_complete: bool}
     */
    public function status(): array
    {
        $uploads = wp_upload_dir();
        $baseDirectory = is_array($uploads) ? rtrim((string) ($uploads['basedir'] ?? ''), '/\\') : '';
        $legacyRoot = $baseDirectory !== '' ? $baseDirectory . '/' . self::LEGACY_UPLOAD_DIRECTORY : '';
        $root = $baseDirectory !== '' ? $baseDirectory . '/' . self::UPLOAD_DIRECTORY : '';
        $filesystemComplete = get_option(self::COMPLETION_OPTION, \false) !== \false;
        $fontTableAvailable = FontTable::exists();
        $cleanupComplete = get_option(self::CLEANUP_OPTION, \false) !== \false;
        return ['complete' => $filesystemComplete && $fontTableAvailable, 'filesystem_complete' => $filesystemComplete, 'font_table_available' => $fontTableAvailable, 'legacy_data_available' => $legacyRoot !== '' && (is_dir($legacyRoot) || is_file($legacyRoot) || is_link($legacyRoot)), 'current_data_available' => $root !== '' && (is_dir($root) || is_file($root) || is_link($root)), 'cleanup_requested' => $this->shouldDeleteLegacyData(), 'cleanup_complete' => $cleanupComplete];
    }
    public function run(): void
    {
        if (get_option(self::COMPLETION_OPTION, \false) !== \false) {
            if ($this->shouldDeleteLegacyData()) {
                $this->deleteLegacyData();
            }
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
        $rootComplete = $attachmentsComplete && $fontsComplete && $cacheComplete && $this->migrateDirectory($legacyRoot, $root, \false, $otherChanged, [self::FONT_DIRECTORY, self::CACHE_DIRECTORY]);
        if ($fontChanged || $cacheChanged) {
            update_option(self::CACHE_REBUILD_OPTION, \true, \false);
        }
        if ($attachmentsComplete && $fontsComplete && $cacheComplete && $rootComplete) {
            update_option(self::COMPLETION_OPTION, JOOOSI_FON::VERSION, \false);
            if ($this->shouldDeleteLegacyData()) {
                $this->deleteLegacyData();
            }
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
        }
        return $complete;
    }
    /**
     * Copy first so the stored legacy path remains valid until every metadata
     * update has succeeded. The legacy file is intentionally retained until
     * the administrator explicitly requests cleanup.
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
    private function migrateDirectory(string $legacyDirectory, string $directory, bool $discardLegacyConflicts, bool &$changed, array $ignoredEntries = []): bool
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
            if (in_array($entry, $ignoredEntries, \true)) {
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
        return $complete;
    }
    private function migrateFile(string $legacyPath, string $path, bool $discardLegacyConflict, bool &$changed): bool
    {
        if (is_file($path)) {
            if (!is_file($legacyPath)) {
                return \true;
            }
            if ($discardLegacyConflict || $this->filesMatch($legacyPath, $path)) {
                return \true;
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
        if (!copy($legacyPath, $path) || !$this->filesMatch($legacyPath, $path)) {
            if (file_exists($path)) {
                wp_delete_file($path);
            }
            return \false;
        }
        $changed = \true;
        return \true;
    }
    /**
     * Delete the old Yabe Webfont upload directory after the rebrand migration
     * has completed and the administrator has explicitly requested cleanup.
     */
    public function deleteLegacyData(): bool
    {
        if (get_option(self::COMPLETION_OPTION, \false) === \false) {
            return \false;
        }
        if (get_option(self::CLEANUP_OPTION, \false) !== \false) {
            return \true;
        }
        $uploads = wp_upload_dir();
        $baseDirectory = is_array($uploads) ? rtrim((string) ($uploads['basedir'] ?? ''), '/\\') : '';
        if ($baseDirectory === '' || !is_dir($baseDirectory)) {
            return \false;
        }
        $legacyRoot = $baseDirectory . '/' . self::LEGACY_UPLOAD_DIRECTORY;
        if (is_link($legacyRoot)) {
            return \false;
        }
        if (!is_dir($legacyRoot)) {
            update_option(self::CLEANUP_OPTION, JOOOSI_FON::VERSION, \false);
            return \true;
        }
        if (!$this->deleteDirectory($legacyRoot)) {
            return \false;
        }
        update_option(self::CLEANUP_OPTION, JOOOSI_FON::VERSION, \false);
        return \true;
    }
    private function deleteDirectory(string $directory): bool
    {
        if (!is_dir($directory)) {
            return !file_exists($directory);
        }
        $entries = scandir($directory);
        if (!is_array($entries)) {
            return \false;
        }
        $complete = \true;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_link($path)) {
                wp_delete_file($path);
                if (file_exists($path) || is_link($path)) {
                    $complete = \false;
                }
                continue;
            }
            if (is_dir($path)) {
                if (!$this->deleteDirectory($path)) {
                    $complete = \false;
                }
                continue;
            }
            if (is_file($path)) {
                wp_delete_file($path);
                if (file_exists($path)) {
                    $complete = \false;
                }
                continue;
            }
            $complete = \false;
        }
        if (!$complete) {
            return \false;
        }
        if (!rmdir($directory)) {
            return !is_dir($directory);
        }
        return \true;
    }
    private function shouldDeleteLegacyData(): bool
    {
        $stored = get_option(JOOOSI_FON::WP_OPTION . '_options', '{}');
        if (!is_string($stored)) {
            return \false;
        }
        try {
            $options = json_decode($stored, \true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return \false;
        }
        return is_array($options) && is_array($options['misc'] ?? null) && !empty($options['misc']['delete_legacy_data']);
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
