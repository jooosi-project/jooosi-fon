<?php

/*
 * This file is part of the Jooosi Fon package.
 *
 * (c) Joshua Gugun Siagian <suabahasa@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace JooosiFon\Builder\Oxygen;

use JOOOSI_FON;
use JooosiFon\Admin\AdminPage;
use JooosiFon\Builder\BuilderInterface;
use JooosiFon\Builder\Gutenberg\Main as GutenbergMain;
use JooosiFon\Utils\Config;
use JooosiFon\Utils\Font;

/**
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
class Main implements BuilderInterface
{
    public function __construct()
    {
        /**
         * Disable Oxygen's built-in Google Fonts.
         */
        if (Config::get('builder_integrations.disable_google_fonts.oxygen', true)) {
            add_filter('pre_option_oxygen_vsb_disable_google_fonts', static fn ($pre_option, $option, $default) => 'true', 1_000_001, 3);
            add_filter('pre_update_option_oxygen_vsb_disable_google_fonts', static fn ($value, $old_value, $option) => 'true', 1_000_001, 3);
            add_filter('pre_option_oxygen_vsb_enable_google_fonts_cache', static fn ($pre_option, $option, $default) => 'true', 1_000_001, 3);
            add_filter('pre_update_option_oxygen_vsb_enable_google_fonts_cache', static fn ($value, $old_value, $option) => 'true', 1_000_001, 3);
            add_filter('pre_option_oxygen_vsb_google_fonts_cache', static fn ($pre_option, $option, $default) => [[
                'family' => 'Inherit',
            ]], 1_000_001, 3);
            add_filter('pre_update_option_oxygen_vsb_google_fonts_cache', static fn ($value, $old_value, $option) => [[
                'family' => 'Inherit',
            ]], 1_000_001, 3);
            add_action('init', fn () => $this->remove_ecf_action(), 1_000_001);
        }

        /**
         * Add Gutenberg non block-based theme support.
         */
        add_filter('f!jooosi/fon/core/cache:build_css.append_content', fn ($css, $rows) => $this->filter_append_build_css_content_for_gutenberg($css, $rows), 1_000_001, 2);

        add_action('wp_enqueue_scripts', fn () => $this->enqueue_editor_style(), 1_000_001);
        add_action('ct_builder_ng_init', fn () => $this->elegant_custom_fonts(), 1_000_001);
        add_action('admin_menu', static fn () => AdminPage::add_redirect_submenu_page('ct_dashboard_page'), 1_000_001);
    }

    public function get_name(): string
    {
        return 'oxygen';
    }

    public function elegant_custom_fonts()
    {
        $output = json_encode(array_column(Font::get_fonts(), 'family'), JSON_THROW_ON_ERROR);
        $output = htmlspecialchars($output, ENT_QUOTES);
        echo sprintf('elegantCustomFonts=%s;', $output);
    }

    public function enqueue_editor_style()
    {
        if (! defined('SHOW_CT_BUILDER')) {
            return;
        }

        wp_enqueue_style('jooosi-fon-for-oxygen-editor', plugin_dir_url(__FILE__) . '/assets/style/editor.css', [], JOOOSI_FON::VERSION);
    }

    public function remove_ecf_action()
    {
        remove_action('oxygen_enqueue_scripts', 'add_web_font');
        remove_action('ct_builder_ng_init', 'ct_init_elegant_custom_fonts');
    }

    /**
     * Support for a non block-based theme.
     */
    public function filter_append_build_css_content_for_gutenberg($css, $rows)
    {
        if (function_exists('wp_is_block_theme') && wp_is_block_theme() && method_exists(GutenbergMain::class, 'non_block_based_theme_support_classes')) {
            $css .= GutenbergMain::non_block_based_theme_support_classes();
        }

        return $css;
    }
}
