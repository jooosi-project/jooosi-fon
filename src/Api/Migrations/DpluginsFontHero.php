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
use wpdb;
class DpluginsFontHero extends AbstractApi implements ApiInterface
{
    public function __construct()
    {
    }
    public function get_prefix(): string
    {
        return 'migrations/font-hero-dplugins';
    }
    public function register_custom_endpoints(): void
    {
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/import-fonts', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->import_fonts($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest)]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/clean-up', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->clean_up($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => ['confirm' => ['required' => \true, 'sanitize_callback' => 'rest_sanitize_boolean', 'validate_callback' => [RequestValidator::class, 'confirmed']]]]);
    }
    /**
     * @see JooosiFon\Api\Font::import()
     */
    public function import_fonts(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        if (!defined('DP_FH_BASE')) {
            return new WP_REST_Response(['message' => 'Font Hero plugin is not activated'], 404);
        }
        /** @var wpdb $wpdb */
        global $wpdb;
        add_filter('wp_check_filetype_and_ext', static fn($data, $file, $filename, $mimes) => Upload::disable_real_mime_check($data, $file, $filename, $mimes), 10, 4);
        add_filter('upload_mimes', static fn($mime_types) => Upload::upload_mimes($mime_types, \true), 1000001);
        $dpfh_fonts = $wpdb->get_results(sprintf('SELECT * FROM %sdp_fh_fonts', $wpdb->prefix));
        if ($wpdb->last_error) {
            return new WP_REST_Response(['message' => __('Font Hero records could not be read.', 'jooosi-fon')], 500);
        }
        $repository = new FontRepository();
        $media = new MediaImportService();
        $insertedIds = [];
        $records = [];
        try {
            foreach ($dpfh_fonts as $dpfh_font) {
                $migrationSlug = 'migration-font-hero-' . absint($dpfh_font->id);
                if ($repository->findBySlug($migrationSlug)) {
                    continue;
                }
                $font_faces = [];
                $dpfh_font_faces = $wpdb->get_results($wpdb->prepare(sprintf('SELECT * FROM %sdp_fh_font_faces WHERE font_id = %%d', $wpdb->prefix), $dpfh_font->id));
                if ($wpdb->last_error) {
                    throw new \RuntimeException('Font Hero font faces could not be read.');
                }
                foreach ($dpfh_font_faces as $dpfh_font_face) {
                    $f_weight = filter_var($dpfh_font_face->font_weight, \FILTER_SANITIZE_NUMBER_INT) ?: 100;
                    $f_style = empty($dpfh_font_face->font_style) ? 'normal' : sanitize_text_field($dpfh_font_face->font_style);
                    $font_face = array_filter($font_faces, static fn($ff) => $ff['weight'] === $f_weight && $ff['style'] === $f_style);
                    if ($font_face !== []) {
                        $font_face = array_shift($font_face);
                        $font_faces = array_filter($font_faces, static fn($ff) => $ff['id'] !== $font_face['id']);
                    } else {
                        $font_face = ['id' => Common::random_slug(10), 'weight' => $f_weight, 'width' => '', 'style' => $f_style, 'display' => empty($dpfh_font_face->font_display) ? '' : sanitize_text_field($dpfh_font_face->font_display), 'selector' => '', 'comment' => '', 'unicodeRange' => '', 'preload' => $dpfh_font_face->font_preload === 'yes', 'files' => []];
                    }
                    if (!empty($dpfh_font_face->font_file)) {
                        $f_ext = pathinfo((string) wp_parse_url($dpfh_font_face->font_file, \PHP_URL_PATH), \PATHINFO_EXTENSION);
                        $file_name = sanitize_title_with_dashes(sprintf('%s-%s-%s-%s-%s', sanitize_text_field($dpfh_font->font_name), $f_weight, $f_style, Common::random_slug(5), time())) . '.' . $f_ext;
                        $font_face['files'][] = $media->remote($dpfh_font_face->font_file, $file_name, $f_ext);
                    }
                    if (!empty($dpfh_font_face->font_file_2)) {
                        $f_ext = pathinfo((string) wp_parse_url($dpfh_font_face->font_file_2, \PHP_URL_PATH), \PATHINFO_EXTENSION);
                        $file_name = sanitize_title_with_dashes(sprintf('%s-%s-%s-%s-%s', sanitize_text_field($dpfh_font->font_name), $f_weight, $f_style, Common::random_slug(5), time())) . '.' . $f_ext;
                        $font_face['files'][] = $media->remote($dpfh_font_face->font_file_2, $file_name, $f_ext);
                    }
                    if (!empty($dpfh_font_face->font_file_3)) {
                        $f_ext = pathinfo((string) wp_parse_url($dpfh_font_face->font_file_3, \PHP_URL_PATH), \PATHINFO_EXTENSION);
                        $file_name = sanitize_title_with_dashes(sprintf('%s-%s-%s-%s-%s', sanitize_text_field($dpfh_font->font_name), $f_weight, $f_style, Common::random_slug(5), time())) . '.' . $f_ext;
                        $font_face['files'][] = $media->remote($dpfh_font_face->font_file_3, $file_name, $f_ext);
                    }
                    $font_faces[] = $font_face;
                }
                $metadata = ['preload' => \false, 'selector' => '', 'display' => 'auto'];
                $records[] = ['type' => 'custom', 'title' => sanitize_text_field($dpfh_font->font_name), 'slug' => $migrationSlug, 'family' => sanitize_text_field($dpfh_font->font_name), 'status' => \true, 'metadata' => FontCodec::encode($metadata), 'font_faces' => FontCodec::encode($font_faces)];
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
            return new WP_REST_Response(['message' => __('The Font Hero migration failed; newly created Jooosi Fon records were rolled back.', 'jooosi-fon')], 500);
        }
        $media->commit();
        FontEvents::dispatch('migration', ['source' => 'font-hero-dplugins', 'ids' => $insertedIds], \true);
        return new WP_REST_Response(['message' => __('Font Hero fonts imported. Source records were preserved.', 'jooosi-fon'), 'imported' => count($insertedIds), 'source_preserved' => \true]);
    }
    public function clean_up(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        if (!defined('DP_FH_BASE')) {
            return new WP_REST_Response(['message' => 'Font Hero plugin is not activated'], 404);
        }
        /** @var wpdb $wpdb */
        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === \false) {
            return new WP_REST_Response(['message' => __('Font Hero cleanup could not start a database transaction.', 'jooosi-fon')], 500);
        }
        try {
            if ($wpdb->query(sprintf('DELETE FROM %sdp_fh_font_faces', $wpdb->prefix)) === \false || $wpdb->query(sprintf('DELETE FROM %sdp_fh_fonts', $wpdb->prefix)) === \false || $wpdb->query('COMMIT') === \false) {
                throw new \RuntimeException($wpdb->last_error ?: 'Font Hero cleanup failed.');
            }
        } catch (\Throwable $throwable) {
            $wpdb->query('ROLLBACK');
            return new WP_REST_Response(['message' => __('Font Hero cleanup failed; the source transaction was rolled back.', 'jooosi-fon')], 500);
        }
        FontEvents::dispatch('clean_up', ['source' => 'font-hero-dplugins'], \true);
        return new WP_REST_Response(['message' => __('Font Hero source records deleted.', 'jooosi-fon')]);
    }
}
