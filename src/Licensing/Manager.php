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
namespace JooosiFon\Licensing;

use EasyDigitalDownloads\Updater\Licensing\License;
use EasyDigitalDownloads\Updater\Messenger;
use EasyDigitalDownloads\Updater\Requests\API;
use EasyDigitalDownloads\Updater\Updaters\Plugin as PluginUpdater;
use JooosiFonDeps\JOOOSI_FON;
use WP_Error;
/**
 * Bridges the plugin's existing license UI to the official EDD Software
 * Licensing SDK and initializes its WordPress plugin updater.
 */
final class Manager
{
    public const INTEGRATION_ID = 'jooosi-fon';
    public const OPTION_NAME = 'jooosi_fon_license_key';
    private const MIGRATION_OPTION = 'jooosi_fon_edd_sl_sdk_migrated';
    private License $license;
    public function __construct()
    {
        $this->license = new License(self::INTEGRATION_ID, self::get_integration_args(), new Messenger());
        $this->migrate_legacy_license();
    }
    /**
     * Arguments shared by the SDK registry and the compatibility bridge.
     *
     * @return array<string, mixed>
     */
    public static function get_integration_args(): array
    {
        return ['id' => self::INTEGRATION_ID, 'url' => JOOOSI_FON::EDD_STORE['store_url'], 'item_id' => JOOOSI_FON::EDD_STORE['item_id'], 'version' => JOOOSI_FON::VERSION, 'file' => JOOOSI_FON::FILE, 'option_name' => self::OPTION_NAME, 'weekly_check' => \true];
    }
    /**
     * Initialize the official updater with the existing WordPress.org override
     * and pre-release behavior preserved.
     */
    public function boot_updater(): void
    {
        if (!current_user_can('manage_options') && !wp_doing_cron()) {
            return;
        }
        $license_key = $this->get_license_key();
        if ($license_key === '') {
            return;
        }
        new PluginUpdater(JOOOSI_FON::EDD_STORE['store_url'], ['file' => JOOOSI_FON::FILE, 'item_id' => JOOOSI_FON::EDD_STORE['item_id'], 'version' => JOOOSI_FON::VERSION, 'license' => $license_key, 'beta' => $this->is_pre_release_enabled(), 'allow_tracking' => $this->license->get_allow_tracking(), 'wp_override' => \true], new Messenger());
    }
    public function get_license_key(): string
    {
        return trim((string) $this->license->get_license_key());
    }
    public function is_activated(): bool
    {
        if ($this->get_license_key() === '') {
            return \false;
        }
        $status = get_option($this->license->get_status_option_name());
        return is_object($status) && ($status->success ?? \true) !== \false && in_array($status->license ?? '', ['active', 'valid'], \true);
    }
    /**
     * Activate and persist a license using the official SDK request client.
     *
     * @return object|WP_Error
     */
    public function activate(string $license_key)
    {
        $license_key = trim($license_key);
        if ($license_key === '') {
            return new WP_Error('missing', __('Enter a license key.', 'jooosi-fon'));
        }
        $old_license_key = $this->get_license_key();
        $license_data = $this->request('activate_license', $license_key);
        if (is_wp_error($license_data)) {
            return $license_data;
        }
        update_option(self::OPTION_NAME, $license_key);
        $this->license->save($license_data);
        $this->clear_cache([$old_license_key, $license_key]);
        return $license_data;
    }
    /**
     * Deactivate and remove the currently stored license.
     *
     * @return object|WP_Error
     */
    public function deactivate()
    {
        $license_key = $this->get_license_key();
        if ($license_key === '') {
            delete_option($this->license->get_status_option_name());
            return (object) ['success' => \true, 'license' => 'inactive'];
        }
        $license_data = $this->request('deactivate_license', $license_key);
        if (is_wp_error($license_data)) {
            return $license_data;
        }
        delete_option(self::OPTION_NAME);
        delete_option($this->license->get_status_option_name());
        $this->clear_cache([$license_key]);
        return $license_data;
    }
    /**
     * Clear update data after activation, deactivation, or plugin activation.
     *
     * @param array<int, string>|null $license_keys
     */
    public function clear_cache(?array $license_keys = null): void
    {
        $license_keys = $license_keys ?? [$this->get_license_key()];
        $slug = basename(dirname(JOOOSI_FON::FILE));
        foreach (array_unique($license_keys) as $license_key) {
            $cache_key = md5(wp_json_encode([$slug, $license_key, (int) $this->is_pre_release_enabled()]));
            delete_option('edd_sl_' . $cache_key);
        }
        delete_transient(JOOOSI_FON::WP_OPTION . '_license_seed');
        delete_transient(JOOOSI_FON::LEGACY_WP_OPTION . '_license_seed');
        delete_site_transient('update_plugins');
    }
    public function error_message(string $code): string
    {
        switch ($code) {
            case 'expired':
                return __('Your license key has expired.', 'jooosi-fon');
            case 'disabled':
            case 'revoked':
                return __('Your license key has been disabled.', 'jooosi-fon');
            case 'inactive':
            case 'site_inactive':
                return __('Your license is not active for this website.', 'jooosi-fon');
            case 'no_activations_left':
                return __('Your license key has reached its activation limit.', 'jooosi-fon');
            case 'missing_url':
                return __('The license does not exist or the website URL was not provided.', 'jooosi-fon');
            case 'key_mismatch':
            case 'missing':
            case 'invalid':
            case 'invalid_item_id':
            case 'item_name_mismatch':
                return __('Invalid license key.', 'jooosi-fon');
            default:
                return __('The license server rejected the request.', 'jooosi-fon');
        }
    }
    /**
     * @return object|WP_Error
     */
    private function request(string $action, string $license_key)
    {
        $api = new API(JOOOSI_FON::EDD_STORE['store_url']);
        $license_data = $api->make_request(['edd_action' => $action, 'license' => $license_key, 'item_id' => JOOOSI_FON::EDD_STORE['item_id']]);
        if (!is_object($license_data)) {
            return new WP_Error('license_server_unavailable', __('The license server could not be reached.', 'jooosi-fon'));
        }
        if (empty($license_data->success)) {
            $code = (string) ($license_data->error ?? $license_data->license ?? 'invalid');
            return new WP_Error($code, $this->error_message($code));
        }
        return $license_data;
    }
    private function is_pre_release_enabled(): bool
    {
        $legacy_license = get_option(JOOOSI_FON::WP_OPTION . '_license', []);
        return is_array($legacy_license) && !empty($legacy_license['opt_in_pre_release']);
    }
    /**
     * Copy license data from the Rosua updater options without changing or
     * deleting them, so upgraded installations keep their activation state.
     *
     * @todo Remove this legacy license migration completely in Jooosi Fon 3.0.0.
     */
    private function migrate_legacy_license(): void
    {
        if (get_option(self::MIGRATION_OPTION, \false)) {
            return;
        }
        $missing = new \stdClass();
        $license_key = get_option(self::OPTION_NAME, $missing);
        if ($license_key === $missing || trim((string) $license_key) === '') {
            $legacy_license = get_option(JOOOSI_FON::WP_OPTION . '_license', []);
            $legacy_key = is_array($legacy_license) ? trim((string) ($legacy_license['key'] ?? '')) : '';
            if ($legacy_key !== '') {
                update_option(self::OPTION_NAME, $legacy_key);
            }
        }
        if (get_option($this->license->get_status_option_name(), $missing) === $missing) {
            foreach ([JOOOSI_FON::WP_OPTION, JOOOSI_FON::LEGACY_WP_OPTION] as $option_prefix) {
                $legacy_status = get_transient($option_prefix . '_license_seed');
                if (is_object($legacy_status) && !empty($legacy_status->license)) {
                    $this->license->save($legacy_status);
                    break;
                }
            }
        }
        update_option(self::MIGRATION_OPTION, \true);
    }
}
