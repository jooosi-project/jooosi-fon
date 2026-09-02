<?php

declare (strict_types=1);
namespace JooosiFon\Database;

use RuntimeException;
/**
 * Keeps the font library table available while the plugin is being rebranded.
 *
 * The migration history can survive an incomplete update, so table presence
 * must not be inferred from a recorded migration version alone.
 */
final class FontTable
{
    public static function migrateLegacy(): void
    {
        global $wpdb;
        $legacyTable = self::legacyTable();
        $table = self::table();
        if (!self::exists($table)) {
            if (self::exists($legacyTable)) {
                self::rename($legacyTable, $table);
            } else {
                self::createTable($table);
            }
        } elseif (self::exists($legacyTable)) {
            $columns = '`id`, `type`, `status`, `title`, `slug`, `family`, `metadata`, `font_faces`, `created_at`, `updated_at`, `deleted_at`';
            if ($wpdb->query("INSERT IGNORE INTO `{$table}` ({$columns}) SELECT {$columns} FROM `{$legacyTable}`") === \false) {
                throw new RuntimeException($wpdb->last_error ?: 'Unable to merge the legacy Jooosi Fon fonts table.');
            }
        }
        self::assertExists($table);
    }
    /**
     * Ensure the current table exists without merging a legacy table that has
     * already been handled by the rebrand migration.
     */
    public static function ensure(): void
    {
        $table = self::table();
        if (self::exists($table)) {
            return;
        }
        $legacyTable = self::legacyTable();
        if (self::exists($legacyTable)) {
            self::rename($legacyTable, $table);
        } else {
            self::createTable($table);
        }
        self::assertExists($table);
    }
    public static function create(): void
    {
        self::createTable(self::table());
    }
    public static function exists(?string $table = null): bool
    {
        global $wpdb;
        $table = $table ?? self::table();
        $foundTable = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        return is_string($foundTable) && $foundTable === $table;
    }
    private static function rename(string $legacyTable, string $table): void
    {
        global $wpdb;
        if ($wpdb->query("RENAME TABLE `{$legacyTable}` TO `{$table}`") === \false) {
            throw new RuntimeException($wpdb->last_error ?: 'Unable to rename the Jooosi Fon fonts table.');
        }
    }
    private static function createTable(string $table): void
    {
        global $wpdb;
        if (!function_exists('dbDelta') && defined('ABSPATH')) {
            require_once \ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        if (!function_exists('dbDelta')) {
            throw new RuntimeException('The WordPress database upgrade API is unavailable.');
        }
        $collation = $wpdb->has_cap('collation') ? $wpdb->get_charset_collate() : '';
        $sql = "CREATE TABLE `{$table}` (\n            `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,\n            `type` VARCHAR(50) NOT NULL DEFAULT 'custom',\n            `status` INT(1) NOT NULL DEFAULT 0,\n            `title` VARCHAR(255) NOT NULL,\n            `slug` VARCHAR(255) NOT NULL,\n            `family` VARCHAR(255),\n            `metadata` TEXT,\n            `font_faces` TEXT,\n            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n            `deleted_at` DATETIME DEFAULT NULL,\n            PRIMARY KEY (`id`)\n        ) {$collation};";
        dbDelta($sql);
    }
    private static function assertExists(string $table): void
    {
        global $wpdb;
        if (!self::exists($table)) {
            throw new RuntimeException($wpdb->last_error ?: 'Unable to create the Jooosi Fon fonts table.');
        }
    }
    private static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'jooosi_fon_fonts';
    }
    private static function legacyTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'yabe_webfont_fonts';
    }
}
