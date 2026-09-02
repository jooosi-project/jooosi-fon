<?php

declare(strict_types=1);

if (! class_exists('JOOOSI_FON')) {
    final class JOOOSI_FON
    {
        public const VERSION = '2.1.0';
        public const WP_OPTION = 'jooosi_fon';
    }
}

$GLOBALS['jooosi_fon_upgrade_options'] = [];
$GLOBALS['jooosi_fon_upgrade_attachment_paths'] = [];
$GLOBALS['jooosi_fon_upgrade_attachment_metadata'] = [];
$GLOBALS['jooosi_fon_upgrade_base_directory'] = '';
$GLOBALS['jooosi_fon_upgrade_fail_post_meta'] = false;

function get_option(string $option, $default = false)
{
    return $GLOBALS['jooosi_fon_upgrade_options'][$option] ?? $default;
}

function update_option(string $option, $value, $autoload = null): bool
{
    $GLOBALS['jooosi_fon_upgrade_options'][$option] = $value;

    return true;
}

function update_post_meta(int $postId, string $key, $value, $previousValue = '')
{
    $current = $GLOBALS['jooosi_fon_upgrade_attachment_paths'][$postId] ?? null;

    if ($GLOBALS['jooosi_fon_upgrade_fail_post_meta']
        || $key !== '_wp_attached_file'
        || ($previousValue !== '' && $current !== $previousValue)) {
        return false;
    }

    $GLOBALS['jooosi_fon_upgrade_attachment_paths'][$postId] = $value;

    return true;
}

function get_post_meta(int $postId, string $key, bool $single = false)
{
    return $key === '_wp_attached_file'
        ? ($GLOBALS['jooosi_fon_upgrade_attachment_paths'][$postId] ?? '')
        : '';
}

function wp_get_attachment_metadata(int $postId)
{
    return $GLOBALS['jooosi_fon_upgrade_attachment_metadata'][$postId] ?? false;
}

function wp_update_attachment_metadata(int $postId, array $metadata): bool
{
    $GLOBALS['jooosi_fon_upgrade_attachment_metadata'][$postId] = $metadata;

    return true;
}

function wp_upload_dir(): array
{
    return [
        'basedir' => $GLOBALS['jooosi_fon_upgrade_base_directory'],
        'baseurl' => 'https://example.test/wp-content/uploads',
        'error' => false,
    ];
}

function wp_mkdir_p(string $directory): bool
{
    return is_dir($directory) || mkdir($directory, 0777, true);
}

function wp_delete_file(string $file): void
{
    if (is_file($file) || is_link($file)) {
        unlink($file);
    }
}

final class JooosiFonLegacyUpgradeFakeWpdb
{
    public string $prefix = 'wp_';

    public string $postmeta = 'wp_postmeta';

    public function esc_like(string $value): string
    {
        return $value;
    }

    public function prepare(string $query, ...$arguments): string
    {
        return $query;
    }

    /**
     * @return array<int, object>
     */
    public function get_results(string $query): array
    {
        $rows = [];

        foreach ($GLOBALS['jooosi_fon_upgrade_attachment_paths'] as $postId => $path) {
            if (strpos((string) $path, 'yabe-webfont/fonts/') === 0) {
                $rows[] = (object) [
                    'post_id' => $postId,
                    'meta_value' => $path,
                ];
            }
        }

        return $rows;
    }

    public function get_var(string $query): string
    {
        return $this->prefix . 'jooosi_fon_fonts';
    }
}

require_once dirname(__DIR__) . '/src/Database/FontTable.php';
require_once dirname(__DIR__) . '/src/Upgrade/LegacyRebrandUpgrade.php';

use JooosiFon\Upgrade\LegacyRebrandUpgrade;

function jooosi_fon_upgrade_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function jooosi_fon_upgrade_remove_directory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir() && ! $item->isLink()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($directory);
}

$baseDirectory = sys_get_temp_dir() . '/jooosi-fon-upgrade-' . bin2hex(random_bytes(6));
$GLOBALS['jooosi_fon_upgrade_base_directory'] = $baseDirectory;
$GLOBALS['wpdb'] = new JooosiFonLegacyUpgradeFakeWpdb();

