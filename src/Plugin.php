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
namespace JooosiFon;

use EasyDigitalDownloads\Updater\Registry;
use Exception;
use JooosiFonDeps\JOOOSI_FON;
use JooosiFon\Admin\AdminPage;
use JooosiFon\Api\Router as ApiRouter;
use JooosiFon\Builder\Integration as BuilderIntegration;
use JooosiFon\Core\Cache;
use JooosiFon\Core\Runtime;
use JooosiFon\Licensing\Manager as LicenseManager;
use JooosiFon\Upgrade\LegacyRebrandUpgrade;
use JooosiFon\Utils\Common;
use JooosiFon\Utils\Debug;
use JooosiFon\Utils\Notice;
/**
 * Manage the plugin lifecycle and provides a single point of entry to the plugin.
 *
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 * @todo Remove all legacy Yabe Webfont hook shims completely in Jooosi Fon 3.0.0.
 */
final class Plugin
{
    /**
     * Easy Digital Downloads Software Licensing integration manager.
     * Pro version only.
     *
     * @var LicenseManager|null
     */
    public $plugin_updater = null;
    /**
     * Stores the instance, implementing a Singleton pattern.
     */
    private static self $instance;
    /**
     * The Singleton's constructor should always be private to prevent direct
     * construction calls with the `new` operator.
     */
    private function __construct()
    {
    }
    /**
     * Singletons should not be cloneable.
     */
    private function __clone()
    {
    }
    /**
     * Singletons should not be restorable from strings.
     *
     * @throws Exception Cannot unserialize a singleton.
     */
    public function __wakeup()
    {
        throw new Exception('Cannot unserialize a singleton.');
    }
    /**
     * This is the static method that controls the access to the singleton
     * instance. On the first run, it creates a singleton object and places it
     * into the static property. On subsequent runs, it returns the client existing
     * object stored in the static property.
     */
    public static function get_instance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    public function boot_debug()
    {
        if (\WP_DEBUG === \false) {
            return;
        }
        // if (class_exists(\Sentry\SentrySdk::class)) {
        // }
        // when php exits, call the shutdown function.
        register_shutdown_function(static fn() => Debug::shutdown());
    }
    public function boot_migration()
    {
        do_action('a!jooosi/fon/plugin:boot_migration.start');
        do_action_deprecated('a!yabe/webfont/plugin:boot_migration.start', [], '2.1.0', 'a!jooosi/fon/plugin:boot_migration.start');
        /** @var wpdb $wpdb */
        global $wpdb;
        $wpdb->jooosi_fon_prefix = JOOOSI_FON::DB_TABLE_PREFIX;
        new \JooosiFon\Migration();
        do_action('a!jooosi/fon/plugin:boot_migration.end');
        do_action_deprecated('a!yabe/webfont/plugin:boot_migration.end', [], '2.1.0', 'a!jooosi/fon/plugin:boot_migration.end');
    }
    /**
     * Boot to the Plugin.
     */
    public function boot(): void
    {
        $this->migrate_legacy_options();
        $this->boot_license_sdk();
        do_action('a!jooosi/fon/plugins:boot_start');
        do_action_deprecated('a!yabe/webfont/plugins:boot_start', [], '2.1.0', 'a!jooosi/fon/plugins:boot_start');
        $this->boot_debug();
        $this->boot_migration();
        (new LegacyRebrandUpgrade())->run();
        // (de)activation hooks.
        register_activation_hook(JOOOSI_FON::FILE, function (): void {
            $this->activate_plugin();
        });
        register_deactivation_hook(JOOOSI_FON::FILE, function (): void {
            $this->deactivate_plugin();
        });
        // upgrade hooks.
        add_action('upgrader_process_complete', function ($upgrader, $options): void {
            if ($options['action'] === 'update' && $options['type'] === 'plugin') {
                foreach ($options['plugins'] as $plugin) {
                    if ($plugin === plugin_basename(JOOOSI_FON::FILE)) {
                        $this->upgrade_plugin();
                    }
                }
            }
        }, 10, 2);
        new Cache();
        new Runtime();
        new BuilderIntegration();
        new ApiRouter();
        $this->maybe_update_plugin();
        // admin hooks.
        if (is_admin()) {
            add_filter('plugin_action_links_' . plugin_basename(JOOOSI_FON::FILE), fn($links) => $this->plugin_action_links($links));
            add_action('plugins_loaded', function (): void {
                $this->plugins_loaded_admin();
            }, 100);
            new AdminPage();
            do_action('a!jooosi/fon/plugins:boot_admin');
            do_action_deprecated('a!yabe/webfont/plugins:boot_admin', [], '2.1.0', 'a!jooosi/fon/plugins:boot_admin');
        }
        do_action('a!jooosi/fon/plugins:boot_end');
        do_action_deprecated('a!yabe/webfont/plugins:boot_end', [], '2.1.0', 'a!jooosi/fon/plugins:boot_end');
    }
    /**
     * Handle the plugin's activation
     */
    public function activate_plugin(): void
    {
        do_action('a!jooosi/fon/plugins:activate_plugin_start');
        do_action_deprecated('a!yabe/webfont/plugins:activate_plugin_start', [], '2.1.0', 'a!jooosi/fon/plugins:activate_plugin_start');
        update_option(JOOOSI_FON::WP_OPTION . '_version', JOOOSI_FON::VERSION);
        if ($this->plugin_updater instanceof LicenseManager) {
            $this->maybe_embedded_license();
            $this->plugin_updater->clear_cache();
        }
        delete_transient('jooosi_fon_scanned_apis_' . JOOOSI_FON::VERSION);
        do_action('a!jooosi/fon/plugins:activate_plugin_end');
        do_action_deprecated('a!yabe/webfont/plugins:activate_plugin_end', [], '2.1.0', 'a!jooosi/fon/plugins:activate_plugin_end');
    }
    /**
     * Handle plugin's deactivation by (maybe) cleaning up after ourselves.
     */
    public function deactivate_plugin(): void
    {
        do_action('a!jooosi/fon/plugins:deactivate_plugin_start');
        do_action_deprecated('a!yabe/webfont/plugins:deactivate_plugin_start', [], '2.1.0', 'a!jooosi/fon/plugins:deactivate_plugin_start');
        // TODO: Add deactivation logic here.
        do_action('a!jooosi/fon/plugins:deactivate_plugin_end');
        do_action_deprecated('a!yabe/webfont/plugins:deactivate_plugin_end', [], '2.1.0', 'a!jooosi/fon/plugins:deactivate_plugin_end');
    }
    /**
     * Handle the plugin's upgrade
     */
    public function upgrade_plugin(): void
    {
        do_action('a!jooosi/fon/plugins:upgrade_plugin_start');
        do_action_deprecated('a!yabe/webfont/plugins:upgrade_plugin_start', [], '2.1.0', 'a!jooosi/fon/plugins:upgrade_plugin_start');
        update_option(JOOOSI_FON::WP_OPTION . '_version', JOOOSI_FON::VERSION);
        do_action('a!jooosi/fon/plugins:upgrade_plugin_end');
        do_action_deprecated('a!yabe/webfont/plugins:upgrade_plugin_end', [], '2.1.0', 'a!jooosi/fon/plugins:upgrade_plugin_end');
    }
    /**
     * Warm up the plugin for admin.
     */
    public function plugins_loaded_admin(): void
    {
        add_action('admin_notices', static function () {
            $messages = Notice::get_lists();
            if ($messages && is_array($messages)) {
                foreach ($messages as $message) {
                    echo sprintf('<div class="notice notice-%s is-dismissible %s">%s</div>', esc_attr($message['status']), JOOOSI_FON::WP_OPTION, esc_html($message['message']));
                }
            }
        }, 100);
    }
    /**
     * Add plugin action links.
     *
     * @param array<string> $links
     * @return array<string>
     */
    public function plugin_action_links(array $links): array
    {
        $base_url = AdminPage::get_page_url();
        array_unshift($links, sprintf('<a href="%s">%s</a>', esc_url(sprintf('%s#/settings', $base_url)), esc_html__('Settings', 'jooosi-fon')));
        array_unshift($links, sprintf('<a href="%s">%s</a>', esc_url(sprintf('%s#/fonts/index', $base_url)), esc_html__('Fonts', 'jooosi-fon')));
        if (!self::is_pro_edition()) {
            array_unshift($links, sprintf('<a href="%s" style="color:#067b34;font-weight:600;" target="_blank">%s</a>', esc_url(Common::plugin_data('PluginURI') . '?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=plugin-action-links&utm_content=Upgrade#pricing'), esc_html__('Upgrade to Pro', 'jooosi-fon')));
        }
        return $links;
    }
    /**
     * Get the initialized license manager.
     * Pro version only.
     *
     * @return LicenseManager|null
     */
    public function maybe_update_plugin()
    {
        return $this->plugin_updater;
    }
    public static function is_pro_edition(): bool
    {
        return is_file(self::get_license_sdk_path());
    }
    /**
     * Register the plugin with EDD's official Software Licensing SDK.
     */
    private function boot_license_sdk(): void
    {
        $sdk_path = self::get_license_sdk_path();
        if (!is_file($sdk_path)) {
            return;
        }
        $this->plugin_updater = new LicenseManager();
        add_action('init', [$this->plugin_updater, 'boot_updater']);
        add_action('edd_sl_sdk_registry', function ($registry): void {
            $this->register_license_integration($registry);
        });
        require_once $sdk_path;
        // Plugin activation can happen after the SDK initialization hooks ran.
        if (did_action('after_setup_theme') && class_exists(Registry::class)) {
            $this->register_license_integration(Registry::instance());
        }
    }
    /**
     * @param mixed $registry
     */
    private function register_license_integration($registry): void
    {
        if (!$registry instanceof Registry) {
            return;
        }
        if (!$registry->offsetExists(LicenseManager::INTEGRATION_ID)) {
            $registry->register(LicenseManager::get_integration_args());
        }
        $handler = $registry->offsetGet(LicenseManager::INTEGRATION_ID);
        if (is_object($handler) && method_exists($handler, 'auto_updater')) {
            // The SDK registry does not pass wp_override or beta through in
            // version 1.0.3, so Manager boots the official updater directly.
            remove_action('init', [$handler, 'auto_updater']);
        }
    }
    private static function get_license_sdk_path(): string
    {
        return dirname(JOOOSI_FON::FILE) . '/vendor/easy-digital-downloads/edd-sl-sdk/edd-sl-sdk.php';
    }
    /**
     * Check if the plugin distributed with an embedded license and activate the license.
     * Pro version only.
     */
    private function maybe_embedded_license(): void
    {
        $license_file = dirname(JOOOSI_FON::FILE) . '/license-data.php';
        if (!file_exists($license_file)) {
            return;
        }
        require_once $license_file;
        $const_names = [
            'JOOOSI_FON_EMBEDDED_LICENSE_KEY_' . JOOOSI_FON::EDD_STORE['item_id'],
            // TODO: Remove this legacy embedded-license constant in Jooosi Fon 3.0.0.
            'ROSUA_EMBEDDED_LICENSE_KEY_' . JOOOSI_FON::EDD_STORE['item_id'],
        ];
        $const_name = null;
        foreach ($const_names as $candidate) {
            if (defined($candidate)) {
                $const_name = $candidate;
                break;
            }
        }
        if ($const_name === null) {
            return;
        }
        $license_key = constant($const_name);
        update_option(JOOOSI_FON::WP_OPTION . '_license', ['key' => $license_key, 'opt_in_pre_release' => \false]);
        unlink($license_file);
        // activate the license.
        $this->plugin_updater->activate((string) $license_key);
    }
    /**
     * Copy persisted values to the rebranded option keys, then keep both names
     * synchronized on upgraded installations. Fresh installs only use the new
     * keys because no legacy option exists to activate this compatibility path.
     *
     * @todo Remove this legacy option migration completely in Jooosi Fon 3.0.0.
     */
    private function migrate_legacy_options(): void
    {
        $missing = new \stdClass();
        $optionMap = [];
        $hasLegacyOptions = \false;
        foreach (['_version', '_options', '_license', '_notices'] as $suffix) {
            $option = JOOOSI_FON::WP_OPTION . $suffix;
            $legacyOption = JOOOSI_FON::LEGACY_WP_OPTION . $suffix;
            $optionMap[$option] = $legacyOption;
            $legacyValue = get_option($legacyOption, $missing);
            if ($legacyValue === $missing) {
                continue;
            }
            $hasLegacyOptions = \true;
            if (get_option($option, $missing) === $missing) {
                add_option($option, $legacyValue);
            }
        }
        if (!$hasLegacyOptions) {
            return;
        }
        $legacyOptionMap = array_flip($optionMap);
        $syncing = \false;
        $syncOption = static function (string $option, $value) use ($optionMap, $legacyOptionMap, &$syncing): void {
            if ($syncing) {
                return;
            }
            $target = $optionMap[$option] ?? $legacyOptionMap[$option] ?? null;
            if ($target === null) {
                return;
            }
            $syncing = \true;
            try {
                update_option($target, $value);
            } finally {
                $syncing = \false;
            }
        };
        add_action('updated_option', static fn($option, $oldValue, $value) => $syncOption($option, $value), 10, 3);
        add_action('added_option', static fn($option, $value) => $syncOption($option, $value), 10, 2);
    }
}
