<?php
require_once __DIR__ . '/../lib/attendance_rules.php';

function assert_true($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$empty = [
    'morning_time_in' => null,
    'morning_time_out' => null,
    'break_time_in' => null,
    'break_time_out' => null,
    'afternoon_time_in' => null,
    'afternoon_time_out' => null,
];

$v1 = attendance_validate_transition($empty, 'morning_in', '08:00');
assert_true($v1['ok'] === true, 'morning_in should be valid on empty record');

$v2 = attendance_validate_transition($empty, 'afternoon_in', '13:00');
assert_true($v2['ok'] === false, 'afternoon_in should be invalid before morning sequence');

$record = $empty;
$record['afternoon_time_in'] = '13:00:00';
$v3 = attendance_validate_transition($record, 'morning_in', '08:00');
assert_true($v3['ok'] === false, 'morning_in after afternoon_in should be rejected');

$bad = [
    'morning_time_in' => '08:00:00',
    'morning_time_out' => '07:59:00',
    'break_time_in' => null,
    'break_time_out' => null,
    'afternoon_time_in' => null,
    'afternoon_time_out' => null,
];
$v4 = attendance_validate_full_record($bad);
assert_true($v4['ok'] === false, 'morning_out earlier than morning_in should be invalid');

echo "Attendance rule tests passed.\n";

