<?php
namespace App\Helpers;

class Translator {
    private static $translations = [];
    private static $currentLang = 'en';

    public static function load($lang = 'en') {
        self::$currentLang = $lang;
        $base = __DIR__ . "/../../lang/en.php";
        $english = file_exists($base) ? require $base : [];
        $file = __DIR__ . "/../../lang/{$lang}.php";

        // English first, then the chosen language on top: a key that is not
        // translated yet shows in English instead of as a raw key.
        $chosen = ($lang !== 'en' && file_exists($file)) ? require $file : [];
        self::$translations = array_merge($english, is_array($chosen) ? $chosen : []);
    }

    public static function get($key) {
        return isset(self::$translations[$key]) ? self::$translations[$key] : $key;
    }

    public static function getCurrentLang() {
        return self::$currentLang;
    }
}


