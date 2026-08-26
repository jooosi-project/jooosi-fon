<?php

declare(strict_types=1);

use JooosiFon\Api\Migrations\Support\SourceFontNormalizer;

require_once dirname(__DIR__) . '/src/Api/Migrations/Support/SourceFontNormalizer.php';

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$brainstormForce = SourceFontNormalizer::brainstormForce([
    'font_display' => 'swap',
    'font_fallback' => 'Arial, sans-serif',
    'variations' => [
        [
            'font_weight' => '400italic',
            'font_style' => 'normal',
            'font_url' => [
                'https://example.test/uploads/inter-regular.woff2?version=2',
                'https://example.test/uploads/inter-regular-duplicate.woff2',
                'https://example.test/uploads/inter-regular.woff',
                'https://example.test/uploads/inter-regular.svg',
            ],
        ],
        [
            'font_weight' => '100 900',
            'font_style' => 'oblique',
            'font_url' => 'https://example.test/uploads/inter-variable.ttf',
        ],
    ],
]);

$assert($brainstormForce['display'] === 'swap', 'Brainstorm Force font-display should be retained.');
$assert($brainstormForce['fallback'] === 'Arial, sans-serif', 'Brainstorm Force fallback should be retained.');
$assert($brainstormForce['skipped'] === 0, 'Usable Brainstorm Force variations should not be marked as skipped.');
$assert(count($brainstormForce['faces']) === 2, 'Brainstorm Force variations should become separate faces.');
$assert($brainstormForce['faces'][0]['weight'] === '400', 'Weights with an italic suffix should be normalized.');
$assert($brainstormForce['faces'][0]['style'] === 'italic', 'An italic weight suffix should set the face style.');
$assert(count($brainstormForce['faces'][0]['files']) === 2, 'Duplicate and unsupported Brainstorm Force files should be excluded.');
$assert($brainstormForce['faces'][1]['weight'] === '100 900', 'Variable Brainstorm Force weight ranges should be retained.');

$useAnyFont = SourceFontNormalizer::useAnyFont([
    'first-face' => [
        'font_name' => 'brand-sans',
        'font_path' => '../unsafe/1234brand-sans',
        'font_weight' => '400',
        'font_style' => 'normal',
        'font_stretch' => 'condensed',
    ],
    'second-face' => [
        'font_name' => 'brand-sans',
        'font_path' => '5678brand-sans',
        'font_weight' => 'bold',
        'font_style' => 'italic',
        'font_stretch' => 'expanded',
    ],
    'serif-face' => [
        'font_name' => 'editorial-serif',
        'font_path' => '9876editorial-serif',
    ],
], [
    [
        'font_key' => 'first-face',
        'font_elements' => 'h1, .hero-title',
    ],
    [
        'font_name' => 'brand-sans',
        'font_elements' => '.button',
    ],
    [
        'font_name' => 'editorial-serif',
        'font_elements' => 'body { color: red; }',
    ],
], '/var/www/uploads/useanyfont', 'optional');

$assert(count($useAnyFont) === 2, 'Use Any Font rows should be grouped by family.');
$assert(count($useAnyFont['brand-sans']['faces']) === 2, 'Use Any Font variants should remain separate faces.');
$assert($useAnyFont['brand-sans']['faces'][0]['width'] === '75%', 'Condensed Use Any Font faces should map to CSS width percentages.');
$assert($useAnyFont['brand-sans']['faces'][1]['weight'] === '700', 'Named Use Any Font weights should be normalized.');
$assert($useAnyFont['brand-sans']['faces'][1]['width'] === '125%', 'Expanded Use Any Font faces should map to CSS width percentages.');
$assert($useAnyFont['brand-sans']['selector'] === 'h1, .hero-title, .button', 'Use Any Font selector assignments should be combined.');
$assert($useAnyFont['editorial-serif']['selector'] === '', 'Unsafe Use Any Font selectors should be rejected.');
$assert($useAnyFont['brand-sans']['display'] === 'optional', 'Use Any Font font-display should be retained.');
$assert(
    $useAnyFont['brand-sans']['faces'][0]['files'][0]['location'] === '/var/www/uploads/useanyfont/1234brand-sans.woff2',
    'Use Any Font source paths must stay inside its upload directory.'
);

