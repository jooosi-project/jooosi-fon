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
use JooosiFon\Api\Support\FontCodec;
use JooosiFon\Api\Support\FontEvents;
use JooosiFon\Api\Support\FontRepository;
use JooosiFon\Api\Support\GoogleFontFileSelector;
use JooosiFon\Api\Support\GoogleFontsClient;
use JooosiFon\Api\Support\MediaImportService;
use JooosiFon\Api\Support\RequestValidator;
use JooosiFon\Builder\Integration;
use JooosiFon\Core\Cache;
use JooosiFon\Utils\Common;
use JooosiFon\Utils\Config;
use JooosiFon\Utils\Upload;
use JooosiFonDeps\Sabberworm\CSS\Parser;
use stdClass;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use wpdb;
/**
 * @todo Remove all legacy Yabe Webfont filter shims completely in Jooosi Fon 3.0.0.
 */
class Font extends \JooosiFon\Api\AbstractApi implements \JooosiFon\Api\ApiInterface
{
    public function __construct()
    {
    }
    public function get_prefix(): string
    {
        return 'fonts';
    }
    public function register_custom_endpoints(): void
    {
        $id_args = ['id' => ['required' => \true, 'sanitize_callback' => 'absint', 'validate_callback' => [RequestValidator::class, 'positiveInteger']]];
        $custom_font_args = ['title' => ['required' => \true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [RequestValidator::class, 'nonEmptyString']], 'family' => ['required' => \true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [RequestValidator::class, 'nonEmptyString']], 'status' => ['required' => \true, 'sanitize_callback' => 'rest_sanitize_boolean', 'validate_callback' => [RequestValidator::class, 'boolean']], 'metadata' => ['required' => \true, 'validate_callback' => [RequestValidator::class, 'arrayValue']], 'font_faces' => ['required' => \true, 'sanitize_callback' => [RequestValidator::class, 'sanitizeFontFaces'], 'validate_callback' => [RequestValidator::class, 'fontFaces']]];
        $google_font_args = ['title' => $custom_font_args['title'], 'status' => $custom_font_args['status'], 'metadata' => ['required' => \true, 'validate_callback' => [RequestValidator::class, 'googleMetadata']]];
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/index', ['methods' => WP_REST_Server::READABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->index($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => ['page' => ['default' => 1, 'sanitize_callback' => 'absint', 'validate_callback' => [RequestValidator::class, 'positiveInteger']], 'per_page' => ['default' => 20, 'sanitize_callback' => 'absint', 'validate_callback' => [RequestValidator::class, 'pageSize']], 'search' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'], 'soft_deleted' => ['default' => \false, 'sanitize_callback' => 'rest_sanitize_boolean', 'validate_callback' => [RequestValidator::class, 'boolean']]]]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/custom/store', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->custom_store($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => $custom_font_args]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/update-status/(?P<id>\d+)', ['methods' => WP_REST_Server::EDITABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->update_status($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => array_merge($id_args, ['status' => ['required' => \true, 'sanitize_callback' => 'rest_sanitize_boolean', 'validate_callback' => [RequestValidator::class, 'boolean']]])]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/delete/(?P<id>\d+)', ['methods' => 'POST, DELETE', 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->destroy($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => $id_args]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/restore/(?P<id>\d+)', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->restore($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => $id_args]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/export', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->export($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => ['items' => ['required' => \true, 'validate_callback' => [RequestValidator::class, 'positiveIntegerList'], 'sanitize_callback' => static fn($items): array => array_values(array_unique(array_map('absint', $items)))]]]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/import', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->import($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => ['site_url' => ['required' => \true, 'sanitize_callback' => 'esc_url_raw', 'validate_callback' => 'wp_http_validate_url'], 'version' => ['required' => \true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [RequestValidator::class, 'nonEmptyString']], 'is_bundled' => ['required' => \true, 'sanitize_callback' => 'rest_sanitize_boolean', 'validate_callback' => [RequestValidator::class, 'boolean']], 'item' => ['required' => \true, 'validate_callback' => [RequestValidator::class, 'importItem']]]]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/detail/(?P<id>\d+)', ['methods' => WP_REST_Server::READABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->detail($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => $id_args]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/custom/update/(?P<id>\d+)', ['methods' => WP_REST_Server::EDITABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->custom_update($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => array_merge($id_args, $custom_font_args)]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/google-fonts/store', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->google_fonts_store($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => $google_font_args]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/google-fonts/update/(?P<id>\d+)', ['methods' => WP_REST_Server::EDITABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->google_fonts_update($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => array_merge($id_args, $google_font_args)]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/google-fonts/metadata', ['methods' => WP_REST_Server::READABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->google_fonts_metadata($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => ['force' => ['default' => \false, 'sanitize_callback' => 'rest_sanitize_boolean', 'validate_callback' => [RequestValidator::class, 'boolean']]]]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/google-fonts/webfonts/(?P<slug>[a-zA-Z0-9_\-+\.]+)', ['methods' => WP_REST_Server::READABLE, 'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->google_fonts_webfonts($wprestRequest), 'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest), 'args' => ['slug' => ['required' => \true, 'sanitize_callback' => 'sanitize_key', 'validate_callback' => [RequestValidator::class, 'nonEmptyString']], 'subsets' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [RequestValidator::class, 'subsets']]]]);
    }
    private function index(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $soft_deleted = rest_sanitize_boolean($wprestRequest->get_param('soft_deleted'));
        $page = max(1, (int) $wprestRequest->get_param('page'));
        $per_page = min(100, max(1, (int) $wprestRequest->get_param('per_page')));
        $offset = $page * $per_page - $per_page;
        $search = trim((string) $wprestRequest->get_param('search'));
        $search = $search !== '' ? $search : null;
        $items = [];
        $where_clause = [];
        if ($search) {
            $escaped_search = '%' . $wpdb->esc_like($search) . '%';
            $where_clause[] = $wpdb->prepare("( title LIKE '%1\$s' OR family LIKE '%1\$s' )", $escaped_search);
        }
        $where_clause[] = $soft_deleted ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
        $where_clause = 'WHERE ' . implode(' AND ', $where_clause);
        $sql = "\n            SELECT * FROM {$wpdb->prefix}jooosi_fon_fonts\n            {$where_clause}\n            ORDER BY id DESC\n            LIMIT %d OFFSET %d\n        ";
        $sql = $wpdb->prepare($sql, $per_page, $offset);
        $result = $wpdb->get_results($sql);
        foreach ($result as $row) {
            $metadata = FontCodec::decode((string) $row->metadata);
            $font_faces = FontCodec::decode((string) $row->font_faces);
            $items[] = ['id' => $row->id, 'type' => $row->type, 'title' => $row->title, 'slug' => $row->slug, 'family' => $row->family, 'metadata' => $metadata, 'font_faces' => Upload::refresh_font_faces_attachment_url($font_faces), 'status' => (bool) $row->status, 'created_at' => strtotime($row->created_at), 'updated_at' => strtotime($row->updated_at), 'deleted_at' => $row->deleted_at ? strtotime($row->deleted_at) : null];
        }
        $totals = $wpdb->get_row("\n            SELECT\n                SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) AS total_exists,\n                SUM(CASE WHEN status = 1 AND deleted_at IS NULL THEN 1 ELSE 0 END) AS total_active,\n                SUM(CASE WHEN type = 'adobe-fonts' AND deleted_at IS NULL THEN 1 ELSE 0 END) AS total_adobe_fonts,\n                SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) AS total_deleted\n            FROM {$wpdb->prefix}jooosi_fon_fonts\n        ");
        $total_exists = (int) ($totals->total_exists ?? 0);
        $total_active = (int) ($totals->total_active ?? 0);
        $total_adobe_fonts = (int) ($totals->total_adobe_fonts ?? 0);
        $total_deleted = (int) ($totals->total_deleted ?? 0);
        $total_filtered = (int) $wpdb->get_var("\n            SELECT COUNT(*) FROM {$wpdb->prefix}jooosi_fon_fonts\n            {$where_clause}\n        ");
        $total_pages = (int) ceil($total_filtered / $per_page);
        $from = $items !== [] ? ($page - 1) * $per_page + 1 : null;
        $to = $items !== [] ? $from + count($items) - 1 : null;
        return new WP_REST_Response(['data' => $items, 'meta' => ['page' => $page, 'per_page' => $per_page, 'search' => $search, 'total_pages' => $total_pages, 'from' => $from, 'to' => $to, 'total_filtered' => $total_filtered, 'total_deleted' => $total_deleted, 'total_exists' => $total_exists, 'infrastructure' => ['google_fonts' => $this->get_google_fonts_catalog_stats(), 'active_fonts' => ['total' => $total_active], 'adobe_fonts' => ['active' => trim((string) Config::get('adobe_fonts.project_id', '')) !== '', 'total' => $total_adobe_fonts], 'integrations' => ['total' => Integration::get_registered_count()]]]], 200, ['X-WP-Total' => $total_filtered, 'X-WP-TotalPages' => $total_pages]);
    }
    /**
     * @return array{total: int, cache_updated_at: int|null}
     */
    private function get_google_fonts_catalog_stats(): array
    {
        $cache_path = Cache::get_cache_path('webfonts.json');
        $has_cache = file_exists($cache_path) && is_readable($cache_path);
        $source_path = $has_cache ? $cache_path : dirname(JOOOSI_FON::FILE) . '/google-fonts.json';
        $source_updated_at = is_readable($source_path) ? filemtime($source_path) : \false;
        $source_updated_at = is_int($source_updated_at) ? $source_updated_at : null;
        $cache_updated_at = $has_cache ? $source_updated_at : null;
        $transient_key = 'jooosi_fon_google_fonts_catalog_stats';
        $cached = get_transient($transient_key);
        if (is_array($cached) && ($cached['source_path'] ?? null) === $source_path && ($cached['source_updated_at'] ?? null) === $source_updated_at) {
            return ['total' => (int) ($cached['total'] ?? 0), 'cache_updated_at' => $cache_updated_at];
        }
        $total = 0;
        if (is_readable($source_path)) {
            try {
                $catalog = json_decode(file_get_contents($source_path), \true, 512, \JSON_THROW_ON_ERROR);
                $total = is_array($catalog) ? count($catalog) : 0;
            } catch (\JsonException $e) {
                $total = 0;
            }
        }
        set_transient($transient_key, ['source_path' => $source_path, 'source_updated_at' => $source_updated_at, 'total' => $total], \DAY_IN_SECONDS);
        return ['total' => $total, 'cache_updated_at' => $cache_updated_at];
    }
    private function detail(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $url_params = $wprestRequest->get_url_params();
        $id = (int) $url_params['id'];
        $sql = "\n            SELECT * FROM {$wpdb->prefix}jooosi_fon_fonts\n            WHERE id = %d\n        ";
        $sql = $wpdb->prepare($sql, $id);
        $row = $wpdb->get_row($sql);
        if (!$row) {
            return new WP_REST_Response(['message' => 'Font not found'], 404, []);
        }
        $metadata = FontCodec::decode((string) $row->metadata);
        $font_faces = FontCodec::decode((string) $row->font_faces);
        $payload = ['id' => $row->id, 'type' => $row->type, 'title' => $row->title, 'slug' => $row->slug, 'family' => $row->family, 'metadata' => $metadata, 'font_faces' => Upload::refresh_font_faces_attachment_url($font_faces), 'status' => (bool) $row->status, 'created_at' => strtotime($row->created_at), 'updated_at' => strtotime($row->updated_at), 'deleted_at' => $row->deleted_at ? strtotime($row->deleted_at) : null];
        if (is_object($payload['metadata']) && property_exists($payload['metadata'], 'google_fonts')) {
            $payload['metadata']->google_fonts->font_files = Upload::refresh_google_fonts_attachment_url($payload['metadata']->google_fonts->font_files);
        }
        return new WP_REST_Response($payload, 200, []);
    }
    private function custom_store(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        try {
            $repository = new FontRepository();
            $id = $repository->insert(['type' => 'custom', 'title' => (string) $wprestRequest->get_param('title'), 'slug' => Common::random_slug(10), 'family' => (string) $wprestRequest->get_param('family'), 'status' => rest_sanitize_boolean($wprestRequest->get_param('status')), 'metadata' => FontCodec::encode((array) $wprestRequest->get_param('metadata')), 'font_faces' => FontCodec::encode((array) $wprestRequest->get_param('font_faces'))], ['%s', '%s', '%s', '%s', '%d', '%s', '%s']);
        } catch (\Throwable $throwable) {
            return $this->operation_error($throwable, __('Unable to create the font.', 'jooosi-fon'));
        }
        FontEvents::dispatch('custom_store', $id);
        return new WP_REST_Response(['id' => $id], 200, []);
    }
    private function update_status(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        $id = (int) $wprestRequest->get_param('id');
        $status = rest_sanitize_boolean($wprestRequest->get_param('status'));
        $repository = new FontRepository();
        if (!$repository->find($id)) {
            return new WP_REST_Response(['message' => __('Font not found', 'jooosi-fon')], 404, []);
        }
        try {
            $repository->update($id, ['status' => $status], ['%d']);
        } catch (\Throwable $throwable) {
            return $this->operation_error($throwable, __('Unable to update the font status.', 'jooosi-fon'));
        }
        FontEvents::dispatch('update_status', $id);
        return new WP_REST_Response(['id' => $id, 'status' => $status], 200, []);
    }
    private function destroy(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        $id = (int) $wprestRequest->get_param('id');
        $repository = new FontRepository();
        $item = $repository->find($id);
        if (!$item) {
            return new WP_REST_Response(['message' => __('Font not found', 'jooosi-fon')], 404, []);
        }
        try {
            if ($item->deleted_at) {
                $fontFaces = FontCodec::decode((string) $item->font_faces);
                $attachmentIds = MediaImportService::attachmentIds($fontFaces);
                $repository->delete($id);
                MediaImportService::deleteAttachments($attachmentIds);
            } else {
                $repository->update($id, ['deleted_at' => current_time('mysql')], ['%s']);
            }
        } catch (\Throwable $throwable) {
            return $this->operation_error($throwable, __('Unable to delete the font.', 'jooosi-fon'));
        }
        FontEvents::dispatch('destroy', $item);
        return new WP_REST_Response(null, 200, []);
    }
    private function restore(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        $id = (int) $wprestRequest->get_param('id');
        $repository = new FontRepository();
        if (!$repository->find($id)) {
            return new WP_REST_Response(['message' => __('Font not found', 'jooosi-fon')], 404, []);
        }
        try {
            $repository->update($id, ['deleted_at' => null], ['%s']);
        } catch (\Throwable $throwable) {
            return $this->operation_error($throwable, __('Unable to restore the font.', 'jooosi-fon'));
        }
        FontEvents::dispatch('restore', $id);
        return new WP_REST_Response(['id' => $id], 200, []);
    }
    private function custom_update(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        $id = (int) $wprestRequest->get_param('id');
        $repository = new FontRepository();
        $existing = $repository->findOfType($id, 'custom');
        if (!$existing) {
            return new WP_REST_Response(['message' => __('Font not found', 'jooosi-fon')], 404, []);
        }
        $fontFaces = (array) $wprestRequest->get_param('font_faces');
        try {
            $oldAttachmentIds = MediaImportService::attachmentIds(FontCodec::decode((string) $existing->font_faces));
            $newAttachmentIds = MediaImportService::attachmentIds($fontFaces);
            $repository->update($id, ['title' => (string) $wprestRequest->get_param('title'), 'family' => (string) $wprestRequest->get_param('family'), 'status' => rest_sanitize_boolean($wprestRequest->get_param('status')), 'metadata' => FontCodec::encode((array) $wprestRequest->get_param('metadata')), 'font_faces' => FontCodec::encode($fontFaces)], ['%s', '%s', '%d', '%s', '%s']);
            MediaImportService::deleteAttachments(array_diff($oldAttachmentIds, $newAttachmentIds));
        } catch (\Throwable $throwable) {
            return $this->operation_error($throwable, __('Unable to update the font.', 'jooosi-fon'));
        }
        FontEvents::dispatch('custom_update', $id);
        return new WP_REST_Response(['id' => $id], 200, []);
    }
    private function google_fonts_store(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        $payload = $wprestRequest->get_json_params();
        $media = new MediaImportService();
        $type = 'google-fonts';
        $title = sanitize_text_field($payload['title']);
        $slug = Common::random_slug(10);
        $status = (bool) $payload['status'];
        $metadata = $payload['metadata'];
        $family = $metadata['google_fonts']['font_data']['family'];
        $font_faces = [];
        add_filter('wp_check_filetype_and_ext', static fn($data, $file, $filename, $mimes) => Upload::disable_real_mime_check($data, $file, $filename, $mimes), 10, 4);
        add_filter('upload_mimes', static fn($mime_types) => Upload::upload_mimes($mime_types, \true), 1000001);
        $m_font_faces = $metadata['google_fonts']['font_faces'];
        $m_font_files = $metadata['google_fonts']['font_files'];
        foreach ($m_font_faces as $k => $m_face) {
            if (!$m_face['isEnabled']) {
                continue;
            }
            if ($metadata['google_fonts']['variable']) {
                if ($m_face['weight'] !== 0) {
                    continue;
                }
                $all_filtered_m_font_files = GoogleFontFileSelector::variableFiles($m_font_files, $m_face, $metadata['google_fonts']['subsets'], $metadata['google_fonts']['formats']);
                foreach ($all_filtered_m_font_files as $filtered_m_font_file) {
                    $wght = array_filter($metadata['google_fonts']['font_data']['axes'], static fn($a) => $a['tag'] === 'wght');
                    $wdth = array_filter($metadata['google_fonts']['font_data']['axes'], static fn($a) => $a['tag'] === 'wdth');
                    $slnt = array_filter($metadata['google_fonts']['font_data']['axes'], static fn($a) => $a['tag'] === 'slnt');
                    $wdth = array_values($wdth);
                    $wght = array_values($wght);
                    $slnt = array_values($slnt);
                    $font_face = ['id' => Common::random_slug(10), 'weight' => $wght !== [] ? sprintf('%s %s', $wght[0]['min'], $wght[0]['max']) : '400', 'width' => $wdth !== [] ? sprintf('%s%% %s%%', $wdth[0]['min'], $wdth[0]['max']) : '100%', 'style' => $slnt !== [] ? sprintf('oblique %sdeg %sdeg', $slnt[0]['max'] * -1, $slnt[0]['min'] * -1) : $m_face['style'], 'display' => $m_face['display'], 'selector' => $m_face['selector'], 'comment' => $m_face['comment'], 'preload' => $m_face['preload']];
                    $file_name = sanitize_title_with_dashes(sprintf(
                        'google-fonts-%s-%s-%s-var-%s-%s',
                        $metadata['google_fonts']['font_data']['slug'],
                        // family
                        $metadata['google_fonts']['font_data']['version'] ?? 'latest',
                        implode('_', $filtered_m_font_file['subsets']),
                        Common::random_slug(5),
                        time()
                    )) . '.' . $filtered_m_font_file['format'];
                    try {
                        $file = $media->remote($filtered_m_font_file['url'], $file_name, $filtered_m_font_file['format'], ['fonts.gstatic.com']);
                    } catch (\Throwable $throwable) {
                        $media->rollback();
                        return $this->operation_error($throwable, __('Unable to download the Google font files.', 'jooosi-fon'));
                    }
                    $metadata['google_fonts']['font_files'] = array_map(static fn($f) => $f['uid'] === $filtered_m_font_file['uid'] ? array_merge($f, ['file' => $file]) : $f, $metadata['google_fonts']['font_files']);
                    $metadata['google_fonts']['font_faces'][$k]['attached_font_files'][] = $filtered_m_font_file['uid'];
                    $font_face['files'] = [$file];
                    $font_face['unicodeRange'] = $filtered_m_font_file['unicodeRange'] ?? '';
                    $font_faces[] = $font_face;
                }
            } else {
                if ($m_face['weight'] === 0) {
                    continue;
                }
                $font_face = ['id' => Common::random_slug(10), 'weight' => $m_face['weight'], 'width' => $m_face['width'] ?: '100%', 'style' => $m_face['style'], 'display' => $m_face['display'], 'selector' => $m_face['selector'], 'comment' => $m_face['comment'], 'unicodeRange' => '', 'preload' => $m_face['preload']];
                $files = [];
                $filtered_m_font_files = array_filter($m_font_files, static fn($f) => $f['weight'] === $m_face['weight'] && $f['style'] === $m_face['style'] && array_diff($metadata['google_fonts']['subsets'], $f['subsets']) === array_diff($f['subsets'], $metadata['google_fonts']['subsets']) && in_array($f['format'], $metadata['google_fonts']['formats'], \true));
                $format_precedence = ['woff2' => 1, 'woff' => 2, 'ttf' => 3, 'otf' => 4, 'eot' => 5];
                usort($filtered_m_font_files, static fn($a, $b) => $format_precedence[$a['format']] <=> $format_precedence[$b['format']]);
                foreach ($filtered_m_font_files as $filtered_m_font_file) {
                    $file_name = sanitize_title_with_dashes(sprintf(
                        'google-fonts-%s-%s-%s-%s-%s-%s',
                        $metadata['google_fonts']['font_data']['slug'],
                        // family
                        $metadata['google_fonts']['font_data']['version'] ?? 'latest',
                        implode('-', $metadata['google_fonts']['subsets']),
                        $filtered_m_font_file['weight'],
                        $filtered_m_font_file['style'],
                        time()
                    )) . '.' . $filtered_m_font_file['format'];
                    try {
                        $file = $media->remote($filtered_m_font_file['url'], $file_name, $filtered_m_font_file['format'], ['fonts.gstatic.com']);
                    } catch (\Throwable $throwable) {
                        $media->rollback();
                        return $this->operation_error($throwable, __('Unable to download the Google font files.', 'jooosi-fon'));
                    }
                    $metadata['google_fonts']['font_files'] = array_map(static fn($f) => $f['uid'] === $filtered_m_font_file['uid'] ? array_merge($f, ['file' => $file]) : $f, $metadata['google_fonts']['font_files']);
                    $metadata['google_fonts']['font_faces'][$k]['attached_font_files'][] = $filtered_m_font_file['uid'];
                    $files[] = $file;
                }
                $font_face['files'] = $files;
                $font_faces[] = $font_face;
            }
        }
        try {
            $repository = new FontRepository();
            $id = $repository->insert(['type' => $type, 'title' => $title, 'slug' => $slug, 'family' => $family, 'status' => $status, 'metadata' => FontCodec::encode($metadata), 'font_faces' => FontCodec::encode($font_faces)], ['%s', '%s', '%s', '%s', '%d', '%s', '%s']);
            $media->commit();
        } catch (\Throwable $throwable) {
            $media->rollback();
            return $this->operation_error($throwable, __('Unable to create the Google font.', 'jooosi-fon'));
        }
        FontEvents::dispatch('google_fonts_store', $id);
        return new WP_REST_Response(['id' => $id], 200, []);
    }
    private function google_fonts_update(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        $payload = $wprestRequest->get_json_params();
        $id = (int) $wprestRequest->get_param('id');
        $repository = new FontRepository();
        $existing = $repository->findOfType($id, 'google-fonts');
        if (!$existing) {
            return new WP_REST_Response(['message' => __('Font not found', 'jooosi-fon')], 404, []);
        }
        $title = sanitize_text_field($payload['title']);
        $status = (bool) $payload['status'];
        $metadata = $payload['metadata'];
        $font_faces = [];
        add_filter('wp_check_filetype_and_ext', static fn($data, $file, $filename, $mimes) => Upload::disable_real_mime_check($data, $file, $filename, $mimes), 10, 4);
        add_filter('upload_mimes', static fn($mime_types) => Upload::upload_mimes($mime_types, \true), 1000001);
        $media = new MediaImportService();
        try {
            $oldAttachmentIds = MediaImportService::attachmentIds(FontCodec::decode((string) $existing->font_faces));
            $this->google_fonts_update_filter($metadata, $font_faces, $media);
            $newAttachmentIds = MediaImportService::attachmentIds($font_faces);
            $repository->update($id, ['title' => $title, 'status' => $status, 'metadata' => FontCodec::encode($metadata), 'font_faces' => FontCodec::encode($font_faces)], ['%s', '%d', '%s', '%s']);
            $media->commit();
            MediaImportService::deleteAttachments(array_diff($oldAttachmentIds, $newAttachmentIds));
        } catch (\Throwable $throwable) {
            $media->rollback();
            return $this->operation_error($throwable, __('Unable to update the Google font.', 'jooosi-fon'));
        }
        FontEvents::dispatch('google_fonts_update', $id);
        return new WP_REST_Response(['id' => $id], 200, []);
    }
    private function export(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $params = $wprestRequest->get_json_params();
        $items = $params['items'];
        if (!is_array($items) || $items === []) {
            return new WP_REST_Response(['message' => 'No items to export'], 400, []);
        }
        $is_bundled = Config::get('misc.export_bundle_binary', \false);
        $placeholder = implode(',', array_fill(0, count($items), '%d'));
        $sql = "\n            SELECT * FROM {$wpdb->prefix}jooosi_fon_fonts\n            WHERE id IN ({$placeholder})\n        ";
        $sql = $wpdb->prepare($sql, $items);
        $rows = $wpdb->get_results($sql);
        $items = [];
        $bundledBytes = 0;
        foreach ($rows as $row) {
            $font_faces = FontCodec::decode((string) $row->font_faces);
            $metadata = FontCodec::decode((string) $row->metadata);
            if ($row->type === 'adobe-fonts') {
                continue;
            } elseif ($row->type === 'custom') {
                foreach ($font_faces as $i => $font_face) {
                    foreach ($font_face->files as $j => $file) {
                        // bundle binary and encode it to base64 if enabled
                        if ($is_bundled) {
                            $file_path = get_attached_file($file->attachment_id);
                            if ($file_path) {
                                $fileSize = filesize($file_path);
                                if (!is_int($fileSize) || $fileSize < 1) {
                                    return new WP_REST_Response(['message' => __('A font attachment is missing or unreadable.', 'jooosi-fon')], 409);
                                }
                                $bundledBytes += $fileSize;
                                if ($bundledBytes > 50 * \MB_IN_BYTES) {
                                    return new WP_REST_Response(['message' => __('The bundled export exceeds the 50 MB limit. Export fewer fonts or disable bundled binaries.', 'jooosi-fon')], 413);
                                }
                                $binary = file_get_contents($file_path);
                                if (!is_string($binary)) {
                                    return new WP_REST_Response(['message' => __('A font attachment could not be read.', 'jooosi-fon')], 409);
                                }
                                $font_faces[$i]->files[$j]->binary = base64_encode($binary);
                            } else {
                                return new WP_REST_Response(['message' => __('A font attachment is missing or unreadable.', 'jooosi-fon')], 409);
                            }
                            unset($font_faces[$i]->files[$j]->attachment_url);
                        } else {
                            $attachment_url = wp_get_attachment_url($file->attachment_id);
                            if ($attachment_url) {
                                $parsed = parse_url($attachment_url);
                                $font_faces[$i]->files[$j]->attachment_url = $parsed['path'];
                            }
                        }
                        unset($font_faces[$i]->files[$j]->attachment_id);
                    }
                }
            } elseif ($row->type === 'google-fonts') {
                // minimize metadata
                $font_faces = [];
                if (property_exists($metadata, 'google_fonts')) {
                    foreach ($metadata->google_fonts->font_files as $i => $font_file) {
                        if (property_exists($font_file, 'file')) {
                            unset($metadata->google_fonts->font_files[$i]->file);
                        }
                    }
                    foreach ($metadata->google_fonts->font_faces as $i => $font_face) {
                        if (property_exists($font_face, 'attached_font_files')) {
                            unset($metadata->google_fonts->font_faces[$i]->attached_font_files);
                        }
                    }
                }
            }
            $item = ['type' => $row->type, 'title' => $row->title, 'slug' => $row->slug, 'family' => $row->family];
            $item['font_faces'] = base64_encode(json_encode($font_faces, \JSON_THROW_ON_ERROR));
            $item['metadata'] = base64_encode(json_encode($metadata, \JSON_THROW_ON_ERROR));
            $items[] = $item;
        }
        $data = ['module_id' => JOOOSI_FON::WP_OPTION, 'version' => JOOOSI_FON::VERSION, 'export_time' => time(), 'site_url' => site_url(), 'is_bundled' => $is_bundled, 'items' => $items];
        return new WP_REST_Response(['data' => $data], 200);
    }
    private function import(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        $params = $wprestRequest->get_json_params();
        $site_url = $params['site_url'];
        $is_bundled = rest_sanitize_boolean($params['is_bundled']);
        $item = $params['item'];
        $type = $item['type'];
        $title = sanitize_text_field($item['title']);
        $slug = Common::random_slug(10);
        $family = sanitize_text_field($item['family']);
        $status = \true;
        try {
            $encodedFontFaces = base64_decode($item['font_faces'], \true);
            $encodedMetadata = base64_decode($item['metadata'], \true);
            if (!is_string($encodedFontFaces) || !is_string($encodedMetadata)) {
                throw new \UnexpectedValueException('The import payload is not valid base64.');
            }
            $font_faces = json_decode($encodedFontFaces, \false, 512, \JSON_THROW_ON_ERROR);
            $metadata = json_decode($encodedMetadata, \false, 512, \JSON_THROW_ON_ERROR);
        } catch (\Throwable $throwable) {
            return $this->operation_error($throwable, __('The font import payload is invalid.', 'jooosi-fon'), 400);
        }
        add_filter('wp_check_filetype_and_ext', static fn($data, $file, $filename, $mimes) => Upload::disable_real_mime_check($data, $file, $filename, $mimes), 10, 4);
        add_filter('upload_mimes', static fn($mime_types) => Upload::upload_mimes($mime_types, \true), 1000001);
        $media = new MediaImportService();
        if ($item['type'] === 'adobe-fonts') {
            return new WP_REST_Response(['message' => 'Adobe Fonts is not importable'], 400, []);
        } elseif ($item['type'] === 'google-fonts') {
            $font_faces = json_decode($encodedFontFaces, \true, 512, \JSON_THROW_ON_ERROR);
            $metadata = json_decode($encodedMetadata, \true, 512, \JSON_THROW_ON_ERROR);
            try {
                $this->google_fonts_update_filter($metadata, $font_faces, $media);
            } catch (\Throwable $throwable) {
                $media->rollback();
                return $this->operation_error($throwable, __('Unable to download the Google font files.', 'jooosi-fon'));
            }
        } elseif ($item['type'] === 'custom') {
            try {
                $sourceHost = (string) wp_parse_url($site_url, \PHP_URL_HOST);
                foreach ($font_faces as $i => $font_face) {
                    foreach ($font_face->files as $j => $file) {
                        // if not first-hand
                        $font_faces[$i]->files[$j]->name = preg_replace('#\-[\_\-a-zA-Z0-9]{5}\-\d{10}$#', '', $file->name);
                        // if explicit using a Google Fonts file
                        $font_faces[$i]->files[$j]->name = preg_replace('#\-\d{10}\-(woff2|woff|ttf)$#', '', $file->name);
                        $file_name = sanitize_title_with_dashes(sprintf('%s-%s-%s', $font_faces[$i]->files[$j]->name, Common::random_slug(5), time())) . '.' . $file->extension;
                        if ($is_bundled) {
                            $binary = base64_decode((string) ($file->binary ?? ''), \true);
                            if (!is_string($binary) || strlen($binary) > 20 * \MB_IN_BYTES) {
                                throw new \UnexpectedValueException('The bundled font file is invalid or exceeds 20 MB.');
                            }
                            $attachment = $media->binary($binary, $file_name, (string) $file->extension);
                            unset($font_faces[$i]->files[$j]->binary);
                        } else {
                            $attachmentPath = (string) ($file->attachment_url ?? '');
                            if ($attachmentPath === '' || $attachmentPath[0] !== '/' || wp_parse_url($attachmentPath, \PHP_URL_HOST) !== null) {
                                throw new \UnexpectedValueException('The imported attachment path is invalid.');
                            }
                            $attachment = $media->remote(untrailingslashit($site_url) . $attachmentPath, $file_name, (string) $file->extension, [$sourceHost]);
                        }
                        foreach ($attachment as $key => $value) {
                            $font_faces[$i]->files[$j]->{$key} = $value;
                        }
                    }
                }
            } catch (\Throwable $throwable) {
                $media->rollback();
                return $this->operation_error($throwable, __('Unable to import the custom font files.', 'jooosi-fon'));
            }
        } else {
            return new WP_REST_Response(['message' => 'Invalid item type'], 400, []);
        }
        try {
            $repository = new FontRepository();
            $id = $repository->insert(['type' => $type, 'title' => $title, 'slug' => $slug, 'family' => $family, 'status' => $status, 'metadata' => FontCodec::encode($metadata), 'font_faces' => FontCodec::encode($font_faces)], ['%s', '%s', '%s', '%s', '%d', '%s', '%s']);
            $media->commit();
        } catch (\Throwable $throwable) {
            $media->rollback();
            return $this->operation_error($throwable, __('Unable to create the imported font.', 'jooosi-fon'));
        }
        FontEvents::dispatch('import', $id);
        return new WP_REST_Response(['id' => $id], 200, []);
    }
    private function google_fonts_update_filter(&$metadata, &$font_faces, MediaImportService $media): void
    {
        $m_font_faces = $metadata['google_fonts']['font_faces'];
        $m_font_files = $metadata['google_fonts']['font_files'];
        foreach ($m_font_faces as $k => $m_face) {
            $metadata['google_fonts']['font_faces'][$k]['attached_font_files'] = [];
            if (!$m_face['isEnabled']) {
                continue;
            }
            if ($metadata['google_fonts']['variable']) {
                if ($m_face['weight'] !== 0) {
                    continue;
                }
                $all_filtered_m_font_files = GoogleFontFileSelector::variableFiles($m_font_files, $m_face, $metadata['google_fonts']['subsets'], $metadata['google_fonts']['formats']);
                foreach ($all_filtered_m_font_files as $filtered_m_font_file) {
                    $wght = array_filter($metadata['google_fonts']['font_data']['axes'], static fn($a) => $a['tag'] === 'wght');
                    $wdth = array_filter($metadata['google_fonts']['font_data']['axes'], static fn($a) => $a['tag'] === 'wdth');
                    $slnt = array_filter($metadata['google_fonts']['font_data']['axes'], static fn($a) => $a['tag'] === 'slnt');
                    $wdth = array_values($wdth);
                    $wght = array_values($wght);
                    $slnt = array_values($slnt);
                    $font_face = ['id' => Common::random_slug(10), 'weight' => $wght !== [] ? sprintf('%s %s', $wght[0]['min'], $wght[0]['max']) : '400', 'width' => $wdth !== [] ? sprintf('%s%% %s%%', $wdth[0]['min'], $wdth[0]['max']) : '100%', 'style' => $slnt !== [] ? sprintf('oblique %sdeg %sdeg', $slnt[0]['max'] * -1, $slnt[0]['min'] * -1) : $m_face['style'], 'display' => $m_face['display'], 'selector' => $m_face['selector'], 'comment' => $m_face['comment'], 'preload' => $m_face['preload']];
                    if (array_key_exists('file', $filtered_m_font_file)) {
                        $file = $filtered_m_font_file['file'];
                    } else {
                        $file_name = sanitize_title_with_dashes(sprintf(
                            'google-fonts-%s-%s-%s-var-%s-%s',
                            $metadata['google_fonts']['font_data']['slug'],
                            // family
                            $metadata['google_fonts']['font_data']['version'] ?? 'latest',
                            implode('_', $filtered_m_font_file['subsets']),
                            Common::random_slug(5),
                            time()
                        )) . '.' . $filtered_m_font_file['format'];
                        $file = $media->remote($filtered_m_font_file['url'], $file_name, $filtered_m_font_file['format'], ['fonts.gstatic.com']);
                        $metadata['google_fonts']['font_files'] = array_map(static fn($f) => $f['uid'] === $filtered_m_font_file['uid'] ? array_merge($f, ['file' => $file]) : $f, $metadata['google_fonts']['font_files']);
                    }
                    $metadata['google_fonts']['font_faces'][$k]['attached_font_files'][] = $filtered_m_font_file['uid'];
                    $font_face['files'] = [$file];
                    $font_face['unicodeRange'] = $filtered_m_font_file['unicodeRange'] ?? '';
                    $font_faces[] = $font_face;
                }
            } else {
                if ($m_face['weight'] === 0) {
                    continue;
                }
                $font_face = ['id' => Common::random_slug(10), 'weight' => $m_face['weight'], 'width' => $m_face['width'] ?: '100%', 'style' => $m_face['style'], 'display' => $m_face['display'], 'selector' => $m_face['selector'], 'comment' => $m_face['comment'], 'unicodeRange' => '', 'preload' => $m_face['preload']];
                $files = [];
                $filtered_m_font_files = array_filter($m_font_files, static fn($f) => $f['weight'] === $m_face['weight'] && $f['style'] === $m_face['style'] && array_diff($metadata['google_fonts']['subsets'], $f['subsets']) === array_diff($f['subsets'], $metadata['google_fonts']['subsets']) && in_array($f['format'], $metadata['google_fonts']['formats'], \true));
                $format_precedence = ['woff2' => 1, 'woff' => 2, 'ttf' => 3, 'otf' => 4, 'eot' => 5];
                usort($filtered_m_font_files, static fn($a, $b) => $format_precedence[$a['format']] <=> $format_precedence[$b['format']]);
                foreach ($filtered_m_font_files as $filtered_m_font_file) {
                    if (array_key_exists('file', $filtered_m_font_file)) {
                        $file = $filtered_m_font_file['file'];
                    } else {
                        $file_name = sanitize_title_with_dashes(sprintf(
                            'google-fonts-%s-%s-%s-%s-%s-%s',
                            $metadata['google_fonts']['font_data']['slug'],
                            // family
                            $metadata['google_fonts']['font_data']['version'] ?? 'latest',
                            implode('-', $metadata['google_fonts']['subsets']),
                            $filtered_m_font_file['weight'],
                            $filtered_m_font_file['style'],
                            time()
                        )) . '.' . $filtered_m_font_file['format'];
                        $file = $media->remote($filtered_m_font_file['url'], $file_name, $filtered_m_font_file['format'], ['fonts.gstatic.com']);
                        $metadata['google_fonts']['font_files'] = array_map(static fn($f) => $f['uid'] === $filtered_m_font_file['uid'] ? array_merge($f, ['file' => $file]) : $f, $metadata['google_fonts']['font_files']);
                    }
                    $metadata['google_fonts']['font_faces'][$k]['attached_font_files'][] = $filtered_m_font_file['uid'];
                    $files[] = $file;
                }
                $font_face['files'] = $files;
                $font_faces[] = $font_face;
            }
        }
    }
    private function update_google_fonts_metadata()
    {
        $file_path = Cache::get_cache_path('webfonts.json');
        // The bundled and cached catalogs remain available while the canonical repository is being migrated.
        $cdn_url = apply_filters('f!jooosi/fon/font:google_fonts.metadata.file_url', 'https://cdn.jsdelivr.net/gh/jooosi-project/jooosi-fon@master/google-fonts.json');
        $cdn_url = apply_filters_deprecated('f!yabe/webfont/font:google_fonts.metadata.file_url', [$cdn_url], '2.1.0', 'f!jooosi/fon/font:google_fonts.metadata.file_url');
        if (!wp_http_validate_url($cdn_url)) {
            throw new \Exception(__('The metadata URL is not safe', 'jooosi-fon'));
        }
        $temp_file = download_url($cdn_url, 30);
        if (is_wp_error($temp_file)) {
            throw new \Exception(__('Failed to download metadata file', 'jooosi-fon'));
        }
        $staging_file = $file_path . '.tmp-' . wp_generate_uuid4();
        try {
            $contents = file_get_contents($temp_file);
            if (!is_string($contents)) {
                throw new \RuntimeException('The downloaded metadata file is not readable.');
            }
            json_decode($contents, \true, 512, \JSON_THROW_ON_ERROR);
            Common::save_file($contents, $staging_file);
            if (!@rename($staging_file, $file_path)) {
                throw new \RuntimeException('The metadata cache could not be replaced atomically.');
            }
            delete_transient('jooosi_fon_google_fonts_catalog_stats');
        } finally {
            if (is_string($temp_file) && file_exists($temp_file)) {
                unlink($temp_file);
            }
            if (file_exists($staging_file)) {
                unlink($staging_file);
            }
        }
    }
    private function google_fonts_metadata(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        $file_path = Cache::get_cache_path('webfonts.json');
        $query = $wprestRequest->get_query_params();
        $force_update = filter_var($query['force'] ?? \false, \FILTER_VALIDATE_BOOLEAN);
        $force_cdn = apply_filters('f!jooosi/fon/font:google_fonts.metadata.force_cdn', \false);
        $force_cdn = apply_filters_deprecated('f!yabe/webfont/font:google_fonts.metadata.force_cdn', [$force_cdn], '2.1.0', 'f!jooosi/fon/font:google_fonts.metadata.force_cdn');
        if ($force_update || $force_cdn) {
            try {
                $this->update_google_fonts_metadata();
            } catch (\Exception $e) {
                return new WP_REST_Response(['message' => $e->getMessage()], 500, []);
            }
            $metadata = json_decode(file_get_contents($file_path), null, 512, \JSON_THROW_ON_ERROR);
            return new WP_REST_Response(['fonts' => $metadata], 200, []);
        }
        if (!file_exists($file_path)) {
            $payload = file_get_contents(dirname(JOOOSI_FON::FILE) . '/google-fonts.json');
            Common::save_file($payload, $file_path);
        }
        $enable_update = apply_filters('f!jooosi/fon/font:google_fonts.metadata.enable_update', \true);
        $enable_update = apply_filters_deprecated('f!yabe/webfont/font:google_fonts.metadata.enable_update', [$enable_update], '2.1.0', 'f!jooosi/fon/font:google_fonts.metadata.enable_update');
        if ($enable_update !== \false) {
            if (filemtime($file_path) < strtotime('-1 day')) {
                try {
                    $this->update_google_fonts_metadata();
                } catch (\Exception $e) {
                    // Keep serving the cached catalog when an automatic refresh is unavailable.
                }
            }
        }
        $metadata = json_decode(file_get_contents($file_path), null, 512, \JSON_THROW_ON_ERROR);
        return new WP_REST_Response(['fonts' => $metadata], 200, []);
    }
    private function google_fonts_webfonts(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        $url_params = $wprestRequest->get_url_params();
        $query = $wprestRequest->get_query_params();
        $slug = $url_params['slug'] ?? '';
        if (trim($slug) === '') {
            return new WP_REST_Response(['message' => __('Slug is required', 'jooosi-fon')], 400, []);
        }
        // retrieve metadata
        $file_path = Cache::get_cache_path('webfonts.json');
        if (!file_exists($file_path)) {
            $payload = file_get_contents(dirname(JOOOSI_FON::FILE) . '/google-fonts.json');
            Common::save_file($payload, $file_path);
        }
        $enable_update = apply_filters('f!jooosi/fon/font:google_fonts.metadata.enable_update', \true);
        $enable_update = apply_filters_deprecated('f!yabe/webfont/font:google_fonts.metadata.enable_update', [$enable_update], '2.1.0', 'f!jooosi/fon/font:google_fonts.metadata.enable_update');
        if ($enable_update !== \false) {
            if (filemtime($file_path) < strtotime('-1 day')) {
                try {
                    $this->update_google_fonts_metadata();
                } catch (\Exception $e) {
                    // Keep serving the cached catalog when an automatic refresh is unavailable.
                }
            }
        }
        $metadata = json_decode(file_get_contents($file_path), null, 512, \JSON_THROW_ON_ERROR);
        // search for the font by slug
        $font = array_filter($metadata, static fn($font) => $font->slug === $slug);
        if (empty($font)) {
            return new WP_REST_Response(['message' => __('Font not found', 'jooosi-fon')], 404, []);
        }
        $font = array_values($font)[0];
        // get the first element of the array
        $subsets = $query['subsets'] ?? '';
        if (!$subsets) {
            $subsets = in_array('latin', $font->subsets, \true) ? ['latin'] : [$font->subsets[0]];
        } else {
            $querySubsets = explode(',', $subsets);
            $subsets = array_intersect($font->subsets, $querySubsets);
        }
        $subsets = array_unique($subsets);
        sort($subsets);
        if ($subsets === []) {
            return new WP_REST_Response(['message' => __('None of the requested subsets are available for this font.', 'jooosi-fon')], 400, []);
        }
        $cache_key = 'jooosi_fon_google_fonts_files_' . md5($slug . ':' . implode(',', $subsets));
        $cachedFontFiles = get_transient($cache_key);
        if ($cachedFontFiles !== \false) {
            return new WP_REST_Response(['font' => $font, 'files' => array_values($cachedFontFiles)], 200, []);
        }
        $variantKeys = $font->variants;
        $fontFormats = ['woff2', 'woff', 'ttf'];
        try {
            foreach ($variantKeys as $variantKey) {
                foreach ($fontFormats as $fontFormat) {
                    $fontUrl = $this->fetch_google_font_file($font, $fontFormat, $variantKey, $subsets);
                    $newFontFile = new stdClass();
                    $newFontFile->format = $fontFormat;
                    $newFontFile->weight = intval($variantKey);
                    switch (preg_replace('/\d/', '', (string) $variantKey)) {
                        case 'i':
                            $newFontFile->style = 'italic';
                            break;
                        case 'o':
                            $newFontFile->style = 'oblique';
                            break;
                        default:
                            $newFontFile->style = 'normal';
                    }
                    $newFontFile->subsets = $subsets;
                    $newFontFile->url = $fontUrl;
                    $font->files[] = $newFontFile;
                }
            }
            if (!empty($font->axes)) {
                $italics = [];
                if (count(array_filter($variantKeys, static fn(string $variantKey) => preg_replace('/\d/', '', $variantKey) === '')) > 0) {
                    array_push($italics, 0);
                }
                if (count(array_filter($variantKeys, static fn(string $variantKey) => preg_replace('/\d/', '', $variantKey) === 'i')) > 0) {
                    array_push($italics, 1);
                }
                foreach ($italics as $italic) {
                    switch ($italic) {
                        case 0:
                            $style = 'normal';
                            break;
                        case 1:
                            $style = 'italic';
                            break;
                        default:
                            $style = 'normal';
                    }
                    $filteredFontFilesVariable = array_filter($font->files, function ($fontFile) use ($style, $subsets) {
                        return $fontFile->format === 'woff2' && $fontFile->weight === 0 && $fontFile->style === $style && array_intersect($fontFile->subsets, $subsets) !== [];
                    });
                    if (count($filteredFontFilesVariable) < count($subsets)) {
                        $fetchedVariableFonts = $this->fetch_google_font_variable_file($font, $italic, $font->axes);
                        foreach ($fetchedVariableFonts as $fetchedVariableFont) {
                            $existFilteredFontFilesVariable = array_filter($filteredFontFilesVariable, function ($fontFile) use ($fetchedVariableFont, $style) {
                                return $fontFile->format === 'woff2' && $fontFile->weight === 0 && $fontFile->style === $style && in_array($fetchedVariableFont['subset'], $fontFile->subsets, \true);
                            });
                            if (empty($existFilteredFontFilesVariable)) {
                                $newFontFile = new stdClass();
                                $newFontFile->format = 'woff2';
                                $newFontFile->weight = 0;
                                $newFontFile->style = $style;
                                $newFontFile->subsets = [$fetchedVariableFont['subset']];
                                $newFontFile->url = $fetchedVariableFont['url'];
                                $newFontFile->unicodeRange = $fetchedVariableFont['unicodeRange'];
                                $font->files[] = $newFontFile;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $throwable) {
            return $this->operation_error($throwable, __('Unable to fetch the Google font stylesheets.', 'jooosi-fon'), 502);
        }
        $filteredFontFiles = array_filter($font->files, static function ($fontFile) use ($subsets) {
            $fontFileSubsets = array_unique($fontFile->subsets);
            sort($fontFileSubsets);
            $variableNumberedSubsets = \false;
            if ($fontFile->weight === 0) {
                foreach ($fontFile->subsets as $ffSubset) {
                    if (preg_match('/\d/', $ffSubset)) {
                        $variableNumberedSubsets = \true;
                        break;
                    }
                }
            }
            return $fontFileSubsets === $subsets || $variableNumberedSubsets || $fontFile->weight === 0 && array_intersect($fontFileSubsets, $subsets) !== [];
        });
        // Cache the result for 1 day
        set_transient($cache_key, $filteredFontFiles, \DAY_IN_SECONDS);
        unset($font->files);
        return new WP_REST_Response(['font' => $font, 'files' => array_values($filteredFontFiles)], 200, []);
    }
    private function fetch_google_font_file($font, string $format, string $variant, array $subsets): string
    {
        switch ($format) {
            case 'woff2':
                $userAgent = JOOOSI_FON::USER_AGENTS['WOFF2'];
                break;
            case 'woff':
                $userAgent = JOOOSI_FON::USER_AGENTS['WOFF'];
                break;
            case 'ttf':
                $userAgent = JOOOSI_FON::USER_AGENTS['TTF'];
                break;
            default:
                $userAgent = apply_filters('f!jooosi/fon/font:google_fonts.fetch.user_agent.default', JOOOSI_FON::USER_AGENTS['CURRENT']);
                $userAgent = apply_filters_deprecated('f!yabe/webfont/font:google_fonts.fetch.user_agent.default', [$userAgent], '2.1.0', 'f!jooosi/fon/font:google_fonts.fetch.user_agent.default');
        }
        $family = str_replace(' ', '+', $font->family);
        $url = sprintf('https://fonts.googleapis.com/css?family=%s:%s&subset=%s', $family, $variant, implode(',', $subsets));
        $body = (new GoogleFontsClient())->fetchCss($url, $userAgent);
        $cssDocument = (new Parser($body))->parse();
        $contents = $cssDocument->getContents();
        if ($contents === [] || $contents[0]->getRules('src') === []) {
            throw new \RuntimeException(__('Google Fonts returned an invalid stylesheet', 'jooosi-fon'));
        }
        $fontUrl = $contents[0]->getRules('src')[0]->getValue()->getListComponents()[0]->getURL()->getString();
        return $fontUrl;
    }
    private function negative_case(string $string): string
    {
        $arr = str_split($string);
        foreach ($arr as $key => $char) {
            $arr[$key] = ctype_upper($char) ? strtolower($char) : strtoupper($char);
        }
        return implode('', $arr);
    }
    private function operation_error(\Throwable $throwable, string $message, int $status = 500): WP_REST_Response
    {
        do_action('a!jooosi/fon/api:error', $throwable, $message, $status);
        if (defined('WP_DEBUG') && \WP_DEBUG) {
            $message = sprintf('%s %s', $message, $throwable->getMessage());
        }
        return new WP_REST_Response(['message' => $message], $status);
    }
    /**
     * @see https://developers.google.com/fonts/docs/css2#api_url_specification
     */
    private function fetch_google_font_variable_file($font, int $italic, array $axes): array
    {
        $axis_tag_list = [];
        $axis_tuple_list = [];
        $family = str_replace(' ', '+', $font->family);
        // convert axes to array
        $axes = array_map(static fn($axis) => ['tag' => $axis->tag, 'min' => $axis->min ?? null, 'max' => $axis->max ?? null, 'defaultValue' => $axis->defaultValue ?? null], $axes);
        // add ital axis
        $axes[] = ['tag' => 'ital', 'defaultValue' => $italic];
        // sort axes by "tag" alphabetically (e.g. a,b,c,A,B,C)
        usort($axes, fn(array $a, array $b) => strcmp((string) $this->negative_case($a['tag']), (string) $this->negative_case($b['tag'])));
        foreach ($axes as $a) {
            $axis_tag_list[] = $a['tag'];
            if (array_key_exists('min', $a) && array_key_exists('max', $a)) {
                $axis_tuple_list[] = sprintf('%s..%s', $a['min'], $a['max']);
            } else {
                $axis_tuple_list[] = sprintf('%s', $a['defaultValue']);
            }
        }
        $url = sprintf('https://fonts.googleapis.com/css2?family=%s:%s@%s', $family, implode(',', $axis_tag_list), implode(',', $axis_tuple_list));
        $parsedFiles = [];
        $userAgent = apply_filters('f!jooosi/fon/font:google_fonts.fetch.user_agent.default', JOOOSI_FON::USER_AGENTS['CURRENT']);
        $userAgent = apply_filters_deprecated('f!yabe/webfont/font:google_fonts.fetch.user_agent.default', [$userAgent], '2.1.0', 'f!jooosi/fon/font:google_fonts.fetch.user_agent.default');
        $body = (new GoogleFontsClient())->fetchCss($url, $userAgent);
        // parse the css
        $cssDocument = (new Parser($body))->parse();
        // match all comment /* comment */
        preg_match_all('/\/\*(.*?)\*\//s', $body, $parsedComments);
        $comments = array_map(static fn(string $comment) => trim($comment), $parsedComments[1]);
        $cssContents = $cssDocument->getContents();
        for ($i = 0; $i < count($cssContents); $i++) {
            if (!isset($comments[$i]) || $cssContents[$i]->getRules('src') === [] || $cssContents[$i]->getRules('unicode-range') === []) {
                continue;
            }
            $subset = $comments[$i];
            $url = $cssContents[$i]->getRules('src')[0]->getValue()->getListComponents()[0]->getURL()->getString();
            $unicodeRangeRuleSet = $cssContents[$i]->getRules('unicode-range')[0]->getValue();
            $unicodeRange = $unicodeRangeRuleSet instanceof \JooosiFonDeps\Sabberworm\CSS\Value\RuleValueList ? implode(', ', $unicodeRangeRuleSet->getListComponents()) : $unicodeRangeRuleSet;
            $parsedFiles[] = ['subset' => $subset, 'url' => $url, 'unicodeRange' => $unicodeRange];
        }
        return $parsedFiles;
    }
}
