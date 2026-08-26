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
final class UseAnyFont extends AbstractApi implements ApiInterface
{
    public function get_prefix(): string
    {
        return 'migrations/use-any-font';
    }
    public function register_custom_endpoints(): void
    {
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/import-fonts', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(WP_REST_Request $request): WP_REST_Response => $this->import_fonts($request), 'permission_callback' => fn(WP_REST_Request $request): bool => $this->permission_callback($request)]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/clean-up', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(WP_REST_Request $request): WP_REST_Response => $this->clean_up($request), 'permission_callback' => fn(WP_REST_Request $request): bool => $this->permission_callback($request), 'args' => ['confirm' => ['required' => \true, 'sanitize_callback' => 'rest_sanitize_boolean', 'validate_callback' => [RequestValidator::class, 'confirmed']]]]);
    }
    public function import_fonts(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->is_active()) {
            return new WP_REST_Response(['message' => __('Use Any Font is not activated.', 'jooosi-fon')], 404);
        }
        $fontRows = $this->decode_option('uaf_font_data');
        $assignments = $this->decode_option('uaf_font_implement');
        if ($fontRows === null || $assignments === null) {
            return new WP_REST_Response(['message' => __('Use Any Font records could not be read.', 'jooosi-fon')], 500);
        }
        /** @var array{dir?: mixed} $paths */
        $paths = uaf_path_details();
        $directory = isset($paths['dir']) && is_string($paths['dir']) ? $paths['dir'] : '';
        if ($directory === '') {
            return new WP_REST_Response(['message' => __('Use Any Font storage could not be located.', 'jooosi-fon')], 500);
        }
        $families = SourceFontNormalizer::useAnyFont($fontRows, $assignments, $directory, (string) get_option('uaf_font_display_property', 'auto'));
        add_filter('wp_check_filetype_and_ext', static fn($data, $file, $filename, $mimes) => Upload::disable_real_mime_check($data, $file, $filename, $mimes), 10, 4);
        add_filter('upload_mimes', static fn($mimeTypes) => Upload::upload_mimes($mimeTypes, \true), 1000001);
        $repository = new FontRepository();
        $media = new MediaImportService();
        $records = [];
        $insertedIds = [];
        $normalizedFaceCount = array_sum(array_map(static fn(array $sourceFamily): int => count($sourceFamily['faces']), $families));
        $skipped = max(0, count($fontRows) - $normalizedFaceCount);
        try {
            foreach ($families as $sourceFamily) {
                $family = sanitize_text_field($sourceFamily['family']);
                $familySlug = sanitize_title_with_dashes($family);
                if ($family === '' || $familySlug === '') {
                    ++$skipped;
                    continue;
                }
                $migrationSlug = 'migration-use-any-font-' . $familySlug;
                if ($repository->findBySlug($migrationSlug)) {
                    continue;
                }
                $fontFaces = [];
                foreach ($sourceFamily['faces'] as $sourceFace) {
                    $files = [];
                    foreach ($sourceFace['files'] as $sourceFile) {
                        if (!is_file($sourceFile['location']) || !is_readable($sourceFile['location'])) {
                            continue;
                        }
                        $fileName = sanitize_title_with_dashes(sprintf('%s-%s-%s-%s-%s', $family, $sourceFace['weight'], $sourceFace['style'], Common::random_slug(5), time())) . '.' . $sourceFile['extension'];
                        $files[] = $media->local($sourceFile['location'], $fileName, $sourceFile['extension']);
                    }
                    if ($files === []) {
                        ++$skipped;
                        continue;
                    }
                    $fontFaces[] = ['id' => Common::random_slug(10), 'weight' => $sourceFace['weight'], 'width' => $sourceFace['width'], 'style' => $sourceFace['style'], 'display' => '', 'selector' => '', 'comment' => '', 'unicodeRange' => '', 'preload' => \false, 'files' => $files];
                }
                if ($fontFaces === []) {
                    ++$skipped;
                    continue;
                }
                $records[] = ['type' => 'custom', 'title' => $family, 'slug' => $migrationSlug, 'family' => $family, 'status' => \true, 'metadata' => FontCodec::encode(['preload' => \false, 'selector' => $sourceFamily['selector'], 'display' => $sourceFamily['display']]), 'font_faces' => FontCodec::encode($fontFaces)];
            }
            if ($skipped > 0) {
                $media->rollback();
                return new WP_REST_Response(['message' => __('Some Use Any Font records had no supported, readable files. Nothing was imported so the source records remain safe.', 'jooosi-fon'), 'skipped' => $skipped], 422);
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
            return new WP_REST_Response(['message' => __('The Use Any Font migration failed; newly created Jooosi Fon records were rolled back.', 'jooosi-fon')], 500);
        }
        $media->commit();
        FontEvents::dispatch('migration', ['source' => 'use-any-font', 'ids' => $insertedIds], \true);
        return new WP_REST_Response(['message' => __('Use Any Font families imported. Source records were preserved.', 'jooosi-fon'), 'imported' => count($insertedIds), 'skipped' => $skipped, 'source_preserved' => \true]);
    }
    public function clean_up(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->is_active()) {
            return new WP_REST_Response(['message' => __('Use Any Font is not activated.', 'jooosi-fon')], 404);
        }
        $fontData = get_option('uaf_font_data', null);
        $assignments = get_option('uaf_font_implement', null);
        try {
            $this->replace_option('uaf_font_data', $fontData, '[]');
            $this->replace_option('uaf_font_implement', $assignments, '[]');
            if (function_exists('uaf_write_css')) {
                uaf_write_css();
            }
        } catch (\Throwable $throwable) {
            $this->restore_option('uaf_font_data', $fontData);
            $this->restore_option('uaf_font_implement', $assignments);
            return new WP_REST_Response(['message' => __('Use Any Font cleanup failed and the source options were restored.', 'jooosi-fon')], 500);
        }
        $decodedFontData = $this->decode_value($fontData);
        $cleaned = is_array($decodedFontData) ? count($decodedFontData) : 0;
        FontEvents::dispatch('clean_up', ['source' => 'use-any-font'], \true);
        return new WP_REST_Response(['message' => __('Use Any Font source records and selector assignments were removed. Original font files were left in place.', 'jooosi-fon'), 'cleaned' => $cleaned]);
    }
    private function is_active(): bool
    {
        return defined('UAF_FILE_PATH') && function_exists('uaf_path_details');
    }
    /**
     * @return array<string|int, mixed>|null
     */
    private function decode_option(string $option): ?array
    {
        return $this->decode_value(get_option($option, '[]'));
    }
    /**
     * @param mixed $value
     * @return array<string|int, mixed>|null
     */
    private function decode_value($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === \false || $value === null || $value === '') {
            return [];
        }
        if (!is_string($value)) {
            return null;
        }
        $decoded = json_decode($value, \true);
        return is_array($decoded) ? $decoded : null;
    }
    /**
     * @param mixed $original
     */
    private function replace_option(string $option, $original, string $replacement): void
    {
        if ($original === $replacement) {
            return;
        }
        if (!update_option($option, $replacement)) {
            throw new \RuntimeException('The Use Any Font source option could not be updated.');
        }
    }
    /**
     * @param mixed $value
     */
    private function restore_option(string $option, $value): void
    {
        if ($value === null || $value === \false) {
            delete_option($option);
            return;
        }
        update_option($option, $value);
    }
}
