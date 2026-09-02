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
namespace JooosiFon\Core;

use JooosiFonDeps\JOOOSI_FON;
use JooosiFon\Core\Cache\FontCacheSnapshotBuilder;
use JooosiFon\Core\Cache\FontCssRenderer;
use JooosiFon\Core\Cache\FontPreloadRenderer;
use JooosiFon\Upgrade\LegacyRebrandUpgrade;
use JooosiFon\Utils\Common;
use JooosiFon\Utils\Config;
use JooosiFon\Utils\Notice;
/**
 * Manage the cache of fonts for the frontpage.
 *
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 * @todo Remove all legacy Yabe Webfont hook shims completely in Jooosi Fon 3.0.0.
 */
class Cache
{
    /**
     * @var string
     */
    public const CSS_CACHE_FILE = 'fonts.css';
    /**
     * @var string
     */
    public const PRELOAD_HTML_FILE = 'preload.html';
    /**
     * @var string
     */
    public const CACHE_DIR = '/jooosi-fon/cache/';
    /**
     * @var string
     */
    public static $typekit_embed = 'css';
    public function __construct()
    {
        $this->migrate_legacy_scheduled_cache();
        add_action('a!jooosi/fon/core/cache:build_cache', fn() => $this->build_cache());
        // listen to fonts event for cache build (async/scheduled)
        add_action('a!jooosi/fon/api/font:fonts_event_async', fn() => $this->schedule_cache(), 10, 1);
        // listen to fonts event for cache build (sync)
        add_action('a!jooosi/fon/api/font:fonts_event', fn() => $this->build_cache(), 10, 1);
        // listen to theme switch for cache build (async/scheduled)
        add_action('switch_theme', fn() => $this->schedule_cache(), 1000001);
        // listen to Config change for cache build (async/scheduled)
        add_action('f!jooosi/fon/api/setting/option:after_store', fn() => $this->schedule_cache(), 10, 1);
        // listen to plugin upgrade for cache build (async/scheduled)
        add_action('a!jooosi/fon/plugins:upgrade_plugin_end', fn() => $this->schedule_cache(), 10, 1);
        $this->maybeRebuildAfterLegacyUpgrade();
    }
    public function schedule_cache(): void
    {
        self::updateBuildStatus(['state' => 'pending', 'requested_at' => time(), 'changed' => \false, 'warnings' => [], 'error' => '']);
        if (!wp_next_scheduled('a!jooosi/fon/core/cache:build_cache')) {
            $scheduled = wp_schedule_single_event(time() + 10, 'a!jooosi/fon/core/cache:build_cache');
            if ($scheduled === \false) {
                self::updateBuildStatus(['state' => 'failed', 'error' => 'The cache build could not be scheduled.', 'completed_at' => time()]);
            }
        }
    }
    public static function get_cache_path(string $file_path = ''): string
    {
        return wp_upload_dir()['basedir'] . self::CACHE_DIR . $file_path;
    }
    public static function get_cache_url(string $file_path = ''): string
    {
        return wp_upload_dir()['baseurl'] . self::CACHE_DIR . $file_path;
    }
    public static function get_versioned_cache_url(string $filePath): string
    {
        $url = self::get_cache_url($filePath);
        $version = self::get_cache_version($filePath);
        return $version !== null ? add_query_arg('ver', $version, $url) : $url;
    }
    public static function get_cache_version(string $filePath): ?string
    {
        $path = self::get_cache_path($filePath);
        if (!is_readable($path)) {
            return null;
        }
        $hash = hash_file('sha256', $path);
        return is_string($hash) ? substr($hash, 0, 16) : null;
    }
    public function build_cache(): void
    {
        try {
            $lock = $this->acquireBuildLock();
        } catch (\Throwable $throwable) {
            $message = sprintf('Failed to start the font cache build: %s', $throwable->getMessage());
            self::updateBuildStatus(['state' => 'failed', 'completed_at' => time(), 'changed' => \false, 'warnings' => [], 'error' => $message]);
            Notice::error($message, 'jooosi-fon-cache-build', \true);
            return;
        }
        if ($lock === \false) {
            return;
        }
        self::updateBuildStatus(['state' => 'running', 'started_at' => time(), 'changed' => \false, 'warnings' => [], 'error' => '']);
        try {
            $artifacts = self::buildArtifacts();
            $payload = sprintf("/*\n! %s v%s | %s\n*/\n\n%s", Common::plugin_data('Name'), JOOOSI_FON::VERSION, date('Y-m-d H:i:s', time()), $artifacts['css']);
            $cssPath = self::get_cache_path(self::CSS_CACHE_FILE);
            $preloadPath = self::get_cache_path(self::PRELOAD_HTML_FILE);
            self::publishArtifacts($payload, $artifacts['preload'], $cssPath, $preloadPath);
            $this->purge_cache_plugin();
            self::updateBuildStatus(['state' => 'succeeded', 'completed_at' => time(), 'hash' => hash('sha256', $payload . "\x00" . $artifacts['preload']), 'changed' => \true, 'warnings' => $artifacts['warnings'], 'error' => '']);
            do_action('a!jooosi/fon/core/cache:build_succeeded', \true, $artifacts['warnings']);
        } catch (\Throwable $throwable) {
            $message = sprintf('Failed to build the font cache: %s', $throwable->getMessage());
            self::updateBuildStatus(['state' => 'failed', 'completed_at' => time(), 'changed' => \false, 'warnings' => [], 'error' => $message]);
            Notice::error($message, 'jooosi-fon-cache-build', \true);
            do_action('a!jooosi/fon/core/cache:build_failed', $throwable);
        } finally {
            flock($lock, \LOCK_UN);
            fclose($lock);
        }
    }
    public static function build_css(): string
    {
        return self::buildArtifacts()['css'];
    }
    public static function build_preload(): string
    {
        return self::buildArtifacts()['preload'];
    }
    public static function get_kit_css($kit_id, ?array &$warnings = null): string
    {
        $kitId = trim((string) $kit_id);
        if (preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $kitId) !== 1) {
            self::adobeWarning($warnings, 'Adobe Fonts CSS was skipped because the project ID is invalid.');
            return '';
        }
        $cachePath = self::get_cache_path('adobe/' . hash('sha256', $kitId) . '.css');
        $cachedCss = is_readable($cachePath) ? file_get_contents($cachePath) : \false;
        $cachedCss = is_string($cachedCss) ? $cachedCss : '';
        if (!self::isSafeStylesheet($cachedCss)) {
            $cachedCss = '';
        }
        $ttl = max(300, (int) apply_filters('f!jooosi/fon/core/cache:adobe_css_ttl', 12 * \HOUR_IN_SECONDS));
        if ($cachedCss !== '' && filemtime($cachePath) > time() - $ttl) {
            return $cachedCss;
        }
        $response = wp_safe_remote_get(sprintf('https://use.typekit.net/%s.css', rawurlencode($kitId)), ['timeout' => 15, 'redirection' => 2, 'limit_response_size' => 2 * \MB_IN_BYTES]);
        if (is_wp_error($response)) {
            return self::adobeFallback($cachedCss, $warnings, 'Adobe Fonts could not be refreshed.');
        }
        $status = wp_remote_retrieve_response_code($response);
        if ($status === 412) {
            self::$typekit_embed = 'js';
            return '';
        }
        if ($status !== 200) {
            return self::adobeFallback($cachedCss, $warnings, sprintf('Adobe Fonts returned HTTP %d.', $status));
        }
        $body = wp_remote_retrieve_body($response);
        if (!is_string($body) || !self::isSafeStylesheet($body)) {
            return self::adobeFallback($cachedCss, $warnings, 'Adobe Fonts returned an invalid stylesheet.');
        }
        try {
            self::publishArtifact($body, $cachePath);
        } catch (\Throwable $throwable) {
            self::adobeWarning($warnings, 'Adobe Fonts CSS was refreshed but could not be cached separately.');
        }
        return $body;
    }
    public static function get_kit_js($kit_id): string
    {
        $kitId = trim((string) $kit_id);
        if (preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $kitId) !== 1) {
            return '';
        }
        $response = wp_safe_remote_get(sprintf('https://use.typekit.net/%s.js', rawurlencode($kitId)), ['timeout' => 15, 'redirection' => 2, 'limit_response_size' => 2 * \MB_IN_BYTES]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return '';
        }
        $body = wp_remote_retrieve_body($response);
        if (!is_string($body) || trim($body) === '') {
            return '';
        }
        $js = '<script type="text/javascript">';
        $js .= $body;
        $js .= "\n\n";
        $js .= sprintf('try{Typekit.load({kitId:%s,scriptTimeout:3E3,async:true});}catch(e){}', wp_json_encode($kitId));
        $js .= '</script>';
        return "\n\n" . $js . "\n\n";
    }
    /**
     * @return array<string, mixed>
     */
    public static function getBuildStatus(): array
    {
        $status = get_option(JOOOSI_FON::WP_OPTION . '_cache_build_status', []);
        return is_array($status) ? $status : [];
    }
    /**
     * @return array{css: string, preload: string, warnings: string[]}
     */
    private static function buildArtifacts(): array
    {
        self::$typekit_embed = 'css';
        $snapshot = (new FontCacheSnapshotBuilder())->build();
        $warnings = $snapshot['warnings'];
        $adobeCss = '';
        $projectId = trim((string) Config::get('adobe_fonts.project_id', ''));
        $hasAdobeFonts = in_array('adobe-fonts', array_column($snapshot['fonts'], 'type'), \true);
        if ($projectId !== '' && $hasAdobeFonts) {
            $adobeCss = self::get_kit_css($projectId, $warnings);
        }
        $css = (new FontCssRenderer())->render($snapshot['fonts'], $adobeCss);
        /**
         * @param string $css The CSS content
         * @param array $rows The active database rows
         * @return string The CSS content
         */
        $css = apply_filters('f!jooosi/fon/core/cache:build_css.append_content', $css, $snapshot['rows']);
        $css = apply_filters_deprecated('f!yabe/webfont/core/cache:build_css.append_content', [$css, $snapshot['rows']], '2.1.0', 'f!jooosi/fon/core/cache:build_css.append_content');
        if (!is_string($css)) {
            throw new \UnexpectedValueException('The font CSS filter must return a string.');
        }
        $preloadLimit = (int) apply_filters('f!jooosi/fon/core/cache:preload_limit', 6);
        $preload = (new FontPreloadRenderer())->render($snapshot['fonts'], $preloadLimit);
        if (self::$typekit_embed === 'js' && $projectId !== '' && $hasAdobeFonts) {
            $preload .= self::get_kit_js($projectId);
        }
        foreach ($warnings as $warning) {
            do_action('a!jooosi/fon/core/cache:build_warning', $warning);
        }
        return ['css' => $css, 'preload' => $preload, 'warnings' => array_values(array_unique($warnings))];
    }
    /**
     * @return resource|false
     */
    private function acquireBuildLock()
    {
        $directory = self::get_cache_path();
        if (!is_dir($directory) && !wp_mkdir_p($directory)) {
            throw new \RuntimeException('The font cache directory could not be created.');
        }
        $lock = fopen($directory . '.build.lock', 'c');
        if ($lock === \false) {
            throw new \RuntimeException('The font cache build lock could not be opened.');
        }
        if (!flock($lock, \LOCK_EX | \LOCK_NB)) {
            fclose($lock);
            return \false;
        }
        return $lock;
    }
    private static function publishArtifacts(string $css, string $preload, string $cssPath, string $preloadPath): void
    {
        $cssTemp = self::stageArtifact($css, $cssPath);
        $preloadTemp = null;
        try {
            $preloadTemp = self::stageArtifact($preload, $preloadPath);
            if (!rename($preloadTemp, $preloadPath)) {
                throw new \RuntimeException('The font preload cache could not be published.');
            }
            $preloadTemp = null;
            if (!rename($cssTemp, $cssPath)) {
                throw new \RuntimeException('The font CSS cache could not be published.');
            }
            $cssTemp = null;
        } finally {
            if (is_string($cssTemp) && file_exists($cssTemp)) {
                unlink($cssTemp);
            }
            if (is_string($preloadTemp) && file_exists($preloadTemp)) {
                unlink($preloadTemp);
            }
        }
    }
    private static function publishArtifact(string $contents, string $path): void
    {
        $temporaryPath = self::stageArtifact($contents, $path);
        try {
            if (!rename($temporaryPath, $path)) {
                throw new \RuntimeException('The cache artifact could not be published.');
            }
            $temporaryPath = null;
        } finally {
            if (is_string($temporaryPath) && file_exists($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
    private static function stageArtifact(string $contents, string $path): string
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !wp_mkdir_p($directory)) {
            throw new \RuntimeException('The cache artifact directory could not be created.');
        }
        $temporaryPath = tempnam($directory, '.jooosi-fon-');
        if (!is_string($temporaryPath)) {
            throw new \RuntimeException('A temporary cache artifact could not be created.');
        }
        $written = file_put_contents($temporaryPath, $contents, \LOCK_EX);
        if ($written === \false || $written !== strlen($contents)) {
            unlink($temporaryPath);
            throw new \RuntimeException('A cache artifact could not be written completely.');
        }
        $permissions = defined('FS_CHMOD_FILE') ? \FS_CHMOD_FILE : 0644;
        chmod($temporaryPath, $permissions);
        return $temporaryPath;
    }
    /**
     * @param array<string, mixed> $status
     */
    private static function updateBuildStatus(array $status): void
    {
        update_option(JOOOSI_FON::WP_OPTION . '_cache_build_status', array_merge(self::getBuildStatus(), $status), \false);
    }
    private static function adobeWarning(?array &$warnings, string $message): void
    {
        if ($warnings !== null && count($warnings) < 100) {
            $warnings[] = $message;
        }
    }
    private static function adobeFallback(string $cachedCss, ?array &$warnings, string $message): string
    {
        if ($cachedCss === '') {
            throw new \RuntimeException($message . ' No cached stylesheet is available.');
        }
        self::adobeWarning($warnings, $message . ' The last-known-good stylesheet was retained.');
        return $cachedCss;
    }
    private static function isSafeStylesheet(string $css): bool
    {
        return trim($css) !== '' && stripos($css, '</style') === \false;
    }
    /**
     * @todo Remove this legacy cron migration completely in Jooosi Fon 3.0.0.
     */
    private function migrate_legacy_scheduled_cache(): void
    {
        $legacyHook = 'a!yabe/webfont/core/cache:build_cache';
        $hook = 'a!jooosi/fon/core/cache:build_cache';
        $legacyTimestamp = wp_next_scheduled($legacyHook);
        if ($legacyTimestamp === \false) {
            return;
        }
        if (!wp_next_scheduled($hook)) {
            wp_schedule_single_event(max(time() + 1, $legacyTimestamp), $hook);
        }
        wp_clear_scheduled_hook($legacyHook);
    }
    /**
     * Rebuild generated files immediately after upload paths have moved so a
     * frontend request never prints cache content containing legacy URLs.
     *
     * @todo Remove this legacy cache rebuild completely in Jooosi Fon 3.0.0.
     */
    private function maybeRebuildAfterLegacyUpgrade(): void
    {
        if (get_option(LegacyRebrandUpgrade::CACHE_REBUILD_OPTION, \false) === \false) {
            return;
        }
        self::updateBuildStatus(['state' => 'pending', 'requested_at' => time(), 'changed' => \false, 'warnings' => [], 'error' => '']);
        $this->build_cache();
        if ((self::getBuildStatus()['state'] ?? null) === 'succeeded') {
            delete_option(LegacyRebrandUpgrade::CACHE_REBUILD_OPTION);
        }
    }
    /**
     * Clear the cache from various cache plugins.
     */
    private function purge_cache_plugin()
    {
        /**
         * Only invalidate this plugin's font catalog. Flushing the complete
         * WordPress object cache can create a site-wide performance spike.
         * @see https://developer.wordpress.org/reference/classes/wp_object_cache/
         */
        \wp_cache_delete('get_fonts', JOOOSI_FON::WP_OPTION);
        do_action('a!jooosi/fon/core/cache:before_page_cache_purge');
        /**
         * WP Rocket
         * @see https://docs.wp-rocket.me/article/92-rocketcleandomain
         */
        if (function_exists('rocket_clean_domain')) {
            \rocket_clean_domain();
        }
        /**
         * WP Super Cache
         * @see https://github.com/Automattic/wp-super-cache/blob/a0872032b1b3fc6847f490eadfabf74c12ad0135/wp-cache-phase2.php#L3013
         */
        if (function_exists('wp_cache_clear_cache')) {
            \wp_cache_clear_cache();
        }
        /**
         * W3 Total Cache
         * @see https://github.com/BoldGrid/w3-total-cache/blob/3a094493064ea60d727b3389dee813639860ef49/w3-total-cache-api.php#L259
         */
        if (function_exists('w3tc_flush_all')) {
            \w3tc_flush_all();
        }
        /**
         * WP Fastest Cache
         * @see https://www.wpfastestcache.com/tutorial/delete-the-cache-by-calling-the-function/
         */
        if (function_exists('wpfc_clear_all_cache')) {
            \wpfc_clear_all_cache(\true);
        }
        /**
         * LiteSpeed Cache
         * @see https://docs.litespeedtech.com/lscache/lscwp/api/#purge-all-existing-caches
         */
        do_action('litespeed_purge_all');
    }
}
