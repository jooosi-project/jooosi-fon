<?php

declare (strict_types=1);
namespace JooosiFon\Migrations;

use JooosiFon\Database\Migration\AbstractMigration;
/**
 * Make font payload storage and the API's common filters scale safely.
 */
final class Version20260818000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expand font payload columns and add API query indexes.';
    }
    public function up(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'jooosi_fon_fonts';
        $statements = ["ALTER TABLE `{$table}` MODIFY `metadata` MEDIUMTEXT NULL, MODIFY `font_faces` MEDIUMTEXT NULL"];
        $indexes = ['jooosi_fon_deleted_status' => '`deleted_at`, `status`', 'jooosi_fon_type_deleted' => '`type`, `deleted_at`', 'jooosi_fon_slug' => '`slug`(191)'];
        foreach ($indexes as $index => $columns) {
            if (!$this->indexExists($table, $index)) {
                $statements[] = "ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columns})";
            }
        }
        foreach ($statements as $statement) {
            if ($wpdb->query($statement) === \false) {
                throw new \RuntimeException($wpdb->last_error ?: 'Unable to optimize the Jooosi Fon fonts table.');
            }
        }
    }
    public function down(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'jooosi_fon_fonts';
        foreach (['jooosi_fon_deleted_status', 'jooosi_fon_type_deleted', 'jooosi_fon_slug'] as $index) {
            if ($this->indexExists($table, $index)) {
                $wpdb->query("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
            }
        }
    }
    private function indexExists(string $table, string $index): bool
    {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare('SELECT 1 FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = %s AND index_name = %s
            LIMIT 1', $table, $index));
    }
}
