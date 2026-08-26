<?php

declare (strict_types=1);
namespace JooosiFon\Database\Migration;

use RuntimeException;
/**
 * Persists and reads migration execution history.
 */
final class MigrationRepository
{
    private \JooosiFon\Database\Migration\MigrationConfiguration $configuration;
    private \JooosiFon\Database\Migration\MigrationRegistry $registry;
    public function __construct(\JooosiFon\Database\Migration\MigrationConfiguration $configuration, \JooosiFon\Database\Migration\MigrationRegistry $registry)
    {
        $this->configuration = $configuration;
        $this->registry = $registry;
    }
    public function install(): void
    {
        if ($this->tableExists()) {
            return;
        }
        global $wpdb;
        $collation = $wpdb->has_cap('collation') ? $wpdb->get_charset_collate() : '';
        $tableName = $this->tableName();
        $sql = "CREATE TABLE `{$tableName}` (\n            `version` VARCHAR(191) NOT NULL,\n            `executed_at` DATETIME DEFAULT NULL,\n            `execution_time` INT DEFAULT NULL,\n            PRIMARY KEY (`version`)\n        ) {$collation};";
        dbDelta($sql);
        if (!$this->tableExists()) {
            throw new RuntimeException($wpdb->last_error ?: 'Unable to create the Jooosi Fon migrations table.');
        }
    }
    /**
     * @return list<array<string, mixed>>
     */
    public function metadata(): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        global $wpdb;
        $results = $wpdb->get_results(sprintf('SELECT * FROM `%s`', $this->tableName()), \ARRAY_A);
        if (!is_array($results)) {
            return [];
        }
        $prefix = $this->configuration->namespace() . '\\';
        return array_values(array_filter($results, static function (array $result) use ($prefix): bool {
            $version = isset($result['version']) ? (string) $result['version'] : '';
            return strncmp($version, $prefix, strlen($prefix)) === 0;
        }));
    }
    /**
     * @return array<string, array{version: string, executed: bool, executed_at: ?string, execution_time: ?int}>
     */
    public function versions(): array
    {
        $versions = [];
        foreach ($this->registry->all() as $definition) {
            $versions[$definition->version] = ['version' => $definition->version, 'executed' => \false, 'executed_at' => null, 'execution_time' => null];
        }
        foreach ($this->metadata() as $metadata) {
            $version = (string) $metadata['version'];
            $versions[$version] = ['version' => $version, 'executed' => \true, 'executed_at' => isset($metadata['executed_at']) ? (string) $metadata['executed_at'] : null, 'execution_time' => isset($metadata['execution_time']) ? (int) $metadata['execution_time'] : null];
        }
        ksort($versions, \SORT_STRING);
        return $versions;
    }
    public function recordExecution(\JooosiFon\Database\Migration\MigrationDefinition $definition, int $executionTime): void
    {
        global $wpdb;
        $inserted = $wpdb->insert($this->tableName(), ['version' => $definition->version, 'executed_at' => $this->timestamp(), 'execution_time' => max(0, $executionTime)], ['%s', '%s', '%d']);
        if ($inserted === \false) {
            throw new RuntimeException($wpdb->last_error ?: sprintf('Unable to record migration "%s".', $definition->version));
        }
    }
    public function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . $this->configuration->tableName();
    }
    /**
     * @phpstan-impure
     */
    private function tableExists(): bool
    {
        global $wpdb;
        $tableName = $this->tableName();
        $foundTable = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tableName)));
        return is_string($foundTable) && $foundTable === $tableName;
    }
    private function timestamp(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
