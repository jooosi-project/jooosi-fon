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
namespace JooosiFon\Utils;

use JooosiFonDeps\JOOOSI_FON;
use JooosiFon\Api\Support\FontCodec;
/**
 * Font utility functions for the plugin.
 *
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
class Font
{
    private const CACHE_KEY = 'get_fonts';
    public static function get_fonts(): array
    {
        $fonts = wp_cache_get(self::CACHE_KEY, JOOOSI_FON::WP_OPTION);
        if ($fonts === \false) {
            /** @var wpdb $wpdb */
            global $wpdb;
            $fonts = [];
            $sql = "\n                SELECT * FROM {$wpdb->prefix}jooosi_fon_fonts\n                WHERE status = 1\n                    AND deleted_at IS NULL\n                ORDER BY title ASC\n            ";
            $result = $wpdb->get_results($sql);
            foreach ($result as $row) {
                $f = ['title' => $row->title, 'family' => $row->family, 'type' => $row->type, 'slug' => $row->slug, 'css' => ['slug' => self::slugify($row->family), 'custom_property' => self::css_custom_property($row->family), 'legacy_custom_property' => self::legacy_css_custom_property($row->family), 'variable' => self::css_variable($row->family)], 'variants' => [], 'fallback_family' => null];
                $font_faces = FontCodec::decode((string) $row->font_faces);
                foreach ($font_faces as $font_face) {
                    if (property_exists($font_face, 'isEnabled') && filter_var($font_face->isEnabled, \FILTER_VALIDATE_BOOLEAN) !== \true) {
                        continue;
                    }
                    $f['variants'][] = ['weight' => $font_face->weight, 'style' => $font_face->style];
                }
                $selectorParts = [];
                $metadata = FontCodec::decode((string) $row->metadata);
                // if property selector is exists
                if (property_exists($metadata, 'selector') && $metadata->selector) {
                    $selectorParts = explode('|', $metadata->selector);
                    $selectorParts = array_map('trim', $selectorParts);
                    $selectorParts = array_filter($selectorParts);
                    $f['fallback_family'] = $selectorParts[1] ?? null;
                }
                $fonts[] = $f;
            }
            wp_cache_set(self::CACHE_KEY, $fonts, JOOOSI_FON::WP_OPTION);
        }
        return $fonts;
    }
    /**
     * Invalidate font data consumed by integrations and WordPress theme.json.
     */
    public static function clear_cache(): void
    {
        wp_cache_delete(self::CACHE_KEY, JOOOSI_FON::WP_OPTION);
        if (method_exists(\WP_Theme_JSON_Resolver::class, 'clean_cached_data')) {
            \WP_Theme_JSON_Resolver::clean_cached_data();
        }
    }
    /**
     * @param string $value font family name
     * @return string css custom property wrapped with variable function. e.g. `var(--jf--family-open-sans)` for `Open Sans`
     */
    public static function css_variable(string $value): string
    {
        return sprintf('var(%s)', self::css_custom_property($value));
    }
    /**
     * @param string $value font family name
     * @return string css custom property. e.g. `--jf--family-open-sans` for `Open Sans`
     */
    public static function css_custom_property(string $value): string
    {
        return sprintf('--jf--family-%s', self::slugify($value));
    }
    /**
     * Return the pre-rebrand property name retained as a generated CSS alias.
     *
     * @todo Remove this legacy CSS property alias completely in Jooosi Fon 3.0.0.
     */
    public static function legacy_css_custom_property(string $value): string
    {
        return sprintf('--ywf--family-%s', self::slugify($value));
    }
    /**
     * @param string $value font family name
     * @return string slugified string. e.g. `open-sans` for `Open Sans`
     */
    public static function slugify(string $value): string
    {
        return preg_replace('#[^a-zA-Z0-9\-_]+#', '-', strtolower($value));
    }
}
