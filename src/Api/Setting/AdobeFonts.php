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
use JooosiFon\Api\Support\FontCodec;
use JooosiFon\Api\Support\FontEvents;
use JooosiFon\Api\Support\FontRepository;
use JooosiFon\Api\Support\RequestValidator;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
class AdobeFonts extends AbstractApi implements ApiInterface
{
    public function __construct()
    {
    }
    public function get_prefix(): string
    {
        return 'setting/adobe-fonts';
    }
    public function register_custom_endpoints(): void
    {
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/get-kits', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->get_kits($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => ['project_id' => ['required' => \true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [RequestValidator::class, 'projectId']]]]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/sync', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->sync($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => ['kit' => ['required' => \true, 'validate_callback' => [RequestValidator::class, 'adobeKit']]]]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/destroy', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->destroy($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest)]);
    }
    public function get_kits(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        $project_id = (string) $wprestRequest->get_param('project_id');
        $response = wp_safe_remote_get(sprintf('https://typekit.com/api/v1/json/kits/%s/published', rawurlencode($project_id)), ['timeout' => 15, 'redirection' => 2, 'limit_response_size' => 2 * \MB_IN_BYTES]);
        if (is_wp_error($response)) {
            return new WP_REST_Response(['message' => 'Failed to get kits'], 500);
        }
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_REST_Response(['message' => 'Kits not found'], $status_code);
        }
        $body = wp_remote_retrieve_body($response);
        try {
            $kits = json_decode($body, \true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return new WP_REST_Response(['message' => __('Adobe Fonts returned an invalid response.', 'jooosi-fon')], 502);
        }
        return new WP_REST_Response(['data' => $kits]);
    }
    public function sync(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        $kit = (array) $wprestRequest->get_param('kit');
        $repository = new FontRepository();
        try {
            $repository->transaction(static function (FontRepository $repository) use ($kit): void {
                $repository->deleteByType('adobe-fonts');
                foreach ($kit['families'] as $family) {
                    $repository->insert(['status' => 1, 'type' => 'adobe-fonts', 'title' => sanitize_text_field($family['name']), 'slug' => sanitize_key($family['id']), 'family' => sanitize_text_field($family['slug']), 'metadata' => FontCodec::encode($family), 'font_faces' => FontCodec::encode([])], ['%d', '%s', '%s', '%s', '%s', '%s', '%s']);
                }
            });
        } catch (\Throwable $throwable) {
            return new WP_REST_Response(['message' => __('Adobe Fonts could not be synchronized. The previous data was preserved.', 'jooosi-fon')], 500);
        }
        FontEvents::invalidate('a!jooosi/fon/api/setting/adobe-fonts:sync');
        return new WP_REST_Response([]);
    }
    public function destroy(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        try {
            $this->delete_fonts();
        } catch (\Throwable $throwable) {
            return new WP_REST_Response(['message' => __('Adobe Fonts could not be disconnected.', 'jooosi-fon')], 500);
        }
        FontEvents::invalidate('a!jooosi/fon/api/setting/adobe-fonts:destroy');
        return new WP_REST_Response([]);
    }
    private function delete_fonts(): void
    {
        (new FontRepository())->deleteByType('adobe-fonts');
    }
}
