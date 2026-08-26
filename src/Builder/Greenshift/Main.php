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

namespace JooosiFon\Builder\Greenshift;

use JooosiFon\Builder\BuilderInterface;
use JooosiFon\Utils\Font;

/**
 * Greenshift integration.
 *
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
class Main implements BuilderInterface
{
    public function __construct()
    {
        add_filter('gspb_local_font_array', fn ($localfonts) => $this->filter_gspb_local_font_array($localfonts), 1_000_001);
    }

    public function get_name(): string
    {
        return 'greenshift';
    }

    public function filter_gspb_local_font_array($localfonts)
    {
        $localfonts = (empty($localfont)) ? [] : json_decode($localfont, true, 512, JSON_THROW_ON_ERROR);

        $fonts = Font::get_fonts();

        foreach ($fonts as $font) {
            $localfonts[$font['family']] = [];
        }

        return json_encode($localfonts, JSON_THROW_ON_ERROR);
    }
}
