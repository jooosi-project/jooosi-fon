<?php

declare(strict_types=1);

$pluginDirectory = dirname(__DIR__);
$bootTiming = 'normal';

foreach (array_slice($argv, 1) as $argument) {
    $candidateDirectory = realpath($argument);

    if ($candidateDirectory !== false && is_dir($candidateDirectory)) {
        $pluginDirectory = $candidateDirectory;
    } else {
        $bootTiming = $argument;
    }
}

if (! is_file($pluginDirectory . '/vendor/autoload.php')) {
    throw new RuntimeException('A plugin directory with installed Composer dependencies is required.');
}

if (! in_array($bootTiming, ['normal', 'late'], true)) {
    throw new RuntimeException('The SDK boot timing must be either normal or late.');
}

if (! defined('ABSPATH')) {
    define('ABSPATH', $pluginDirectory . '/');
}

$_SERVER['DOCUMENT_ROOT'] = $pluginDirectory;
$_SERVER['HTTP_HOST'] = 'example.test';

final class WP_Error
{
    private string $code;

    private string $message;

    public function __construct(string $code, string $message)
    {
        $this->code = $code;
        $this->message = $message;
    }

    public function get_error_code(): string
    {
        return $this->code;
    }

    public function get_error_message(): string
    {
        return $this->message;
    }
}

$GLOBALS['jooosi_fon_test_options'] = [
    'jooosi_fon_license' => [
        'key' => 'legacy-key',
        'opt_in_pre_release' => true,
    ],
];
$GLOBALS['jooosi_fon_test_transients'] = [
    'jooosi_fon_license_seed' => (object) [
        'success' => true,
        'license' => 'valid',
    ],
];
$GLOBALS['jooosi_fon_test_remote_response'] = [
    'response' => ['code' => 200],
    'body' => wp_json_encode([
        'success' => true,
        'license' => 'valid',
    ]),
];
$GLOBALS['jooosi_fon_test_hooks'] = [];
$GLOBALS['jooosi_fon_test_action_counts'] = [];
$GLOBALS['jooosi_fon_test_action_stack'] = [];

function add_action($hook, $callback, $priority = 10, $accepted_args = 1): bool
{
    $GLOBALS['jooosi_fon_test_hooks'][$hook][$priority][] = [$callback, $accepted_args];

    return true;
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): bool
{
    return add_action($hook, $callback, $priority, $accepted_args);
}

function remove_action($hook, $callback, $priority = 10): bool
{
    foreach ($GLOBALS['jooosi_fon_test_hooks'][$hook][$priority] ?? [] as $index => $registered) {
        if ($registered[0] === $callback) {
            unset($GLOBALS['jooosi_fon_test_hooks'][$hook][$priority][$index]);

            return true;
        }
    }

    return false;
}

function do_action($hook, ...$args): void
{
    $GLOBALS['jooosi_fon_test_action_counts'][$hook] = did_action($hook) + 1;
    $GLOBALS['jooosi_fon_test_action_stack'][] = $hook;
    $priorities = $GLOBALS['jooosi_fon_test_hooks'][$hook] ?? [];
    ksort($priorities);

    foreach ($priorities as $callbacks) {
        foreach ($callbacks as [$callback, $accepted_args]) {
            $callback(...array_slice($args, 0, $accepted_args));
        }
    }

    array_pop($GLOBALS['jooosi_fon_test_action_stack']);
}

function did_action($hook): int
{
    return (int) ($GLOBALS['jooosi_fon_test_action_counts'][$hook] ?? 0);
}

function doing_action($hook = null): bool
{
    if ($hook === null) {
        return $GLOBALS['jooosi_fon_test_action_stack'] !== [];
    }

    return in_array($hook, $GLOBALS['jooosi_fon_test_action_stack'], true);
}

function get_option($name, $default = false)
{
    return array_key_exists($name, $GLOBALS['jooosi_fon_test_options'])
        ? $GLOBALS['jooosi_fon_test_options'][$name]
        : $default;
}

function update_option($name, $value, $autoload = null): bool
{
    $GLOBALS['jooosi_fon_test_options'][$name] = $value;

    return true;
}

function delete_option($name): bool
{
    unset($GLOBALS['jooosi_fon_test_options'][$name]);

    return true;
}

function get_transient($name)
{
    return $GLOBALS['jooosi_fon_test_transients'][$name] ?? false;
}

function delete_transient($name): bool
{
    unset($GLOBALS['jooosi_fon_test_transients'][$name]);

    return true;
}

function delete_site_transient($name): bool
{
    $GLOBALS['jooosi_fon_test_deleted_site_transients'][] = $name;

    return true;
}

function wp_json_encode($value): string
{
    return json_encode($value, JSON_THROW_ON_ERROR);
}

function __($text, $domain = 'default'): string
{
    return $text;
}

function __return_null()
{
    return null;
}

function is_wp_error($value): bool
{
    return $value instanceof WP_Error;
}

function apply_filters($hook, $value, ...$args)
{
    return $value;
}

