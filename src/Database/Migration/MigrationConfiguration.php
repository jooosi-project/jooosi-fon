<?php

declare (strict_types=1);
namespace JooosiFon\Database\Migration;

/**
 * Immutable configuration for the plugin's migration subsystem.
 */
final class MigrationConfiguration
{
    private string $tableName;
    private string $namespace;
    private string $directory;
    private string $basePath;
    private string $commandNamespace;
    /**
     * @param array<string, string> $configuration
     */
    public function __construct(array $configuration = [])
    {
        $configuration = array_merge(['tableName' => 'jooosi_fon_migrations', 'namespace' => 'JooosiFon\Migrations', 'directory' => 'migrations', 'basePath' => dirname(__DIR__, 3), 'commandNamespace' => 'jooosi-fon migrations'], $configuration);
        $this->tableName = $configuration['tableName'];
        $this->namespace = trim($configuration['namespace'], '\\');
        $this->directory = trim($configuration['directory'], \DIRECTORY_SEPARATOR);
        $this->basePath = rtrim($configuration['basePath'], \DIRECTORY_SEPARATOR);
        $this->commandNamespace = $configuration['commandNamespace'];
    }
    public function tableName(): string
    {
        return $this->tableName;
    }
    public function namespace(): string
    {
        return $this->namespace;
    }
    public function directory(): string
    {
        return $this->directory;
    }
    public function basePath(): string
    {
        return $this->basePath;
    }
    public function migrationsPath(): string
    {
        return $this->basePath . \DIRECTORY_SEPARATOR . $this->directory;
    }
    public function commandNamespace(): string
    {
        return $this->commandNamespace;
    }
}
