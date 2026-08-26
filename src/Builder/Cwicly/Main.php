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
namespace JooosiFon\Builder\Cwicly;

use JooosiFon\Admin\AdminPage;
use JooosiFon\Builder\BuilderInterface;
use JooosiFon\Utils\Font;
/**
 * Cwicly integration.
 *
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
class Main implements BuilderInterface
{
    public function __construct()
    {
        add_action('enqueue_block_editor_assets', fn() => $this->enqueue_block_editor_assets(), 1000001);
        add_action('admin_menu', static fn() => AdminPage::add_redirect_submenu_page('cwicly'), 1000001);
    }
    public function get_name(): string
    {
        return 'cwicly';
    }
    public function enqueue_block_editor_assets()
    {
        if (!wp_script_is('cwicly_editor_blocks', 'registered')) {
            return;
        }
        $fonts = Font::get_fonts();
        $localFonts = [];
        foreach ($fonts as $font) {
            $key = sprintf('custom-jooosi-fon-%s', Font::slugify($font['family']));
            $localFonts[$key] = ['family' => $font['family'], 'fonts' => [], 'type' => 'custom', 'category' => 'Sans Serif', 'display' => 'swap', 'subsets' => [], 'axes' => []];
        }
        wp_add_inline_script('cwicly_editor_blocks', 'var jooosiFonCwiclyLocalFonts = ' . json_encode($localFonts, \JSON_THROW_ON_ERROR), 'before');
        wp_add_inline_script('cwicly_editor_blocks', "\n            if (typeof cwicly_info.starters.localfonts !== 'object') {\n                cwicly_info.starters.localfonts = {};\n            }\n\n            if (!Array.isArray(cwicly_info.starters.localactivefonts)) {\n                cwicly_info.starters.localactivefonts = [];\n            }\n\n            Object.entries(jooosiFonCwiclyLocalFonts).forEach(function ([key, value]) {\n                cwicly_info.starters.localfonts[key] = value;\n                cwicly_info.starters.localactivefonts.push(key);\n            });\n\n            setTimeout(function () {\n                wp.data.dispatch('cwicly/base').writeLocalActiveFonts(cwicly_info.starters.localactivefonts);\n                wp.data.dispatch('cwicly/base').writeLocalFonts(cwicly_info.starters.localfonts);\n            }, 1000);\n        ", 'before');
    }
}
