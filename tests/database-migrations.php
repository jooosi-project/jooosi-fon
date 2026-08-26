<?php

declare(strict_types=1);

use JooosiFon\Database\Migration\MigrationHistoryUpgrader;
use JooosiFon\Database\Migration\MigrationManager;

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (! function_exists('current_time')) {
    function current_time(string $type): string
    {
        return $type === 'mysql' ? '2026-08-25 12:00:00' : '';
    }
}

if (! function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $directory): bool
    {
        return is_dir($directory) || mkdir($directory, 0777, true);
    }
}

if (! function_exists('dbDelta')) {
    /**
     * @param string|array<string> $queries
     *
     * @return array<string>
     */
    function dbDelta($queries = ''): array
    {
        $GLOBALS['jooosi_fon_test_db_delta'] = $queries;
        $GLOBALS['wpdb']->tableExists = true;

        return [];
    }
}

final class JooosiFonMigrationFakeWpdb
{
    public string $prefix = 'wp_';

    public string $last_error = '';

    public bool $tableExists;

    /**
     * @var list<array<string, mixed>>
     */
    public array $rows;

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(array $rows, bool $tableExists = true)
    {
        $this->rows = $rows;
        $this->tableExists = $tableExists;
    }

    public function has_cap(string $capability): bool
    {
        return $capability === 'collation';
    }

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    public function esc_like(string $value): string
    {
        return $value;
    }

    /**
     * @param mixed ...$arguments
     */
    public function prepare(string $query, ...$arguments): string
    {
        return $query . implode('', array_map('strval', $arguments));
    }

