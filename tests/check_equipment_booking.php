<?php
// Guards the equipment double-booking fix (Issues 6 and 7): a machine must not be confirmed
// twice for the same day, and a failed status change must not be reported as success.
// Offline: reads the controller source (the behaviour itself was tested end to end against
// MariaDB, including a two-session race, when the fix was written).
// Run: php tests/check_equipment_booking.php
$fail = 0;
function check($name, $ok) { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$ok) { $fail++; } }

$src = file_get_contents(__DIR__ . '/../app/Controllers/EquipmentController.php');
preg_match('/function updateBookingStatus\(\).*?\n    }\n/s', $src, $u);
preg_match('/function bookEquipment\(\).*?\n    }\n/s', $src, $b);
$upd = $u[0] ?? ''; $book = $b[0] ?? '';

check('found updateBookingStatus and bookEquipment', $upd !== '' && $book !== '');
check('status change runs in a transaction', strpos($upd, 'beginTransaction') !== false && strpos($upd, 'commit()') !== false);
check('booking row is locked (FOR UPDATE) so simultaneous confirms take turns', strpos($upd, 'FOR UPDATE') !== false);
check('owner check stays in the query (e.owner_id = ?)', strpos($upd, 'e.owner_id = ?') !== false);
check('unknown booking is reported as not found, not success', strpos($upd, 'Booking not found.') !== false);
check('only forward status moves are allowed', strpos($upd, "'pending'   => ['confirmed', 'cancelled']") !== false
    && strpos($upd, "'confirmed' => ['completed', 'cancelled']") !== false);
check('confirming checks for another confirmed booking on the same day',
    preg_match("/status === 'confirmed' && \\\$this->hasConfirmedBooking/", $upd) === 1);
check('success is based on rows really changed', strpos($upd, 'rowCount() > 0') !== false);
check('success is no longer simply the result of execute()', strpos($upd, '$success = $stmt->execute') === false);
check('new request refused when the day is already confirmed', strpos($book, 'hasConfirmedBooking') !== false);
check('farmer cannot repeat an open request for the same day', strpos($book, "status IN ('pending', 'confirmed')") !== false);
check('hasConfirmedBooking only counts confirmed bookings', preg_match("/function hasConfirmedBooking.*status = 'confirmed'/s", $src) === 1);

exit($fail ? 1 : 0);
