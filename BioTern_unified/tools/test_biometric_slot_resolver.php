<?php
require_once __DIR__ . '/biometric_auto_import.php';

function assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected: ' . var_export($expected, true) . ' Actual: ' . var_export($actual, true));
    }
}

$machineConfig = [
    'slotAdvanceMinimumMinutes' => 10,
];

$morningOnlySchedule = [
    'attendance_session' => 'whole_day',
    'schedule_time_in' => '08:00',
    'schedule_time_out' => '12:00',
    'late_after_time' => '',
];

$row = [
    'attendance_date' => '2026-03-26',
    'morning_time_in' => '',
    'morning_time_out' => '',
    'afternoon_time_in' => '',
    'afternoon_time_out' => '',
];
assert_same(
    'afternoon_time_in',
    resolveAttendanceColumnForPunch($row, 1, null, '13:18:19', $machineConfig, $morningOnlySchedule),
    'First afternoon punch should start afternoon_time_in for a morning-only schedule.'
);

$row = [
    'attendance_date' => '2026-03-26',
    'morning_time_in' => '10:58:46',
    'morning_time_out' => '12:35:11',
    'afternoon_time_in' => '',
    'afternoon_time_out' => '',
];
assert_same(
    'afternoon_time_in',
    resolveAttendanceColumnForPunch($row, 1, null, '13:03:24', $machineConfig, $morningOnlySchedule),
    'Post-noon continuation should fill afternoon_time_in after completed morning slots.'
);

$row = [
    'attendance_date' => '2026-03-26',
    'morning_time_in' => '10:58:46',
    'morning_time_out' => '12:35:11',
    'afternoon_time_in' => '13:03:24',
    'afternoon_time_out' => '',
];
assert_same(
    'afternoon_time_out',
    resolveAttendanceColumnForPunch($row, 1, null, '17:54:52', $machineConfig, $morningOnlySchedule),
    'Late continuation should close afternoon_time_out once afternoon_time_in exists.'
);

$wholeDaySchedule = [
    'attendance_session' => 'whole_day',
    'schedule_time_in' => '08:00',
    'schedule_time_out' => '19:00',
    'late_after_time' => '08:00',
];

$row = [
    'attendance_date' => '2026-03-26',
    'morning_time_in' => '11:22:45',
    'morning_time_out' => '12:30:20',
    'afternoon_time_in' => '',
    'afternoon_time_out' => '',
];
assert_same(
    'afternoon_time_in',
    resolveAttendanceColumnForPunch($row, 1, null, '13:03:27', $machineConfig, $wholeDaySchedule),
    'Whole-day schedule should assign the next post-lunch punch to afternoon_time_in.'
);

echo "Biometric slot resolver tests passed.\n";