try {
    wp_mkdir_p($baseDirectory . '/yabe-webfont/fonts');
    wp_mkdir_p($baseDirectory . '/yabe-webfont/cache');
    wp_mkdir_p($baseDirectory . '/yabe-webfont/debug');
    wp_mkdir_p($baseDirectory . '/jooosi-fon/cache');

    file_put_contents($baseDirectory . '/yabe-webfont/fonts/acme.woff2', 'font-data');
    file_put_contents($baseDirectory . '/yabe-webfont/cache/fonts.css', 'legacy-cache');
    file_put_contents($baseDirectory . '/jooosi-fon/cache/fonts.css', 'current-cache');
    file_put_contents($baseDirectory . '/yabe-webfont/debug/stopwatch.log', 'debug-data');

    $GLOBALS['jooosi_fon_upgrade_attachment_paths'][42] = 'yabe-webfont/fonts/acme.woff2';
    $GLOBALS['jooosi_fon_upgrade_attachment_metadata'][42] = [
        'file' => 'yabe-webfont/fonts/acme.woff2',
    ];

    (new LegacyRebrandUpgrade())->run();

    jooosi_fon_upgrade_assert(
        is_file($baseDirectory . '/jooosi-fon/fonts/acme.woff2'),
        'The legacy font file was not copied.'
    );
    jooosi_fon_upgrade_assert(
        $GLOBALS['jooosi_fon_upgrade_attachment_paths'][42] === 'jooosi-fon/fonts/acme.woff2',
        'The attached file path was not renamed.'
    );
    jooosi_fon_upgrade_assert(
        $GLOBALS['jooosi_fon_upgrade_attachment_metadata'][42]['file'] === 'jooosi-fon/fonts/acme.woff2',
        'The attachment metadata path was not renamed.'
    );
    jooosi_fon_upgrade_assert(
        file_get_contents($baseDirectory . '/jooosi-fon/cache/fonts.css') === 'current-cache',
        'A legacy cache conflict replaced the current cache.'
    );
    jooosi_fon_upgrade_assert(
        is_file($baseDirectory . '/jooosi-fon/debug/stopwatch.log'),
        'Other legacy upload artifacts were not copied.'
    );
    jooosi_fon_upgrade_assert(
        is_file($baseDirectory . '/yabe-webfont/fonts/acme.woff2')
            && is_file($baseDirectory . '/yabe-webfont/cache/fonts.css')
            && is_file($baseDirectory . '/yabe-webfont/debug/stopwatch.log'),
        'The legacy upload data was not preserved.'
    );
    jooosi_fon_upgrade_assert(
        ($GLOBALS['jooosi_fon_upgrade_options']['jooosi_fon_legacy_cache_rebuild_required'] ?? false) === true,
        'The cache rebuild was not requested after moving legacy files.'
    );

    $status = (new LegacyRebrandUpgrade())->status();
    jooosi_fon_upgrade_assert($status['complete'] === true, 'The completed legacy migration was not reported correctly.');
    jooosi_fon_upgrade_assert($status['legacy_data_available'] === true, 'The preserved legacy data was not reported.');
    jooosi_fon_upgrade_assert(
        ($GLOBALS['jooosi_fon_upgrade_options']['jooosi_fon_legacy_rebrand_upgraded'] ?? null) === JOOOSI_FON::VERSION,
        'The completed legacy upgrade was not recorded.'
    );

    $upgrade = new LegacyRebrandUpgrade();
    jooosi_fon_upgrade_assert(
        $upgrade->deleteLegacyData(),
        'The explicit legacy data cleanup did not complete.'
    );
    jooosi_fon_upgrade_assert(
        ! file_exists($baseDirectory . '/yabe-webfont'),
        'The explicit legacy data cleanup did not remove the legacy upload directory.'
    );

    $GLOBALS['jooosi_fon_upgrade_options'] = [];
    $GLOBALS['jooosi_fon_upgrade_attachment_paths'] = [
        84 => 'yabe-webfont/fonts/conflict.woff2',
    ];
    $GLOBALS['jooosi_fon_upgrade_attachment_metadata'] = [];
    wp_mkdir_p($baseDirectory . '/yabe-webfont/fonts');
    wp_mkdir_p($baseDirectory . '/jooosi-fon/fonts');
    file_put_contents($baseDirectory . '/yabe-webfont/fonts/conflict.woff2', 'legacy-font');
    file_put_contents($baseDirectory . '/jooosi-fon/fonts/conflict.woff2', 'current-font');

    (new LegacyRebrandUpgrade())->run();

    jooosi_fon_upgrade_assert(
        $GLOBALS['jooosi_fon_upgrade_attachment_paths'][84] === 'yabe-webfont/fonts/conflict.woff2',
        'A conflicting font attachment was pointed at the wrong file.'
    );
    jooosi_fon_upgrade_assert(
        ! isset($GLOBALS['jooosi_fon_upgrade_options']['jooosi_fon_legacy_rebrand_upgraded']),
        'A conflicted filesystem migration was incorrectly marked complete.'
    );

    wp_delete_file($baseDirectory . '/jooosi-fon/fonts/conflict.woff2');
    wp_delete_file($baseDirectory . '/yabe-webfont/fonts/conflict.woff2');
    $GLOBALS['jooosi_fon_upgrade_options'] = [];
    $GLOBALS['jooosi_fon_upgrade_attachment_paths'] = [
        126 => 'yabe-webfont/fonts/retry.woff2',
    ];
    $GLOBALS['jooosi_fon_upgrade_fail_post_meta'] = true;
    file_put_contents($baseDirectory . '/yabe-webfont/fonts/retry.woff2', 'retry-font');

    (new LegacyRebrandUpgrade())->run();

    jooosi_fon_upgrade_assert(
        is_file($baseDirectory . '/yabe-webfont/fonts/retry.woff2'),
        'A failed metadata update removed the working legacy font file.'
    );
    jooosi_fon_upgrade_assert(
        $GLOBALS['jooosi_fon_upgrade_attachment_paths'][126] === 'yabe-webfont/fonts/retry.woff2',
        'A failed metadata update changed the attachment path.'
    );
    jooosi_fon_upgrade_assert(
        ! isset($GLOBALS['jooosi_fon_upgrade_options']['jooosi_fon_legacy_rebrand_upgraded']),
        'A failed metadata update was incorrectly marked complete.'
    );
} finally {
    jooosi_fon_upgrade_remove_directory($baseDirectory);
}

echo "Legacy rebrand upgrade tests passed.\n";
