<?php

namespace MifestaBrowserData;

/**
 * Phone, tablet and crawler detection for the current HTTP request.
 *
 * The device database comes from mobiledetect/mobiledetectlib.
 * Releases 2.8, 3.74 and 4.x do not share a constructor, so the client
 * picks one from the installed library.
 *
 * The detector is cached only while the HTTP_* values in $_SERVER stay the
 * same. PHP-FPM and other long-lived workers replace $_SERVER on the next
 * request, and the next call builds a new detector.
 */
class BrowserData
{
    /**
     * @var object|null
     */
    private static $detect;

    /**
     * @var string|null
     */
    private static $fingerprint;

    /**
     * @var string|null
     */
    private static $libraryClass;

    /**
     * @var bool|null
     */
    private static $modern;

    private function __construct()
    {
    }

    /**
     * @param string|null $userAgent
     * @return string
     */
    public static function setUserAgent($userAgent)
    {
        $_SERVER['HTTP_USER_AGENT'] = (string) $userAgent;
        self::forget();

        return self::userAgent();
    }

    /**
     * Replace the current HTTP_* headers. Null keeps $_SERVER and only drops
     * the cached detector.
     *
     * @param array|null $httpHeaders
     * @return void
     */
    public static function setHttpHeaders($httpHeaders = null)
    {
        if ($httpHeaders === null) {
            self::forget();
            return;
        }

        foreach (array_keys($_SERVER) as $key) {
            if (is_string($key) && strncmp($key, 'HTTP_', 5) === 0) {
                unset($_SERVER[$key]);
            }
        }

        foreach ($httpHeaders as $key => $value) {
            if (!is_string($key) || strncmp($key, 'HTTP_', 5) !== 0 || !is_scalar($value)) {
                continue;
            }
            $_SERVER[$key] = (string) $value;
        }

        self::forget();
    }

    /**
     * @return bool
     */
    public static function isMobile()
    {
        if (Crawler::isMobileBot(self::userAgent())) {
            return true;
        }

        return self::libraryBool('isMobile');
    }

    /**
     * @return bool
     */
    public static function isTablet()
    {
        return self::libraryBool('isTablet');
    }

    /**
     * Crawler that is not a mobile crawler.
     *
     * @return bool
     */
    public static function isBot()
    {
        return Crawler::isBot(self::userAgent());
    }

    /**
     * Any crawler, including mobile crawlers.
     *
     * @return bool
     */
    public static function isAnyBot()
    {
        return Crawler::matches(self::userAgent());
    }

    /**
     * Mobile, tablet, bot and anybot are case-insensitive.
     * Any other key is a device, OS or browser rule from mobiledetectlib.
     *
     * @param string $key
     * @return bool
     */
    public static function is($key)
    {
        switch (strtolower(trim((string) $key))) {
            case 'mobile':
                return self::isMobile();
            case 'tablet':
                return self::isTablet();
            case 'bot':
                return self::isBot();
            case 'anybot':
                return self::isAnyBot();
            case 'mobilebot':
                return Crawler::isMobileBot(self::userAgent());
            default:
                return self::libraryIs($key);
        }
    }

    /**
     * @param string $regex
     * @param string|null $userAgent
     * @return bool
     */
    public static function match($regex, $userAgent = null)
    {
        $ua = $userAgent === null ? self::userAgent() : (string) $userAgent;

        return (bool) self::detect()->match($regex, $ua);
    }

    /**
     * @param string $propertyName
     * @param string|null $type VERSION_TYPE_STRING ("text") or VERSION_TYPE_FLOAT ("float")
     * @return string|float|false
     */
    public static function version($propertyName, $type = null)
    {
        $detect = self::detect();
        if ($type === null || $type === '') {
            return $detect->version((string) $propertyName);
        }

        return $detect->version((string) $propertyName, (string) $type);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        $name = (string) $name;

        if ($name === 'getScriptVersion') {
            return self::scriptVersion();
        }
        if ($name === 'getCfHeaders') {
            return self::cloudFrontHeaders();
        }
        if ($name === 'setCfHeaders') {
            $headers = isset($arguments[0]) ? $arguments[0] : null;

            return self::applyCloudFrontHeaders($headers);
        }
        if ($name === 'setDetectionType') {
            return null;
        }
        if ($name === 'mobileGrade') {
            $detect = self::detect();
            if (method_exists($detect, 'mobileGrade')) {
                return call_user_func(array($detect, 'mobileGrade'));
            }
            throw new \BadMethodCallException(
                'mobileGrade() was removed from mobiledetectlib and is not available in this release.'
            );
        }
        if ($name === 'getUserAgents' || $name === 'getMobileDetectionRules' || $name === 'getMobileDetectionRulesExtended') {
            return self::rulesAlias($name);
        }

        $detect = self::detect();
        if (self::canCall($detect, $name, false)) {
            return call_user_func_array(array($detect, $name), $arguments);
        }

        $class = self::libraryClass();
        if (self::canCall($class, $name, true)) {
            return call_user_func_array(array($class, $name), $arguments);
        }

        throw new \BadMethodCallException('Call to undefined method ' . __CLASS__ . '::' . $name . '()');
    }

