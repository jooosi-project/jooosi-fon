<?php

declare (strict_types=1);
namespace JooosiFon\Database\Migration;

/**
 * Coordinates migration discovery, persistence, execution, and CLI access.
 */
final class MigrationManager
{
    private \JooosiFon\Database\Migration\MigrationConfiguration $configuration;
    private \JooosiFon\Database\Migration\MigrationRegistry $registry;
    private \JooosiFon\Database\Migration\MigrationRepository $repository;
    private \JooosiFon\Database\Migration\MigrationGenerator $generator;
    /**
     * @param array<string, string> $configuration
     */
    public function __construct(array $configuration = [])
    {
        $this->configuration = new \JooosiFon\Database\Migration\MigrationConfiguration($configuration);
        $this->registry = new \JooosiFon\Database\Migration\MigrationRegistry($this->configuration);
        $this->repository = new \JooosiFon\Database\Migration\MigrationRepository($this->configuration, $this->registry);
        $this->generator = new \JooosiFon\Database\Migration\MigrationGenerator($this->configuration);
    }
    public function boot(): void
    {
        if (!function_exists('dbDelta')) {
            require_once \ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        if (class_exists('WP_CLI')) {
            \WP_CLI::add_command($this->configuration->commandNamespace(), new \JooosiFon\Database\Migration\MigrationCommand($this));
        }
    }
    public function install(): void
    {
        $this->repository->install();
    }
    public function generate(?string $tableName = null): string
    {
        return $this->generator->generate($tableName);
    }
    /**
     * @return array<string, array{version: string, executed: bool, executed_at: ?string, execution_time: ?int}>
     */
    public function list(): array
    {
        return $this->repository->versions();
    }
    /**
     * @return list<array{version: string, executed_at: string, execution_time: int}>
     */
    public function execute(): array
    {
        $this->install();
        $versions = $this->list();
        $definitions = [];
        foreach ($this->registry->all() as $definition) {
            $definitions[$definition->version] = $definition;
        }
        $executed = [];
        foreach ($versions as $version) {
            if ($version['executed'] || !isset($definitions[$version['version']])) {
                continue;
            }
            $definition = $definitions[$version['version']];
            $migration = $this->registry->create($definition->className);
            $startedAt = microtime(\true);
            $migration->preUp();
            $migration->up();
            $migration->postUp();
            $executionTime = (int) round((microtime(\true) - $startedAt) * 1000);
            $this->repository->recordExecution($definition, $executionTime);
            $executed[] = ['version' => $definition->version, 'executed_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'), 'execution_time' => $executionTime];
        }
        return $executed;
    }
}
