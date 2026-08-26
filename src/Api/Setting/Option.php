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
        return new WP_REST_Response(['options' => $options]);
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
        do_action('f!jooosi/fon/api/setting/option:after_store', $options);
        do_action_deprecated('f!yabe/webfont/api/setting/option:after_store', [$options], '2.1.0', 'f!jooosi/fon/api/setting/option:after_store');
        return $this->index($wprestRequest);
    }
}
