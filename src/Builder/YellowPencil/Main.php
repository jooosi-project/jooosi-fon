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

namespace JooosiFon\Builder\YellowPencil;

use JOOOSI_FON;
use JooosiFon\Admin\AdminPage;
use JooosiFon\Builder\BuilderInterface;
use JooosiFon\Utils\Font;

/**
 * Yellow Pencil integration.
 *
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
class Main implements BuilderInterface
{
    public function __construct()
    {
        // enqueue scripts on wp-admin if the current page is revslider
        add_action('yp_editor_header', fn () => $this->editor_header(), 1_000_001);

        add_action('admin_menu', static fn () => AdminPage::add_redirect_submenu_page('yellow-pencil-changes'), 1_000_001);
    }

    public function get_name(): string
    {
        return 'yellow-pencil';
    }

    public function editor_header()
    {
        $fonts = Font::get_fonts();

        $jooosi_fonts = [];

        foreach ($fonts as $font) {
            $jooosi_fonts[] = [
                'value' => $font['family'],
                'label' => $font['title'],
                'category' => 'Jooosi Fon',
            ];
        }

        echo '<script src="' . plugin_dir_url(__FILE__) . 'assets/script/yellow-pencil.js?ver=' . JOOOSI_FON::VERSION . '" id="jooosi-fon-for-yellow-pencil"></script>';
        echo '<script>var jooosiFonts = ' . json_encode($jooosi_fonts) . ';</script>';
    }
}