    public function get_var(string $query): ?string
    {
        return $this->tableExists ? $this->prefix . 'test_migrations' : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get_results(string $query, string $output): array
    {
        return $this->rows;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $format
     */
    public function insert(string $table, array $data, array $format): int
    {
        $this->rows[] = $data;

        return 1;
    }
}

final class JooosiFonMigrationHistoryFakeWpdb
{
    public string $prefix = 'wp_';

    public string $last_error = '';

    /**
     * @var list<string>
     */
    public array $tables;

    /**
     * @var list<array{sql: string, arguments: list<string>}>
     */
    public array $queries = [];

    /**
     * @var list<string>
     */
    public array $getVarQueries = [];

    /**
     * @var list<string>
     */
    private array $preparedArguments = [];

    private ?string $legacyVersion;

    /**
     * @param list<string> $tables
     */
    public function __construct(array $tables, ?string $legacyVersion = null)
    {
        $this->tables = $tables;
        $this->legacyVersion = $legacyVersion;
    }

    public function esc_like(string $value): string
    {
        return $value;
    }

    /**
     * @param scalar ...$arguments
     */
    public function prepare(string $query, ...$arguments): string
    {
        $this->preparedArguments = array_map('strval', $arguments);

        return $query;
    }

    public function get_var(string $query): ?string
    {
        $this->getVarQueries[] = $query;

        if (strpos($query, 'SHOW TABLES LIKE') === 0) {
            $table = $this->preparedArguments[0] ?? '';

            return in_array($table, $this->tables, true) ? $table : null;
        }

        return $this->legacyVersion;
    }

    public function query(string $query): int
    {
        $this->queries[] = [
            'sql' => $query,
            'arguments' => $this->preparedArguments,
        ];

        return 1;
    }
}

function jooosi_fon_migration_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @return list<string>
 */
function jooosi_fon_test_execution_log(): array
{
    $executed = $GLOBALS['jooosi_fon_test_executed'] ?? [];

    return is_array($executed) ? array_values(array_map('strval', $executed)) : [];
}

function jooosi_fon_test_history_upgrade(): void
{
    $upgrader = new MigrationHistoryUpgrader();
    $legacyTable = 'wp_yabe_webfont_migrations';
    $table = 'wp_jooosi_fon_migrations';
    $legacyNamespace = 'Yabe\\Webfont\\Migrations\\';
    $namespace = 'JooosiFon\\Migrations\\';

    $GLOBALS['wpdb'] = new JooosiFonMigrationHistoryFakeWpdb([$legacyTable]);
    $upgrader->upgrade('yabe_webfont_migrations', 'jooosi_fon_migrations', $legacyNamespace, $namespace);
    jooosi_fon_migration_assert(
        $GLOBALS['wpdb']->queries[0]['sql'] === "RENAME TABLE `{$legacyTable}` TO `{$table}`",
        'The legacy migrations table was not renamed.'
    );
    jooosi_fon_migration_assert(
        strpos($GLOBALS['wpdb']->getVarQueries[2], "FROM `{$table}`") !== false,
        'The namespace upgrade did not switch to the renamed table.'
    );

    $GLOBALS['wpdb'] = new JooosiFonMigrationHistoryFakeWpdb([$legacyTable, $table]);
    $upgrader->upgrade('yabe_webfont_migrations', 'jooosi_fon_migrations', $legacyNamespace, $namespace);
    jooosi_fon_migration_assert(
        strpos($GLOBALS['wpdb']->queries[0]['sql'], "INSERT IGNORE INTO `{$table}`") === 0
            && strpos($GLOBALS['wpdb']->queries[0]['sql'], "FROM `{$legacyTable}`") !== false,
        'Parallel legacy and current histories were not merged safely.'
    );
    jooosi_fon_migration_assert(
        $GLOBALS['wpdb']->queries[1]['sql'] === "DROP TABLE `{$legacyTable}`",
        'The merged legacy migrations table was not removed.'
    );

    $legacyVersion = $legacyNamespace . 'Version20221121114557';
    $GLOBALS['wpdb'] = new JooosiFonMigrationHistoryFakeWpdb([$table], $legacyVersion);
    $upgrader->upgrade('yabe_webfont_migrations', 'jooosi_fon_migrations', $legacyNamespace, $namespace);
    jooosi_fon_migration_assert(
        strpos($GLOBALS['wpdb']->queries[0]['sql'], "INSERT IGNORE INTO `{$table}`") === 0
            && $GLOBALS['wpdb']->queries[0]['arguments'][0] === $legacyNamespace
            && $GLOBALS['wpdb']->queries[0]['arguments'][1] === $namespace,
        'Legacy migration versions were not rewritten in the current table.'
    );
    jooosi_fon_migration_assert(
        strpos($GLOBALS['wpdb']->queries[1]['sql'], "DELETE FROM `{$table}`") === 0,
        'Legacy migration version rows were not cleaned up.'
    );
}

jooosi_fon_test_history_upgrade();

$basePath = sys_get_temp_dir() . '/jooosi-fon-migrations-' . bin2hex(random_bytes(6));
$migrationsPath = $basePath . '/migrations';

if (! mkdir($migrationsPath, 0777, true) && ! is_dir($migrationsPath)) {
    throw new RuntimeException('Unable to create the migration test directory.');
}

$namespace = 'JooosiFonMigrationTest';
$firstClass = $namespace . '\\Version20260825000100';
$secondClass = $namespace . '\\Version20260825000200';
$template = <<<'PHP'
<?php

declare(strict_types=1);

namespace JooosiFonMigrationTest;

use JooosiFon\Database\Migration\AbstractMigration;

final class %s extends AbstractMigration
{
    public function getDescription(): string
    {
        return '%s';
    }

    public function up(): void
    {
        $GLOBALS['jooosi_fon_test_executed'][] = '%s';
    }
}
PHP;

file_put_contents(
    $migrationsPath . '/Version20260825000100.php',
    sprintf($template, 'Version20260825000100', 'First test migration.', 'first')
);
file_put_contents(
    $migrationsPath . '/Version20260825000200.php',
    sprintf($template, 'Version20260825000200', 'Second test migration.', 'second')
);

try {
    $GLOBALS['wpdb'] = new JooosiFonMigrationFakeWpdb([[
        'version' => $firstClass,
        'executed_at' => '2026-08-25 11:00:00',
        'execution_time' => 4,
    ]], false);
    $GLOBALS['jooosi_fon_test_executed'] = [];

    $manager = new MigrationManager([
        'tableName' => 'test_migrations',
        'namespace' => $namespace,
        'directory' => 'migrations',
        'basePath' => $basePath,
        'commandNamespace' => 'jooosi-fon-test migrations',
    ]);

    $manager->install();
    jooosi_fon_migration_assert($GLOBALS['wpdb']->tableExists, 'The manager did not create the migrations table.');
    jooosi_fon_migration_assert(
        is_string($GLOBALS['jooosi_fon_test_db_delta'])
            && strpos($GLOBALS['jooosi_fon_test_db_delta'], 'CREATE TABLE `wp_test_migrations`') !== false,
        'The manager generated an invalid migrations table statement.'
    );

    $versions = $manager->list();
    jooosi_fon_migration_assert(count($versions) === 2, 'The registry did not discover both migrations.');
    jooosi_fon_migration_assert($versions[$firstClass]['executed'] === true, 'The recorded migration was not marked executed.');
    jooosi_fon_migration_assert($versions[$secondClass]['executed'] === false, 'The pending migration was marked executed.');

    $executed = $manager->execute();
    jooosi_fon_migration_assert(count($executed) === 1, 'The manager did not execute exactly one pending migration.');
    jooosi_fon_migration_assert(jooosi_fon_test_execution_log() === ['second'], 'The manager replayed an executed migration.');
    jooosi_fon_migration_assert(count($GLOBALS['wpdb']->rows) === 2, 'The manager did not record migration history.');
    jooosi_fon_migration_assert($manager->list()[$secondClass]['executed'] === true, 'The executed migration remained pending.');

    $generatedPath = $manager->generate('generated_table');
    $generatedCode = file_get_contents($generatedPath);
    jooosi_fon_migration_assert(is_string($generatedCode), 'The generated migration could not be read.');
    jooosi_fon_migration_assert(
        strpos($generatedCode, 'use JooosiFon\\Database\\Migration\\AbstractMigration;') !== false,
        'The generated migration does not use the internal base class.'
    );
    jooosi_fon_migration_assert(
        strpos($generatedCode, '{$wpdb->prefix}generated_table') !== false,
        'The generated table migration does not contain the requested table name.'
    );
} finally {
    $files = glob($migrationsPath . '/*.php');

    if (is_array($files)) {
        foreach ($files as $file) {
            unlink($file);
        }
    }

    rmdir($migrationsPath);
    rmdir($basePath);
}

echo "Database migration tests passed.\n";
