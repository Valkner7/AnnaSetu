<?php
namespace App\Helpers;

/**
 * Puts the price service's English text into the user's language (en, hi, pa).
 *
 * The price service writes the trend word, the confidence note and the
 * data-age notices in English. AnnaSetu already has Hindi and Punjabi
 * (lang/*.php), so the page text is translated here from the numbers and
 * dates the service sends, not by parsing its sentences. English keeps the
 * service's own wording. Pure functions: pass in a translate callable so
 * tests need no session or Translator state.
 */
class MandiText {

    /** "Bhindi(Ladies Finger)" -> "bhindi_ladies_finger" (lang key suffix). */
    public static function slug($name) {
        return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower((string) $name)), '_');
    }

    /** Translated crop name with the service's name in brackets, e.g. "आलू (Potato)". */
    public static function cropLabel($crop, callable $t) {
        $crop = (string) $crop;
        $key = 'mp_crop_' . self::slug($crop);
        $native = (string) $t($key);
        if ($native === $key || $native === '' || $native === $crop) {
            return $crop;
        }
        return $native . ' (' . $crop . ')';
    }

    /** Fill {name} placeholders. Unknown placeholders are left as they are. */
    public static function fill($text, array $vars) {
        return preg_replace_callback('/\{(\w+)\}/', function ($m) use ($vars) {
            return array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0];
        }, (string) $text);
    }

    /** Whole days from an ISO date to $today (ISO, defaults to today). Null if the date is unusable. */
    public static function daysSince($isoDate, $today = null) {
        if (!is_string($isoDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $isoDate)) { return null; }
        $then = strtotime($isoDate . ' 00:00:00 UTC');
        $now = strtotime(($today ?: gmdate('Y-m-d')) . ' 00:00:00 UTC');
        if ($then === false || $now === false) { return null; }
        return max(0, (int) floor(($now - $then) / 86400));
    }

    /**
     * Returns the forecast payload with trend_label always set, and for hi/pa
     * the confidence note and data notices rewritten from lang keys.
     * A notice is only rewritten when the service sent one (so it never
     * invents a warning) and when the numbers for it are available;
     * otherwise the service's English text is kept rather than guessing.
     */
    public static function localize(array $d, $lang, callable $t, $today = null) {
        $trend = (string) ($d['trend'] ?? '');
        $key = 'mp_trend_' . self::slug($trend);
        $label = $trend === '' ? '' : (string) $t($key);
        $d['trend_label'] = ($trend === '' || $label === $key) ? $trend : $label;

        if ($lang === 'en') {
            return $d;
        }

        if (!empty($d['confidence_note'])) {
            $note = (string) $t('mp_confidence_note');
            if ($note !== 'mp_confidence_note' && $note !== '') {
                $d['confidence_note'] = $note;
            }
        }

        if (!empty($d['data_age_warning'])) {
            $date = $d['dataset_latest_date'] ?? ($d['latest_date'] ?? null);
            $days = $d['data_age_days'] ?? null;
            if (is_string($date) && $date !== '' && is_numeric($days)) {
                $d['data_age_warning'] = self::fill($t('mp_data_age_warning'), ['date' => $date, 'days' => (int) $days]);
            }
        }

        if (!empty($d['data_note'])) {
            $date = $d['latest_date'] ?? null;
            $days = self::daysSince($date, $today);
            if ($days !== null) {
                $d['data_note'] = self::fill($t('mp_data_note'), ['date' => $date, 'days' => $days]);
            }
        }
        return $d;
    }
}
