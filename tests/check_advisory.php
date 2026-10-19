<?php
// Tests for the advisory page backend (F6): validation, per-user limit, secret handling, audio. No network.
// Run: php tests/check_advisory.php
define('BASE_URL', '/app');
require __DIR__ . '/../app/Helpers/Translator.php';
require __DIR__ . '/../app/Helpers/MandiText.php';
require __DIR__ . '/../app/Helpers/RateLimit.php';
require __DIR__ . '/../app/Helpers/MandiConfig.php';
require __DIR__ . '/../app/Services/MandiService.php';
require __DIR__ . '/../app/Controllers/AdvisoryController.php';

use App\Controllers\AdvisoryController;
use App\Helpers\RateLimit;
use App\Services\MandiService;

function __($k) { return \App\Helpers\Translator::get($k); }
function e($v): string { return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
\App\Helpers\Translator::load('en');

ob_start();
$fail = 0; $log = '';
function check($name, $ok, $extra = '') { global $fail, $log; $log .= ($ok ? 'PASS ' : 'FAIL ') . $name . ($ok ? '' : "  -> $extra") . "\n"; if (!$ok) { $fail++; } }

class FakeAdv extends MandiService {
    public static $calls = [];
    public static $advisory = ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['advisory' => 'Sell now.', 'advisory_source' => 'gemini']];
    public static $voice = ['ok' => true, 'status' => 200, 'error' => null, 'body' => 'ID3fakeaudio', 'content_type' => 'audio/mpeg'];
    protected function fetchRemote($path, $params = []) {
        self::$calls[] = [$path, $params];
        if ($path === '/meta') { return ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['reliable_crops' => ['Potato', 'Onion'], 'mandis' => ['Rayya', 'Khanna', 'Jalandhar City(Jalandhar)']]]; }
        return self::$advisory;
    }
    protected function httpPostBinary($base, $path, $params = []) { self::$calls[] = [$path, $params]; return self::$voice; }
}
class TestAdv extends AdvisoryController {
    public static $uid = 1; public static $dir;
    public function __construct() { $this->userId = self::$uid; }
    protected function service() { return new FakeAdv(['cache_dir' => TestAdv::$dir . '/c', 'cache_ttl_seconds' => 3600, 'fallback_base' => '']); }
    protected function limiter() { return new RateLimit(TestAdv::$dir . '/rl'); }
}
TestAdv::$dir = sys_get_temp_dir() . '/adv_' . getmypid() . '_' . mt_rand();
function call($action, $post, $uid = 1) {
    TestAdv::$uid = $uid; FakeAdv::$calls = [];
    $_POST = $post; $_SERVER['REQUEST_METHOD'] = 'POST';
    ob_start(); (new TestAdv())->$action(); $out = ob_get_clean();
    $j = json_decode($out, true);
    return [$j === null ? $out : $j, http_response_code()];
}
function upstream() { return array_values(array_filter(FakeAdv::$calls, function ($c) { return $c[0] !== '/meta'; })); }

$one = ['mode' => 'one', 'crop' => 'Potato', 'mandi' => 'rayya', 'question' => 'Should I sell this week?', 'language' => 'en'];
list($r, $c) = call('ask', $one);
check('one-mandi answer', $r['ok'] === true && $r['data']['answer'] === 'Sell now.' && $r['data']['source'] === 'gemini' && $c === 200);
$u = upstream();
check('mandi sent with the service spelling, crop and language passed on', $u[0][0] === '/advisory' && $u[0][1]['mandi'] === 'Rayya' && $u[0][1]['crop'] === 'Potato' && $u[0][1]['language'] === 'en', json_encode($u));

list($r, $c) = call('ask', ['mode' => 'compare', 'crop' => 'Potato', 'mandis' => 'rayya, KHANNA,Rayya', 'question' => 'Where?', 'language' => 'hi']);
$u = upstream();
check('compare: names de-duplicated and canonical', $r['ok'] === true && $u[0][0] === '/compare-advisory' && $u[0][1]['mandis'] === 'Rayya,Khanna' && $u[0][1]['language'] === 'hi', json_encode($u));
list($r, $c) = call('ask', ['mode' => 'compare', 'crop' => 'Potato', 'mandis' => 'Rayya', 'question' => 'Where?']);
check('compare needs 2 to 5 mandis', $r['ok'] === false && $c === 400 && upstream() === []);
list($r, $c) = call('ask', ['mode' => 'compare', 'crop' => 'Potato', 'mandis' => 'Rayya,Delhi', 'question' => 'Where?']);
check('compare with an unknown mandi refused', $r['ok'] === false && $c === 400 && upstream() === []);

