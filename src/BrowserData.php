<?php

namespace MifestaBrowserData;

/**
 * Class ClientBrowserData
 *
 * @method static array getHttpHeaders()
 * @method static array getHttpHeader(string $header)
 * @method static array getMobileHeaders()
 * @method static array getUaHttpHeaders()
 * @method static boolean setCfHeaders(array|null $cfHeaders = null)
 * @method static array getCfHeaders()
 * @method static string|null getUserAgent()
 * @method static string|null setDetectionType($type = null)
 * @method static string|null getMatchingRegex()
 * @method static array|null getMatchesArray()
 * @method static array getMobileDetectionRulesExtended()
 * @method static array getRules()
 * @method static array checkHttpHeadersForMobile()
 * @method static boolean isTablet()
 * @method static boolean match(string $regex)
 * @method static float prepareVersionNo(string $ver)
 * @method static string|float version(string $propertyName, string|null $type = null)
 * @method static string mobileGrade()
 * @method static string getScriptVersion()
 * @method static array getPhoneDevices()
 * @method static array getTabletDevices()
 * @method static array getUserAgents()
 * @method static array getBrowsers()
 * @method static array getUtilities()
 * @method static array getMobileDetectionRules()
 * @method static array getOperatingSystems()
 * @method static array getProperties()
 *
 * @see \Mobile_Detect
 */
abstract class BrowserData
{
    /**
     * Public methods from \Mobile_Detect
     *
     * @var array
     */
    private static $valid_functions = [
        'getHttpHeaders',
        'getHttpHeader',
        'getMobileHeaders',
        'getUaHttpHeaders',
        'setCfHeaders',
        'getCfHeaders',
        'getUserAgent',
        'setDetectionType',
        'getMatchingRegex',
        'getMatchesArray',
        'getMobileDetectionRulesExtended',
        'getRules',
        'checkHttpHeadersForMobile',
        'isTablet',
        'match',
        'prepareVersionNo',
        'version',
        'mobileGrade',
    ];

    /**
     * Public static methods from \Mobile_Detect
     *
     * @var array
     */
    private static $valid_static_functions = [
        'getScriptVersion',
        'getPhoneDevices',
        'getTabletDevices',
        'getUserAgents',
        'getBrowsers',
        'getUtilities',
        'getMobileDetectionRules',
        'getOperatingSystems',
        'getProperties',
    ];

    /**
     * @var \Mobile_Detect
     */
    private static $_mobile_detect_class;

    /**
     * Magic method __callStatic for ClientBrowserData
     *
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     * @throws \Exception
     */
    public static function __callStatic($name, $arguments)
    {
        if (in_array($name, self::$valid_functions)) {
            return call_user_func_array([self::getMobileDetect(), $name], $arguments);
        } elseif (in_array($name, self::$valid_static_functions)) {
            return call_user_func_array([\Mobile_Detect::class, $name], $arguments);
        } else {
            throw new \Exception('Call to undefined methods '.__CLASS__.'::'.$name.'()');
        }
    }

    /**
     * Check if the device is mobile.
     * Returns true if any type of mobile device detected, including special ones
     *
     * @return bool
     */
    public static function isMobile()
    {
        return self::getMobileDetect()->is('MobileBot') || self::getMobileDetect()->isMobile();
    }

    /**
     * This method checks for a 'Bot' property in the userAgent.
     *
     * @return bool
     */
    public static function isAnyBot()
    {
        return preg_match('#(bot|spider|yeti|ichiro)#is', self::getMobileDetect()->getUserAgent() ?? '') || self::getMobileDetect()->is('MobileBot') || self::getMobileDetect()->is('Bot');
    }

    /**
     * This method checks for a 'Bot' property in the userAgent.
     * Does not include mobile bots.
     *
     * @return bool
     */
    public static function isBot()
    {
        $classMobileDetect = self::getMobileDetect();
        $user_agent = $classMobileDetect->getUserAgent() ?? '';
        return $classMobileDetect->is('Bot') || preg_match('#(yeti|ichiro)#is', $user_agent) || (preg_match('#(bot|spider)#is', $user_agent) && ! $classMobileDetect->is('MobileBot'));
    }

    /**
     * This method checks for a certain property in the userAgent.
     *
     * @param string $key
     *
     * @return bool|int|null
     */
    public static function is($key)
    {
        switch ($key) {
            case 'Bot':
                return self::isBot();
                break;
            case 'Mobile':
                return self::isMobile();
                break;
            case 'AnyBot':
                return self::isAnyBot();
                break;
            default:
                return self::getMobileDetect()->is($key);
        }
    }

    /**
     * Get class \Mobile_Detect
     *
     * @return \Mobile_Detect
     */
    private static function getMobileDetect()
    {
        if (! self::$_mobile_detect_class) {
            self::$_mobile_detect_class = new \Mobile_Detect($_SERVER, $_SERVER['HTTP_USER_AGENT'] ?: null);
        }

        return self::$_mobile_detect_class;
    }
}
