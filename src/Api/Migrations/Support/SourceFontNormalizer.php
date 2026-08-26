<?php

declare (strict_types=1);
namespace JooosiFon\Api\Migrations\Support;

/**
 * Converts third-party plugin records into a small, source-agnostic shape.
 * Keeping this work free of WordPress side effects makes the source schemas
 * straightforward to test without booting a complete WordPress installation.
 */
final class SourceFontNormalizer
{
    private const SUPPORTED_EXTENSIONS = ['woff2', 'woff', 'ttf', 'otf', 'eot'];
    /**
     * @param array<string, mixed> $fontData
     * @return array{
     *     display: string,
     *     fallback: string,
     *     skipped: int,
     *     faces: array<int, array{
     *         weight: string,
     *         width: string,
     *         style: string,
     *         files: array<int, array{location: string, extension: string}>
     *     }>
     * }
     */
    public static function brainstormForce(array $fontData): array
    {
        $faces = [];
        $skipped = 0;
        $variations = isset($fontData['variations']) && is_array($fontData['variations']) ? $fontData['variations'] : [];
        foreach ($variations as $variation) {
            if (!is_array($variation)) {
                ++$skipped;
                continue;
            }
            $weightSource = (string) ($variation['font_weight'] ?? '400');
            $files = self::remoteFiles($variation['font_url'] ?? []);
            if ($files === []) {
                ++$skipped;
                continue;
            }
            $faces[] = ['weight' => self::weight($weightSource), 'width' => '100%', 'style' => self::style((string) ($variation['font_style'] ?? ''), $weightSource), 'files' => $files];
        }
        $display = $fontData['font_display'] ?? 'auto';
        $fallback = $fontData['font_fallback'] ?? '';
        return ['display' => self::display(is_string($display) ? $display : 'auto'), 'fallback' => is_string($fallback) ? trim($fallback) : '', 'skipped' => $skipped, 'faces' => $faces];
    }
    /**
     * @param array<string|int, mixed> $fontRows
     * @param array<string|int, mixed> $assignments
     * @return array<string, array{
     *     family: string,
     *     display: string,
     *     selector: string,
     *     faces: array<int, array{
     *         source_key: string,
     *         weight: string,
     *         width: string,
     *         style: string,
     *         files: array<int, array{location: string, extension: string}>
     *     }>
     * }>
     */
    public static function useAnyFont(array $fontRows, array $assignments, string $baseDirectory, string $display): array
    {
        $families = [];
        $rowFamilies = [];
        $baseDirectory = rtrim($baseDirectory, '/\\') . \DIRECTORY_SEPARATOR;
        foreach ($fontRows as $sourceKey => $fontRow) {
            if (!is_array($fontRow)) {
                continue;
            }
            $family = trim((string) ($fontRow['font_name'] ?? ''));
            $fontPath = basename(str_replace('\\', '/', trim((string) ($fontRow['font_path'] ?? ''))));
            if ($family === '' || $fontPath === '') {
                continue;
            }
            if (!isset($families[$family])) {
                $families[$family] = ['family' => $family, 'display' => self::display($display), 'selector' => '', 'faces' => []];
            }
            $key = (string) $sourceKey;
            $rowFamilies[$key] = $family;
            $files = [];
            foreach (['woff2', 'woff', 'eot'] as $extension) {
                $files[] = ['location' => $baseDirectory . $fontPath . '.' . $extension, 'extension' => $extension];
            }
            $families[$family]['faces'][] = ['source_key' => $key, 'weight' => self::weight((string) ($fontRow['font_weight'] ?? '400')), 'width' => self::width((string) ($fontRow['font_stretch'] ?? 'normal')), 'style' => self::style((string) ($fontRow['font_style'] ?? '')), 'files' => $files];
        }
        $selectors = [];
        foreach ($assignments as $assignment) {
            if (!is_array($assignment)) {
                continue;
            }
            $family = trim((string) ($assignment['font_name'] ?? ''));
            $sourceKey = (string) ($assignment['font_key'] ?? '');
            if ($family === '' && isset($rowFamilies[$sourceKey])) {
                $family = $rowFamilies[$sourceKey];
            }
            $selector = self::selector((string) ($assignment['font_elements'] ?? ''));
            if ($family === '' || $selector === '' || !isset($families[$family])) {
                continue;
            }
            $selectors[$family][] = $selector;
        }
        foreach ($selectors as $family => $familySelectors) {
            $families[$family]['selector'] = self::selector(implode(', ', array_unique($familySelectors)));
        }
        return $families;
    }
    /**
     * @param array<int, array<string, mixed>> $fontRows
     * @param array<int, array<string, mixed>> $assignments
     * @return array<string, array<string, mixed>>
     */
    public static function fontsPlugin(array $fontRows, array $assignments, string $display): array
    {
        $families = [];
        $rowFamilies = [];
        foreach ($fontRows as $fontRow) {
            if (!is_array($fontRow)) {
                continue;
            }
            $family = trim(self::stringValue($fontRow['family'] ?? ''));
            if ($family === '') {
                $family = trim(self::stringValue($fontRow['name'] ?? ''));
            }
            $sourceKey = trim(self::stringValue($fontRow['source_key'] ?? $fontRow['slug'] ?? ''));
            $files = self::remoteFiles([$fontRow['woff2'] ?? '', $fontRow['woff'] ?? '', $fontRow['ttf'] ?? '', $fontRow['otf'] ?? '', $fontRow['eot'] ?? '']);
            if ($family === '' || $files === []) {
                continue;
            }
            if (!isset($families[$family])) {
                $families[$family] = ['family' => $family, 'display' => self::display($display), 'selector' => '', 'preload' => \false, 'faces' => []];
            }
            if ($sourceKey !== '') {
                $rowFamilies[$sourceKey] = $family;
            }
            $preload = filter_var($fontRow['preload'] ?? \false, \FILTER_VALIDATE_BOOLEAN);
            $families[$family]['preload'] = $families[$family]['preload'] || $preload;
            $families[$family]['faces'][] = ['source_key' => $sourceKey, 'weight' => self::weight(self::stringValue($fontRow['weight'] ?? '400', '400')), 'width' => self::width(self::stringValue($fontRow['stretch'] ?? 'normal', 'normal')), 'style' => self::style(self::stringValue($fontRow['style'] ?? 'normal', 'normal')), 'preload' => $preload, 'files' => $files];
        }
        $selectors = [];
        foreach ($assignments as $assignment) {
            if (!is_array($assignment)) {
                continue;
            }
            $sourceKey = trim(self::stringValue($assignment['source_key'] ?? ''));
            $family = trim(self::stringValue($assignment['family'] ?? ''));
            if ($family === '' && isset($rowFamilies[$sourceKey])) {
                $family = $rowFamilies[$sourceKey];
            }
            $selector = self::selector(self::stringValue($assignment['selector'] ?? ''));
            if ($family !== '' && $selector !== '' && isset($families[$family])) {
                $selectors[$family][] = $selector;
            }
        }
        foreach ($selectors as $family => $familySelectors) {
            $families[$family]['selector'] = self::selector(implode(', ', array_unique($familySelectors)));
        }
        return $families;
    }
    /**
     * @param array<int, mixed> $variants
     * @return array{display: string, skipped: int, faces: array<int, array<string, mixed>>}
     */
    public static function elementor(array $variants, string $display = 'auto'): array
    {
        $faces = [];
        $skipped = 0;
        foreach ($variants as $variant) {
            if (!is_array($variant)) {
                ++$skipped;
                continue;
            }
            $fontWeight = (string) ($variant['font_weight'] ?? $variant['weight'] ?? '400');
            $files = [];
            foreach (self::SUPPORTED_EXTENSIONS as $extension) {
                $source = $variant[$extension] ?? $variant['font_face_file_' . $extension] ?? null;
                if (is_array($source)) {
                    $source = ['url' => $source['url'] ?? $source['location'] ?? '', 'id' => $source['id'] ?? $source['attachment_id'] ?? 0];
                }
                $files[] = $source;
            }
            $files = self::remoteFiles($files);
            if ($files === []) {
                ++$skipped;
                continue;
            }
            $faces[] = ['weight' => self::weight($fontWeight), 'width' => self::width((string) ($variant['font_stretch'] ?? $variant['stretch'] ?? 'normal')), 'style' => self::style((string) ($variant['font_style'] ?? $variant['style'] ?? 'normal'), $fontWeight), 'preload' => filter_var($variant['preload'] ?? \false, \FILTER_VALIDATE_BOOLEAN), 'files' => $files];
        }
        return ['display' => self::display($display), 'skipped' => $skipped, 'faces' => $faces];
    }
    /**
     * @param array<string, mixed> $source
     * @return array{id: string, families: array<int, array<string, mixed>>}
     */
    public static function customAdobeKit(array $source): array
    {
        $projectId = trim(self::stringValue($source['custom-typekit-font-id'] ?? ''));
        $details = isset($source['custom-typekit-font-details']) && is_array($source['custom-typekit-font-details']) ? $source['custom-typekit-font-details'] : [];
        $families = [];
        foreach ($details as $sourceName => $detail) {
            if (!is_array($detail)) {
                continue;
            }
            $cssNames = array_values(array_filter(array_map('strval', (array) ($detail['css_names'] ?? [])), static fn(string $name): bool => trim($name) !== ''));
            $slug = trim((string) ($detail['slug'] ?? $cssNames[0] ?? ''));
            $family = trim((string) ($detail['family'] ?? $sourceName));
            if ($slug === '' || $family === '') {
                continue;
            }
            $name = ucwords(str_replace(['-', '_'], ' ', $family));
            $weights = array_values(array_unique(array_map(static fn($weight): string => self::weight((string) $weight), (array) ($detail['weights'] ?? []))));
            $families[] = ['id' => $slug, 'name' => $name, 'slug' => $cssNames[0] ?? $slug, 'css_names' => $cssNames, 'css_stack' => trim((string) ($detail['fallback'] ?? '')), 'weights' => $weights];
        }
        return ['id' => $projectId, 'families' => $families];
    }
    /**
     * @param mixed $urls
     * @return array<int, array{location: string, extension: string, attachment_id?: int}>
     */
    private static function remoteFiles($urls): array
    {
        if (is_string($urls)) {
            $urls = [$urls];
        }
        if (!is_array($urls)) {
            return [];
        }
        $files = [];
        $seenExtensions = [];
        foreach ($urls as $url) {
            $attachmentId = 0;
            if (is_array($url)) {
                $attachmentId = max(0, (int) ($url['id'] ?? $url['attachment_id'] ?? 0));
                $url = $url['url'] ?? $url['location'] ?? '';
            }
            if (!is_string($url) || trim($url) === '') {
                continue;
            }
            $path = parse_url(trim($url), \PHP_URL_PATH);
            $extension = is_string($path) ? strtolower(pathinfo($path, \PATHINFO_EXTENSION)) : '';
            if (!in_array($extension, self::SUPPORTED_EXTENSIONS, \true) || isset($seenExtensions[$extension])) {
                continue;
            }
            $file = ['location' => trim($url), 'extension' => $extension];
            if ($attachmentId > 0) {
                $file['attachment_id'] = $attachmentId;
            }
            $seenExtensions[$extension] = \true;
            $files[] = $file;
        }
        return $files;
    }
    private static function display(string $display): string
    {
        $display = strtolower(trim($display));
        return in_array($display, ['auto', 'block', 'swap', 'fallback', 'optional'], \true) ? $display : 'auto';
    }
    /**
     * @param mixed $value
     */
    private static function stringValue($value, string $default = ''): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : $default;
    }
    private static function style(string $style, string $weight = ''): string
    {
        $style = strtolower(trim($style));
        if ($style === 'italic' || stripos($weight, 'italic') !== \false) {
            return 'italic';
        }
        return $style === 'oblique' ? 'oblique' : 'normal';
    }
    private static function weight(string $weight): string
    {
        $weight = strtolower(trim($weight));
        if ($weight === 'normal' || $weight === 'regular') {
            return '400';
        }
        if ($weight === 'bold') {
            return '700';
        }
        if (preg_match('/^([1-9]\d{0,3})(?:\s+([1-9]\d{0,3}))?/', $weight, $matches) !== 1) {
            return '400';
        }
        $minimum = (int) $matches[1];
        $maximum = isset($matches[2]) ? (int) $matches[2] : null;
        if ($minimum > 1000 || $maximum !== null && ($maximum > 1000 || $minimum > $maximum)) {
            return '400';
        }
        return $maximum === null ? (string) $minimum : $minimum . ' ' . $maximum;
    }
    private static function width(string $stretch): string
    {
        $widths = ['ultra-condensed' => '50%', 'extra-condensed' => '62.5%', 'condensed' => '75%', 'semi-condensed' => '87.5%', 'normal' => '100%', 'semi-expanded' => '112.5%', 'expanded' => '125%', 'extra-expanded' => '150%', 'ultra-expanded' => '200%'];
        $stretch = strtolower(trim($stretch));
        return $widths[$stretch] ?? '100%';
    }
    private static function selector(string $selector): string
    {
        $selector = trim($selector);
        if ($selector === '' || strlen($selector) > 1000 || preg_match('/[<{};@]|\/\*|\*\/|[\x00-\x08\x0B\x0C\x0E-\x1F]/', $selector) !== 0) {
            return '';
        }
        return $selector;
    }
}
