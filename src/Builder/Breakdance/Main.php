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
namespace JooosiFon\Builder\Breakdance;

use Breakdance\Fonts\FontsController;
use JooosiFon\Admin\AdminPage;
use JooosiFon\Builder\BuilderInterface;
use JooosiFon\Core\Cache;
use JooosiFon\Utils\Config;
use JooosiFon\Utils\Font;
/**
 * Breakdance integration.
 *
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
class Main implements BuilderInterface
{
    public function __construct()
    {
        if (defined('__BREAKDANCE_VERSION')) {
            // 1.2.1 is the last breakpoint version that supports the old font registration method
            if (version_compare(\__BREAKDANCE_VERSION, '1.2.1', '>')) {
                if (Config::get('builder_integrations.disable_google_fonts.breakdance', \true)) {
                    remove_action('breakdance_register_fonts', '\Breakdance\GoogleFontsPlugin\loadGoogleFonts');
                }
                add_action('breakdance_register_fonts', fn(FontsController $fontsController) => $this->register_fonts($fontsController), -1000001);
            } else {
                if (Config::get('builder_integrations.disable_google_fonts.breakdance', \true)) {
                    remove_action('init', '\Breakdance\GoogleFontsPlugin\loadGoogleFonts');
                }
                add_action('breakdance_loaded', fn() => $this->register_fonts_v10201(), 1000001);
            }
        }
        add_action('admin_menu', static fn() => AdminPage::add_redirect_submenu_page('breakdance'), 1000001);
    }
    public function get_name(): string
    {
        return 'breakdance';
    }
    public function register_fonts(FontsController $fontsController)
    {
        $fonts = Font::get_fonts();
        foreach ($fonts as $font) {
            $cssName = Font::css_variable($font['family']);
            $dropdownLabel = sprintf('[Jooosi Fon] %s', $font['title']);
            $fallbackString = $font['fallback_family'] ?? '';
            $dependencies = ['styles' => []];
            $previewImageUrl = null;
            if ($font['type'] === 'google-fonts') {
                $previewImageUrl = sprintf('https://cdn.jsdelivr.net/gh/khoben/gfont-previews/output/previews/%s-regular.png', str_replace(' ', '%20', $font['family']));
            }
            $fontsController->registerFont(Font::slugify($font['family']), $cssName, $dropdownLabel, $fallbackString, $dependencies, $previewImageUrl);
        }
    }
    public function register_fonts_v10201()
    {
        $fonts = Font::get_fonts();
        foreach ($fonts as $font) {
            $cssName = Font::css_variable($font['family']);
            $dropdownLabel = sprintf('[Jooosi Fon] %s', $font['title']);
            $fallbackString = $font['fallback_family'] ?? '';
            $dependencies = ['styles' => []];
            \Breakdance\Fonts\registerFont(Font::slugify($font['family']), $cssName, $dropdownLabel, $fallbackString, $dependencies);
        }
    }
}