function wp_parse_args($args, $defaults = []): array
{
    return array_merge($defaults, is_array($args) ? $args : []);
}

function wp_parse_url($url)
{
    return parse_url($url);
}

function trailingslashit($value): string
{
    return rtrim($value, '/\\') . '/';
}

function add_query_arg($args, $url): string
{
    return $url . '?' . http_build_query($args);
}

function home_url(): string
{
    return 'https://example.test';
}

function wp_get_environment_type(): string
{
    return 'production';
}

function wp_remote_get($url, $args)
{
    $GLOBALS['jooosi_fon_test_remote_request'] = [$url, $args];

    return $GLOBALS['jooosi_fon_test_remote_response'];
}

function wp_remote_retrieve_response_code($response): int
{
    return (int) ($response['response']['code'] ?? 0);
}

function wp_remote_retrieve_body($response): string
{
    return (string) ($response['body'] ?? '');
}

function plugin_basename($file): string
{
    return basename(dirname($file)) . '/' . basename($file);
}

function wp_next_scheduled($hook)
{
    return false;
}

function wp_schedule_event($timestamp, $recurrence, $hook): bool
{
    $GLOBALS['jooosi_fon_test_scheduled_events'][] = [$timestamp, $recurrence, $hook];

    return true;
}

function current_user_can($capability): bool
{
    return true;
}

function wp_doing_cron(): bool
{
    return false;
}

require_once $pluginDirectory . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$manager = new JooosiFon\Licensing\Manager();
$assert(
    get_option(JooosiFon\Licensing\Manager::OPTION_NAME) === 'legacy-key',
    'The legacy Rosua license key was not migrated to the SDK option.'
);
$assert($manager->is_activated(), 'The cached legacy activation status was not migrated.');

$integration = JooosiFon\Licensing\Manager::get_integration_args();
$assert($integration['id'] === 'jooosi-fon', 'The SDK integration ID is incorrect.');
$assert($integration['item_id'] === 18, 'The SDK integration item ID is incorrect.');
$assert($integration['option_name'] === 'jooosi_fon_license_key', 'The SDK option name is incorrect.');

$activation = $manager->activate('new-key');
$assert(! is_wp_error($activation), 'A valid SDK activation request failed.');
$assert($manager->get_license_key() === 'new-key', 'The activated license key was not saved.');
$assert($manager->is_activated(), 'The activated SDK license status was not saved.');
$assert(
    strpos($GLOBALS['jooosi_fon_test_remote_request'][0], 'edd_action=activate_license') !== false,
    'The SDK activation action was not sent.'
);
$assert(
    strpos($GLOBALS['jooosi_fon_test_remote_request'][0], 'item_id=18') !== false,
    'The SDK activation item ID was not sent.'
);

$GLOBALS['jooosi_fon_test_remote_response']['body'] = wp_json_encode([
    'success' => true,
    'license' => 'deactivated',
]);
$deactivation = $manager->deactivate();
$assert(! is_wp_error($deactivation), 'A valid SDK deactivation request failed.');
$assert($manager->get_license_key() === '', 'The deactivated license key was not removed.');
$assert(! $manager->is_activated(), 'The deactivated SDK license still appears active.');
$manager_after_deactivation = new JooosiFon\Licensing\Manager();
$assert(
    $manager_after_deactivation->get_license_key() === '',
    'A deliberately removed SDK license was incorrectly re-imported from the legacy option.'
);

$GLOBALS['jooosi_fon_test_remote_response'] = new WP_Error('http_request_failed', 'Network unavailable.');
$failed_activation = $manager->activate('unreachable-key');
$assert(is_wp_error($failed_activation), 'A failed SDK request did not return a WP_Error.');
$assert(
    $failed_activation->get_error_code() === 'license_server_unavailable',
    'A failed SDK request returned the wrong error code.'
);

$plugin = JooosiFon\Plugin::get_instance();
$boot_sdk = new ReflectionMethod($plugin, 'boot_license_sdk');

if (PHP_VERSION_ID < 80100) {
    $boot_sdk->setAccessible(true);
}

if ($bootTiming === 'late') {
    do_action('after_setup_theme');
    $boot_sdk->invoke($plugin);
} else {
    $boot_sdk->invoke($plugin);
    do_action('after_setup_theme');
}

$registry = EasyDigitalDownloads\Updater\Registry::instance();
$assert(
    $registry->offsetExists(JooosiFon\Licensing\Manager::INTEGRATION_ID),
    'The plugin was not registered through the official EDD SDK registry hook.'
);

$has_duplicate_registry_updater = false;

foreach ($GLOBALS['jooosi_fon_test_hooks']['init'][10] ?? [] as [$callback]) {
    if (is_array($callback)
        && $callback[0] instanceof EasyDigitalDownloads\Updater\Handlers\Plugin
        && $callback[1] === 'auto_updater') {
        $has_duplicate_registry_updater = true;
    }
}

$assert(
    ! $has_duplicate_registry_updater,
    'The registry updater was not replaced by the wp_override-compatible SDK updater.'
);

echo "Licensing manager tests passed.\n";
