<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Database/FontTable.php';

use JooosiFon\Database\FontTable;

if (! function_exists('dbDelta')) {
    function dbDelta(string $query): array
    {
        preg_match('/CREATE TABLE `([^`]+)`/', $query, $matches);
        $table = $matches[1] ?? '';

        if ($table !== '' && isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb']->tables[] = $table;
        }

        return [];
    }
}

final class JooosiFonFontTableFakeWpdb
{
    public string $prefix = 'wp_';

    public string $last_error = '';

    /**
     * @var list<string>
     */
    public array $tables;

    /**
     * @var list<string>
     */
    public array $queries = [];

    /**
     * @var list<string>
     */
    private array $preparedArguments = [];

    /**
     * @param list<string> $tables
     */
    public function __construct(array $tables)
    {
        $this->tables = $tables;
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
        if (strpos($query, 'SHOW TABLES LIKE') === 0) {
            $table = $this->preparedArguments[0] ?? '';

            return in_array($table, $this->tables, true) ? $table : null;
        }

        return null;
    }

    public function has_cap(string $capability): bool
    {
        return $capability === 'collation';
    }

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    public function query(string $query): int
    {
        $this->queries[] = $query;

        if (preg_match('/RENAME TABLE `([^`]+)` TO `([^`]+)`/', $query, $matches)) {
            $this->tables = array_values(array_diff($this->tables, [$matches[1]]));
            $this->tables[] = $matches[2];
        }

        return 1;
    }
}

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$legacyTable = 'wp_yabe_webfont_fonts';
$currentTable = 'wp_jooosi_fon_fonts';

$GLOBALS['wpdb'] = new JooosiFonFontTableFakeWpdb([$legacyTable]);
FontTable::migrateLegacy();
$assert(! in_array($legacyTable, $GLOBALS['wpdb']->tables, true), 'The legacy table was not renamed.');
$assert(in_array($currentTable, $GLOBALS['wpdb']->tables, true), 'The renamed current table is missing.');
$assert(strpos($GLOBALS['wpdb']->queries[0], 'RENAME TABLE') === 0, 'The legacy table was not moved safely.');

$GLOBALS['wpdb'] = new JooosiFonFontTableFakeWpdb([]);
FontTable::migrateLegacy();
$assert(in_array($currentTable, $GLOBALS['wpdb']->tables, true), 'A missing current table was not created.');

$GLOBALS['wpdb'] = new JooosiFonFontTableFakeWpdb([$currentTable, $legacyTable]);
FontTable::migrateLegacy();
$assert(count($GLOBALS['wpdb']->queries) === 1, 'Parallel tables were not merged.');
$assert(strpos($GLOBALS['wpdb']->queries[0], 'INSERT IGNORE INTO') === 0, 'Parallel tables were not merged without dropping the source.');
$assert(in_array($legacyTable, $GLOBALS['wpdb']->tables, true), 'The legacy table was dropped during the merge.');

echo "Font table tests passed.\n";
