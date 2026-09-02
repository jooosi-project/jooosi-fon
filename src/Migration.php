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
namespace JooosiFon;

use JooosiFonDeps\JOOOSI_FON;
use JooosiFon\Database\Migration\MigrationHistoryUpgrader;
use JooosiFon\Database\Migration\MigrationManager;
/**
 * Manage the plugin custom database tables.
 *
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
final class Migration
{
    /**
     * @todo Remove the legacy table/namespace upgrade constants in Jooosi Fon 3.0.0.
     */
    private const LEGACY_MIGRATION_TABLE = 'yabe_webfont_migrations';
    private const LEGACY_MIGRATION_UPGRADE_OPTION = 'jooosi_fon_legacy_migration_history_upgraded';
    private const MIGRATION_TABLE = 'jooosi_fon_migrations';
    private MigrationManager $migrationManager;
    public function __construct()
    {
        $this->upgradeLegacyMigrationHistory();
        $this->migrationManager = new MigrationManager(['tableName' => self::MIGRATION_TABLE, 'namespace' => 'JooosiFon\Migrations', 'directory' => 'migrations', 'basePath' => dirname(JOOOSI_FON::FILE), 'commandNamespace' => 'jooosi-fon migrations']);
        add_action('a!jooosi/fon/plugins:activate_plugin_start', fn() => $this->install());
        add_action('a!jooosi/fon/plugins:upgrade_plugin_start', fn() => $this->upgrade());
        $this->migrationManager->boot();
        $this->maybe_upgrade();
    }
    public function install(): void
    {
        $this->migrationManager->install();
        $this->migrationManager->execute();
    }
    public function upgrade(): void
    {
        $this->migrationManager->install();
        $this->migrationManager->execute();
    }
    /**
     * @todo Remove this legacy migration-history upgrade completely in Jooosi Fon 3.0.0.
     */
    private function upgradeLegacyMigrationHistory(): void
    {
        if (get_option(self::LEGACY_MIGRATION_UPGRADE_OPTION, \false) !== \false) {
            return;
        }
        (new MigrationHistoryUpgrader())->upgrade(self::LEGACY_MIGRATION_TABLE, self::MIGRATION_TABLE, 'Yabe\Webfont\Migrations\\', 'JooosiFon\Migrations\\');
        update_option(self::LEGACY_MIGRATION_UPGRADE_OPTION, JOOOSI_FON::VERSION, \false);
    }
    /**
     * Run pending migrations before services query renamed tables. Activation
     * still owns first-time installation; this path handles normal code updates.
     */
    private function maybe_upgrade(): void
    {
        $installedVersion = get_option(JOOOSI_FON::WP_OPTION . '_version', \false);
        if (is_string($installedVersion) && version_compare($installedVersion, JOOOSI_FON::VERSION, '>=')) {
            return;
        }
        $this->upgrade();
        update_option(JOOOSI_FON::WP_OPTION . '_version', JOOOSI_FON::VERSION);
    }
}
