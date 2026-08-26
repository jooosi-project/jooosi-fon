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
namespace JooosiFon\Core;

use JooosiFonDeps\JOOOSI_FON;
use JooosiFon\Utils\Config;
use JooosiFon\Utils\Debug;
/**
 * Serve the font on the frontpage.
 *
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 * @todo Remove the legacy Yabe Webfont hook shim completely in Jooosi Fon 3.0.0.
 */
final class Frontpage
{
    /**
     * @var string[]
     */
    private array $preload_script_handles = [];
    /**
     * @var array<int, array<string, string>>
     */
    private array $preload_resources = [];
    private bool $print_preload_resources = \false;
    public function __construct()
    {
        /**
         * @see wp-includes\default-filters.php for the priority.
         */
        add_action('wp_head', fn() => $this->append_header(), 1000001);
        add_filter('wp_preload_resources', fn($resources) => $this->preload($resources));
        add_action('wp_enqueue_scripts', fn() => $this->disable_user_google_fonts(), 1000001);
    }
    public static function enqueue_css_cache()
    {
        if (defined('JooosiFonDeps\JOOOSI_FON_CSS_CACHE_WAS_LOADED')) {
            return;
        }
        Debug::stopwatch()->start(sprintf('%s::%s', self::class, __FUNCTION__));
        if (file_exists(\JooosiFon\Core\Cache::get_cache_path(\JooosiFon\Core\Cache::CSS_CACHE_FILE))) {
            $handle = JOOOSI_FON::WP_OPTION . '-cache';
            $version = \JooosiFon\Core\Cache::get_cache_version(\JooosiFon\Core\Cache::CSS_CACHE_FILE) ?? JOOOSI_FON::VERSION;
            if (Config::get('cache.inline_print', \false)) {
                $css = file_get_contents(\JooosiFon\Core\Cache::get_cache_path(\JooosiFon\Core\Cache::CSS_CACHE_FILE));
                if ($css !== \false) {
                    wp_register_style($handle, \false, [], null);
                    wp_add_inline_style($handle, $css);
                }
            } else {
                wp_register_style($handle, \JooosiFon\Core\Cache::get_cache_url(\JooosiFon\Core\Cache::CSS_CACHE_FILE), [], $version);
            }
            do_action('a!jooosi/fon/core/frontpage:before_print_style');
            do_action_deprecated('a!yabe/webfont/core/frontpage:before_print_style', [], '2.1.0', 'a!jooosi/fon/core/frontpage:before_print_style');
            wp_enqueue_style($handle);
            wp_print_styles($handle);
        }
        define('JooosiFonDeps\JOOOSI_FON_CSS_CACHE_WAS_LOADED', \true);
        Debug::stopwatch()->stop(sprintf('%s::%s', self::class, __FUNCTION__));
    }
    /**
     * Append the header to the frontpage.
     */
    private function append_header()
    {
        if (function_exists('wp_preload_resources')) {
            $this->print_preload_resources = \true;
            wp_preload_resources();
            $this->print_preload_resources = \false;
        } else {
            $this->preload([]);
        }
        self::enqueue_css_cache();
        if ($this->preload_script_handles !== []) {
            wp_print_scripts($this->preload_script_handles);
        }
    }
    /**
     * Preload the fonts file on the frontpage.
     */
    private function preload($resources)
    {
        if (defined('JooosiFonDeps\JOOOSI_FON_PRELOAD_HTML_WAS_LOADED') && !$this->print_preload_resources) {
            return $resources;
        }
        if (!defined('JooosiFonDeps\JOOOSI_FON_PRELOAD_HTML_WAS_LOADED')) {
            Debug::stopwatch()->start(sprintf('%s::%s', self::class, __FUNCTION__));
            if (file_exists(\JooosiFon\Core\Cache::get_cache_path(\JooosiFon\Core\Cache::PRELOAD_HTML_FILE))) {
                $preload_html = file_get_contents(\JooosiFon\Core\Cache::get_cache_path(\JooosiFon\Core\Cache::PRELOAD_HTML_FILE));
                if ($preload_html !== \false) {
                    preg_match_all('/<link\b[^>]*>/i', $preload_html, $link_tags);
                    foreach ($link_tags[0] as $link_tag) {
                        $rel = $this->get_attribute($link_tag, 'rel');
                        $href = $this->get_attribute($link_tag, 'href');
                        if (strtolower((string) $rel) !== 'preload' || $href === null) {
                            continue;
                        }
                        $resource = ['href' => $href];
                        foreach (['as', 'type', 'media', 'fetchpriority'] as $attribute) {
                            $value = $this->get_attribute($link_tag, $attribute);
                            if ($value !== null) {
                                $resource[$attribute] = $value;
                            }
                        }
                        if (stripos($link_tag, ' crossorigin') !== \false) {
                            $resource['crossorigin'] = '';
                        }
                        $this->preload_resources[] = $resource;
                    }
                    preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $preload_html, $script_tags);
                    foreach ($script_tags[1] as $script) {
                        $script = trim($script);
                        if ($script === '') {
                            continue;
                        }
                        $handle = sprintf('%s-preload-%s', JOOOSI_FON::WP_OPTION, md5($script));
                        wp_register_script($handle, \false, [], null, \false);
                        wp_add_inline_script($handle, $script);
                        $this->preload_script_handles[] = $handle;
                    }
                }
            }
            define('JooosiFonDeps\JOOOSI_FON_PRELOAD_HTML_WAS_LOADED', \true);
            Debug::stopwatch()->stop(sprintf('%s::%s', self::class, __FUNCTION__));
        }
        return $this->print_preload_resources ? $this->preload_resources : $resources;
    }
    private function get_attribute(string $tag, string $attribute): ?string
    {
        $pattern = sprintf('/\b%s\s*=\s*([\'\"])(.*?)\1/is', preg_quote($attribute, '/'));
        if (preg_match($pattern, $tag, $matches)) {
            return html_entity_decode($matches[2], \ENT_QUOTES, 'UTF-8');
        }
        return null;
    }
    /**
     * Scan and disable Google Fonts API that loaded manually by the theme or plugin through `wp_enqueue_style` function.
     */
    private function disable_user_google_fonts()
    {
        $is_disable = Config::get('misc.disable_user_google_fonts', \false);
        if (!$is_disable) {
            return;
        }
        global $wp_styles;
        foreach ($wp_styles->queue as $q) {
            if ($wp_styles->registered[$q]->src && strpos($wp_styles->registered[$q]->src, 'fonts.googleapis.com') !== \false) {
                wp_dequeue_style($q);
                wp_deregister_style($q);
            }
        }
    }
}
