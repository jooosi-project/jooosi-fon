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
namespace JooosiFon\Builder\Blocksy;

use JooosiFon\Admin\AdminPage;
use JooosiFon\Builder\BuilderInterface;
use JooosiFon\Utils\Font;
/**
 * Blocksy integration.
 *
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
class Main implements BuilderInterface
{
    public function __construct()
    {
        add_filter('blocksy_typography_font_sources', fn($blocksy_fonts) => $this->font_sources($blocksy_fonts), 1000001);
        add_action('admin_menu', static fn() => AdminPage::add_redirect_submenu_page('ct-dashboard'), 1000001);
    }
    public function get_name(): string
    {
        return 'blocksy';
    }
    public function font_sources($blocksy_fonts)
    {
        unset($blocksy_fonts['google']);
        $fonts = Font::get_fonts();
        foreach ($fonts as $font) {
            array_unshift($blocksy_fonts['system']['families'], ['__custom' => \true, 'source' => 'system', 'family' => $font['family'], 'display' => $font['title'], 'variations' => [], 'all_variations' => ['n1', 'i1', 'n2', 'i2', 'n3', 'i3', 'n4', 'i4', 'n5', 'i5', 'n6', 'i6', 'n7', 'i7', 'n8', 'i8', 'n9', 'i9']]);
        }
        return $blocksy_fonts;
    }
}
