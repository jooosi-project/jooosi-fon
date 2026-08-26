<?php

declare (strict_types=1);
namespace JooosiFon\Database\Migration;

/**
 * Metadata for a discovered migration.
 */
final class MigrationDefinition
{
    public string $version;
    public string $className;
    public string $description;
    public function __construct(string $className, string $description)
    {
        // Existing installations store the fully-qualified class name as the version.
        $this->version = $className;
        $this->className = $className;
        $this->description = $description;
    }
}
