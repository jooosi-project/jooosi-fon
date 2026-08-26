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

namespace JooosiFon\Builder\Zion;

use JooosiFon\Admin\AdminPage;
use JooosiFon\Builder\BuilderInterface;
use JooosiFon\Core\Frontpage;
use JooosiFon\Utils\Font;

/**
 * Zion integration.
 *
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
class Main implements BuilderInterface
{
    public function __construct()
    {
        add_filter('rest_request_after_callbacks', fn ($response, array $handler, \WP_REST_Request $wprestRequest) => $this->filter_rest_request_after_callbacks($response, $handler, $wprestRequest), 1_000_001, 3);
        add_action('zionbuilder/editor/after_scripts', fn () => $this->enqueue_assets());
        add_action('admin_menu', static fn () => AdminPage::add_redirect_submenu_page('zionbuilder'), 1_000_001);
    }

    public function get_name(): string
    {
        return 'zionbuilder';
    }

    /**
     * @see https://github.com/WordPress/wordpress-develop/blob/ea08277674413b9853aa19df6ecc23841de894b6/src/wp-includes/rest-api/class-wp-rest-server.php#L1197
     * @param \WP_REST_Response|\WP_HTTP_Response|\WP_Error|mixed $response
     * @param array $handler  Route handler used for the request.
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_HTTP_Response|\WP_Error|mixed
     */
    public function filter_rest_request_after_callbacks($response, $handler, $request)
    {
        if ($request->get_route() === '/zionbuilder/v1/data-sets' && $response instanceof \WP_REST_Response) {
            $data = $response->get_data();
            if (array_key_exists('fonts_list', $data)) {
                if (! array_key_exists('custom_fonts', $data['fonts_list'])) {
                    $data['fonts_list']['custom_fonts'] = [];
                }
                $fonts = Font::get_fonts();

                foreach ($fonts as $font) {
                    $data['fonts_list']['custom_fonts'][] = [
                        'id' => $font['family'],
                        'name' => '[Jooosi Fon] ' . $font['title'],
                    ];
                }

                $response->set_data($data);
            }
        }

        return $response;
    }

    public function enqueue_assets()
    {
        Frontpage::enqueue_css_cache();
    }
}