    /**
     * @return object
     */
    private static function detect()
    {
        $headers = self::httpHeaders();
        $fingerprint = md5(serialize($headers));
        if (self::$detect !== null && self::$fingerprint === $fingerprint) {
            return self::$detect;
        }

        $class = self::libraryClass();
        if (self::usesModernConstructor($class)) {
            $detect = new $class();
            if (count($headers) > 0) {
                $detect->setHttpHeaders($headers);
            }
            if (method_exists($detect, 'hasUserAgent') && !$detect->hasUserAgent()) {
                $detect->setUserAgent('');
            }
        } else {
            // A non-empty User-Agent stops 2.8 and 3.74 from reading
            // X-Device-User-Agent and the other device UA headers.
            // Leave the argument empty when one of those headers is present
            // so the library concatenates them. A lone User-Agent is still
            // passed through, which keeps CloudFront from replacing it.
            $ua = null;
            if (!self::hasAlternateUserAgent($headers)
                && isset($headers['HTTP_USER_AGENT'])
                && $headers['HTTP_USER_AGENT'] !== ''
            ) {
                $ua = $headers['HTTP_USER_AGENT'];
            }
            $detect = new $class($headers, $ua);
        }

        self::$detect = $detect;
        self::$fingerprint = $fingerprint;

        return self::$detect;
    }

