<?php
// The bundled price service runs in slim mode (Issue 6): the start scripts must turn it on,
// install only requirements-slim.txt, and CI must run its test. Source checks, no network.
// Run: php tests/check_slim_bundle.php
$root = dirname(__DIR__);
$fails = 0;
function check($name, $cond, $extra = '') { global $fails; fwrite(STDOUT, ($cond ? 'PASS ' : 'FAIL ') . $name . ($cond ? '' : "  -> $extra") . "\n"); if (!$cond) { $fails++; } }
function read($p) { global $root; $f = "$root/$p"; return is_file($f) ? file_get_contents($f) : ''; }

$ps1 = read('tools/start-mandi-service.ps1');
$sh  = read('tools/linux/start-mandi-service.sh');
$svc = read('tools/linux/mandi-service.service.example');
$ci  = read('.github/workflows/ci.yml');
$env = read('config/mandi-service.env.example');

$ps1Default = strpos($ps1, 'MANDI_SLIM = "1"');
$ps1EnvFile = strpos($ps1, 'config\mandi-service.env"');
check('PowerShell start script defaults MANDI_SLIM to 1', (bool) preg_match('/if \(-not \$env:MANDI_SLIM\) \{ \$env:MANDI_SLIM = "1" \}/', $ps1));
check('PowerShell sets the default BEFORE reading mandi-service.env (so the file can override)', $ps1Default !== false && $ps1EnvFile !== false && $ps1Default < $ps1EnvFile);
check('PowerShell installs requirements-slim.txt, not requirements.txt', strpos($ps1, '"requirements-slim.txt"') !== false && strpos($ps1, '"requirements.txt"') === false);
check('PowerShell braces balance', substr_count($ps1, '{') === substr_count($ps1, '}'));

$shDefault = strpos($sh, 'export MANDI_SLIM="${MANDI_SLIM:-1}"');
$shSource  = strpos($sh, '. "$ENV_FILE"');
check('bash start script defaults MANDI_SLIM to 1', $shDefault !== false);
check('bash sets the default BEFORE sourcing mandi-service.env', $shDefault !== false && $shSource !== false && $shDefault < $shSource);
check('bash installs requirements-slim.txt, not requirements.txt', strpos($sh, '-r "$SVC/requirements-slim.txt"') !== false && strpos($sh, '-r "$SVC/requirements.txt"') === false);

$svcSlim = strpos($svc, 'Environment=MANDI_SLIM=1');
check('systemd example sets MANDI_SLIM=1 before the env file', $svcSlim !== false && $svcSlim < strpos($svc, 'EnvironmentFile='));
check('env example documents MANDI_SLIM', strpos($env, 'MANDI_SLIM') !== false);
check('CI runs test_slim_mode', strpos($ci, 'test_slim_mode') !== false);

check('requirements-slim.txt exists in the bundle', is_file("$root/ai/mandi_service/requirements-slim.txt"));
check('test_slim_mode.py exists in the bundle', is_file("$root/ai/mandi_service/test_slim_mode.py"));
$slim = read('ai/mandi_service/requirements-slim.txt');
check('slim list has no twilio / libsql / python-multipart', !preg_match('/^(twilio|libsql|python-multipart)\s*$/mi', $slim));
check('slim list still has what the service needs (fastapi, lightgbm, scikit-learn)',
    (bool) (preg_match('/^fastapi\s*$/m', $slim) && preg_match('/^lightgbm\s*$/m', $slim) && preg_match('/^scikit-learn\s*$/m', $slim)));

fwrite(STDOUT, $fails === 0 ? "\nOK: the bundle runs the price service in slim mode.\n" : "\n$fails check(s) FAILED.\n");
exit($fails === 0 ? 0 : 1);
