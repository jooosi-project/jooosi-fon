<?php

declare (strict_types=1);
namespace JooosiFon\Api\Support;

/**
 * Encodes font payloads and reads both the current compressed representation
 * and the legacy plain JSON representation.
 */
final class FontCodec
{
    /**
     * @return array|object
     */
    public static function decode(string $payload, bool $associative = \false)
    {
        try {
            return self::decodeJson($payload, $associative);
        } catch (\JsonException $exception) {
            $compressed = base64_decode($payload, \true);
            if ($compressed === \false) {
                throw new \UnexpectedValueException('The stored font payload is neither JSON nor valid base64.', 0, $exception);
            }
            $json = @gzuncompress($compressed);
            if (!is_string($json)) {
                throw new \UnexpectedValueException('The stored font payload could not be decompressed.', 0, $exception);
            }
            return self::decodeJson($json, $associative);
        }
    }
    /**
     * @param array|object $payload
     */
    public static function encode($payload, bool $compressed = \true): string
    {
        $json = json_encode($payload, \JSON_THROW_ON_ERROR);
        if (!$compressed) {
            return $json;
        }
        $encoded = gzcompress($json, 9);
        if ($encoded === \false) {
            throw new \RuntimeException('The font payload could not be compressed.');
        }
        return base64_encode($encoded);
    }
    /**
     * @return array|object
     */
    private static function decodeJson(string $payload, bool $associative)
    {
        $decoded = json_decode($payload, $associative, 512, \JSON_THROW_ON_ERROR);
        if (!is_array($decoded) && !is_object($decoded)) {
            throw new \UnexpectedValueException('The font payload must decode to an object or array.');
        }
        return $decoded;
    }
}
