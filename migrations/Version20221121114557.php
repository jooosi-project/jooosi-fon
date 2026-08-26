<?php

declare(strict_types=1);

namespace JooosiFon\Migrations;

use JooosiFon\Database\Migration\AbstractMigration;
use wpdb;

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
        /** @var wpdb $wpdb */
        global $wpdb;

        $sql[] = "CREATE TABLE `{$wpdb->prefix}jooosi_fon_fonts` (
            `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `type` VARCHAR(50) NOT NULL DEFAULT 'custom',
            `status` INT(1) NOT NULL DEFAULT 0,
            `title` VARCHAR(255) NOT NULL,
            `slug` VARCHAR(255) NOT NULL,
            `family` VARCHAR(255),
            `metadata` TEXT,
            `font_faces` TEXT,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) {$this->collation()};";

        // $sql[] = "CREATE TRIGGER lastUpdateTrigger BEFORE
        // UPDATE ON `{$wpdb->prefix}jooosi_fon_fonts` FOR EACH ROW
        // BEGIN IF (
        //         NEW.title <> OLD.title
        //         || NEW.family <> OLD.family
        //         || NEW.metadata <> OLD.metadata
        //         || NEW.font_faces <> OLD.font_faces
        //     ) THEN
        //     SET
        //         NEW.updated_at = CURRENT_TIMESTAMP();
        // END IF;
        // END;";

        dbDelta($sql);
    }

    public function down(): void
    {
        /** @var wpdb $wpdb */
        global $wpdb;

        $sql = "DROP TABLE IF EXISTS `{$wpdb->prefix}jooosi_fon_fonts`";

        $wpdb->query($sql);
    }
}
