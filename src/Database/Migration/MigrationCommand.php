<?php

declare (strict_types=1);
namespace JooosiFon\Database\Migration;

use WP_CLI_Command;
use function WP_CLI\Utils\format_items;
/**
 * WP-CLI commands for Jooosi Fon migrations.
 */
final class MigrationCommand extends WP_CLI_Command
{
    private \JooosiFon\Database\Migration\MigrationManager $manager;
    public function __construct(\JooosiFon\Database\Migration\MigrationManager $manager)
    {
        $this->manager = $manager;
    }
    /**
     * Generate a migration stub.
     *
     * ## OPTIONS
     *
     * [--table-name=<table-name>]
     * : Optionally prefill a create/drop table migration.
     *
     * @when wp_loaded
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function generate(array $args, array $assocArgs): void
    {
        $tableName = isset($assocArgs['table-name']) ? sanitize_key((string) $assocArgs['table-name']) : null;
        $path = $this->manager->generate($tableName);
        \WP_CLI::success(sprintf('Generated migration: %s', $path));
    }
    /**
     * List discovered migrations and their execution state.
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function list(array $args, array $assocArgs): void
    {
        format_items('table', $this->manager->list(), ['version', 'executed_at', 'execution_time', 'executed']);
    }
    /**
     * Run all pending migrations.
     *
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function run(array $args, array $assocArgs): void
    {
        $executed = $this->manager->execute();
        if ($executed === []) {
            \WP_CLI::success('No pending migrations.');
            return;
        }
        format_items('table', $executed, ['version', 'executed_at', 'execution_time']);
        \WP_CLI::success(sprintf('%d migration(s) executed.', count($executed)));
    }
}
