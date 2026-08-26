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
namespace JooosiFon\Api;

use JooosiFonDeps\JOOOSI_FON;
use WP_REST_Request;
class AbstractApi
{
    /**
     * @var string
     */
    public const API_NAMESPACE = JOOOSI_FON::REST_NAMESPACE;
    /**
     * The API is currently an administrator-only surface used by the plugin's
     * wp-admin application. Keep both checks explicit so custom authentication
     * integrations cannot accidentally bypass the CSRF boundary.
     */
    protected function permission_callback(WP_REST_Request $request): bool
    {
        return wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest') && current_user_can('manage_options');
    }
}
