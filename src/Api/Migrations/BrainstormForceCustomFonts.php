<?php

declare (strict_types=1);
namespace JooosiFon\Api\Migrations;

use JooosiFon\Api\AbstractApi;
use JooosiFon\Api\ApiInterface;
use JooosiFon\Api\Migrations\Support\SourceFontNormalizer;
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
final class BrainstormForceCustomFonts extends AbstractApi implements ApiInterface
{
    public function get_prefix(): string
    {
        return 'migrations/brainstorm-force-custom-fonts';
    }
    public function register_custom_endpoints(): void
    {
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/import-fonts', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(WP_REST_Request $request): WP_REST_Response => $this->import_fonts($request), 'permission_callback' => fn(WP_REST_Request $request): bool => $this->permission_callback($request)]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/clean-up', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(WP_REST_Request $request): WP_REST_Response => $this->clean_up($request), 'permission_callback' => fn(WP_REST_Request $request): bool => $this->permission_callback($request), 'args' => ['confirm' => ['required' => \true, 'sanitize_callback' => 'rest_sanitize_boolean', 'validate_callback' => [RequestValidator::class, 'confirmed']]]]);
    }
    public function import_fonts(WP_REST_Request $request): WP_REST_Response
    {
        if (!defined('BSF_CUSTOM_FONTS_POST_TYPE')) {
            return new WP_REST_Response(['message' => __('Custom Fonts by Brainstorm Force is not activated.', 'jooosi-fon')], 404);
        }
        $fontIds = get_posts(['post_type' => \BSF_CUSTOM_FONTS_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => \true]);
        $preload = filter_var(get_option('bcf_preloading_fonts', \false), \FILTER_VALIDATE_BOOLEAN);
        add_filter('wp_check_filetype_and_ext', static fn($data, $file, $filename, $mimes) => Upload::disable_real_mime_check($data, $file, $filename, $mimes), 10, 4);
        add_filter('upload_mimes', static fn($mimeTypes) => Upload::upload_mimes($mimeTypes, \true), 1000001);
        $repository = new FontRepository();
        $media = new MediaImportService();
        $records = [];
        $insertedIds = [];
        $skipped = 0;
        try {
            foreach ($fontIds as $fontId) {
                $fontId = absint($fontId);
                $migrationSlug = 'migration-brainstorm-force-' . $fontId;
                if ($fontId < 1 || $repository->findBySlug($migrationSlug)) {
                    continue;
                }
                $post = get_post($fontId);
                $source = get_post_meta($fontId, 'fonts-data', \true);
                if (!$post || !is_array($source)) {
                    ++$skipped;
                    continue;
                }
                $family = sanitize_text_field($post->post_title);
                if ($family === '') {
                    ++$skipped;
                    continue;
                }
                $normalized = SourceFontNormalizer::brainstormForce($source);
                $skipped += $normalized['skipped'];
                $fontFaces = [];
                foreach ($normalized['faces'] as $sourceFace) {
                    $files = [];
                    foreach ($sourceFace['files'] as $sourceFile) {
                        $fileName = sanitize_title_with_dashes(sprintf('%s-%s-%s-%s-%s', $post->post_title, $sourceFace['weight'], $sourceFace['style'], Common::random_slug(5), time())) . '.' . $sourceFile['extension'];
                        $files[] = $media->remote($sourceFile['location'], $fileName, $sourceFile['extension']);
                    }
                    if ($files === []) {
                        continue;
                    }
                    $fontFaces[] = ['id' => Common::random_slug(10), 'weight' => $sourceFace['weight'], 'width' => $sourceFace['width'], 'style' => $sourceFace['style'], 'display' => '', 'selector' => '', 'comment' => '', 'unicodeRange' => '', 'preload' => \false, 'files' => $files];
                }
                if ($fontFaces === []) {
                    ++$skipped;
                    continue;
                }
                $selector = $normalized['fallback'] === '' ? '' : '| ' . sanitize_text_field($normalized['fallback']);
                $records[] = ['type' => 'custom', 'title' => $family, 'slug' => $migrationSlug, 'family' => $family, 'status' => \true, 'metadata' => FontCodec::encode(['preload' => $preload, 'selector' => $selector, 'display' => $normalized['display']]), 'font_faces' => FontCodec::encode($fontFaces)];
            }
            if ($skipped > 0) {
                $media->rollback();
                return new WP_REST_Response(['message' => __('Some Brainstorm Force font records had no supported, readable files. Nothing was imported so the source records remain safe.', 'jooosi-fon'), 'skipped' => $skipped], 422);
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
            return new WP_REST_Response(['message' => __('The Brainstorm Force Custom Fonts migration failed; newly created Jooosi Fon records were rolled back.', 'jooosi-fon')], 500);
        }
        $media->commit();
        FontEvents::dispatch('migration', ['source' => 'brainstorm-force-custom-fonts', 'ids' => $insertedIds], \true);
        return new WP_REST_Response(['message' => __('Brainstorm Force Custom Fonts imported. Source records were preserved.', 'jooosi-fon'), 'imported' => count($insertedIds), 'skipped' => $skipped, 'source_preserved' => \true]);
    }
    public function clean_up(WP_REST_Request $request): WP_REST_Response
    {
        if (!defined('BSF_CUSTOM_FONTS_POST_TYPE')) {
            return new WP_REST_Response(['message' => __('Custom Fonts by Brainstorm Force is not activated.', 'jooosi-fon')], 404);
        }
        $fontIds = get_posts(['post_type' => \BSF_CUSTOM_FONTS_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => \true]);
        $trashedIds = [];
        foreach ($fontIds as $fontId) {
            $fontId = absint($fontId);
            if ($fontId < 1 || !wp_trash_post($fontId)) {
                foreach ($trashedIds as $trashedId) {
                    wp_untrash_post($trashedId);
                }
                return new WP_REST_Response(['message' => __('Brainstorm Force source cleanup failed and previously trashed records were restored.', 'jooosi-fon')], 500);
            }
            $trashedIds[] = $fontId;
        }
        FontEvents::dispatch('clean_up', ['source' => 'brainstorm-force-custom-fonts', 'ids' => $trashedIds], \true);
        return new WP_REST_Response(['message' => __('Brainstorm Force Custom Fonts source records moved to the trash.', 'jooosi-fon'), 'cleaned' => count($trashedIds)]);
    }
}
