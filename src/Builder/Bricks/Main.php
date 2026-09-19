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

namespace JooosiFon\Builder\Bricks;

use JooosiFon\Admin\AdminPage;
use JooosiFon\Builder\BuilderInterface;
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
         * Prevent Google Fonts loading
         * @see https://academy.bricksbuilder.io/article/filter-bricks-assets-load_webfonts/
         */
        if (Config::get('builder_integrations.disable_google_fonts.bricks', true)) {
            add_filter('bricks/assets/load_webfonts', '__return_false', 1_000_001);
        }

        /**
         * @see https://academy.bricksbuilder.io/article/filter-standard-fonts/
         */
        // add_filter('bricks/builder/standard_fonts', static fn ($fonts) => array_merge($fonts, array_column(Font::get_fonts(), 'family')), 1_000_001);

        add_action('admin_menu', static fn () => AdminPage::add_redirect_submenu_page('bricks'), 1_000_001);

        add_action('wp_enqueue_scripts', fn () => $this->enqueue_scripts(), 1_000_001);
    }

    public function get_name(): string
    {
        return 'bricks';
    }

    public function enqueue_scripts()
    {
        if (! function_exists('bricks_is_builder') || ! bricks_is_builder()) {
            return;
        }

        if (! wp_script_is('bricks-builder', 'registered')) {
            return;
        }

        $jooosiFonBricksOptions = [];
        $fonts = Font::get_fonts();
        foreach ($fonts as $font) {
            $jooosiFonBricksOptions[$font['family']] = $font['title'];
        }
        wp_add_inline_script('bricks-builder', 'var jooosiFonBricksOptions = ' . json_encode($jooosiFonBricksOptions, JSON_THROW_ON_ERROR), 'before');

        if (version_compare(BRICKS_VERSION, '2.0-alpha', '>=')) {
            wp_add_inline_script('bricks-builder', 'bricksData.fonts.standard = bricksData.fonts.standard.concat(' . json_encode(array_column($fonts, 'family'), JSON_THROW_ON_ERROR) . ');', 'before');
            wp_add_inline_script('bricks-builder', 'bricksData.loadData = bricksData.loadData || {}; bricksData.loadData.fontFavorites = (bricksData.loadData.fontFavorites || []).concat(' . json_encode(array_map(static fn ($font) => 'standard_' . $font['family'], $fonts), JSON_THROW_ON_ERROR) . ');', 'before');
            wp_add_inline_script('bricks-builder', "bricksData.fonts.options = { ...{'jooosiFonGroupTitle': 'Jooosi Fon' }, ...jooosiFonBricksOptions, ...bricksData.fonts.options};", 'before');
        } elseif (version_compare(BRICKS_VERSION, '1.7.1', '>=')) {
            wp_add_inline_script('bricks-builder', 'bricksData.fonts.jooosiFon = ' . json_encode(array_column($fonts, 'family'), JSON_THROW_ON_ERROR), 'before');
            wp_add_inline_script('bricks-builder', "bricksData.fonts.options = { ...{'jooosiFonGroupTitle': 'Jooosi Fon' }, ...jooosiFonBricksOptions, ...bricksData.fonts.options};", 'before');
        } else {
            wp_add_inline_script('bricks-builder', 'bricksData.loadData.fonts.jooosiFon = ' . json_encode(array_column($fonts, 'family'), JSON_THROW_ON_ERROR), 'before');
            wp_add_inline_script('bricks-builder', "bricksData.loadData.fonts.options = { ...{'jooosiFonGroupTitle': 'Jooosi Fon' }, ...jooosiFonBricksOptions, ...bricksData.loadData.fonts.options};", 'before');
        }
    }
}
