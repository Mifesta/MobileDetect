<?php

namespace MifestaBrowserData;

/**
 * Distinguishes crawlers from phones whose names contain "bot".
 *
 * MobileDetect 3.74 and 4.x no longer ship the Bot and MobileBot rule lists.
 * The same checks are used on every PHP line so the result does not depend
 * on which major of mobiledetectlib is installed.
 */
class Crawler
{
    /**
     * Device brands. Removed before the generic search so CUBOT is not a crawler.
     *
     * @var string[]
     */
    private static $devices = array('cubot', 'irobot');

    /**
     * Names from the MobileDetect 2.8 Bot list that do not end in bot or spider.
     *
     * @var string[]
     */
    private static $named = array(
        'facebookexternalhit',
        'google-amphtml',
        's~amp-validator',
        'google keyword suggestion',
        'ia_archiver',
        'ezooms',
        'twikle',
        'unwindfetchor',
        'yandeximages',
        'pingdom',
        'contentkingapp',
        'semrush',
        'metadatascraper',
    );

    /**
     * Crawlers that should count as mobile even when the UA has no device token.
     *
     * @var string[]
     */
    private static $mobileNames = array(
        'googlebot-mobile',
        'adsbot-google-mobile',
        'yandexmobilebot',
        'yahooseeker/m1a1-r2d2',
    );

    /**
     * @param string $userAgent
     * @return bool
     */
    public static function matches($userAgent)
    {
        return self::isCrawler(self::normalize($userAgent));
    }

    /**
     * A crawler that is presenting a phone or tablet.
     *
     * @param string $userAgent
     * @return bool
     */
    public static function isMobileBot($userAgent)
    {
        $ua = self::normalize($userAgent);
        if ($ua === '' || !self::isCrawler($ua)) {
            return false;
        }

        foreach (self::$mobileNames as $name) {
            if (strpos($ua, $name) !== false) {
                return true;
            }
        }

        return (bool) preg_match(
            '/(?:^|[^a-z0-9])(?:mobile|android|iphone|ipad|ipod|windows phone)(?:[^a-z0-9]|$)/',
            $ua
        );
    }

    /**
     * A crawler that is not presenting a phone or tablet.
     *
     * @param string $userAgent
     * @return bool
     */
    public static function isBot($userAgent)
    {
        return self::matches($userAgent) && !self::isMobileBot($userAgent);
    }

    /**
     * @param string $userAgent
     * @return string
     */
    private static function normalize($userAgent)
    {
        $ua = strtolower(trim((string) $userAgent));
        if ($ua === '') {
            return '';
        }

        foreach (self::$devices as $device) {
            $ua = str_replace($device, ' ', $ua);
        }

        return $ua;
    }

    /**
     * @param string $ua already normalized
     * @return bool
     */
    private static function isCrawler($ua)
    {
        if ($ua === '') {
            return false;
        }

        $tokens = array_merge(self::$named, self::$mobileNames);
        foreach ($tokens as $token) {
            if (strpos($ua, $token) !== false) {
                return true;
            }
        }

        return (bool) preg_match(
            '/(?:^|[^a-z0-9])(?:[a-z0-9_.-]*bot|spider|crawler|slurp|yeti|ichiro)(?:[^a-z0-9]|$)/',
            $ua
        );
    }
}
