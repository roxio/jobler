<?php
require_once __DIR__ . '/SiteSettings.php';

class Language {
    private static $translations = [];
    private static $locale = null;

    public static function available() {
        $dir = dirname(__DIR__) . '/lang';
        if (!is_dir($dir)) {
            return [];
        }

        $languages = [];
        foreach (glob($dir . '/*.php') as $file) {
            $code = basename($file, '.php');
            if (!preg_match('/^[A-Z]{2}_[a-z]{2}$/', $code)) {
                continue;
            }

            $dictionary = include $file;
            if (!is_array($dictionary)) {
                $dictionary = [];
            }

            $languages[$code] = [
                'code' => $code,
                'name' => $dictionary['locale.name'] ?? $code,
                'short' => $dictionary['locale.short'] ?? substr($code, 0, 2),
            ];
        }

        ksort($languages);
        return $languages;
    }

    public static function defaultLocale($context = 'frontend') {
        $settings = (new SiteSettings())->getSettings();
        $key = $context === 'admin' ? 'admin_default_language' : 'frontend_default_language';
        $locale = $settings[$key] ?? ($settings['default_language'] ?? 'PL_pl');
        return self::normalize($locale);
    }

    public static function current($context = 'frontend') {
        if (self::$locale !== null) {
            return self::$locale;
        }

        $available = self::available();
        $default = self::defaultLocale($context);

        if (isset($_GET['lang'])) {
            $requested = self::normalize($_GET['lang']);
            if (isset($available[$requested])) {
                $_SESSION['lang'] = $requested;
                setcookie('lang', $requested, time() + 60 * 60 * 24 * 365, '/');
                self::$locale = $requested;
                return self::$locale;
            }
        }

        $sessionLocale = isset($_SESSION['lang']) ? self::normalize($_SESSION['lang']) : '';
        if ($sessionLocale && isset($available[$sessionLocale])) {
            self::$locale = $sessionLocale;
            return self::$locale;
        }

        $cookieLocale = isset($_COOKIE['lang']) ? self::normalize($_COOKIE['lang']) : '';
        if ($cookieLocale && isset($available[$cookieLocale])) {
            self::$locale = $cookieLocale;
            return self::$locale;
        }

        if (isset($available[$default])) {
            self::$locale = $default;
        } else {
            $keys = array_keys($available);
            self::$locale = $keys[0] ?? 'PL_pl';
        }
        return self::$locale;
    }

    public static function setCurrent($locale) {
        self::$locale = self::normalize($locale);
        self::$translations = [];
    }

    public static function translate($key, array $replace = [], $fallback = null) {
        $locale = self::current();
        if (!isset(self::$translations[$locale])) {
            self::$translations[$locale] = self::load($locale);
        }

        $text = self::$translations[$locale][$key] ?? $fallback ?? $key;
        foreach ($replace as $name => $value) {
            $text = str_replace('{' . $name . '}', (string)$value, $text);
        }
        return $text;
    }

    public static function normalize($locale) {
        $locale = trim((string)$locale);
        if ($locale === '') {
            return 'PL_pl';
        }

        $locale = str_replace('-', '_', $locale);
        if (preg_match('/^([a-z]{2})_([a-z]{2})$/i', $locale, $match)) {
            return strtoupper($match[1]) . '_' . strtolower($match[2]);
        }

        $legacy = strtolower($locale);
        $map = [
            'pl' => 'PL_pl',
            'en' => 'EN_gb',
            'de' => 'DE_de',
            'ru' => 'RU_ru',
        ];
        return $map[$legacy] ?? $locale;
    }

    public static function urlWithLocale($locale, $url = null) {
        $locale = self::normalize($locale);
        $url = $url ?: ($_SERVER['REQUEST_URI'] ?? '/');
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        $query = [];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query['lang'] = $locale;
        return $path . '?' . http_build_query($query);
    }

    private static function load($locale) {
        $baseFile = dirname(__DIR__) . '/lang/PL_pl.php';
        $baseDictionary = is_file($baseFile) ? include $baseFile : [];
        if (!is_array($baseDictionary)) {
            $baseDictionary = [];
        }

        $file = dirname(__DIR__) . '/lang/' . self::normalize($locale) . '.php';
        if (!is_file($file)) {
            return $baseDictionary;
        }

        $dictionary = include $file;
        return is_array($dictionary) ? array_merge($baseDictionary, $dictionary) : $baseDictionary;
    }
}

if (!function_exists('__t')) {
    function __t($key, array $replace = [], $fallback = null) {
        return Language::translate($key, $replace, $fallback);
    }
}
?>
