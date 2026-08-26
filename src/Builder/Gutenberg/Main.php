<?php

/*
 * This file is part of the Jooosi Fon package.
 *
 * (c) Joshua Gugun Siagian <suabahasa@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare (strict_types=1);
namespace JooosiFon\Builder\Gutenberg;

use JooosiFonDeps\JOOOSI_FON;
use JooosiFon\Builder\BuilderInterface;
use JooosiFon\Core\Cache;
use JooosiFon\Core\Frontpage;
use JooosiFon\Utils\Config;
use JooosiFon\Utils\Font;
use WP_Theme_JSON;
use WP_Theme_JSON_Data;
/**
 * Gutenberg integration.
 *
 * @see https://developer.wordpress.org/block-editor/how-to-guides/themes/theme-json/
 * @see https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/
 * @see https://developer.wordpress.org/themes/advanced-topics/theme-json/
 * @see https://make.wordpress.org/core/2022/10/10/filters-for-theme-json-data/
 * @see https://make.wordpress.org/core/2021/09/28/implementing-a-webfonts-api-in-wordpress-core/
 *
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
class Main implements BuilderInterface
{
    public function __construct()
    {
        add_filter('wp_theme_json_data_theme', fn($theme_json) => $this->filter_theme_json_data_theme($theme_json), 1000001);
        add_filter('f!jooosi/fon/core/cache:build_css.append_content', fn($css, $rows) => $this->filter_append_build_css_content($css, $rows), 1000001, 2);
        add_filter('f!jooosi/fon/core/cache:build_css.append_content', fn($css, $rows) => $this->filter_ensure_block_editor($css, $rows), 1000001, 2);
        // add_action('enqueue_block_editor_assets', fn () => $this->enqueue_block_editor_assets(), 1_000_001);
        // add_action('after_setup_theme', fn () => $this->after_setup_theme(), 1_000_001);
        add_action('enqueue_block_assets', fn() => $this->enqueue_block_assets(), 1000001);
    }
    public function get_name(): string
    {
        return 'gutenberg';
    }
    /**
     * @see https://make.wordpress.org/core/2022/10/10/filters-for-theme-json-data/
     * @param WP_Theme_JSON_Data $theme_json
     * @return WP_Theme_JSON_Data
     */
    public function filter_theme_json_data_theme($theme_json)
    {
        $theme_json_data = $theme_json->get_data();
        $theme_json_font_families = $theme_json_data['settings']['typography']['fontFamilies']['theme'] ?? [];
        $font_family_presets = [];
        foreach (Font::get_fonts() as $font) {
            /**
             * @see https://www.w3.org/TR/CSS22/syndata.html#value-def-identifier
             */
            $font_family_presets[] = ['name' => $font['title'], 'slug' => Font::slugify($font['family']), 'fontFamily' => Font::css_variable($font['family'])];
        }
        $new_data = ['version' => WP_Theme_JSON::LATEST_SCHEMA, 'settings' => ['typography' => ['fontFamilies' => self::merge_font_family_presets($theme_json_font_families, $font_family_presets)]]];
        return $theme_json->update_with($new_data);
    }
    public function enqueue_block_editor_assets()
    {
        $screen = get_current_screen();
        if (is_admin() && $screen->is_block_editor()) {
            add_action('admin_head', static function () {
                add_filter('f!jooosi/fon/api/setting/option:index_options', static function ($options) {
                    Config::propertyAccessor()->setValue($options, 'cache.inline_print', \false);
                    return $options;
                }, 1000001);
                Frontpage::enqueue_css_cache();
            }, 1000001);
        }
    }
    public function after_setup_theme()
    {
        // Add support for editor styles.
        add_theme_support('editor-styles');
        add_editor_style(Cache::get_versioned_cache_url(Cache::CSS_CACHE_FILE));
    }
    public function enqueue_block_assets()
    {
        if (is_admin() && file_exists(Cache::get_cache_path(Cache::CSS_CACHE_FILE))) {
            $handle = JOOOSI_FON::WP_OPTION . '-cache';
            $version = Cache::get_cache_version(Cache::CSS_CACHE_FILE) ?? JOOOSI_FON::VERSION;
            wp_enqueue_style($handle, Cache::get_cache_url(Cache::CSS_CACHE_FILE), [], $version);
        }
    }
    /**
     * ensure the css file is loaded to the block editor, template editor, or site editor
     */
    public function filter_ensure_block_editor($css, $rows): string
    {
        return $css . ".wp-block, .editor-styles-wrapper { } \n\n";
    }
    /**
     * Support for a non block-based theme.
     */
    public function filter_append_build_css_content($css, $rows)
    {
        if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
            return $css;
        }
        return $css . self::non_block_based_theme_support_classes();
    }
    public static function non_block_based_theme_support_classes(): string
    {
        $inline_css = '';
        $fonts = Font::get_fonts();
        foreach ($fonts as $font) {
            $inline_css .= sprintf(".has-%s-font-family {\n", Font::slugify($font['family']));
            $inline_css .= sprintf("\tfont-family: %s !important;\n", Font::css_variable($font['family']));
            $inline_css .= "}\n\n";
        }
        return $inline_css;
    }
    private static function merge_font_family_presets(array $existing, array $incoming): array
    {
        $merged = [];
        $positions = [];
        foreach (array_merge($existing, $incoming) as $preset) {
            $slug = is_array($preset) && isset($preset['slug']) && is_string($preset['slug']) ? $preset['slug'] : '';
            if ($slug === '') {
                $merged[] = $preset;
                continue;
            }
            if (isset($positions[$slug])) {
                $merged[$positions[$slug]] = $preset;
                continue;
            }
            $positions[$slug] = count($merged);
            $merged[] = $preset;
        }
        return array_values($merged);
    }
}
