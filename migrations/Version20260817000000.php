<?php

declare (strict_types=1);
namespace JooosiFon\Migrations;

use JooosiFon\Database\FontTable;
use JooosiFon\Database\Migration\AbstractMigration;
use wpdb;
/**
 * Rename the font library table for the Jooosi Fon rebrand.
 *
 * @todo Remove this legacy rebrand migration completely in Jooosi Fon 3.0.0.
 */
final class Version20260817000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename the legacy fonts table to Jooosi Fon.';
    }
    public function up(): void
    {
        FontTable::migrateLegacy();
    }
    public function down(): void
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $legacyTable = $wpdb->prefix . 'yabe_webfont_fonts';
        $table = $wpdb->prefix . 'jooosi_fon_fonts';
        if ($this->table_exists($table) && !$this->table_exists($legacyTable)) {
            if ($wpdb->query("RENAME TABLE `{$table}` TO `{$legacyTable}`") === \false) {
                throw new \RuntimeException($wpdb->last_error ?: 'Unable to restore the legacy Jooosi Fon fonts table.');
            }
        }
    }
    private function table_exists(string $table): bool
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }
}
