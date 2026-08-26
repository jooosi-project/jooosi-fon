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
final class ElementorProCustomFonts extends AbstractApi implements ApiInterface
{
    private const LEGACY_POST_TYPE = 'elementor_font';
    private const FONT_FACE_POST_TYPE = 'elementor_font_face';
    private const LEGACY_META_KEY = 'elementor_font_files';
    public function get_prefix(): string
    {
        return 'migrations/elementor-pro-custom-fonts';
    }
    public function register_custom_endpoints(): void
    {
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/import-fonts', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(WP_REST_Request $request): WP_REST_Response => $this->import_fonts($request), 'permission_callback' => fn(WP_REST_Request $request): bool => $this->permission_callback($request)]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/clean-up', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(WP_REST_Request $request): WP_REST_Response => $this->clean_up($request), 'permission_callback' => fn(WP_REST_Request $request): bool => $this->permission_callback($request), 'args' => ['confirm' => ['required' => \true, 'sanitize_callback' => 'rest_sanitize_boolean', 'validate_callback' => [RequestValidator::class, 'confirmed']]]]);
    }
    public function import_fonts(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->is_active()) {
            return new WP_REST_Response(['message' => __('Elementor Pro Custom Fonts is not activated.', 'jooosi-fon')], 404);
        }
        $sourceRecords = $this->source_records();
        add_filter('wp_check_filetype_and_ext', static fn($data, $file, $filename, $mimes) => Upload::disable_real_mime_check($data, $file, $filename, $mimes), 10, 4);
        add_filter('upload_mimes', static fn($mimeTypes) => Upload::upload_mimes($mimeTypes, \true), 1000001);
        $repository = new FontRepository();
        $media = new MediaImportService();
        $records = [];
        $insertedIds = [];
        $skipped = 0;
        try {
            foreach ($sourceRecords as $sourceRecord) {
                $family = sanitize_text_field((string) $sourceRecord['title']);
                $fontId = (int) $sourceRecord['id'];
                $postType = (string) $sourceRecord['post_type'];
                if ($family === '' || $fontId < 1) {
                    ++$skipped;
                    continue;
                }
                $display = (string) apply_filters('elementor_pro/custom_fonts/font_display', 'auto', $family, $sourceRecord['variants']);
                $normalized = SourceFontNormalizer::elementor($sourceRecord['variants'], $display);
                $skipped += $normalized['skipped'];
                if ($normalized['faces'] === []) {
                    if ($normalized['skipped'] === 0) {
                        ++$skipped;
                    }
                    continue;
                }
                $migrationSlug = sprintf('migration-elementor-pro-%s-%d', $postType === self::FONT_FACE_POST_TYPE ? 'font-face' : 'font', $fontId);
                if ($repository->findBySlug($migrationSlug)) {
                    continue;
                }
                $fontFaces = [];
                foreach ($normalized['faces'] as $sourceFace) {
                    $files = [];
                    foreach ($sourceFace['files'] as $sourceFile) {
                        $fileName = sanitize_title_with_dashes(sprintf('%s-%s-%s-%s-%s', $family, $sourceFace['weight'], $sourceFace['style'], Common::random_slug(5), time())) . '.' . $sourceFile['extension'];
                        $files[] = $this->import_file($media, $sourceFile, $fileName);
                    }
                    if ($files === []) {
                        ++$skipped;
                        continue;
                    }
                    $fontFaces[] = ['id' => Common::random_slug(10), 'weight' => $sourceFace['weight'], 'width' => $sourceFace['width'], 'style' => $sourceFace['style'], 'display' => '', 'selector' => '', 'comment' => '', 'unicodeRange' => '', 'preload' => (bool) $sourceFace['preload'], 'files' => $files];
                }
                $records[] = ['type' => 'custom', 'title' => $family, 'slug' => $migrationSlug, 'family' => $family, 'status' => \true, 'metadata' => FontCodec::encode(['preload' => \false, 'selector' => '', 'display' => $normalized['display']]), 'font_faces' => FontCodec::encode($fontFaces)];
            }
            if ($skipped > 0) {
                $media->rollback();
                return new WP_REST_Response(['message' => __('Some Elementor Pro font variants had no supported files. Nothing was imported so the source records remain safe.', 'jooosi-fon'), 'skipped' => $skipped], 422);
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
            return new WP_REST_Response(['message' => __('The Elementor Pro Custom Fonts migration failed; newly created Jooosi Fon records were rolled back.', 'jooosi-fon')], 500);
        }
        $media->commit();
        FontEvents::dispatch('migration', ['source' => 'elementor-pro-custom-fonts', 'ids' => $insertedIds], \true);
        return new WP_REST_Response(['message' => __('Elementor Pro custom font families imported. Source records were preserved.', 'jooosi-fon'), 'imported' => count($insertedIds), 'skipped' => $skipped, 'source_preserved' => \true]);
    }
    public function clean_up(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->is_active()) {
            return new WP_REST_Response(['message' => __('Elementor Pro Custom Fonts is not activated.', 'jooosi-fon')], 404);
        }
        $fontIds = array_values(array_unique(array_map(static fn(array $sourceRecord): int => (int) $sourceRecord['id'], $this->source_records())));
        $trashedIds = [];
        foreach ($fontIds as $fontId) {
            if ($fontId < 1 || !wp_trash_post($fontId)) {
                foreach ($trashedIds as $trashedId) {
                    wp_untrash_post($trashedId);
                }
                return new WP_REST_Response(['message' => __('Elementor Pro source cleanup failed and previously trashed records were restored.', 'jooosi-fon')], 500);
            }
            $trashedIds[] = $fontId;
        }
        FontEvents::dispatch('clean_up', ['source' => 'elementor-pro-custom-fonts', 'ids' => $trashedIds], \true);
        return new WP_REST_Response(['message' => __('Elementor Pro custom-font records moved to the trash. Original media files were left in place.', 'jooosi-fon'), 'cleaned' => count($trashedIds)]);
    }
    private function is_active(): bool
    {
        return defined('ELEMENTOR_PRO_VERSION') && (post_type_exists(self::LEGACY_POST_TYPE) || post_type_exists(self::FONT_FACE_POST_TYPE));
    }
    /**
     * @return array<int, array{id: int, post_type: string, title: string, variants: array<int, mixed>}>
     */
    private function source_records(): array
    {
        $records = [];
        foreach ([self::LEGACY_POST_TYPE, self::FONT_FACE_POST_TYPE] as $postType) {
            if (!post_type_exists($postType)) {
                continue;
            }
            $fontIds = get_posts(['post_type' => $postType, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => \true]);
            foreach ($fontIds as $fontId) {
                $fontId = absint($fontId);
                $post = get_post($fontId);
                if ($fontId < 1 || !$post) {
                    continue;
                }
                $variants = $postType === self::LEGACY_POST_TYPE ? get_post_meta($fontId, self::LEGACY_META_KEY, \true) : get_post_meta($fontId, 'font_face', \true);
                if (!is_array($variants) || $variants === []) {
                    $variant = $this->font_face_variant($fontId);
                    $variants = $variant === [] ? [] : [$variant];
                } elseif (isset($variants['font_weight']) || isset($variants['weight']) || array_intersect(['woff2', 'woff', 'ttf', 'otf', 'eot'], array_keys($variants)) !== []) {
                    $variants = [$variants];
                }
                $records[] = ['id' => $fontId, 'post_type' => $postType, 'title' => (string) $post->post_title, 'variants' => array_values($variants)];
            }
        }
        return $records;
    }
    /**
     * @return array<string, mixed>
     */
    private function font_face_variant(int $fontId): array
    {
        $variant = ['font_weight' => $this->first_meta($fontId, ['font_face_weight', 'font_weight', 'weight'], '400'), 'font_style' => $this->first_meta($fontId, ['font_face_style', 'font_style', 'style'], 'normal'), 'font_stretch' => $this->first_meta($fontId, ['font_face_stretch', 'font_stretch', 'stretch'], 'normal')];
        $hasFile = \false;
        foreach (['woff2', 'woff', 'ttf', 'otf', 'eot'] as $extension) {
            $source = $this->first_meta($fontId, ['font_face_file_' . $extension, 'font_file_' . $extension, $extension], null);
            if (is_numeric($source) && (int) $source > 0) {
                $source = ['id' => (int) $source, 'url' => wp_get_attachment_url((int) $source) ?: ''];
            } elseif (is_array($source) && empty($source['url']) && !empty($source['id'])) {
                $source['url'] = wp_get_attachment_url((int) $source['id']) ?: '';
            }
            if (is_string($source) && $source !== '' || is_array($source) && !empty($source['url'])) {
                $variant[$extension] = $source;
                $hasFile = \true;
            }
        }
        return $hasFile ? $variant : [];
    }
    /**
     * @param string[] $keys
     * @return mixed
     */
    private function first_meta(int $fontId, array $keys, $default)
    {
        foreach ($keys as $key) {
            $value = get_post_meta($fontId, $key, \true);
            if ($value !== '' && $value !== null && $value !== \false) {
                return $value;
            }
        }
        return $default;
    }
    /**
     * @param array<string, mixed> $sourceFile
     * @return array<string, mixed>
     */
    private function import_file(MediaImportService $media, array $sourceFile, string $fileName): array
    {
        $location = (string) ($sourceFile['location'] ?? '');
        $extension = (string) ($sourceFile['extension'] ?? '');
        $attachmentId = (int) ($sourceFile['attachment_id'] ?? attachment_url_to_postid($location));
        $path = $attachmentId > 0 ? get_attached_file($attachmentId) : \false;
        if (is_string($path) && is_readable($path)) {
            return $media->local($path, $fileName, $extension);
        }
        return $media->remote($location, $fileName, $extension);
    }
}
