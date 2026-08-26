<?php

declare (strict_types=1);
namespace JooosiFon\Core\Cache;

/**
 * Pure renderer for an already validated font-cache snapshot.
 *
 * @phpstan-type CacheFile array{attachment_id: int, url: string, extension: string, mime: string, format: string}
 * @phpstan-type CacheFace array{weight: string, width: string, style: string, display: string, selector: string, comment: string, unicode_range: string, preload: bool, files: array<int, CacheFile>}
 * @phpstan-type CacheFont array{id: int, type: string, family: string, custom_property: string, legacy_custom_property: string, display: string, preload: bool, selector: string, fallback: string, faces: array<int, CacheFace>}
 */
final class FontCssRenderer
{
    /**
     * @param array<int, CacheFont> $fonts
     */
    public function render(array $fonts, string $adobeCss = ''): string
    {
        $css = trim($adobeCss);
        $css = $css !== '' ? $css . "\n\n" : '';
        foreach ($fonts as $font) {
            foreach ($font['faces'] as $face) {
                if ($face['files'] === []) {
                    continue;
                }
                if ($face['comment'] !== '') {
                    $css .= sprintf("/* %s */\n", $face['comment']);
                }
                $sources = array_map(function (array $file): string {
                    return sprintf("url('%s') format('%s')", $this->escapeString($file['url']), $this->escapeString($file['format']));
                }, $face['files']);
                $display = $face['display'] !== '' ? $face['display'] : $font['display'];
                $css .= "@font-face {\n";
                $css .= sprintf("\tfont-family: '%s';\n", $this->escapeString($font['family']));
                $css .= sprintf("\tfont-style: %s;\n", $face['style']);
                $css .= sprintf("\tfont-weight: %s;\n", $face['weight']);
                $css .= sprintf("\tfont-stretch: %s;\n", $face['width']);
                $css .= sprintf("\tfont-display: %s;\n", $display);
                $css .= sprintf("\tsrc: %s;\n", implode(",\n\t\t", $sources));
                if ($face['unicode_range'] !== '') {
                    $css .= sprintf("\tunicode-range: %s;\n", $face['unicode_range']);
                }
                $css .= "}\n\n";
            }
        }
        $variables = [];
        foreach ($fonts as $font) {
            $fallback = $font['fallback'] !== '' ? ', ' . $font['fallback'] : '';
            $variables[$font['custom_property']] = ['legacy_property' => $font['legacy_custom_property'], 'value' => sprintf("'%s'%s", $this->escapeString($font['family']), $fallback)];
        }
        if ($variables !== []) {
            $css .= ":root {\n";
            foreach ($variables as $property => $variable) {
                $css .= sprintf("\t%s: %s;\n", $property, $variable['value']);
                // TODO: Remove this legacy CSS property alias completely in Jooosi Fon 3.0.0.
                $css .= sprintf("\t%s: var(%s);\n", $variable['legacy_property'], $property);
            }
            $css .= "}\n\n";
        }
        foreach ($fonts as $font) {
            if ($font['selector'] !== '') {
                $css .= sprintf("%s {\n\tfont-family: var(%s);\n}\n\n", $font['selector'], $font['custom_property']);
            }
            foreach ($font['faces'] as $face) {
                if ($face['selector'] === '') {
                    continue;
                }
                $css .= sprintf("%s {\n", $face['selector']);
                $css .= sprintf("\tfont-family: var(%s);\n", $font['custom_property']);
                $css .= sprintf("\tfont-style: %s;\n", $face['style']);
                $css .= sprintf("\tfont-weight: %s;\n", $face['weight']);
                $css .= "}\n\n";
            }
        }
        return $css;
    }
    private function escapeString(string $value): string
    {
        return str_replace(['\\', "'", '<', "\r", "\n", "\f"], ['\\\\', "\\'", '\3C ', '\D ', '\A ', '\C '], $value);
    }
}
