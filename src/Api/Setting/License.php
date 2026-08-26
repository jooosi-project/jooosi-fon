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
namespace JooosiFon\Api\Setting;

use JooosiFonDeps\JOOOSI_FON;
use JooosiFon\Api\AbstractApi;
use JooosiFon\Api\ApiInterface;
use JooosiFon\Plugin;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
class License extends AbstractApi implements ApiInterface
{
    public function __construct()
    {
    }
    public function get_prefix(): string
    {
        return 'setting/license';
    }
    public function register_custom_endpoints(): void
    {
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/index', ['methods' => WP_REST_Server::READABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->index($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest)]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/store', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->store($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => ['license' => ['required' => \true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => static fn($value): bool => is_string($value) && strlen($value) <= 255]]]);
    }
    public function index(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        return new WP_REST_Response(['license' => $this->get_license()]);
    }
    private function store(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        $new_license_key = trim((string) $wprestRequest->get_param('license'));
        $old_license = $this->get_license();
        $plugin_updater = Plugin::get_instance()->plugin_updater;
        $notice = [];
        if ($new_license_key !== $old_license['key']) {
            if (!$plugin_updater) {
                return new WP_REST_Response(['message' => __('License management is unavailable in this edition.', 'jooosi-fon'), 'license' => $this->get_license()], 404);
            }
            if ($new_license_key === '') {
                $response = $plugin_updater->deactivate();
                if (is_wp_error($response)) {
                    $message = $response->get_error_message();
                    $status = $response->get_error_code() === 'license_server_unavailable' ? 502 : 422;
                    return new WP_REST_Response(['message' => $message, 'license' => $this->get_license(), 'notice' => ['error' => $message]], $status);
                }
                $notice['success'] = __('Plugin license key deactivated successfully.', 'jooosi-fon');
            } else {
                $response = $plugin_updater->activate($new_license_key);
                if (is_wp_error($response)) {
                    $message = $response->get_error_message();
                    $status = $response->get_error_code() === 'license_server_unavailable' ? 502 : 422;
                    return new WP_REST_Response(['message' => $message, 'license' => $this->get_license(), 'notice' => ['error' => $message]], $status);
                }
                $notice['success'] = __('Plugin license key activated successfully.', 'jooosi-fon');
            }
            $newOption = ['key' => $new_license_key, 'opt_in_pre_release' => (bool) $old_license['opt_in_pre_release']];
            if (!update_option(JOOOSI_FON::WP_OPTION . '_license', $newOption) && get_option(JOOOSI_FON::WP_OPTION . '_license') !== $newOption) {
                return new WP_REST_Response(['message' => __('The license was accepted but could not be saved locally.', 'jooosi-fon'), 'license' => $this->get_license()], 500);
            }
        }
        return new WP_REST_Response(['license' => $this->get_license(), 'notice' => $notice]);
    }
    private function get_license(): array
    {
        $license = get_option(JOOOSI_FON::WP_OPTION . '_license', ['key' => '', 'opt_in_pre_release' => \false]);
        $license = wp_parse_args(is_array($license) ? $license : [], ['key' => '', 'opt_in_pre_release' => \false]);
        $plugin_updater = Plugin::get_instance()->plugin_updater;
        if ($plugin_updater) {
            $license['key'] = $plugin_updater->get_license_key();
        }
        try {
            $license['is_activated'] = $plugin_updater && $plugin_updater->is_activated();
        } catch (\Throwable $throwable) {
            $license['is_activated'] = \false;
        }
        return $license;
    }
}
