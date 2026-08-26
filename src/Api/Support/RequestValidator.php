<?php

declare (strict_types=1);
namespace JooosiFon\Api\Support;

final class RequestValidator
{
    private const FONT_EXTENSION_ALIASES = ['font/woff2' => 'woff2', 'font/woff' => 'woff', 'font/ttf' => 'ttf', 'font/otf' => 'otf', 'font/eot' => 'eot', 'application/font-woff2' => 'woff2', 'application/font-woff' => 'woff', 'application/x-font-woff2' => 'woff2', 'application/x-font-woff' => 'woff', 'application/x-font-ttf' => 'ttf', 'application/x-font-truetype' => 'ttf', 'application/x-font-opentype' => 'otf', 'application/vnd.ms-opentype' => 'otf', 'application/vnd.ms-fontobject' => 'eot', 'x-font-woff2' => 'woff2', 'x-font-woff' => 'woff', 'x-font-ttf' => 'ttf', 'x-font-opentype' => 'otf', 'vnd.ms-opentype' => 'otf', 'vnd.ms-fontobject' => 'eot', 'truetype' => 'ttf', 'opentype' => 'otf', 'embedded-opentype' => 'eot'];
    public static function positiveInteger($value): bool
    {
        return filter_var($value, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== \false;
    }
    public static function pageSize($value): bool
    {
        return filter_var($value, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]) !== \false;
    }
    public static function boolean($value): bool
    {
        return is_bool($value) || in_array($value, [0, 1, '0', '1', 'false', 'true'], \true);
    }
    public static function nonEmptyString($value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
    public static function arrayValue($value): bool
    {
        if (!is_array($value)) {
            return \false;
        }
        $encoded = json_encode($value);
        return is_string($encoded) && strlen($encoded) <= 2 * 1024 * 1024;
    }
    public static function positiveIntegerList($value): bool
    {
        if (!is_array($value) || $value === [] || count($value) > 50) {
            return \false;
        }
        foreach ($value as $item) {
            if (!self::positiveInteger($item)) {
                return \false;
            }
        }
        return \true;
    }
    public static function fontType($value): bool
    {
        return is_string($value) && in_array($value, ['custom', 'google-fonts', 'adobe-fonts'], \true);
    }
    public static function projectId($value): bool
    {
        return is_string($value) && preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $value) === 1;
    }
    public static function subsets($value): bool
    {
        return is_string($value) && ($value === '' || preg_match('/^[a-zA-Z0-9_-]+(?:,[a-zA-Z0-9_-]+)*$/', $value) === 1);
    }
    public static function importItem($value): bool
    {
        return is_array($value) && isset($value['type'], $value['title'], $value['family'], $value['font_faces'], $value['metadata']) && self::fontType($value['type']) && self::nonEmptyString($value['title']) && self::nonEmptyString($value['family']) && is_string($value['font_faces']) && is_string($value['metadata']) && strlen($value['font_faces']) <= 30 * 1024 * 1024 && strlen($value['metadata']) <= 5 * 1024 * 1024;
    }
    public static function googleMetadata($value): bool
    {
        if (!is_array($value) || !isset($value['google_fonts']) || !is_array($value['google_fonts'])) {
            return \false;
        }
        $google = $value['google_fonts'];
        if (!isset($google['font_data'], $google['font_faces'], $google['font_files'], $google['formats'], $google['subsets']) || !is_array($google['font_data']) || !is_array($google['font_faces']) || !is_array($google['font_files']) || !is_array($google['formats']) || !is_array($google['subsets']) || !isset($google['font_data']['family'], $google['font_data']['slug']) || !self::nonEmptyString($google['font_data']['family']) || !self::nonEmptyString($google['font_data']['slug']) || strlen($google['font_data']['family']) > 255 || strlen($google['font_data']['slug']) > 255 || count($google['font_faces']) > 100 || count($google['font_files']) > 500 || count($google['formats']) > 3 || count($google['subsets']) > 50 || $google['formats'] === [] || $google['subsets'] === [] || $google['font_files'] === [] || $google['font_faces'] === []) {
            return \false;
        }
        foreach ($google['formats'] as $format) {
            if (!in_array($format, ['woff2', 'woff', 'ttf'], \true)) {
                return \false;
            }
        }
        foreach ($google['subsets'] as $subset) {
            if (!is_string($subset) || preg_match('/^[a-zA-Z0-9_-]+$/', $subset) !== 1) {
                return \false;
            }
        }
        foreach ($google['font_files'] as $file) {
            if (!is_array($file) || !isset($file['uid'], $file['format'], $file['weight'], $file['style'], $file['subsets'], $file['url']) || !self::nonEmptyString($file['uid']) || strlen($file['uid']) > 255 || !in_array($file['format'], ['woff2', 'woff', 'ttf'], \true) || !is_numeric($file['weight']) || !is_string($file['style']) || !is_array($file['subsets']) || count($file['subsets']) > 50 || !filter_var($file['url'], \FILTER_VALIDATE_URL) || strtolower((string) parse_url($file['url'], \PHP_URL_SCHEME)) !== 'https' || strtolower((string) parse_url($file['url'], \PHP_URL_HOST)) !== 'fonts.gstatic.com') {
                return \false;
            }
            foreach ($file['subsets'] as $subset) {
                if (!is_string($subset) || preg_match('/^[a-zA-Z0-9_-]+$/', $subset) !== 1) {
                    return \false;
                }
            }
            if (array_key_exists('file', $file) && !self::attachmentFile($file['file'])) {
                return \false;
            }
        }
        $hasEnabledFace = \false;
        foreach ($google['font_faces'] as $face) {
            if (!is_array($face) || !array_key_exists('weight', $face) || !array_key_exists('width', $face) || !array_key_exists('style', $face) || !array_key_exists('isEnabled', $face) || !array_key_exists('display', $face) || !array_key_exists('selector', $face) || !array_key_exists('comment', $face) || !array_key_exists('preload', $face) || !is_numeric($face['weight']) || !is_string($face['style']) || !self::boolean($face['isEnabled'])) {
                return \false;
            }
            $hasEnabledFace = $hasEnabledFace || filter_var($face['isEnabled'], \FILTER_VALIDATE_BOOLEAN) === \true;
        }
        if (!empty($google['variable']) && (!isset($google['font_data']['axes']) || !is_array($google['font_data']['axes']))) {
            return \false;
        }
        if (!empty($google['variable'])) {
            if ($google['font_data']['axes'] === [] || count($google['font_data']['axes']) > 20) {
                return \false;
            }
            foreach ($google['font_data']['axes'] as $axis) {
                if (!is_array($axis) || !isset($axis['tag'], $axis['min'], $axis['max']) || !is_string($axis['tag']) || preg_match('/^[a-zA-Z0-9]{1,10}$/', $axis['tag']) !== 1 || !is_numeric($axis['min']) || !is_numeric($axis['max']) || (float) $axis['min'] > (float) $axis['max']) {
                    return \false;
                }
            }
        }
        return $hasEnabledFace;
    }
    public static function fontFaces($value): bool
    {
        if (!is_array($value) || $value === [] || count($value) > 100) {
            return \false;
        }
        foreach ($value as $face) {
            if (!is_array($face) || !isset($face['id'], $face['weight'], $face['style'], $face['files']) || !self::nonEmptyString($face['id']) || !self::fontWeight($face['weight']) || !is_string($face['style']) || array_key_exists('isEnabled', $face) && !self::boolean($face['isEnabled']) || !is_array($face['files']) || count($face['files']) > 10) {
                return \false;
            }
            foreach ($face['files'] as $file) {
                if (!self::attachmentFile($file)) {
                    return \false;
                }
            }
        }
        return \true;
    }
    /**
     * Normalize font file extensions from WordPress MIME subtypes and legacy
     * payloads before they are persisted.
     */
    public static function sanitizeFontFaces($value): array
    {
        if (!is_array($value)) {
            return (array) $value;
        }
        foreach ($value as &$face) {
            if (!is_array($face) || !isset($face['files']) || !is_array($face['files'])) {
                continue;
            }
            foreach ($face['files'] as &$file) {
                if (!is_array($file) || !isset($file['extension'])) {
                    continue;
                }
                $extension = self::fontExtension($file['extension']);
                if ($extension !== null) {
                    $file['extension'] = $extension;
                }
            }
            unset($file);
        }
        unset($face);
        return $value;
    }
    public static function attachmentFile($value): bool
    {
        return is_array($value) && isset($value['attachment_id'], $value['extension']) && self::positiveInteger($value['attachment_id']) && self::fontExtension($value['extension']) !== null;
    }
    private static function fontExtension($value): ?string
    {
        $extension = strtolower(trim((string) $value));
        if (in_array($extension, ['woff2', 'woff', 'ttf', 'otf', 'eot'], \true)) {
            return $extension;
        }
        return self::FONT_EXTENSION_ALIASES[$extension] ?? null;
    }
    public static function fontWeight($value): bool
    {
        if (is_int($value) || is_float($value)) {
            return $value >= 1 && $value <= 1000;
        }
        if (!is_string($value) || preg_match('/^(?:normal|bold|[1-9]\d{0,3})(?:\s+[1-9]\d{0,3})?$/', trim($value)) !== 1) {
            return \false;
        }
        $weights = preg_split('/\s+/', trim($value));
        if ($weights === \false) {
            return \false;
        }
        foreach ($weights as $weight) {
            if (is_numeric($weight) && ((int) $weight < 1 || (int) $weight > 1000)) {
                return \false;
            }
        }
        return \true;
    }
    public static function adobeKit($value): bool
    {
        if (!is_array($value) || !isset($value['families']) || !is_array($value['families'])) {
            return \false;
        }
        foreach ($value['families'] as $family) {
            if (!is_array($family) || !isset($family['id'], $family['name'], $family['slug']) || !self::nonEmptyString($family['id']) || !self::nonEmptyString($family['name']) || !self::nonEmptyString($family['slug'])) {
                return \false;
            }
        }
        return \true;
    }
    public static function confirmed($value): bool
    {
        return filter_var($value, \FILTER_VALIDATE_BOOLEAN) === \true;
    }
}
