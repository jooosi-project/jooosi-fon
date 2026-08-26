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
namespace JooosiFon\Admin;

use JooosiFonDeps\JOOOSI_FON;
use JooosiFon\Plugin;
use JooosiFon\Utils\Common;
use JooosiFon\Utils\Config;
use JooosiFon\Utils\Upload;
use JooosiFonDeps\Nabasa\VitePlus\Assets;
use WP_Query;
class AdminPage
{
    public function __construct()
    {
        add_action('admin_init', fn() => $this->redirect_legacy_page());
        add_filter('wp_check_filetype_and_ext', static fn($data, $file, $filename, $mimes) => Upload::disable_real_mime_check($data, $file, $filename, $mimes), 10, 4);
        add_filter('upload_mimes', static fn($mime_types) => Upload::upload_mimes($mime_types), 1000001);
        add_filter('upload_dir', fn($uploads) => $this->upload_dir($uploads), 1000001);
        add_action('admin_menu', fn() => $this->add_admin_menu());
        if (Config::get('misc.hide_media_library', \false)) {
            add_filter('ajax_query_attachments_args', fn(array $query) => $this->ajax_query_attachments_args($query), 1000001);
            add_action('load-upload.php', function () {
                add_action('pre_get_posts', fn(WP_Query $wpQuery) => $this->load_upload_pre_get_posts($wpQuery), 1000001);
            });
        }
    }
    public static function get_page_url(): string
    {
        return add_query_arg(['page' => JOOOSI_FON::WP_OPTION], admin_url('admin.php'));
    }
    public static function redirect_to_page(): void
    {
        Common::redirect(self::get_page_url());
    }
    public static function add_redirect_submenu_page($root_slug)
    {
        add_submenu_page($root_slug, __('Jooosi Fon', 'jooosi-fon'), __('Jooosi Fon', 'jooosi-fon'), 'manage_options', 'jooosi-fon-builder-redirect', static fn() => self::redirect_to_page());
    }
    public function add_admin_menu()
    {
        $hook = add_menu_page(__('Jooosi Fon', 'jooosi-fon'), __('Jooosi Fon', 'jooosi-fon'), 'manage_options', JOOOSI_FON::WP_OPTION, fn() => $this->render(), 'data:image/svg+xml;base64,' . base64_encode(file_get_contents(dirname(JOOOSI_FON::FILE) . '/jooosi-fon.svg')), 100);
        add_action('load-' . $hook, fn() => $this->init_hooks());
    }
    private function render()
    {
        add_filter('admin_footer_text', static fn($text) => 'Thank you for using <b>Jooosi Fon</b>! Join us on the <a href="https://www.facebook.com/groups/1142662969627943" target="_blank" rel="noopener noreferrer">Facebook Group</a>.', 1000001);
        add_filter('update_footer', static fn($text) => $text . ' | Jooosi Fon ' . JOOOSI_FON::VERSION, 1000001);
        echo '<div id="jooosi-fon-app" class=""></div>';
    }
    /**
     * Keep old bookmarks and integration links working during the 2.x rebrand.
     *
     * @todo Remove this legacy admin-page redirect completely in Jooosi Fon 3.0.0.
     */
    private function redirect_legacy_page(): void
    {
        if (!current_user_can('manage_options') || !isset($_GET['page']) || wp_unslash($_GET['page']) !== JOOOSI_FON::LEGACY_WP_OPTION) {
            return;
        }
        Common::redirect(self::get_page_url(), \true);
    }
    private function init_hooks()
    {
        add_action('admin_head', static fn() => remove_action('admin_notices', 'update_nag', 3), 1);
        add_action('admin_enqueue_scripts', fn() => $this->enqueue_scripts());
    }
    private function enqueue_scripts()
    {
        wp_enqueue_media();
        $handle = JOOOSI_FON::WP_OPTION . ':app';
        $assets = new Assets(dirname(JOOOSI_FON::FILE) . '/assets/dist', 'jooosi-fon');
        $assets->enqueue('resources/main.tsx', ['handle' => $handle, 'dependencies' => ['wp-i18n'], 'in_footer' => \true]);
        wp_set_script_translations($handle, 'jooosi-fon');
        wp_localize_script($handle, 'jooosiFon', ['_version' => JOOOSI_FON::VERSION, '_wpnonce' => wp_create_nonce(JOOOSI_FON::WP_OPTION), 'option_namespace' => JOOOSI_FON::WP_OPTION, 'text_domain' => 'jooosi-fon', 'web_history' => self::get_page_url(), 'rest_api' => ['nonce' => wp_create_nonce('wp_rest'), 'root' => esc_url_raw(rest_url()), 'namespace' => JOOOSI_FON::REST_NAMESPACE, 'url' => esc_url_raw(rest_url(JOOOSI_FON::REST_NAMESPACE))], 'assets' => ['url' => $assets->url()], 'lite_edition' => !Plugin::is_pro_edition()]);
    }
    private function ajax_query_attachments_args(array $query): array
    {
        if ($query['post_type'] !== 'attachment') {
            return $query;
        }
        if (!isset($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], 'page=' . JOOOSI_FON::WP_OPTION) === \false) {
            $all_mimes = get_allowed_mime_types();
            $query['post_mime_type'] = $all_mimes;
        }
        return $query;
    }
    private function load_upload_pre_get_posts(WP_Query $wpQuery): void
    {
        if (!isset($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], 'page=' . JOOOSI_FON::WP_OPTION) === \false) {
            $all_mimes = get_allowed_mime_types();
            $wpQuery->set('post_mime_type', $all_mimes);
        }
    }
    private function upload_dir($uploads)
    {
        if (!isset($_POST['jooosi_fon_font_upload'])) {
            return $uploads;
        }
        return Upload::font_upload_dir($uploads);
    }
}
