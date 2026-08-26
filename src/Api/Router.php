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
namespace JooosiFon\Api;

use JooosiFon\Api\Migrations\BrainstormForceCustomFonts;
use JooosiFon\Api\Migrations\BricksCustomFonts;
use JooosiFon\Api\Migrations\CustomAdobeFonts;
use JooosiFon\Api\Migrations\DpluginsFontHero;
use JooosiFon\Api\Migrations\ElementorProCustomFonts;
use JooosiFon\Api\Migrations\FontsPlugin;
use JooosiFon\Api\Migrations\UseAnyFont;
use JooosiFon\Api\Setting\AdobeFonts;
use JooosiFon\Api\Setting\Cache;
use JooosiFon\Api\Setting\License;
use JooosiFon\Api\Setting\Option;
use ReflectionClass;
use JooosiFonDeps\Symfony\Component\Finder\Finder;
/**
 * @todo Remove all legacy Yabe Webfont hook shims completely in Jooosi Fon 3.0.0.
 */
class Router
{
    /**
     * @var array<string, array{name: string, file_path: string|false, class_name: class-string<ApiInterface>}>
     */
    private array $apis = [];
    public function __construct()
    {
        $this->scan_apis();
        add_action('rest_api_init', function (): void {
            $this->register_apis();
        });
    }
    public function scan_apis(): void
    {
        /** @param array<string, class-string<ApiInterface>> $classes */
        $classes = apply_filters('f!jooosi/fon/api/router:classes', ['fonts' => \JooosiFon\Api\Font::class, 'setting/adobe-fonts' => AdobeFonts::class, 'setting/cache' => Cache::class, 'setting/license' => License::class, 'setting/option' => Option::class, 'migrations/bricks-custom-fonts' => BricksCustomFonts::class, 'migrations/brainstorm-force-custom-fonts' => BrainstormForceCustomFonts::class, 'migrations/custom-adobe-fonts' => CustomAdobeFonts::class, 'migrations/elementor-pro-custom-fonts' => ElementorProCustomFonts::class, 'migrations/font-hero-dplugins' => DpluginsFontHero::class, 'migrations/fonts-plugin' => FontsPlugin::class, 'migrations/use-any-font' => UseAnyFont::class]);
        $classes = apply_filters_deprecated('f!yabe/webfont/api/router:classes', [$classes], '2.1.0', 'f!jooosi/fon/api/router:classes');
        foreach ((array) $classes as $prefix => $className) {
            $this->addApi((string) $prefix, $className);
        }
        $this->scanLegacyExtensionDirectories();
    }
    public function register_apis(): void
    {
        /** @var array<string, array{name: string, file_path: string|false, class_name: class-string<ApiInterface>}> $apis */
        $apis = apply_filters('f!jooosi/fon/api/router:register_apis', $this->apis);
        $apis = apply_filters_deprecated('f!yabe/webfont/api/router:register_apis', [$apis], '2.1.0', 'f!jooosi/fon/api/router:register_apis');
        foreach ((array) $apis as $api) {
            $className = $api['class_name'] ?? null;
            if (!is_string($className) || !is_a($className, \JooosiFon\Api\ApiInterface::class, \true)) {
                do_action('a!jooosi/fon/api/router:invalid_api', $api);
                continue;
            }
            $reflector = new ReflectionClass($className);
            if (!$reflector->isInstantiable()) {
                do_action('a!jooosi/fon/api/router:invalid_api', $api);
                continue;
            }
            /** @var ApiInterface $instance */
            $instance = $reflector->newInstance();
            $instance->register_custom_endpoints();
        }
    }
    /**
     * @param mixed $className
     */
    private function addApi(string $prefix, $className): void
    {
        if ($prefix === '' || !is_string($className) || !is_a($className, \JooosiFon\Api\ApiInterface::class, \true)) {
            do_action('a!jooosi/fon/api/router:invalid_api', $prefix, $className);
            return;
        }
        $reflector = new ReflectionClass($className);
        if (!$reflector->isInstantiable()) {
            do_action('a!jooosi/fon/api/router:invalid_api', $prefix, $className);
            return;
        }
        $this->apis[$prefix] = ['name' => $prefix, 'file_path' => $reflector->getFileName(), 'class_name' => $className];
    }
    /**
     * Compatibility path for extensions still using the old Finder hook. It is
     * only activated when a listener exists and is never cached, preventing a
     * class name from surviving without its defining file on the next request.
     *
     * @todo Remove this legacy extension scan completely in Jooosi Fon 3.0.0.
     */
    private function scanLegacyExtensionDirectories(): void
    {
        $hook = 'a!jooosi/fon/api/router:before_scan';
        $legacyHook = 'a!yabe/webfont/api/router:before_scan';
        if (!has_action($hook) && !has_action($legacyHook)) {
            return;
        }
        $finder = new Finder();
        $finder->files()->in(__DIR__)->name('*.php');
        do_action($hook, $finder);
        do_action_deprecated($legacyHook, [$finder], '2.1.0', $hook);
        foreach ($finder as $file) {
            if ($file->isReadable()) {
                require_once $file->getPathname();
            }
        }
        foreach (get_declared_classes() as $className) {
            if (!is_a($className, \JooosiFon\Api\ApiInterface::class, \true)) {
                continue;
            }
            $reflector = new ReflectionClass($className);
            if (!$reflector->isInstantiable()) {
                continue;
            }
            /** @var ApiInterface $instance */
            $instance = $reflector->newInstanceWithoutConstructor();
            $this->addApi($instance->get_prefix(), $className);
        }
    }
}
