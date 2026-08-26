<?php

declare (strict_types=1);
namespace JooosiFon\Api\Support;

use JooosiFon\Utils\Font;
/**
 * Emits the public font lifecycle hooks and exactly one cache invalidation
 * event for each successful mutation.
 *
 * @todo Remove all legacy Yabe Webfont hook shims completely in Jooosi Fon 3.0.0.
 */
final class FontEvents
{
    /**
     * @param mixed $payload
     */
    public static function dispatch(string $event, $payload = null, bool $async = \true): void
    {
        $hook = 'a!jooosi/fon/api/font:' . $event;
        $legacyHook = 'a!yabe/webfont/api/font:' . $event;
        Font::clear_cache();
        do_action($hook, $payload);
        do_action_deprecated($legacyHook, [$payload], '2.1.0', $hook);
        self::emitInvalidation($hook, $payload, $async);
    }
    /**
     * @param mixed $payload
     */
    public static function invalidate(string $source, $payload = null, bool $async = \true): void
    {
        Font::clear_cache();
        self::emitInvalidation($source, $payload, $async);
    }
    /**
     * @param mixed $payload
     */
    private static function emitInvalidation(string $source, $payload, bool $async): void
    {
        $hook = $async ? 'a!jooosi/fon/api/font:fonts_event_async' : 'a!jooosi/fon/api/font:fonts_event';
        $legacyHook = $async ? 'a!yabe/webfont/api/font:fonts_event_async' : 'a!yabe/webfont/api/font:fonts_event';
        do_action($hook, $source, $payload);
        do_action_deprecated($legacyHook, [str_replace('!jooosi/fon/', '!yabe/webfont/', $source), $payload], '2.1.0', $hook);
    }
}
