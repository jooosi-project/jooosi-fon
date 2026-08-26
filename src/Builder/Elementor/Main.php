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
namespace JooosiFon\Builder\Elementor;

use JooosiFon\Admin\AdminPage;
use JooosiFon\Builder\BuilderInterface;
use JooosiFon\Utils\Config;
use JooosiFon\Utils\Font;
/**
 * Elementor integration.
 *
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
class Main implements BuilderInterface
{
    public function __construct()
    {
        /**
         * Disable Elementor's built-in Google Fonts.
         */
        if (Config::get('builder_integrations.disable_google_fonts.elementor', \true)) {
            add_filter('pre_option_elementor_google_font', static fn($pre_option, $option, $default) => '0', 1000001, 3);
            add_filter('pre_update_option_elementor_google_font', static fn($value, $old_value, $option) => '0', 1000001, 3);
        }
        /**
         * Register fonts with 'custom' category for Atomic Widgets compatibility.
         * Atomic Widgets (Elementor v4) only supports: system, custom, googlefonts
         */
        add_filter('elementor/fonts/additional_fonts', fn($fonts) => $this->filter_elementor_fonts($fonts), 1000001);
        add_action('admin_menu', static fn() => AdminPage::add_redirect_submenu_page('elementor'), 1000001);
    }
    public function get_name(): string
    {
        return 'elementor';
    }
    /**
     * Register fonts with Elementor.
     * Uses 'custom' category for compatibility with both Classic Editor and Atomic Widgets.
     *
     * @param array $elementor_fonts Existing Elementor fonts.
     * @return array Modified fonts array.
     */
    public function filter_elementor_fonts($elementor_fonts)
    {
        $fonts = Font::get_fonts();
        foreach ($fonts as $font) {
            // Use 'custom' category for Atomic Widgets v4 compatibility
            // Atomic Widgets only recognizes: system, custom, googlefonts
            $elementor_fonts[$font['family']] = 'custom';
        }
        return $elementor_fonts;
    }
}