$fontsPlugin = SourceFontNormalizer::fontsPlugin([
    [
        'source_key' => 'editorial-regular',
        'name' => 'Editorial Regular',
        'family' => 'Editorial Sans',
        'woff2' => 'https://example.test/uploads/editorial-regular.woff2',
        'woff' => 'https://example.test/uploads/editorial-regular.woff',
        'weight' => 'normal',
        'style' => 'normal',
        'preload' => '1',
    ],
    [
        'source_key' => 'editorial-bold-italic',
        'name' => 'Editorial Bold Italic',
        'family' => 'Editorial Sans',
        'woff2' => 'https://example.test/uploads/editorial-bold-italic.woff2',
        'weight' => 'bold',
        'style' => 'italic',
    ],
], [
    [
        'source_key' => 'editorial-regular',
        'selector' => 'body, .entry-content',
    ],
    [
        'source_key' => 'editorial-bold-italic',
        'selector' => 'h1, h2',
    ],
], 'fallback');

$assert(count($fontsPlugin) === 1, 'Fonts Plugin variants should be grouped by family.');
$assert(count($fontsPlugin['Editorial Sans']['faces']) === 2, 'Fonts Plugin variants should remain separate faces.');
$assert($fontsPlugin['Editorial Sans']['faces'][0]['weight'] === '400', 'Fonts Plugin named weights should be normalized.');
$assert($fontsPlugin['Editorial Sans']['faces'][1]['weight'] === '700', 'Fonts Plugin bold weights should be normalized.');
$assert($fontsPlugin['Editorial Sans']['preload'] === true, 'Fonts Plugin family preload should reflect its preloaded variants.');
$assert($fontsPlugin['Editorial Sans']['display'] === 'fallback', 'Fonts Plugin font-display should be retained.');
$assert(
    $fontsPlugin['Editorial Sans']['selector'] === 'body, .entry-content, h1, h2',
    'Fonts Plugin Customizer assignments should be combined by family.'
);

$elementor = SourceFontNormalizer::elementor([
    [
        'font_weight' => '100 900',
        'font_style' => 'normal',
        'woff2' => [
            'id' => 42,
            'url' => 'https://example.test/uploads/elementor-variable.woff2',
        ],
    ],
    [
        'font_weight' => '700italic',
        'font_style' => 'normal',
        'woff' => [
            'url' => 'https://example.test/uploads/elementor-bold-italic.woff',
        ],
    ],
    [
        'font_weight' => '400',
        'svg' => [
            'url' => 'https://example.test/uploads/unsupported.svg',
        ],
    ],
], 'swap');

$assert(count($elementor['faces']) === 2, 'Supported Elementor variants should become separate faces.');
$assert($elementor['skipped'] === 1, 'Elementor variants with only unsupported files should be skipped.');
$assert($elementor['faces'][0]['weight'] === '100 900', 'Elementor variable weight ranges should be retained.');
$assert($elementor['faces'][0]['files'][0]['attachment_id'] === 42, 'Elementor attachment IDs should be retained for local copying.');
$assert($elementor['faces'][1]['style'] === 'italic', 'Elementor italic weight suffixes should set the face style.');
$assert($elementor['display'] === 'swap', 'Elementor font-display should be retained.');

$customAdobe = SourceFontNormalizer::customAdobeKit([
    'custom-typekit-font-id' => 'abc1234',
    'custom-typekit-font-details' => [
        'Source-Sans-Pro' => [
            'family' => 'Source-Sans-Pro',
            'fallback' => 'source-sans-pro, sans-serif',
            'weights' => ['400', '700'],
            'slug' => 'source-sans-pro',
            'css_names' => ['source-sans-pro'],
        ],
        'invalid-family' => [
            'family' => 'Invalid Family',
        ],
    ],
]);

$assert($customAdobe['id'] === 'abc1234', 'Custom Adobe Fonts project IDs should be retained.');
$assert(count($customAdobe['families']) === 1, 'Invalid Custom Adobe Fonts families should be excluded.');
$assert($customAdobe['families'][0]['name'] === 'Source Sans Pro', 'Custom Adobe Fonts family names should be humanized.');
$assert($customAdobe['families'][0]['slug'] === 'source-sans-pro', 'Custom Adobe Fonts CSS family names should be retained.');
$assert($customAdobe['families'][0]['weights'] === ['400', '700'], 'Custom Adobe Fonts weights should be normalized.');

$scoperConfig = file_get_contents(dirname(__DIR__) . '/deploy/scoper.inc.php');
$assert(is_string($scoperConfig), 'The deploy scoper configuration should be readable.');

foreach ([
    'ogf_get_custom_elements',
    'ogf_get_elements',
    'uaf_path_details',
    'uaf_write_css',
    'BSF_CUSTOM_FONTS_POST_TYPE',
    'CUSTOM_TYPEKIT_FONTS_VER',
    'ELEMENTOR_PRO_VERSION',
    'OGF_VERSION',
    'UAF_FILE_PATH',
] as $externalSymbol) {
    $assert(
        strpos($scoperConfig, "'{$externalSymbol}'") !== false,
        "The deploy scoper configuration must preserve {$externalSymbol}."
    );
}

echo "Migration source tests passed.\n";
