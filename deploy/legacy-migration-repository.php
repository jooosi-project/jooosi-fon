<?php

declare(strict_types=1);

/**
 * Compatibility stub for the first update from Yabe Webfont.
 *
 * WordPress keeps the old plugin classes in memory while it replaces the
 * plugin directory. The old Migrator can therefore run after the new package
 * has removed vendor/rosua/migrations. Keep the class available for that one
 * request; the new migration manager takes over on the next request.
 */
namespace _YabeWebfont\Rosua\Migrations;

final class MigrationRepository
{
    /**
     * @param mixed $configuration
     */
    public function __construct($configuration)
    {
    }

    /**
     * The new package owns migration discovery and execution.
     *
     * @return array<string, array{version: string, executed: bool, executed_at: null, execution_time: null}>
     */
    public function getMigrationVersions(): array
    {
        return [];
    }
}
