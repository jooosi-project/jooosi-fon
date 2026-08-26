<?php

declare (strict_types=1);
namespace JooosiFon\Api\Migrations;

use JooosiFonDeps\JOOOSI_FON;
use JooosiFon\Api\AbstractApi;
use JooosiFon\Api\ApiInterface;
use JooosiFon\Api\Migrations\Support\SourceFontNormalizer;
use JooosiFon\Api\Support\FontCodec;
use JooosiFon\Api\Support\FontEvents;
use JooosiFon\Api\Support\FontRepository;
use JooosiFon\Api\Support\RequestValidator;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
final class CustomAdobeFonts extends AbstractApi implements ApiInterface
{
    private const SOURCE_OPTION = 'custom-typekit-fonts';
    public function get_prefix(): string
    {
        return 'migrations/custom-adobe-fonts';
    }
    public function register_custom_endpoints(): void
    {
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/import-fonts', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(WP_REST_Request $request): WP_REST_Response => $this->import_fonts($request), 'permission_callback' => fn(WP_REST_Request $request): bool => $this->permission_callback($request)]);
        register_rest_route(self::API_NAMESPACE, $this->get_prefix() . '/clean-up', ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(WP_REST_Request $request): WP_REST_Response => $this->clean_up($request), 'permission_callback' => fn(WP_REST_Request $request): bool => $this->permission_callback($request), 'args' => ['confirm' => ['required' => \true, 'sanitize_callback' => 'rest_sanitize_boolean', 'validate_callback' => [RequestValidator::class, 'confirmed']]]]);
    }
    public function import_fonts(WP_REST_Request $request): WP_REST_Response
    {
        if (!defined('CUSTOM_TYPEKIT_FONTS_VER')) {
            return new WP_REST_Response(['message' => __('Custom Adobe Fonts is not activated.', 'jooosi-fon')], 404);
        }
        $source = get_option(self::SOURCE_OPTION, null);
        if (!is_array($source)) {
            return new WP_REST_Response(['message' => __('Custom Adobe Fonts records could not be read.', 'jooosi-fon')], 500);
        }
        $kit = SourceFontNormalizer::customAdobeKit($source);
        if (!RequestValidator::projectId($kit['id']) || !RequestValidator::adobeKit($kit) || $kit['families'] === []) {
            return new WP_REST_Response(['message' => __('The Custom Adobe Fonts project has no usable published families.', 'jooosi-fon')], 422);
        }
        $options = $this->jooosi_options();
        if ($options === null) {
            return new WP_REST_Response(['message' => __('Jooosi Fon settings are invalid, so the Adobe project was not changed.', 'jooosi-fon')], 500);
        }
        $adobeOptions = isset($options['adobe_fonts']) && is_array($options['adobe_fonts']) ? $options['adobe_fonts'] : [];
        $currentProjectId = trim((string) ($adobeOptions['project_id'] ?? ''));
        if ($currentProjectId !== '' && $currentProjectId !== $kit['id']) {
            return new WP_REST_Response(['message' => __('Jooosi Fon is already connected to a different Adobe Fonts project. Disconnect it before migrating this source.', 'jooosi-fon')], 409);
        }
        $options['adobe_fonts'] = ['project_id' => $kit['id'], 'kit' => $kit];
        $repository = new FontRepository();
        $insertedIds = [];
        try {
            $insertedIds = $repository->transaction(function (FontRepository $repository) use ($kit, $options): array {
                $repository->deleteByType('adobe-fonts');
                $ids = [];
                foreach ($kit['families'] as $family) {
                    $ids[] = $repository->insert(['status' => 1, 'type' => 'adobe-fonts', 'title' => sanitize_text_field($family['name']), 'slug' => sanitize_key($family['id']), 'family' => sanitize_text_field($family['slug']), 'metadata' => FontCodec::encode($family), 'font_faces' => FontCodec::encode([])], ['%d', '%s', '%s', '%s', '%s', '%s', '%s']);
                }
                $this->store_jooosi_options($options);
                return $ids;
            });
        } catch (\Throwable $throwable) {
            wp_cache_delete(JOOOSI_FON::WP_OPTION . '_options', 'options');
            return new WP_REST_Response(['message' => __('The Custom Adobe Fonts migration failed; the previous Adobe project and families were preserved.', 'jooosi-fon')], 500);
        }
        FontEvents::dispatch('migration', ['source' => 'custom-adobe-fonts', 'ids' => $insertedIds], \true);
        return new WP_REST_Response(['message' => __('Custom Adobe Fonts project and families imported. Source settings were preserved.', 'jooosi-fon'), 'imported' => count($insertedIds), 'source_preserved' => \true]);
    }
    public function clean_up(WP_REST_Request $request): WP_REST_Response
    {
        if (!defined('CUSTOM_TYPEKIT_FONTS_VER')) {
            return new WP_REST_Response(['message' => __('Custom Adobe Fonts is not activated.', 'jooosi-fon')], 404);
        }
        $source = get_option(self::SOURCE_OPTION, null);
        if ($source !== null && !delete_option(self::SOURCE_OPTION)) {
            return new WP_REST_Response(['message' => __('Custom Adobe Fonts cleanup failed and its source settings were preserved.', 'jooosi-fon')], 500);
        }
        FontEvents::dispatch('clean_up', ['source' => 'custom-adobe-fonts'], \true);
        return new WP_REST_Response(['message' => __('Custom Adobe Fonts source settings were removed. The migrated Jooosi Fon Adobe project remains connected.', 'jooosi-fon'), 'cleaned' => is_array($source) ? 1 : 0]);
    }
    /**
     * @return array<string, mixed>|null
     */
    private function jooosi_options(): ?array
    {
        $stored = get_option(JOOOSI_FON::WP_OPTION . '_options', '{}');
        if (!is_string($stored)) {
            return null;
        }
        try {
            $options = json_decode($stored, \true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return null;
        }
        return is_array($options) ? $options : null;
    }
    /**
     * @param array<string, mixed> $options
     */
    private function store_jooosi_options(array $options): void
    {
        $encoded = json_encode($options, \JSON_THROW_ON_ERROR);
        $option = JOOOSI_FON::WP_OPTION . '_options';
        if (!update_option($option, $encoded) && get_option($option) !== $encoded) {
            throw new \RuntimeException('The Jooosi Fon Adobe settings could not be stored.');
        }
    }
}
