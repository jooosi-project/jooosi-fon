<?php

declare (strict_types=1);
namespace JooosiFon\Database\Migration;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
/**
 * Generates migration stubs for WP-CLI.
 */
final class MigrationGenerator
{
    private \JooosiFon\Database\Migration\MigrationConfiguration $configuration;
    public function __construct(\JooosiFon\Database\Migration\MigrationConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }
    public function generate(?string $tableName = null): string
    {
        $className = 'Version' . (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('YmdHis');
        $directory = $this->configuration->migrationsPath();
        $path = $directory . \DIRECTORY_SEPARATOR . $className . '.php';
        if (!is_dir($directory) && !wp_mkdir_p($directory)) {
            throw new RuntimeException(sprintf('Unable to create migrations directory "%s".', $directory));
        }
        if (file_exists($path)) {
            throw new RuntimeException(sprintf('Migration file "%s" already exists.', $path));
        }
        $code = $this->stub($className, $tableName);
        if (file_put_contents($path, $code, \LOCK_EX) === \false) {
            throw new RuntimeException(sprintf('Unable to write migration file "%s".', $path));
        }
        return $path;
    }
    private function stub(string $className, ?string $tableName): string
    {
        $up = '';
        $down = '';
        if ($tableName !== null && $tableName !== '') {
            $up = sprintf(<<<'PHP'
        $sql = "CREATE TABLE `{$wpdb->prefix}%s` (
            `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (`id`)
        ) {$this->collation()};";

        dbDelta($sql);
PHP
, $tableName);
            $down = sprintf(<<<'PHP'
        $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}%s`");
PHP
, $tableName);
        }
        return sprintf(<<<'PHP'
<?php

declare(strict_types=1);

namespace %s;

use JooosiFon\Database\Migration\AbstractMigration;

/**
 * Auto-generated migration. Update the description and implementation.
 */
final class %s extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        global $wpdb;

%s
    }

    public function down(): void
    {
        global $wpdb;

%s
    }
}
PHP
, $this->configuration->namespace(), $className, $up, $down);
    }
}
