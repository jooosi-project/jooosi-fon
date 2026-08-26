<?php

/**
 * Jooosi Fon
 *
 * @wordpress-plugin
 * Plugin Name:         Jooosi Fon
 * Plugin URI:          https://fon.jooo.si
 * Description:         Easy self-host Google Fonts, Adobe Fonts support, or upload custom fonts in WordPress that are integrated into the most popular themes and page builders.
 * Version:             1.1.1
 * Requires at least:   6.0
 * Requires PHP:        7.4
 * Author:              Jooosi
 * Author URI:          https://jooo.si
 * Donate link:         https://ko-fi.com/Q5Q75XSF7
 * Text Domain:         jooosi-fon
 * Domain Path:         /languages
 * License:             GPL-3.0-or-later
 *
 * @package             JooosiFon
 * @author              Joshua Gugun Siagian <suabahasa@gmail.com>
 */
declare (strict_types=1);
namespace JooosiFonDeps;

\defined('ABSPATH') || exit;
if (\file_exists(__DIR__ . '/vendor/autoload.php')) {
    if (\file_exists(__DIR__ . '/vendor/scoper-autoload.php')) {
        require_once __DIR__ . '/vendor/scoper-autoload.php';
    } else {
        require_once __DIR__ . '/vendor/autoload.php';
    }
    \JooosiFon\Plugin::get_instance()->boot();
}
