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
namespace JooosiFon\Api\Migrations;

use JooosiFon\Api\AbstractApi;
use JooosiFon\Api\ApiInterface;
use JooosiFon\Api\Support\FontCodec;
use JooosiFon\Api\Support\FontEvents;
use JooosiFon\Api\Support\FontRepository;
use JooosiFon\Api\Support\MediaImportService;
use JooosiFon\Api\Support\RequestValidator;
use JooosiFon\Utils\Common;
use JooosiFon\Utils\Upload;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
class BricksCustomFonts extends AbstractApi implements ApiInterface
{
    public function __construct()
    {
    }
    public function get_prefix(): string
    {
        return 'migrations/bricks-custom-fonts';
    }
    public function register_custom_endpoints(): void
    {
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/import-fonts', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->import_fonts($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest)]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/clean-up', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->clean_up($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => ['confirm' => ['required' => \true, 'sanitize_callback' => 'rest_sanitize_boolean', 'validate_callback' => [RequestValidator::class, 'confirmed']]]]);
    }
    /**
     * @see Bricks\Custom_Fonts::get_custom_fonts()
     * @see JooosiFon\Api\Font::import()
     */
    public function import_fonts(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        if (!defined('BRICKS_DB_CUSTOM_FONTS') || !defined('BRICKS_DB_CUSTOM_FONT_FACES')) {
            return new WP_REST_Response(['message' => 'Bricks theme not activated'], 404);
        }
        $font_ids = get_posts(['post_type' => \BRICKS_DB_CUSTOM_FONTS, 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => \true]);
        add_filter('wp_check_filetype_and_ext', static fn($data, $file, $filename, $mimes) => Upload::disable_real_mime_check($data, $file, $filename, $mimes), 10, 4);
        add_filter('upload_mimes', static fn($mime_types) => Upload::upload_mimes($mime_types, \true), 1000001);
        $repository = new FontRepository();
        $media = new MediaImportService();
        $insertedIds = [];
        $records = [];
        try {
            foreach ($font_ids as $font_id) {
                $migrationSlug = 'migration-bricks-' . absint($font_id);
                if ($repository->findBySlug($migrationSlug)) {
                    continue;
                }
                $bricks_font_faces = get_post_meta($font_id, \BRICKS_DB_CUSTOM_FONT_FACES, \true);
                if ($bricks_font_faces === \false || empty($bricks_font_faces)) {
                    continue;
                }
                $post = get_post($font_id);
                if (!$post) {
                    throw new \RuntimeException('A Bricks font record could not be loaded.');
                }
                $font_faces = [];
                // $key: font-weight + variant (e.g.: 700italic)
                foreach ($bricks_font_faces as $key => $bricks_font_face) {
                    $font_weight = filter_var($key, \FILTER_SANITIZE_NUMBER_INT);
                    $font_style = str_replace($font_weight, '', $key);
                    $font_face = ['id' => Common::random_slug(10), 'weight' => $font_weight, 'width' => '', 'style' => empty($font_style) ? 'normal' : $font_style, 'display' => '', 'selector' => '', 'comment' => '', 'unicodeRange' => '', 'preload' => \false, 'files' => []];
                    foreach ($bricks_font_face as $key_format => $bricks_font_face_file) {
                        $file_name = sanitize_title_with_dashes(sprintf('%s-%s-%s-%s-%s', $post->post_title, $font_weight, empty($font_style) ? 'normal' : $font_style, Common::random_slug(5), time())) . '.' . $key_format;
                        $old_attachment_url = wp_get_attachment_url($bricks_font_face_file);
                        if ($old_attachment_url === \false) {
                            continue;
                        }
                        $font_face['files'][] = $media->remote($old_attachment_url, $file_name, $key_format);
                    }
                    $font_faces[] = $font_face;
                }
                $metadata = ['preload' => \false, 'selector' => '', 'display' => 'auto'];
                $records[] = ['type' => 'custom', 'title' => $post->post_title, 'slug' => $migrationSlug, 'family' => $post->post_title, 'status' => \true, 'metadata' => FontCodec::encode($metadata), 'font_faces' => FontCodec::encode($font_faces)];
            }
            $insertedIds = $repository->transaction(static function (FontRepository $repository) use ($records): array {
                $ids = [];
                foreach ($records as $record) {
                    $ids[] = $repository->insert($record, ['%s', '%s', '%s', '%s', '%d', '%s', '%s']);
                }
                return $ids;
            });
        } catch (\Throwable $throwable) {
            $media->rollback();
            return new WP_REST_Response(['message' => __('The Bricks migration failed; newly created Jooosi Fon records were rolled back.', 'jooosi-fon')], 500);
        }
        $media->commit();
        FontEvents::dispatch('migration', ['source' => 'bricks-custom-fonts', 'ids' => $insertedIds], \true);
        return new WP_REST_Response(['message' => __('Bricks fonts imported. Source records were preserved.', 'jooosi-fon'), 'imported' => count($insertedIds), 'source_preserved' => \true]);
    }
    public function clean_up(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        if (!defined('BRICKS_DB_CUSTOM_FONTS')) {
            return new WP_REST_Response(['message' => 'Bricks theme not activated'], 404);
        }
        $font_ids = get_posts(['post_type' => \BRICKS_DB_CUSTOM_FONTS, 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => \true]);
        $trashedIds = [];
        foreach ($font_ids as $font_id) {
            if (!wp_trash_post($font_id)) {
                foreach ($trashedIds as $trashedId) {
                    wp_untrash_post($trashedId);
                }
                return new WP_REST_Response(['message' => __('Bricks source cleanup failed and previously trashed records were restored.', 'jooosi-fon')], 500);
            }
            $trashedIds[] = $font_id;
        }
        FontEvents::dispatch('clean_up', ['source' => 'bricks-custom-fonts', 'ids' => $trashedIds], \true);
        return new WP_REST_Response(['message' => __('Bricks source records moved to the trash.', 'jooosi-fon'), 'cleaned' => count($trashedIds)]);
    }
}
