<?php
// Guards the daily job scripts' handling of the trainer's quality gate: exit code 3 means "the new
// model did not beat 'price stays the same' and was NOT saved", which must be a warning, not a
// failed job (a failed job would also make the scheduled task look broken every time the gate
// does its work). Any other non-zero code must still fail. Offline: reads the scripts.
// (The Linux script was also run end to end with a stub trainer returning 0, 3 and 1.)
// Run: php tests/check_job_retrain_exit.php
$fail = 0;
function check($name, $ok) { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$ok) { $fail++; } }
$root = __DIR__ . '/..';

$trainer = file_get_contents($root . '/ai/mandi_service/train_forecast_model.py');
check('trainer uses exit code 3 for a rejected model', preg_match('/EXIT_MODEL_REJECTED\s*=\s*3\b/', $trainer) === 1);

$sh  = file_get_contents($root . '/tools/linux/mandi-daily-job.sh');
$ps1 = file_get_contents($root . '/tools/mandi-daily-job.ps1');
check('linux job: exit 3 is a warning', preg_match('/"\$rc" = 3 \]; then\s*#.*?\n(?:\s*#.*\n)*\s*log "WARNING:/s', $sh) === 1);
check('linux job: other non-zero codes still fail', preg_match('/elif \[ "\$rc" != 0 \]; then\s*fail "Retraining failed \(exit \$rc\)/', $sh) === 1);
check('linux job: exit code of the trainer is captured, not swallowed', strpos($sh, '|| rc=$?') !== false);
check('windows job: exit 3 is a warning', preg_match('/\$trainExit -eq 3\b.*?Log "WARNING:/s', $ps1) === 1);
check('windows job: other non-zero codes still fail', preg_match('/elseif \(\$trainExit -ne 0\) \{ throw "Retraining failed/', $ps1) === 1);

exit($fail ? 1 : 0);
