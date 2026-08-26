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
final class FontsPlugin extends AbstractApi implements ApiInterface
{
    private const TAXONOMY = 'ogf_custom_fonts';
    public function get_prefix(): string
    {
        return 'migrations/fonts-plugin';
    }
    public function register_custom_endpoints(): void
    {
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/import-fonts', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(WP_REST_Request $request): WP_REST_Response => $this->import_fonts($request), 'permission_callback' => fn(WP_REST_Request $request): bool => $this->permission_callback($request)]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/clean-up', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(WP_REST_Request $request): WP_REST_Response => $this->clean_up($request), 'permission_callback' => fn(WP_REST_Request $request): bool => $this->permission_callback($request), 'args' => ['confirm' => ['required' => \true, 'sanitize_callback' => 'rest_sanitize_boolean', 'validate_callback' => [RequestValidator::class, 'confirmed']]]]);
    }
    public function import_fonts(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->is_active()) {
            return new WP_REST_Response(['message' => __('Fonts Plugin is not activated.', 'jooosi-fon')], 404);
        }
        $terms = $this->source_terms();
        if (is_wp_error($terms)) {
            return new WP_REST_Response(['message' => __('Fonts Plugin records could not be read.', 'jooosi-fon')], 500);
        }
        $rows = [];
        foreach ($terms as $term) {
            $data = get_option($this->term_option_name((int) $term->term_id), []);
            if (!is_array($data)) {
                continue;
            }
            $rows[] = array_merge($data, ['source_key' => (string) $term->slug, 'name' => (string) $term->name]);
        }
        $families = SourceFontNormalizer::fontsPlugin($rows, $this->selector_assignments(), (string) get_theme_mod('ogf_font_display', 'swap'));
        $normalizedFaceCount = array_sum(array_map(static fn(array $family): int => count($family['faces']), $families));
        $skipped = max(0, count($terms) - $normalizedFaceCount);
        add_filter('wp_check_filetype_and_ext', static fn($data, $file, $filename, $mimes) => Upload::disable_real_mime_check($data, $file, $filename, $mimes), 10, 4);
        add_filter('upload_mimes', static fn($mimeTypes) => Upload::upload_mimes($mimeTypes, \true), 1000001);
        $repository = new FontRepository();
        $media = new MediaImportService();
        $records = [];
        $insertedIds = [];
        try {
            foreach ($families as $sourceFamily) {
                $family = sanitize_text_field((string) $sourceFamily['family']);
                $familySlug = sanitize_title_with_dashes($family);
                if ($family === '' || $familySlug === '') {
                    ++$skipped;
                    continue;
                }
                $migrationSlug = 'migration-fonts-plugin-' . $familySlug;
                if ($repository->findBySlug($migrationSlug)) {
                    continue;
                }
                $fontFaces = [];
                foreach ($sourceFamily['faces'] as $sourceFace) {
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
                if ($fontFaces === []) {
                    ++$skipped;
                    continue;
                }
                $records[] = ['type' => 'custom', 'title' => $family, 'slug' => $migrationSlug, 'family' => $family, 'status' => \true, 'metadata' => FontCodec::encode(['preload' => (bool) $sourceFamily['preload'], 'selector' => $sourceFamily['selector'], 'display' => $sourceFamily['display']]), 'font_faces' => FontCodec::encode($fontFaces)];
            }
            if ($skipped > 0) {
                $media->rollback();
                return new WP_REST_Response(['message' => __('Some Fonts Plugin variants had no supported font files. Nothing was imported so the source records remain safe.', 'jooosi-fon'), 'skipped' => $skipped], 422);
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
            return new WP_REST_Response(['message' => __('The Fonts Plugin migration failed; newly created Jooosi Fon records were rolled back.', 'jooosi-fon')], 500);
        }
        $media->commit();
        FontEvents::dispatch('migration', ['source' => 'fonts-plugin', 'ids' => $insertedIds], \true);
        return new WP_REST_Response(['message' => __('Fonts Plugin uploaded families imported. Source records were preserved.', 'jooosi-fon'), 'imported' => count($insertedIds), 'skipped' => $skipped, 'source_preserved' => \true]);
    }
    public function clean_up(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->is_active()) {
            return new WP_REST_Response(['message' => __('Fonts Plugin is not activated.', 'jooosi-fon')], 404);
        }
        $terms = $this->source_terms();
        if (is_wp_error($terms)) {
            return new WP_REST_Response(['message' => __('Fonts Plugin records could not be read.', 'jooosi-fon')], 500);
        }
        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === \false) {
            return new WP_REST_Response(['message' => __('Fonts Plugin cleanup could not start a database transaction.', 'jooosi-fon')], 500);
        }
        $termIds = array_map(static fn($term): int => (int) $term->term_id, $terms);
        $termOptionNames = array_map(fn(int $termId): string => $this->term_option_name($termId), $termIds);
        $assignedThemeMods = $this->assigned_theme_mods();
        try {
            foreach ($terms as $term) {
                delete_option($this->term_option_name((int) $term->term_id));
                $deleted = wp_delete_term((int) $term->term_id, self::TAXONOMY);
                if ($deleted === \false || is_wp_error($deleted)) {
                    throw new \RuntimeException('A Fonts Plugin term could not be deleted.');
                }
            }
            foreach (array_keys($assignedThemeMods) as $themeMod) {
                remove_theme_mod($themeMod);
            }
            if ($wpdb->query('COMMIT') === \false) {
                throw new \RuntimeException($wpdb->last_error ?: 'Fonts Plugin cleanup could not be committed.');
            }
        } catch (\Throwable $throwable) {
            $wpdb->query('ROLLBACK');
            clean_term_cache($termIds, self::TAXONOMY);
            foreach ($termOptionNames as $termOptionName) {
                wp_cache_delete($termOptionName, 'options');
            }
            foreach ($assignedThemeMods as $themeMod => $value) {
                set_theme_mod($themeMod, $value);
            }
            return new WP_REST_Response(['message' => __('Fonts Plugin cleanup failed and the source transaction was rolled back.', 'jooosi-fon')], 500);
        }
        FontEvents::dispatch('clean_up', ['source' => 'fonts-plugin', 'ids' => $termIds], \true);
        return new WP_REST_Response(['message' => __('Fonts Plugin uploaded-font records and their selector assignments were removed. Original media files were left in place.', 'jooosi-fon'), 'cleaned' => count($termIds)]);
    }
    private function is_active(): bool
    {
        return defined('OGF_VERSION') && taxonomy_exists(self::TAXONOMY) && function_exists('ogf_get_elements') && function_exists('ogf_get_custom_elements');
    }
    /**
     * @return array<int, object>|\WP_Error
     */
    private function source_terms()
    {
        return get_terms(['taxonomy' => self::TAXONOMY, 'hide_empty' => \false]);
    }
    /**
     * @return array<int, array{source_key: string, selector: string}>
     */
    private function selector_assignments(): array
    {
        $assignments = [];
        foreach ($this->source_elements() as $elementId => $element) {
            $fontId = get_theme_mod((string) $elementId . '_font', '');
            if (!is_string($fontId) || strpos($fontId, 'cf-') !== 0) {
                continue;
            }
            $selector = is_array($element) ? $element['selectors'] ?? '' : '';
            $assignments[] = ['source_key' => substr($fontId, 3), 'selector' => is_string($selector) ? $selector : ''];
        }
        return $assignments;
    }
    /**
     * @return array<string, mixed>
     */
    private function assigned_theme_mods(): array
    {
        $themeMods = [];
        foreach ($this->source_elements() as $elementId => $element) {
            $themeMod = (string) $elementId . '_font';
            $value = get_theme_mod($themeMod, null);
            if (is_string($value) && strpos($value, 'cf-') === 0) {
                $themeMods[$themeMod] = $value;
            }
        }
        return $themeMods;
    }
    /**
     * @return array<string, mixed>
     */
    private function source_elements(): array
    {
        $standard = ogf_get_elements();
        $custom = ogf_get_custom_elements();
        return array_merge(is_array($standard) ? $standard : [], is_array($custom) ? $custom : []);
    }
    private function term_option_name(int $termId): string
    {
        return 'taxonomy_' . self::TAXONOMY . '_' . $termId;
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
