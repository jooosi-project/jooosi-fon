<?php

declare(strict_types=1);

namespace JooosiFon\Core {
    function date(string $format, ?int $timestamp = null): string
    {
        return '2026-08-26 12:34:56';
    }

    function time(): int
    {
        return 1_777_777_777;
    }
}

namespace {
    use JooosiFon\Core\Cache;

    require_once dirname(__DIR__) . '/vendor/autoload.php';

    $GLOBALS['jooosi_fon_cache_test_options'] = [];
    $GLOBALS['jooosi_fon_cache_test_object_cache'] = [];
    $GLOBALS['jooosi_fon_cache_test_actions'] = [];
    $GLOBALS['jooosi_fon_cache_test_deletes'] = [];
    $GLOBALS['jooosi_fon_cache_test_directory'] = sys_get_temp_dir()
        . '/jooosi-fon-cache-build-'
        . bin2hex(random_bytes(6));

    function get_option(string $option, $default = false)
    {
        return $GLOBALS['jooosi_fon_cache_test_options'][$option] ?? $default;
    }

    function update_option(string $option, $value, $autoload = null): bool
    {
        $GLOBALS['jooosi_fon_cache_test_options'][$option] = $value;

        return true;
    }

    function apply_filters(string $hook, $value, ...$args)
    {
        return $value;
    }

    function apply_filters_deprecated(string $hook, array $args, string $version, ?string $replacement = null, ?string $message = null)
    {
        return $args[0];
    }

    function do_action(string $hook, ...$args): void
    {
        $GLOBALS['jooosi_fon_cache_test_actions'][] = [$hook, $args];
    }

    function wp_upload_dir(): array
    {
        return [
            'basedir' => $GLOBALS['jooosi_fon_cache_test_directory'],
            'baseurl' => 'https://example.test/wp-content/uploads',
            'error' => false,
        ];
    }

    function wp_mkdir_p(string $directory): bool
    {
        return is_dir($directory) || mkdir($directory, 0777, true);
    }

    function wp_cache_get(string $key, string $group = '')
    {
        return $GLOBALS['jooosi_fon_cache_test_object_cache'][$group][$key] ?? false;
    }

    function wp_cache_set(string $key, $value, string $group = ''): bool
    {
        $GLOBALS['jooosi_fon_cache_test_object_cache'][$group][$key] = $value;

        return true;
    }

    function wp_cache_delete(string $key, string $group = ''): bool
    {
        $GLOBALS['jooosi_fon_cache_test_deletes'][] = [$key, $group];
        unset($GLOBALS['jooosi_fon_cache_test_object_cache'][$group][$key]);

        return true;
    }

    function get_plugin_data(string $pluginFile): array
    {
        return [
            'Name' => 'Jooosi Fon',
        ];
    }

    $GLOBALS['wpdb'] = new class() {
        public string $prefix = 'wp_';

        public string $last_error = '';

        public function esc_like(string $value): string
        {
            return $value;
        }

        public function prepare(string $query, ...$arguments): string
        {
            return $query;
        }

        public function get_var(string $query): string
        {
            return $this->prefix . 'jooosi_fon_fonts';
        }

        public function has_cap(string $capability): bool
        {
            return $capability === 'collation';
        }

        public function get_charset_collate(): string
        {
            return '';
        }

        public function get_results(string $query): array
        {
            return [];
        }
    };

    $assert = static function (bool $condition, string $message): void {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    };

    $cache = (new ReflectionClass(Cache::class))->newInstanceWithoutConstructor();
    $cacheDirectory = Cache::get_cache_path();
    $cssPath = Cache::get_cache_path(Cache::CSS_CACHE_FILE);
    $preloadPath = Cache::get_cache_path(Cache::PRELOAD_HTML_FILE);

    try {
        $cache->build_cache();
        $firstInode = fileinode($cssPath);

        $cache->build_cache();
        clearstatcache(true, $cssPath);
        $secondInode = fileinode($cssPath);
        $css = file_get_contents($cssPath);
        $purges = array_filter(
            $GLOBALS['jooosi_fon_cache_test_actions'],
            static fn (array $action): bool => $action[0] === 'a!jooosi/fon/core/cache:before_page_cache_purge'
        );
        $status = Cache::getBuildStatus();

        $assert(is_string($css), 'The CSS cache must be readable after regeneration.');
        $assert(
            strpos($css, "/*\n! Jooosi Fon v" . \JOOOSI_FON::VERSION . " | 2026-08-26 12:34:56\n*/") === 0,
            'The CSS cache header must include the generation timestamp.'
        );
        $assert($firstInode !== $secondInode, 'An identical cache request must republish the CSS artifact.');
        $assert(count($purges) === 2, 'Every successful cache request must purge downstream page caches.');
        $assert(count($GLOBALS['jooosi_fon_cache_test_deletes']) === 2, 'Every successful cache request must invalidate the font catalog.');
        $assert(($status['state'] ?? null) === 'succeeded', 'The regenerated cache must report success.');
        $assert(($status['changed'] ?? null) === true, 'Every regenerated cache must report a published change.');
    } finally {
        foreach ([$cssPath, $preloadPath, $cacheDirectory . '.build.lock'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($cacheDirectory)) {
            rmdir($cacheDirectory);
        }

        $pluginCacheDirectory = dirname($cacheDirectory);

        if (is_dir($pluginCacheDirectory)) {
            rmdir($pluginCacheDirectory);
        }

        if (is_dir($GLOBALS['jooosi_fon_cache_test_directory'])) {
            rmdir($GLOBALS['jooosi_fon_cache_test_directory']);
        }
    }

    echo "Cache build tests passed.\n";
}
