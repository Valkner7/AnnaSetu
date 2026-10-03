<?php
namespace App\Helpers;

class Translator {
    private static $translations = [];
    private static $currentLang = 'en';

    public static function load($lang = 'en') {
        self::$currentLang = $lang;
        $file = __DIR__ . "/../../lang/{$lang}.php";
        
        if (file_exists($file)) {
            self::$translations = require $file;
        } else {
            // Fallback to English
            $fallback = __DIR__ . "/../../lang/en.php";
            if (file_exists($fallback)) {
                self::$translations = require $fallback;
            } else {
                self::$translations = [];
            }
        }
    }

    public static function get($key) {
        return isset(self::$translations[$key]) ? self::$translations[$key] : $key;
    }

    public static function getCurrentLang() {
        return self::$currentLang;
    }
}


