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
namespace JooosiFon\Builder\Builderius;

use JooosiFon\Admin\AdminPage;
use JooosiFon\Builder\BuilderInterface;
use JooosiFon\Utils\Font;
/**
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 *
 * @todo Built-in Google Fonts are not disabled.
 */
class Main implements BuilderInterface
{
    public function __construct()
    {
        add_action('admin_menu', static fn() => AdminPage::add_redirect_submenu_page('builderius'), 1000001);
        add_action('wp_enqueue_scripts', fn() => $this->enqueue_scripts(), 1000001);
    }
    public function get_name(): string
    {
        return 'builderius';
    }
    public function enqueue_scripts()
    {
        if (!wp_script_is('builderius-builder', 'registered')) {
            return;
        }
        $fonts = Font::get_fonts();
        $inline_script_content = <<<JS
    const moduleTypography = builderiusBackend.settingsList?.module?.advanced?.typography?.find((item) => item.name === 'font');

    if (moduleTypography) {
        moduleTypography.options.fontType.values.push('Jooosi Fon');
        moduleTypography.options.genericFamily.values['Jooosi Fon'] = ['Jooosi Fon'];
        moduleTypography.options.fontFamily.values['Jooosi Fon.Jooosi Fon'] = jooosiFonBuilderiusFonts;
    }

    const GlobalTypography = builderiusBackend.settingsList?.global?.advanced?.typography?.find((item) => item.name === 'font');

    if (GlobalTypography) {
        GlobalTypography.options.fontType.values.push('Jooosi Fon');
        GlobalTypography.options.genericFamily.values['Jooosi Fon'] = ['Jooosi Fon'];
        GlobalTypography.options.fontFamily.values['Jooosi Fon.Jooosi Fon'] = jooosiFonBuilderiusFonts;
    }

    const templateTypography = builderiusBackend.settingsList?.template?.advanced?.typography?.find((item) => item.name === 'font');

    if (templateTypography) {
        templateTypography.options.fontType.values.push('Jooosi Fon');
        templateTypography.options.genericFamily.values['Jooosi Fon'] = ['Jooosi Fon'];
        templateTypography.options.fontFamily.values['Jooosi Fon.Jooosi Fon'] = jooosiFonBuilderiusFonts;
    }
JS;
        wp_add_inline_script('builderius-builder', 'const jooosiFonBuilderiusFonts = ' . json_encode(array_column($fonts, 'family'), \JSON_THROW_ON_ERROR), 'before');
        wp_add_inline_script('builderius-builder', $inline_script_content, 'before');
    }
}
