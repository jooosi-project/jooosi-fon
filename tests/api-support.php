<?php

declare(strict_types=1);

use JooosiFon\Api\Support\FontCodec;
use JooosiFon\Api\Support\FontEvents;
use JooosiFon\Api\Support\GoogleFontFileSelector;
use JooosiFon\Api\Support\RequestValidator;
use JooosiFon\Builder\Gutenberg\Main as GutenbergMain;

if (! class_exists('JOOOSI_FON')) {
    final class JOOOSI_FON
    {
        public const WP_OPTION = 'jooosi_fon';
    }
}

if (! class_exists('WP_Theme_JSON_Resolver')) {
    final class WP_Theme_JSON_Resolver
    {
        public static int $cleanCachedDataCalls = 0;

        public static function clean_cached_data(): void
        {
            ++self::$cleanCachedDataCalls;
        }
    }
}

if (! function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = ''): bool
    {
        $GLOBALS['jooosi_fon_test_cache_deletes'][] = [$key, $group];

        return true;
    }
}

if (! function_exists('do_action')) {
    function do_action($hook, ...$args): void
    {
        $GLOBALS['jooosi_fon_test_actions'][] = [$hook, $args];
    }
}

if (! function_exists('do_action_deprecated')) {
    function do_action_deprecated($hook, $args, $version, $replacement = null, $message = null): void
    {
        $GLOBALS['jooosi_fon_test_deprecated_actions'][] = [$hook, $args, $version, $replacement, $message];
    }
}

require_once dirname(__DIR__) . '/src/Api/Support/FontCodec.php';
require_once dirname(__DIR__) . '/src/Api/Support/FontEvents.php';
require_once dirname(__DIR__) . '/src/Api/Support/RequestValidator.php';
require_once dirname(__DIR__) . '/src/Api/Support/GoogleFontFileSelector.php';
require_once dirname(__DIR__) . '/src/Builder/BuilderInterface.php';
require_once dirname(__DIR__) . '/src/Builder/Gutenberg/Main.php';
require_once dirname(__DIR__) . '/src/Utils/Font.php';

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$payload = [
    'display' => 'swap',
    'files' => [[
        'attachment_id' => 42,
    ]],
];
$assert(FontCodec::decode(FontCodec::encode($payload), true) === $payload, 'Compressed font payload round-trip failed.');
$assert(FontCodec::decode(json_encode($payload, JSON_THROW_ON_ERROR), true) === $payload, 'Legacy JSON payload decoding failed.');

$invalidPayloadFailed = false;

try {
    FontCodec::decode('not-a-font-payload');
} catch (UnexpectedValueException $exception) {
    $invalidPayloadFailed = true;
}

$assert($invalidPayloadFailed, 'Invalid font payloads must fail closed.');
$assert(RequestValidator::positiveInteger(1), 'Positive integer validation failed.');
$assert(! RequestValidator::positiveInteger(0), 'Zero must not be a positive integer.');
$assert(RequestValidator::pageSize(100), 'Maximum page size should be accepted.');
$assert(! RequestValidator::pageSize(101), 'Oversized pages must be rejected.');
$assert(RequestValidator::boolean('false'), 'REST boolean strings should be accepted.');
$assert(RequestValidator::subsets('latin,latin-ext'), 'Valid subsets should be accepted.');
$assert(! RequestValidator::subsets('latin,<script>'), 'Invalid subsets must be rejected.');
$assert(RequestValidator::fontWeight('100 900'), 'Variable custom-font weight ranges should be accepted.');
$assert(! RequestValidator::fontWeight('0 1200'), 'Out-of-range custom-font weights must be rejected.');
$fontFaces = [[
    'id' => 'regular',
    'weight' => 400,
    'style' => 'normal',
    'files' => [[
        'attachment_id' => 42,
        'extension' => 'font/woff2',
    ]],
]];
$assert(RequestValidator::fontFaces($fontFaces), 'WordPress MIME-style font extensions should be accepted.');
$sanitizedFontFaces = RequestValidator::sanitizeFontFaces($fontFaces);
$assert($sanitizedFontFaces[0]['files'][0]['extension'] === 'woff2', 'Font MIME-style extensions should be normalized before storage.');
$assert(RequestValidator::arrayValue([
    'display' => 'swap',
]), 'Bounded settings arrays should be accepted.');
$assert(! RequestValidator::arrayValue([
    'value' => str_repeat('x', 2 * 1024 * 1024),
]), 'Oversized structured payloads must be rejected.');

