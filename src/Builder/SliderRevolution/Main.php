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

namespace JooosiFon\Builder\SliderRevolution;

use JOOOSI_FON;
use JooosiFon\Admin\AdminPage;
use JooosiFon\Builder\BuilderInterface;
use JooosiFon\Core\Cache;
use JooosiFon\Utils\Config;
use JooosiFon\Utils\Font;

/**
 * Slider Revolution integration.
 *
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
class Main implements BuilderInterface
{
    public function __construct()
    {
        // disable revslider's built-in Google Fonts
        if (Config::get('builder_integrations.disable_google_fonts.slider_revolution', true)) {
            add_filter('revslider_data_get_font_familys', fn ($rev_fonts) => $this->remove_google_fonts($rev_fonts), 1_000_001);
        }

        add_filter('revslider_data_get_font_familys', fn ($rev_fonts) => $this->get_font_families($rev_fonts), 1_000_001);

        // enqueue scripts on wp-admin if the current page is revslider
        add_action('admin_enqueue_scripts', fn () => $this->enqueue_scripts(), 1_000_001);

        add_action('admin_menu', static fn () => AdminPage::add_redirect_submenu_page('revslider'), 1_000_001);
    }

    public function get_name(): string
    {
        return 'revolution-slider';
    }

    public function get_font_families($rev_fonts)
    {
        $fonts = Font::get_fonts();

        foreach ($fonts as $font) {
            // unshift to the beginning of the array
            array_unshift($rev_fonts, [
                'type' => 'jooosi-fon',
                'version' => 'Serif Fonts',
                'label' => $font['family'],
            ]);
        }

        return $rev_fonts;
    }

    public function remove_google_fonts($rev_fonts)
    {
        $rev_fonts = array_filter($rev_fonts, fn ($font) => $font['type'] !== 'googlefont');

        return $rev_fonts;
    }

    public function enqueue_scripts()
    {
        // check if the current page is revslider
        if (! function_exists('get_current_screen') || ! get_current_screen() || get_current_screen()->id !== 'toplevel_page_revslider') {
            return;
        }

        $version = Cache::get_cache_version(Cache::CSS_CACHE_FILE) ?? JOOOSI_FON::VERSION;

        wp_enqueue_style('jooosi-fon-revslider', Cache::get_cache_url(Cache::CSS_CACHE_FILE), [], $version);
    }
}
