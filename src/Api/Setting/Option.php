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
use JooosiFon\Api\Support\RequestValidator;
use JooosiFon\Database\FontTable;
use JooosiFon\Upgrade\LegacyRebrandUpgrade;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
/**
 * @todo Remove all legacy Yabe Webfont filter shims completely in Jooosi Fon 3.0.0.
 */
class Option extends AbstractApi implements ApiInterface
{
    public function __construct()
    {
    }
    public function get_prefix(): string
    {
        return 'setting/option';
    }
    public function register_custom_endpoints(): void
    {
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/index', ['methods' => WP_REST_Server::READABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->index($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest)]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/store', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->store($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => ['options' => ['required' => \true, 'validate_callback' => [RequestValidator::class, 'arrayValue']]]]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/migrate-legacy', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->migrate_legacy($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest)]);
    }
    public function index(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        try {
            $stored = get_option(JOOOSI_FON::WP_OPTION . '_options', '{}');
            $options = json_decode(is_string($stored) ? $stored : '{}', null, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $options = new \stdClass();
        }
        $options = apply_filters('f!jooosi/fon/api/setting/option:index_options', $options);
        $options = apply_filters_deprecated('f!yabe/webfont/api/setting/option:index_options', [$options], '2.1.0', 'f!jooosi/fon/api/setting/option:index_options');
        return new WP_REST_Response(['options' => $options, 'legacy_migration' => (new LegacyRebrandUpgrade())->status()]);
    }
    public function migrate_legacy(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        try {
            FontTable::migrateLegacy();
            $upgrade = new LegacyRebrandUpgrade();
            $upgrade->run();
            $status = $upgrade->status();
            if (!$status['complete']) {
                return new WP_REST_Response(['message' => __('The Yabe Webfont migration is incomplete. The original data was preserved; try again after resolving the reported file issue.', 'jooosi-fon'), 'legacy_migration' => $status], 500);
            }
            return new WP_REST_Response(['legacy_migration' => $status]);
        } catch (\Throwable $throwable) {
            return new WP_REST_Response(['message' => $throwable->getMessage(), 'legacy_migration' => (new LegacyRebrandUpgrade())->status()], 500);
        }
    }
    public function store(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        $options = (array) $wprestRequest->get_param('options');
        if (empty($options)) {
            $options = (object) $options;
        }
        $options = apply_filters('f!jooosi/fon/api/setting/option:store_options', $options);
        $options = apply_filters_deprecated('f!yabe/webfont/api/setting/option:store_options', [$options], '2.1.0', 'f!jooosi/fon/api/setting/option:store_options');
        try {
            $encoded = json_encode($options, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return new WP_REST_Response(['message' => __('The settings payload could not be encoded.', 'jooosi-fon')], 400);
        }
        if (!update_option(JOOOSI_FON::WP_OPTION . '_options', $encoded) && get_option(JOOOSI_FON::WP_OPTION . '_options') !== $encoded) {
            return new WP_REST_Response(['message' => __('The settings could not be saved.', 'jooosi-fon')], 500);
        }
        if ($this->shouldDeleteLegacyData($options) && !(new LegacyRebrandUpgrade())->deleteLegacyData()) {
            return new WP_REST_Response(['message' => __('The legacy Yabe Webfont data cleanup was incomplete. Any remaining data was preserved.', 'jooosi-fon')], 500);
        }
        do_action('f!jooosi/fon/api/setting/option:after_store', $options);
        do_action_deprecated('f!yabe/webfont/api/setting/option:after_store', [$options], '2.1.0', 'f!jooosi/fon/api/setting/option:after_store');
        return $this->index($wprestRequest);
    }
    /**
     * @param mixed $options
     */
    private function shouldDeleteLegacyData($options): bool
    {
        if (is_object($options)) {
            $options = get_object_vars($options);
        }
        if (!is_array($options) || !is_array($options['misc'] ?? null)) {
            return \false;
        }
        return !empty($options['misc']['delete_legacy_data']);
    }
}
