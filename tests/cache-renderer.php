<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Core/Cache/FontCssRenderer.php';
require_once dirname(__DIR__) . '/src/Core/Cache/FontPreloadRenderer.php';
require_once dirname(__DIR__) . '/src/Api/Support/FontCodec.php';
require_once dirname(__DIR__) . '/src/Utils/Font.php';
require_once dirname(__DIR__) . '/src/Core/Cache/FontCacheSnapshotBuilder.php';

use JooosiFon\Core\Cache\FontCacheSnapshotBuilder;
use JooosiFon\Core\Cache\FontCssRenderer;
use JooosiFon\Core\Cache\FontPreloadRenderer;

$cacheTestAttachmentUrls = [
    10 => "https://cdn.example.test/font's.woff2?version=1&source=test",
];

if (! function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url(int $attachmentId)
    {
        global $cacheTestAttachmentUrls;

        return $cacheTestAttachmentUrls[$attachmentId] ?? false;
    }
}

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$fonts = [[
    'id' => 1,
    'type' => 'custom',
    'family' => "O'Font",
    'custom_property' => '--jf--family-o-font',
    'legacy_custom_property' => '--ywf--family-o-font',
    'display' => 'swap',
    'preload' => true,
    'selector' => '.uses-o-font',
    'fallback' => 'Arial, sans-serif',
    'faces' => [[
        'weight' => '100 900',
        'width' => '75% 125%',
        'style' => 'normal',
        'display' => '',
        'selector' => '.uses-o-font-bold',
        'comment' => 'Variable face',
        'unicode_range' => 'U+0000-00FF',
        'preload' => false,
        'files' => [[
            'attachment_id' => 10,
            'url' => "https://cdn.example.test/font's.woff2?version=1&source=test",
            'extension' => 'woff2',
            'mime' => 'font/woff2',
            'format' => 'woff2',
        ]],
    ]],
], [
    'id' => 2,
    'type' => 'custom',
    'family' => 'Empty Font',
    'custom_property' => '--jf--family-empty-font',
    'legacy_custom_property' => '--ywf--family-empty-font',
    'display' => 'swap',
    'preload' => false,
    'selector' => '',
    'fallback' => '',
    'faces' => [[
        'weight' => '400',
        'width' => '100%',
        'style' => 'normal',
        'display' => '',
        'selector' => '',
        'comment' => '',
        'unicode_range' => '',
        'preload' => false,
        'files' => [],
    ]],
]];

$css = (new FontCssRenderer())->render($fonts);
$assert(substr_count($css, '@font-face') === 1, 'Faces without usable files must not be rendered.');
$assert(strpos($css, "font-family: 'O\\'Font';") !== false, 'Font family strings must be CSS escaped.');
$assert(strpos($css, "font's.woff2") === false, 'Font URLs must be CSS escaped.');
$assert(strpos($css, '.uses-o-font-bold') !== false, 'Validated face selectors must be rendered.');
$assert(strpos($css, "--jf--family-o-font: 'O\\'Font', Arial, sans-serif;") !== false, 'Canonical font variables and fallbacks must be rendered.');
$assert(strpos($css, '--ywf--family-o-font: var(--jf--family-o-font);') !== false, 'Legacy font variables must alias their canonical property.');
$assert(strpos($css, 'font-family: var(--jf--family-o-font);') !== false, 'Generated selectors must use the canonical font variable.');

$unsafeFonts = $fonts;
$unsafeFonts[0]['family'] = 'Unsafe </style><script>alert(1)</script>';
$unsafeCss = (new FontCssRenderer())->render($unsafeFonts);
$assert(stripos($unsafeCss, '</style') === false, 'Inline CSS must neutralize closing style tags.');

$preload = (new FontPreloadRenderer())->render($fonts, 1);
$assert(substr_count($preload, 'rel="preload"') === 1, 'Preload output must respect its configured limit.');
$assert(strpos($preload, '&amp;source=test') !== false, 'Preload attributes must be HTML escaped.');

$row = (object) [
    'id' => 42,
    'type' => 'custom',
    'family' => 'Snapshot Font',
    'metadata' => json_encode([
        'display' => 'swap',
        'preload' => true,
        'selector' => '.snapshot | Arial, sans-serif',
    ], JSON_THROW_ON_ERROR),
    'font_faces' => json_encode([
        'named-face' => 'invalid',
        [
            'isEnabled' => false,
            'weight' => '700',
            'width' => '100%',
            'style' => 'normal',
            'comment' => 'Disabled face',
            'files' => [[
                'attachment_id' => 10,
                'extension' => 'woff2',
            ]],
        ],
        [
            'weight' => '9999',
            'width' => 'not-valid',
            'style' => 'not-valid',
            'selector' => '</style><script>alert(1)</script>',
            'comment' => '</style> comment',
            'files' => [[
                'attachment_id' => 10,
                'extension' => 'woff2',
            ], [
                'attachment_id' => 10,
                'extension' => 'woff2',
            ], [
                'attachment_id' => 999,
                'extension' => 'woff',
            ]],
        ],
    ], JSON_THROW_ON_ERROR),
];

$GLOBALS['wpdb'] = new class([$row]) {
    public $prefix = 'wp_';

    public $last_error = '';

    private $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function get_results(string $query): array
    {
        return $this->rows;
    }
};

$snapshot = (new FontCacheSnapshotBuilder())->build();
$assert(count($snapshot['fonts']) === 1, 'A valid stored font must be normalized into the cache snapshot.');
$assert($snapshot['fonts'][0]['custom_property'] === '--jf--family-snapshot-font', 'Snapshots must expose the canonical Jooosi Fon variable.');
$assert($snapshot['fonts'][0]['legacy_custom_property'] === '--ywf--family-snapshot-font', 'Snapshots must retain the legacy variable alias.');
$assert($snapshot['fonts'][0]['faces'][0]['weight'] === '400', 'Invalid face weights must use a safe default.');
$assert($snapshot['fonts'][0]['faces'][0]['width'] === '100%', 'Invalid face widths must use a safe default.');
$assert($snapshot['fonts'][0]['faces'][0]['selector'] === '', 'Unsafe selectors must not reach the CSS renderer.');
$assert(count($snapshot['fonts'][0]['faces']) === 1, 'Disabled custom font faces must not reach the cache snapshot.');
$assert(count($snapshot['fonts'][0]['faces'][0]['files']) === 1, 'Duplicate and missing font resources must be excluded.');
$assert(count($snapshot['warnings']) >= 2, 'Malformed faces and missing resources must be reported as build warnings.');

echo "Cache renderer tests passed.\n";