    /**
     * @param array $headers
     * @return bool
     */
    private static function hasAlternateUserAgent($headers)
    {
        $keys = array(
            'HTTP_X_DEVICE_USER_AGENT',
            'HTTP_X_ORIGINAL_USER_AGENT',
            'HTTP_X_OPERAMINI_PHONE_UA',
            'HTTP_X_SKYFIRE_PHONE',
            'HTTP_X_BOLT_PHONE_UA',
            'HTTP_DEVICE_STOCK_UA',
            'HTTP_X_UCBROWSER_DEVICE_UA',
        );
        foreach ($keys as $key) {
            if (isset($headers[$key]) && $headers[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string
     */
    private static function libraryClass()
    {
        if (self::$libraryClass !== null) {
            return self::$libraryClass;
        }

        if (class_exists('Mobile_Detect', true)) {
            self::$libraryClass = 'Mobile_Detect';

            return self::$libraryClass;
        }
        if (class_exists('Detection\\MobileDetect', true)) {
            self::$libraryClass = 'Detection\\MobileDetect';

            return self::$libraryClass;
        }

        throw new \RuntimeException(
            'Install mobiledetect/mobiledetectlib before using ' . __CLASS__ . '.'
        );
    }

    /**
     * MobileDetect 4.x takes a PSR-16 cache. 2.8 and 3.74 take headers and a user agent.
     *
     * @param string $class
     * @return bool
     */
    private static function usesModernConstructor($class)
    {
        if (self::$modern !== null) {
            return self::$modern;
        }
        if ($class === 'Mobile_Detect') {
            self::$modern = false;

            return false;
        }

        $constructor = (new \ReflectionClass($class))->getConstructor();
        $parameters = $constructor ? $constructor->getParameters() : array();
        if (count($parameters) === 0) {
            self::$modern = true;

            return true;
        }

        $type = $parameters[0]->getType();
        if ($type === null) {
            self::$modern = false;

            return false;
        }

        $typeName = method_exists($type, 'getName')
            ? strtolower($type->getName())
            : strtolower(ltrim((string) $type, '?'));
        self::$modern = $typeName !== 'array';

        return self::$modern;
    }

    /**
     * @param string $method
     * @return bool
     */
    private static function libraryBool($method)
    {
        try {
            return (bool) self::detect()->{$method}();
        } catch (\Exception $exception) {
            if (self::userAgent() === '') {
                return false;
            }
            throw $exception;
        }
    }

    /**
     * @param string $key
     * @return bool
     */
    private static function libraryIs($key)
    {
        try {
            return (bool) self::detect()->is($key);
        } catch (\Exception $exception) {
            if (self::userAgent() === '') {
                return false;
            }
            throw $exception;
        }
    }

    /**
     * @return string
     */
    private static function scriptVersion()
    {
        $class = self::libraryClass();
        if (method_exists($class, 'getScriptVersion')) {
            return (string) call_user_func(array($class, 'getScriptVersion'));
        }

        return (string) self::detect()->getVersion();
    }

    /**
     * @return array
     */
    private static function cloudFrontHeaders()
    {
        $detect = self::detect();
        if (method_exists($detect, 'getCfHeaders')) {
            $headers = $detect->getCfHeaders();

            return is_array($headers) ? $headers : array();
        }

        $found = array();
        foreach ($detect->getCloudFrontHttpHeaders() as $name) {
            $value = $detect->getHttpHeader($name);
            if ($value !== null && $value !== '') {
                $found[$name] = $value;
            }
        }

        return $found;
    }

    /**
     * @param array|null $cfHeaders
     * @return bool
     */
    private static function applyCloudFrontHeaders($cfHeaders)
    {
        if (!is_array($cfHeaders) || count($cfHeaders) === 0) {
            $cfHeaders = $_SERVER;
        }

        $applied = false;
        foreach ($cfHeaders as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }
            if (substr(strtolower($key), 0, 16) === 'http_cloudfront_') {
                $_SERVER[strtoupper($key)] = (string) $value;
                $applied = true;
            }
        }

        self::forget();

        return $applied;
    }

    /**
     * @param string $name
     * @return array
     */
    private static function rulesAlias($name)
    {
        $detect = self::detect();
        if ($name === 'getUserAgents') {
            if (self::canCall($detect, 'getUserAgents', false) || self::canCall(self::libraryClass(), 'getUserAgents', true)) {
                $target = self::canCall(self::libraryClass(), 'getUserAgents', true) ? self::libraryClass() : $detect;

                return call_user_func(array($target, 'getUserAgents'));
            }

            return $detect->getBrowsers();
        }

        if (self::canCall($detect, $name, false)) {
            return call_user_func(array($detect, $name));
        }
        if (self::canCall(self::libraryClass(), $name, true)) {
            return call_user_func(array(self::libraryClass(), $name));
        }

        return $detect->getRules();
    }

    /**
     * @param object|string $target
     * @param string $name
     * @param bool $static
     * @return bool
     */
    private static function canCall($target, $name, $static)
    {
        if (!method_exists($target, $name)) {
            return false;
        }

        $method = new \ReflectionMethod($target, $name);
        if (!$method->isPublic()) {
            return false;
        }

        return $static ? $method->isStatic() : !$method->isStatic();
    }

    /**
     * @return array<string, string>
     */
    private static function httpHeaders()
    {
        $headers = array();
        foreach ($_SERVER as $key => $value) {
            if (!is_string($key) || strncmp($key, 'HTTP_', 5) !== 0 || !is_scalar($value)) {
                continue;
            }
            $headers[$key] = (string) $value;
        }
        ksort($headers);

        return $headers;
    }

    /**
     * User-Agent headers joined in a stable order. CloudFront's rewritten
     * library value is not used here, so a crawler token in the original
     * header is still visible.
     *
     * @return string
     */
    private static function userAgent()
    {
        $headers = self::httpHeaders();
        $keys = array(
            'HTTP_USER_AGENT',
            'HTTP_X_DEVICE_USER_AGENT',
            'HTTP_X_ORIGINAL_USER_AGENT',
            'HTTP_X_OPERAMINI_PHONE_UA',
            'HTTP_X_UCBROWSER_DEVICE_UA',
            'HTTP_DEVICE_STOCK_UA',
        );
        $parts = array();
        foreach ($keys as $key) {
            if (isset($headers[$key]) && $headers[$key] !== '') {
                $parts[] = $headers[$key];
            }
        }

        return trim(implode(' ', $parts));
    }

    /**
     * @return void
     */
    private static function forget()
    {
        self::$detect = null;
        self::$fingerprint = null;
    }
}
