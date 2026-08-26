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

use JooosiFon\Api\AbstractApi;
use JooosiFon\Api\ApiInterface;
use JooosiFon\Api\Support\FontEvents;
use JooosiFon\Core\Cache as CoreCache;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
class Cache extends AbstractApi implements ApiInterface
{
    public function __construct()
    {
    }
    public function get_prefix(): string
    {
        return 'setting/cache';
    }
    public function register_custom_endpoints(): void
    {
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/index', ['methods' => WP_REST_Server::READABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->index($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest)]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/generate', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->generate($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest)]);
    }
    public function index(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        $cache_path = CoreCache::get_cache_path(CoreCache::CSS_CACHE_FILE);
        $cache = ['last_generated' => '', 'pending_task' => \false, 'file_url' => '', 'state' => 'idle', 'error' => '', 'warnings' => []];
        $buildStatus = CoreCache::getBuildStatus();
        $cache['state'] = (string) ($buildStatus['state'] ?? 'idle');
        $cache['error'] = (string) ($buildStatus['error'] ?? '');
        $cache['warnings'] = is_array($buildStatus['warnings'] ?? null) ? array_values($buildStatus['warnings']) : [];
        $cache['hash'] = (string) ($buildStatus['hash'] ?? '');
        $cache['changed'] = (bool) ($buildStatus['changed'] ?? \false);
        if (file_exists($cache_path) && is_readable($cache_path)) {
            $cache['file_url'] = CoreCache::get_versioned_cache_url(CoreCache::CSS_CACHE_FILE);
            $cache['last_generated'] = filemtime($cache_path);
        }
        if (wp_next_scheduled('a!jooosi/fon/core/cache:build_cache') || $cache['state'] === 'running') {
            $cache['pending_task'] = \true;
        }
        return new WP_REST_Response(['cache' => $cache]);
    }
    public function generate(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        wp_clear_scheduled_hook('a!jooosi/fon/core/cache:build_cache');
        FontEvents::invalidate('a!jooosi/fon/api/setting/cache:generate', null, \false);
        return $this->index($wprestRequest);
    }
}
