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
namespace JooosiFon\Builder\FunnelKit;

use JooosiFon\Builder\BuilderInterface;
use JooosiFon\Utils\Config;
use JooosiFon\Utils\Font;
/**
 * FunnelKit integration.
 *
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
class Main implements BuilderInterface
{
    public function __construct()
    {
        /**
         * Disable SlingBlocks' built-in Google Fonts.
         */
        if (Config::get('builder_integrations.disable_google_fonts.funnelkit', \true)) {
            add_filter('bwf_custom_google_font', static fn() => [], 1000001);
            add_filter('bwf_custom_google_font_names_list', static fn() => [], 1000001);
            add_action('enqueue_block_editor_assets', fn() => $this->remove_google_fonts_list(), 1000001);
            add_action('wp_print_scripts', fn() => $this->dequeue_webfont(), 1000001);
        }
        // SlingBlocks
        add_filter('bwf_custom_system_font', fn($f) => $this->add_block_fonts($f), 1000001);
    }
    public function get_name(): string
    {
        return 'funnelkit';
    }
    private function add_block_fonts(array $bwf_fonts): array
    {
        $fonts = Font::get_fonts();
        $jooosi_fonts = [];
        foreach ($fonts as $font) {
            $jooosi_fonts[] = ['label' => '[Jooosi Fon] ' . $font['title'], 'value' => Font::css_variable($font['family']), 'google' => \false];
        }
        return array_merge($jooosi_fonts, $bwf_fonts);
    }
    private function remove_google_fonts_list()
    {
        if (!defined('SLINGBLOCKS_PLUGIN_VERSION')) {
            return;
        }
        $screen = get_current_screen();
        if (is_admin() && $screen->is_block_editor()) {
            if (!wp_script_is('slingblocks-editor', 'registered')) {
                return;
            }
            wp_add_inline_script('slingblocks-editor', 'const WebFont = { load: () => {} }', 'before');
        }
    }
    private function dequeue_webfont()
    {
        if (!defined('SLINGBLOCKS_PLUGIN_VERSION')) {
            return;
        }
        wp_dequeue_script('web-font');
    }
}
