<?php

declare (strict_types=1);
namespace JooosiFon\Core\Cache;

/**
 * Pure renderer for the bounded set of critical WOFF2 preload resources.
 *
 * @phpstan-type CacheFile array{attachment_id: int, url: string, extension: string, mime: string, format: string}
 * @phpstan-type CacheFace array{weight: string, width: string, style: string, display: string, selector: string, comment: string, unicode_range: string, preload: bool, files: array<int, CacheFile>}
 * @phpstan-type CacheFont array{id: int, type: string, family: string, custom_property: string, legacy_custom_property: string, display: string, preload: bool, selector: string, fallback: string, faces: array<int, CacheFace>}
 */
final class FontPreloadRenderer
{
    /**
     * @param array<int, CacheFont> $fonts
     */
    public function render(array $fonts, int $limit = 6): string
    {
        $limit = max(0, min(50, $limit));
        if ($limit === 0) {
            return '';
        }
        $resources = [];
        foreach ($fonts as $font) {
            foreach ($font['faces'] as $face) {
                if (!$font['preload'] && !$face['preload']) {
                    continue;
                }
                foreach ($face['files'] as $file) {
                    if ($file['mime'] !== 'font/woff2') {
                        continue;
                    }
                    $resources[$file['url']] = $file['mime'];
                    if (count($resources) >= $limit) {
                        break 3;
                    }
                }
            }
        }
        $html = '';
        foreach ($resources as $url => $mime) {
            $html .= sprintf('<link rel="preload" href="%s" as="font" type="%s" crossorigin>' . \PHP_EOL, htmlspecialchars($url, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'), htmlspecialchars($mime, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'));
        }
        return $html;
    }
}
