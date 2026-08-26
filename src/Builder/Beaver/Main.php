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

namespace JooosiFon\Builder\Beaver;

use JooosiFon\Builder\BuilderInterface;
use JooosiFon\Utils\Font;

/**
 * Beaver integration.
 *
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
class Main implements BuilderInterface
{
    public function __construct()
    {
        add_filter('fl_builder_google_fonts_pre_enqueue', static fn () => [], 1_000_001);
        add_filter('fl_builder_font_families_google', static fn () => [], 1_000_001);

        add_filter('fl_builder_font_families_system', fn ($fonts) => $this->custom_fonts($fonts), 1_000_001);
    }

    public function get_name(): string
    {
        return 'beaver';
    }

    public function custom_fonts($fl_fonts)
    {
        $fonts = Font::get_fonts();

        $jooosi_fonts = [];

        foreach ($fonts as $font) {
            $jooosi_fonts[$font['family']] = [
                'fallback' => 'system-ui, sans-serif',
                'weights' => ['100', '200', '300', '400', '500', '600', '700', '800', '900'],
            ];
        }

        return array_merge($jooosi_fonts, $fl_fonts);
    }
}
