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
namespace JooosiFonDeps;

/**
 * Plugin constants.
 *
 * @since 2.0.0
 */
class JOOOSI_FON
{
    /**
     * @var string
     */
    public const FILE = __DIR__ . '/jooosi-fon.php';
    /**
     * @var string
     */
    public const VERSION = '1.1.1';
    /**
     * @var string
     */
    public const WP_OPTION = 'jooosi_fon';
    /**
     * Existing installations used this prefix for persisted options and the
     * original admin-page slug.
     *
     * @todo Remove this legacy option prefix completely in Jooosi Fon 3.0.0.
     *
     * @var string
     */
    public const LEGACY_WP_OPTION = 'yabe_webfont';
    /**
     * @var string
     */
    public const DB_TABLE_PREFIX = 'jooosi_fon';
    /**
     * The text domain should use the literal string 'jooosi-fon' as the text domain.
     * This constant is used for reference only and should not be used as the actual text domain.
     *
     * @var string
     */
    public const TEXT_DOMAIN = 'jooosi-fon';
    /**
     * @var array
     */
    public const EDD_STORE = ['store_url' => 'https://jooo.si', 'item_id' => 18];
    /**
     * @var string
     */
    public const REST_NAMESPACE = 'jooosi-fon/v1';
    /**
     * @var string
     */
    public const PLUGIN_URI = 'https://fon.jooo.si';
    /**
     * @var
     */
    public const USER_AGENTS = [
        'WOFF2' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:43.0) Gecko/20100101 Firefox/43.0',
        'WOFF' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:38.0) Gecko/20100101 Firefox/38.0',
        'TTF' => 'Mozilla/5.0 (Unknown; Linux x86_64) AppleWebKit/538.1 (KHTML, like Gecko) Safari/538.1 Daum/4.1',
        // 'CURRENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/115.0',
        'CURRENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:139.0) Gecko/20100101 Firefox/139.0',
    ];
}
/**
 * Plugin constants.
 *
 * @since 2.0.0
 */
\class_alias('JooosiFonDeps\JOOOSI_FON', 'JOOOSI_FON', \false);
/**
 * The former global constants class is retained for 2.x integrations.
 *
 * @todo Remove this legacy class alias completely in Jooosi Fon 3.0.0.
 */
if (!\class_exists('JooosiFonDeps\YABE_WEBFONT', \false)) {
    \class_alias(\JooosiFonDeps\JOOOSI_FON::class, 'JooosiFonDeps\YABE_WEBFONT');
}
/**
 * The developer documentation exposed this utility class before the rebrand.
 *
 * @todo Remove this legacy class alias completely in Jooosi Fon 3.0.0.
 */
if (!\class_exists('JooosiFonDeps\Yabe\Webfont\Utils\Font', \false)) {
    \class_alias(\JooosiFon\Utils\Font::class, 'JooosiFonDeps\Yabe\Webfont\Utils\Font');
}
