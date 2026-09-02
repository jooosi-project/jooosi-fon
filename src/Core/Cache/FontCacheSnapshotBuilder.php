<?php

declare (strict_types=1);
namespace JooosiFon\Core\Cache;

use JooosiFon\Api\Support\FontCodec;
use JooosiFon\Database\FontTable;
use JooosiFon\Utils\Font;
/**
 * Reads active font records once and converts them into a safe, predictable
 * representation shared by the stylesheet and preload renderers.
 */
final class FontCacheSnapshotBuilder
{
    /**
     * @return array{fonts: array<int, array<string, mixed>>, rows: array<int, object>, warnings: string[]}
     */
    public function build(): array
    {
        global $wpdb;
        // Cache generation may be the first request after a failed update.
        // Ensure the table exists even when migration history is stale.
        FontTable::ensure();
        $rows = $wpdb->get_results("\n            SELECT id, type, family, metadata, font_faces\n            FROM {$wpdb->prefix}jooosi_fon_fonts\n            WHERE status = 1\n                AND deleted_at IS NULL\n            ORDER BY type ASC, family ASC, id ASC\n        ");
        if ($wpdb->last_error) {
            throw new \RuntimeException($wpdb->last_error);
        }
        if (!is_array($rows)) {
            throw new \RuntimeException('The active font records could not be loaded.');
        }
        $fonts = [];
        $warnings = [];
        foreach ($rows as $row) {
            try {
                $metadata = FontCodec::decode((string) $row->metadata, \true);
                $fontFaces = FontCodec::decode((string) $row->font_faces, \true);
            } catch (\Throwable $throwable) {
                $this->warn($warnings, sprintf('Font #%d was skipped because its stored payload is invalid.', (int) $row->id));
                continue;
            }
            if (!is_array($metadata) || !is_array($fontFaces)) {
                $this->warn($warnings, sprintf('Font #%d was skipped because its stored payload has an invalid shape.', (int) $row->id));
                continue;
            }
            $family = trim((string) $row->family);
            if ($family === '' || preg_match('//u', $family) !== 1) {
                $this->warn($warnings, sprintf('Font #%d was skipped because its family name is missing or invalid.', (int) $row->id));
                continue;
            }
            [$selector, $fallback] = $this->fontSelector((string) ($metadata['selector'] ?? ''));
            $faces = [];
            foreach ($fontFaces as $faceIndex => $fontFace) {
                if (!is_array($fontFace)) {
                    $this->warn($warnings, sprintf('Font #%d face #%d was skipped because it is invalid.', (int) $row->id, (int) $faceIndex + 1));
                    continue;
                }
                if (array_key_exists('isEnabled', $fontFace) && filter_var($fontFace['isEnabled'], \FILTER_VALIDATE_BOOLEAN) !== \true) {
                    continue;
                }
                $normalizedFace = $this->normalizeFace($fontFace, (int) $row->id, (int) $faceIndex, $warnings);
                if ($normalizedFace !== null) {
                    $faces[] = $normalizedFace;
                }
            }
            if ($faces === [] && $row->type !== 'adobe-fonts') {
                $this->warn($warnings, sprintf('Font #%d was skipped because it has no usable font files.', (int) $row->id));
                continue;
            }
            $fonts[] = ['id' => (int) $row->id, 'type' => (string) $row->type, 'family' => $family, 'custom_property' => Font::css_custom_property($family), 'legacy_custom_property' => Font::legacy_css_custom_property($family), 'display' => $this->display((string) ($metadata['display'] ?? 'auto'), 'auto'), 'preload' => filter_var($metadata['preload'] ?? \false, \FILTER_VALIDATE_BOOLEAN), 'selector' => $selector, 'fallback' => $fallback, 'faces' => $faces];
        }
        return ['fonts' => $fonts, 'rows' => $rows, 'warnings' => $warnings];
    }
    /**
     * @param array<string, mixed> $face
     * @param string[] $warnings
     * @return array<string, mixed>|null
     */
    private function normalizeFace(array $face, int $fontId, int $faceIndex, array &$warnings): ?array
    {
        $files = [];
        $seenExtensions = [];
        foreach ((array) ($face['files'] ?? []) as $file) {
            if (!is_array($file)) {
                continue;
            }
            $extension = strtolower((string) ($file['extension'] ?? ''));
            $attachmentId = (int) ($file['attachment_id'] ?? 0);
            $mime = $this->mime($extension);
            if ($attachmentId < 1 || $mime === null || isset($seenExtensions[$extension])) {
                continue;
            }
            $url = wp_get_attachment_url($attachmentId);
            if (!is_string($url) || $url === '') {
                $this->warn($warnings, sprintf('Font #%d face #%d references a missing attachment.', $fontId, $faceIndex + 1));
                continue;
            }
            $seenExtensions[$extension] = \true;
            $files[] = ['attachment_id' => $attachmentId, 'url' => $url, 'extension' => $extension, 'mime' => $mime, 'format' => $this->format($extension)];
        }
        if ($files === []) {
            $this->warn($warnings, sprintf('Font #%d face #%d was skipped because it has no usable files.', $fontId, $faceIndex + 1));
            return null;
        }
        usort($files, static function (array $left, array $right): int {
            $precedence = ['woff2' => 1, 'woff' => 2, 'ttf' => 3, 'otf' => 4, 'eot' => 5];
            return $precedence[$left['extension']] <=> $precedence[$right['extension']];
        });
        return ['weight' => $this->weight($face['weight'] ?? 400), 'width' => $this->width($face['width'] ?? '100%'), 'style' => $this->style((string) ($face['style'] ?? 'normal')), 'display' => $this->display((string) ($face['display'] ?? ''), ''), 'selector' => $this->selector((string) ($face['selector'] ?? '')), 'comment' => $this->comment((string) ($face['comment'] ?? '')), 'unicode_range' => $this->unicodeRange((string) ($face['unicodeRange'] ?? '')), 'preload' => filter_var($face['preload'] ?? \false, \FILTER_VALIDATE_BOOLEAN), 'files' => $files];
    }
    /**
     * @return array{0: string, 1: string}
     */
    private function fontSelector(string $value): array
    {
        $parts = array_map('trim', explode('|', $value, 2));
        $selector = $this->selector($parts[0] ?? '');
        $fallback = trim($parts[1] ?? '');
        if ($fallback !== '' && (strlen($fallback) > 500 || preg_match('/[^a-zA-Z0-9\s,_\'"-]/', $fallback) !== 0 || substr_count($fallback, "'") % 2 !== 0 || substr_count($fallback, '"') % 2 !== 0)) {
            $fallback = '';
        }
        return [$selector, $fallback];
    }
    private function selector(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 1000 || preg_match('/[<{};@]|\/\*|\*\/|[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) !== 0) {
            return '';
        }
        return $value;
    }
    private function comment(string $value): string
    {
        $value = trim(str_replace(['/*', '*/', '<'], ['', '* /', '\3C '], $value));
        return strlen($value) <= 500 ? $value : substr($value, 0, 500);
    }
    private function style(string $value): string
    {
        $value = trim(strtolower($value));
        return preg_match('/^(?:normal|italic|oblique(?:\s+-?\d+(?:\.\d+)?deg(?:\s+-?\d+(?:\.\d+)?deg)?)?)$/', $value) ? $value : 'normal';
    }
    /**
     * @param mixed $value
     */
    private function weight($value): string
    {
        $value = trim((string) $value);
        if (in_array($value, ['normal', 'bold'], \true)) {
            return $value;
        }
        if (preg_match('/^[1-9]\d{0,3}(?:\s+[1-9]\d{0,3})?$/', $value) !== 1) {
            return '400';
        }
        foreach (preg_split('/\s+/', $value) ?: [] as $weight) {
            if ((int) $weight < 1 || (int) $weight > 1000) {
                return '400';
            }
        }
        return $value;
    }
    /**
     * @param mixed $value
     */
    private function width($value): string
    {
        $value = trim((string) $value);
        $keywords = ['normal', 'ultra-condensed', 'extra-condensed', 'condensed', 'semi-condensed', 'semi-expanded', 'expanded', 'extra-expanded', 'ultra-expanded'];
        if (in_array($value, $keywords, \true) || preg_match('/^\d+(?:\.\d+)?%(?:\s+\d+(?:\.\d+)?%)?$/', $value) === 1) {
            return $value;
        }
        return '100%';
    }
    private function display(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['auto', 'block', 'swap', 'fallback', 'optional'], \true) ? $value : $fallback;
    }
    private function unicodeRange(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        return preg_match('/^U\+[0-9A-F?]{1,6}(?:-[0-9A-F]{1,6})?(?:\s*,\s*U\+[0-9A-F?]{1,6}(?:-[0-9A-F]{1,6})?)*$/i', $value) ? $value : '';
    }
    private function mime(string $extension): ?string
    {
        $mimes = ['woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf', 'otf' => 'font/otf', 'eot' => 'font/eot'];
        return $mimes[$extension] ?? null;
    }
    private function format(string $extension): string
    {
        $formats = ['ttf' => 'truetype', 'otf' => 'opentype', 'eot' => 'embedded-opentype'];
        return $formats[$extension] ?? $extension;
    }
    /**
     * @param string[] $warnings
     */
    private function warn(array &$warnings, string $message): void
    {
        if (count($warnings) < 100) {
            $warnings[] = $message;
        }
    }
}