$googleMetadata = [
    'google_fonts' => [
        'variable' => false,
        'formats' => ['woff2'],
        'subsets' => ['latin'],
        'font_data' => [
            'family' => 'Inter',
            'slug' => 'inter',
        ],
        'font_files' => [[
            'uid' => 'file-id',
            'format' => 'woff2',
            'weight' => 400,
            'style' => 'normal',
            'subsets' => ['latin'],
            'url' => 'https://fonts.gstatic.com/s/inter/example.woff2',
        ]],
        'font_faces' => [[
            'weight' => 400,
            'width' => '',
            'style' => 'normal',
            'isEnabled' => true,
            'display' => 'swap',
            'selector' => '',
            'comment' => '',
            'preload' => false,
        ]],
    ],
];
$assert(RequestValidator::googleMetadata($googleMetadata), 'Valid Google Fonts metadata should pass.');
$disabledGoogleMetadata = $googleMetadata;
$disabledGoogleMetadata['google_fonts']['font_faces'][0]['isEnabled'] = false;
$assert(! RequestValidator::googleMetadata($disabledGoogleMetadata), 'Google Fonts metadata must enable at least one face.');
$googleMetadata['google_fonts']['font_files'][0]['url'] = 'https://example.com/font.woff2';
$assert(! RequestValidator::googleMetadata($googleMetadata), 'Unapproved Google font hosts must be rejected.');

$variableFiles = [
    [
        'uid' => 'latin',
        'weight' => 0,
        'style' => 'normal',
        'format' => 'woff2',
        'subsets' => ['latin'],
    ],
    [
        'uid' => 'latin-ext',
        'weight' => 0,
        'style' => 'normal',
        'format' => 'woff2',
        'subsets' => ['latin-ext'],
    ],
];
$selectedVariableFiles = GoogleFontFileSelector::variableFiles(
    $variableFiles,
    [
        'weight' => 0,
        'style' => 'normal',
    ],
    ['latin', 'latin-ext'],
    ['woff2']
);
$assert(count($selectedVariableFiles) === 2, 'Variable font selection must retain every requested subset.');

$themePresets = [
    [
        'name' => 'Theme Sans',
        'slug' => 'theme-sans',
        'fontFamily' => 'system-ui',
    ],
    [
        'name' => 'Theme Inter',
        'slug' => 'inter',
        'fontFamily' => 'Inter, sans-serif',
    ],
];
$pluginPresets = [
    [
        'name' => 'Jooosi Inter',
        'slug' => 'inter',
        'fontFamily' => 'var(--jf--family-inter)',
    ],
    [
        'name' => 'Jooosi Serif',
        'slug' => 'jooosi-serif',
        'fontFamily' => 'var(--jf--family-jooosi-serif)',
    ],
];
$mergePresets = new ReflectionMethod(GutenbergMain::class, 'merge_font_family_presets');

if (PHP_VERSION_ID < 80100) {
    $mergePresets->setAccessible(true);
}

$mergedPresets = $mergePresets->invoke(null, $themePresets, $pluginPresets);
$assert(count($mergedPresets) === 3, 'Font preset collisions must not create duplicate choices.');
$assert($mergedPresets[1] === $pluginPresets[0], 'Jooosi font presets must win same-slug theme collisions.');
$assert(
    $mergePresets->invoke(null, $mergedPresets, $pluginPresets) === $mergedPresets,
    'Font preset merging must be idempotent.'
);

$GLOBALS['jooosi_fon_test_cache_deletes'] = [];
WP_Theme_JSON_Resolver::$cleanCachedDataCalls = 0;
FontEvents::invalidate('test:font-mutation');
$assert(
    $GLOBALS['jooosi_fon_test_cache_deletes'] === [['get_fonts', JOOOSI_FON::WP_OPTION]],
    'Font mutations must immediately invalidate the shared font cache.'
);
$assert(
    WP_Theme_JSON_Resolver::$cleanCachedDataCalls === 1,
    'Font mutations must reset WordPress theme.json resolver caches.'
);

$GLOBALS['jooosi_fon_test_cache_deletes'] = [];
WP_Theme_JSON_Resolver::$cleanCachedDataCalls = 0;
FontEvents::dispatch('test:font-mutation');
$assert(
    $GLOBALS['jooosi_fon_test_cache_deletes'] === [['get_fonts', JOOOSI_FON::WP_OPTION]],
    'A dispatched font mutation must invalidate the font cache exactly once.'
);
$assert(
    WP_Theme_JSON_Resolver::$cleanCachedDataCalls === 1,
    'A dispatched font mutation must reset theme.json caches exactly once.'
);

echo "API support tests passed.\n";
