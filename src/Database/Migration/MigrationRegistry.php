<?php

declare (strict_types=1);
namespace JooosiFon\Database\Migration;

use RuntimeException;
/**
 * Discovers migrations from the configured directory.
 */
final class MigrationRegistry
{
    private \JooosiFon\Database\Migration\MigrationConfiguration $configuration;
    public function __construct(\JooosiFon\Database\Migration\MigrationConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }
    /**
     * @return list<MigrationDefinition>
     */
    public function all(): array
    {
        $files = glob($this->configuration->migrationsPath() . \DIRECTORY_SEPARATOR . 'Version*.php');
        if (!is_array($files)) {
            return [];
        }
        sort($files, \SORT_STRING);
        $definitions = [];
        foreach ($files as $file) {
            require_once $file;
            $className = $this->configuration->namespace() . '\\' . pathinfo($file, \PATHINFO_FILENAME);
            if (!class_exists($className)) {
                throw new RuntimeException(sprintf('Migration class "%s" could not be loaded from "%s".', $className, $file));
            }
            $migration = new $className();
            if (!$migration instanceof \JooosiFon\Database\Migration\AbstractMigration) {
                throw new RuntimeException(sprintf('Migration class "%s" must extend %s.', $className, \JooosiFon\Database\Migration\AbstractMigration::class));
            }
            $definitions[] = new \JooosiFon\Database\Migration\MigrationDefinition($className, $migration->getDescription());
        }
        return $definitions;
    }
    public function create(string $className): \JooosiFon\Database\Migration\AbstractMigration
    {
        if (!class_exists($className)) {
            $this->all();
        }
        if (!class_exists($className)) {
            throw new RuntimeException(sprintf('Migration class "%s" could not be loaded.', $className));
        }
        $migration = new $className();
        if (!$migration instanceof \JooosiFon\Database\Migration\AbstractMigration) {
            throw new RuntimeException(sprintf('Migration class "%s" must extend %s.', $className, \JooosiFon\Database\Migration\AbstractMigration::class));
        }
        return $migration;
    }
}