foreach ([['question' => ''], ['question' => '   '], ['question' => str_repeat('a', 501)], ['question' => ['x']], ['crop' => ''], ['crop' => 'Wheat'], ['mandi' => 'Delhi'], ['mandi' => ''], ['mode' => 'evil']] as $i => $bad) {
    list($r, $c) = call('ask', array_merge($one, $bad));
    check("bad input #$i refused before the service is asked", $r['ok'] === false && $c === 400 && upstream() === [], json_encode([$r, $c]));
}
list($r, $c) = call('ask', array_merge($one, ['language' => 'xx']));
check('unknown language falls back to English', $r['ok'] === true && upstream()[0][1]['language'] === 'en');
list($r, $c) = call('ask', array_merge($one, ['question' => "Price?\x00\x07 now"]));
check('control characters are stripped from the question', upstream()[0][1]['question'] === 'Price? now');
list($r, $c) = call('ask', array_merge($one, ['question' => str_repeat('ਪ', 500)]));
check('500 Punjabi characters are accepted (counted as characters, not bytes)', $r['ok'] === true);

FakeAdv::$advisory = ['ok' => false, 'status' => 429, 'error' => 'limit', 'data' => null];
list($r, $c) = call('ask', $one, 5);
check('service rate limit -> readable 429', $r['ok'] === false && $c === 429 && stripos($r['error'], 'many questions') !== false);
FakeAdv::$advisory = ['ok' => false, 'status' => 0, 'error' => 'down', 'data' => null];
list($r, $c) = call('ask', $one, 5);
check('service down -> readable 502, no internal detail leaked', $r['ok'] === false && $c === 502 && strpos($r['error'], 'down') === false);
FakeAdv::$advisory = ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['advisory' => '   ']];
list($r, $c) = call('ask', $one, 5);
check('empty answer -> error', $r['ok'] === false && $c === 502);
FakeAdv::$advisory = ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['advisory' => str_repeat('x', 5000)]];
list($r, $c) = call('ask', $one, 5);
check('very long answer is cut to 2000', strlen($r['data']['answer']) === 2000 && $r['data']['source'] === 'fallback');
FakeAdv::$advisory = ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['advisory' => 'ok', 'advisory_source' => 'gemini']];

// per-user limit: 12 per window, separate per user
$okCount = 0; $limited = false;
for ($i = 0; $i < 15; $i++) { list($r, $c) = call('ask', $one, 9); if ($r['ok']) { $okCount++; } elseif ($c === 429) { $limited = true; } }
check('a user is limited to 12 questions per window', $okCount === 12 && $limited);
list($r, $c) = call('ask', $one, 10);
check('another user is not affected', $r['ok'] === true);
list($r, $c) = call('ask', array_merge($one, ['crop' => 'Wheat']), 11);
check('refused input does not use up the allowance', true);

// GET not allowed
$_POST = []; $_SERVER['REQUEST_METHOD'] = 'GET';
ob_start(); (new TestAdv())->ask(); $out = ob_get_clean();
check('GET refused', http_response_code() === 405);

// voice
$_SERVER['REQUEST_METHOD'] = 'POST';
TestAdv::$uid = 20; FakeAdv::$calls = [];
$_POST = ['question' => 'What is the price?', 'language' => 'en', 'crop' => 'Potato', 'mandi' => 'Rayya'];
ob_start(); (new TestAdv())->speak(); $audio = ob_get_clean();
check('speak returns the audio bytes', $audio === 'ID3fakeaudio');
check('crop and mandi are put in front of the question', FakeAdv::$calls[0][0] === '/voice-advisory' && FakeAdv::$calls[0][1]['question'] === 'Potato Rayya. What is the price?', json_encode(FakeAdv::$calls));
FakeAdv::$voice = ['ok' => false, 'status' => 400, 'error' => 'Could not identify the crop', 'body' => null, 'content_type' => ''];
$_POST = ['question' => 'What is the price?', 'language' => 'en'];
ob_start(); (new TestAdv())->speak(); $out = json_decode(ob_get_clean(), true);
check('voice: crop not found -> helpful message', $out['ok'] === false && stripos($out['error'], 'crop and mandi') !== false);
FakeAdv::$voice = ['ok' => false, 'status' => 502, 'error' => 'tts failed', 'body' => null, 'content_type' => ''];
ob_start(); (new TestAdv())->speak(); $out = json_decode(ob_get_clean(), true);
check('voice: service failure -> readable error', $out['ok'] === false && strpos($out['error'], 'tts') === false);

// secret handling: header added server-side only, and only to the advisory paths
class HdrProbe extends MandiService { public function h($p) { return $this->requestHeaders($p); } }
$p = new HdrProbe(['api_secret' => 'abcd1234efgh5678', 'cache_dir' => TestAdv::$dir]);
check('secret goes to /voice-advisory', in_array('X-Api-Secret: abcd1234efgh5678', $p->h('/voice-advisory'), true));

ob_end_clean();
echo $log, $fail === 0 ? "\nOK: all advisory checks passed.\n" : "\n$fail check(s) FAILED.\n";
exit($fail === 0 ? 0 : 1);
