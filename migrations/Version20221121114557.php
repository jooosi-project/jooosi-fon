<?php

declare (strict_types=1);
namespace JooosiFon\Migrations;

use JooosiFon\Database\FontTable;
use JooosiFon\Database\Migration\AbstractMigration;
/**
 * Create the Jooosi Fon font library table.
 */
final class Version20221121114557 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the Jooosi Fon fonts table.';
    }
    public function up(): void
    {
        FontTable::create();
    }
    public function down(): void
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $sql = "DROP TABLE IF EXISTS `{$wpdb->prefix}jooosi_fon_fonts`";
        $wpdb->query($sql);
    }
}
