<?php

declare (strict_types=1);
namespace JooosiFon\Api\Support;

final class GoogleFontFileSelector
{
    /**
     * @param array<int, array<string, mixed>> $files
     * @param array<string, mixed> $face
     * @param string[] $subsets
     * @param string[] $formats
     * @return array<int, array<string, mixed>>
     */
    public static function variableFiles(array $files, array $face, array $subsets, array $formats): array
    {
        $selected = [];
        foreach ($subsets as $subset) {
            foreach ($files as $file) {
                if (self::matchesFace($file, $face, $formats) && in_array($subset, (array) ($file['subsets'] ?? []), \true)) {
                    $selected[] = $file;
                }
            }
        }
        $usesNamedSubsets = array_reduce($subsets, static fn(bool $carry, string $subset): bool => $carry && preg_match('/\d/', $subset) !== 1, \true);
        if ($usesNamedSubsets) {
            foreach ($files as $file) {
                if (self::matchesFace($file, $face, $formats)) {
                    $selected[] = $file;
                }
            }
        }
        return array_values(array_unique($selected, \SORT_REGULAR));
    }
    /**
     * @param array<string, mixed> $file
     * @param array<string, mixed> $face
     * @param string[] $formats
     */
    private static function matchesFace(array $file, array $face, array $formats): bool
    {
        return ($file['weight'] ?? null) === ($face['weight'] ?? null) && ($file['style'] ?? null) === ($face['style'] ?? null) && in_array($file['format'] ?? null, $formats, \true);
    }
}
