<?php

declare (strict_types=1);
namespace JooosiFon\Database\Migration;

use RuntimeException;
/**
 * Moves legacy migration history to the current table and namespace.
 *
 * @todo Remove this legacy rebrand upgrader completely in Jooosi Fon 3.0.0.
 */
final class MigrationHistoryUpgrader
{
    public function upgrade(string $legacyTableName, string $tableName, string $legacyNamespace, string $namespace): void
    {
        global $wpdb;
        $legacyTable = $wpdb->prefix . $legacyTableName;
        $table = $wpdb->prefix . $tableName;
        $legacyTableExists = $this->tableExists($legacyTable);
        $tableExists = $this->tableExists($table);
        if ($legacyTableExists && !$tableExists) {
            $this->renameTable($legacyTable, $table);
            $tableExists = \true;
        } elseif ($legacyTableExists) {
            $this->mergeTables($legacyTable, $table);
            $this->dropTable($legacyTable);
        }
        if ($tableExists) {
            $this->migrateNamespace($table, $legacyNamespace, $namespace);
        }
    }
    private function renameTable(string $legacyTable, string $table): void
    {
        global $wpdb;
        if ($wpdb->query("RENAME TABLE `{$legacyTable}` TO `{$table}`") === \false) {
            throw new RuntimeException($wpdb->last_error ?: 'Unable to rename the Jooosi Fon migrations table.');
        }
    }
    private function mergeTables(string $legacyTable, string $table): void
    {
        global $wpdb;
        $merged = $wpdb->query("INSERT IGNORE INTO `{$table}` (`version`, `executed_at`, `execution_time`)\n            SELECT `version`, `executed_at`, `execution_time` FROM `{$legacyTable}`");
        if ($merged === \false) {
            throw new RuntimeException($wpdb->last_error ?: 'Unable to merge the legacy Jooosi Fon migration history.');
        }
    }
    private function dropTable(string $legacyTable): void
    {
        global $wpdb;
        if ($wpdb->query("DROP TABLE `{$legacyTable}`") === \false) {
            throw new RuntimeException($wpdb->last_error ?: 'Unable to remove the legacy Jooosi Fon migrations table.');
        }
    }
    private function migrateNamespace(string $table, string $legacyNamespace, string $namespace): void
    {
        global $wpdb;
        $like = $wpdb->esc_like($legacyNamespace) . '%';
        $legacyVersion = $wpdb->get_var($wpdb->prepare("SELECT `version` FROM `{$table}` WHERE `version` LIKE %s LIMIT 1", $like));
        if (!is_string($legacyVersion)) {
            return;
        }
        $inserted = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO `{$table}` (`version`, `executed_at`, `execution_time`)\n            SELECT REPLACE(`version`, %s, %s), `executed_at`, `execution_time`\n            FROM `{$table}` WHERE `version` LIKE %s", $legacyNamespace, $namespace, $like));
        if ($inserted === \false) {
            throw new RuntimeException($wpdb->last_error ?: 'Unable to migrate the Jooosi Fon migration namespace.');
        }
        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM `{$table}` WHERE `version` LIKE %s", $like));
        if ($deleted === \false) {
            throw new RuntimeException($wpdb->last_error ?: 'Unable to clean up the legacy Jooosi Fon migration namespace.');
        }
    }
    /**
     * @phpstan-impure
     */
    private function tableExists(string $table): bool
    {
        global $wpdb;
        $foundTable = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        return is_string($foundTable) && $foundTable === $table;
    }
}
