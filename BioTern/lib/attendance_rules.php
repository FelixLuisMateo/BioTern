<?php

function attendance_action_to_column(string $clock_type): ?string
{
    $map = [
        'morning_in' => 'morning_time_in',
        'morning_out' => 'morning_time_out',
        'break_in' => 'break_time_in',
        'break_out' => 'break_time_out',
        'afternoon_in' => 'afternoon_time_in',
        'afternoon_out' => 'afternoon_time_out',
    ];

    return $map[$clock_type] ?? null;
}

function attendance_expected_previous(string $clock_type): ?string
{
    $order = [
        'morning_in',
        'morning_out',
        'break_in',
        'break_out',
        'afternoon_in',
        'afternoon_out',
    ];

    $idx = array_search($clock_type, $order, true);
    if ($idx === false || $idx === 0) {
        return null;
    }

    return $order[$idx - 1];
}

function attendance_time_to_minutes(?string $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return null;
    }

    return (int)date('G', $ts) * 60 + (int)date('i', $ts);
}

function attendance_validate_transition(array $record, string $clock_type, string $clock_time): array
{
    $target_column = attendance_action_to_column($clock_type);
    if ($target_column === null) {
        return ['ok' => false, 'message' => 'Invalid clock type.'];
    }

    if (!empty($record[$target_column])) {
        return ['ok' => false, 'message' => ucfirst(str_replace('_', ' ', $clock_type)) . ' already recorded.'];
    }

    // Prevent adding earlier sequence entries after later entries already exist.
    $order = ['morning_in','morning_out','break_in','break_out','afternoon_in','afternoon_out'];
    $current_idx = array_search($clock_type, $order, true);
    if ($current_idx !== false) {
        for ($i = $current_idx + 1; $i < count($order); $i++) {
            $later_col = attendance_action_to_column($order[$i]);
            if ($later_col !== null && !empty($record[$later_col])) {
                return ['ok' => false, 'message' => 'Cannot record ' . str_replace('_', ' ', $clock_type) . ' after ' . str_replace('_', ' ', $order[$i]) . ' already exists.'];
            }
        }
    }

    $prev_action = attendance_expected_previous($clock_type);
    if ($prev_action !== null) {
        $prev_col = attendance_action_to_column($prev_action);
        if ($prev_col !== null && empty($record[$prev_col])) {
            return ['ok' => false, 'message' => 'Please record ' . str_replace('_', ' ', $prev_action) . ' before ' . str_replace('_', ' ', $clock_type) . '.'];
        }
    }

    // Time must not move backwards against the last recorded action.
    $new_minutes = attendance_time_to_minutes($clock_time);
    if ($new_minutes === null) {
        return ['ok' => false, 'message' => 'Invalid clock time format.'];
    }

    $last_recorded_minutes = null;
    for ($i = 0; $i < count($order); $i++) {
        $col = attendance_action_to_column($order[$i]);
        if ($col !== null && !empty($record[$col])) {
            $minutes = attendance_time_to_minutes($record[$col]);
            if ($minutes !== null) {
                $last_recorded_minutes = $minutes;
            }
        }
    }

    if ($last_recorded_minutes !== null && $new_minutes < $last_recorded_minutes) {
        return ['ok' => false, 'message' => 'Time conflict: new entry is earlier than the last recorded event.'];
    }

    return ['ok' => true, 'message' => 'OK'];
}

function attendance_schedule_action_order(array $schedule): array
{
    $session = function_exists('section_schedule_normalize_session')
        ? section_schedule_normalize_session((string)($schedule['attendance_session'] ?? 'whole_day'))
        : strtolower(trim((string)($schedule['attendance_session'] ?? 'whole_day')));

    if ($session === 'morning_only') {
        return ['morning_in', 'morning_out'];
    }
    if ($session === 'afternoon_only') {
        return ['afternoon_in', 'afternoon_out'];
    }

    return ['morning_in', 'morning_out', 'afternoon_in', 'afternoon_out'];
}

function attendance_validate_scheduled_transition(array $record, string $clock_type, string $clock_time, array $schedule): array
{
    $dayType = function_exists('section_schedule_normalize_day_type')
        ? section_schedule_normalize_day_type((string)($schedule['day_type'] ?? 'class'))
        : strtolower(trim((string)($schedule['day_type'] ?? 'class')));
    if ($dayType === 'no_class') {
        return ['ok' => false, 'message' => 'No attendance punch is allowed today because your section schedule is marked no class.'];
    }

    $order = attendance_schedule_action_order($schedule);
    $targetColumn = attendance_action_to_column($clock_type);
    if ($targetColumn === null || !in_array($clock_type, $order, true)) {
        return ['ok' => false, 'message' => ucfirst(str_replace('_', ' ', $clock_type)) . ' is not part of your section schedule today.'];
    }
    if (!empty($record[$targetColumn])) {
        return ['ok' => false, 'message' => ucfirst(str_replace('_', ' ', $clock_type)) . ' already recorded.'];
    }

    $currentIndex = array_search($clock_type, $order, true);
    for ($i = $currentIndex + 1; $i < count($order); $i++) {
        $laterColumn = attendance_action_to_column($order[$i]);
        if ($laterColumn !== null && !empty($record[$laterColumn])) {
            return ['ok' => false, 'message' => 'Cannot record ' . str_replace('_', ' ', $clock_type) . ' after ' . str_replace('_', ' ', $order[$i]) . ' already exists.'];
        }
    }

    if ($currentIndex > 0) {
        $previousAction = $order[$currentIndex - 1];
        $previousColumn = attendance_action_to_column($previousAction);
        if ($previousColumn !== null && empty($record[$previousColumn])) {
            return ['ok' => false, 'message' => 'Please record ' . str_replace('_', ' ', $previousAction) . ' before ' . str_replace('_', ' ', $clock_type) . '.'];
        }
    }

    $newMinutes = attendance_time_to_minutes($clock_time);
    if ($newMinutes === null) {
        return ['ok' => false, 'message' => 'Invalid clock time format.'];
    }

    $previousMinutes = null;
    foreach ($order as $action) {
        if ($action === $clock_type) {
            break;
        }
        $column = attendance_action_to_column($action);
        if ($column !== null && !empty($record[$column])) {
            $minutes = attendance_time_to_minutes($record[$column]);
            if ($minutes !== null) {
                $previousMinutes = $minutes;
            }
        }
    }
    if ($previousMinutes !== null && $newMinutes < $previousMinutes) {
        return ['ok' => false, 'message' => 'Time conflict: new entry is earlier than the last recorded event.'];
    }

    return ['ok' => true, 'message' => 'OK'];
}

function attendance_scheduled_action_locked(array $record, string $clock_type, array $schedule): bool
{
    return empty(attendance_validate_scheduled_transition($record, $clock_type, date('H:i:s'), $schedule)['ok']);
}

function attendance_validate_full_record(array $record): array
{
    $pairs = [
        ['in' => 'morning_time_in', 'out' => 'morning_time_out', 'label' => 'Morning'],
        ['in' => 'break_time_in', 'out' => 'break_time_out', 'label' => 'Break'],
        ['in' => 'afternoon_time_in', 'out' => 'afternoon_time_out', 'label' => 'Afternoon'],
    ];

    foreach ($pairs as $pair) {
        $in_val = attendance_time_to_minutes($record[$pair['in']] ?? null);
        $out_val = attendance_time_to_minutes($record[$pair['out']] ?? null);
        if ($in_val !== null && $out_val !== null && $out_val < $in_val) {
            return ['ok' => false, 'message' => $pair['label'] . ' out cannot be earlier than in.'];
        }
    }

    $timeline = [
        $record['morning_time_in'] ?? null,
        $record['morning_time_out'] ?? null,
        $record['break_time_in'] ?? null,
        $record['break_time_out'] ?? null,
        $record['afternoon_time_in'] ?? null,
        $record['afternoon_time_out'] ?? null,
    ];

    $previous = null;
    foreach ($timeline as $time) {
        $mins = attendance_time_to_minutes($time);
        if ($mins === null) {
            continue;
        }
        if ($previous !== null && $mins < $previous) {
            return ['ok' => false, 'message' => 'Time entries overlap or are out of order.'];
        }
        $previous = $mins;
    }

    return ['ok' => true, 'message' => 'OK'];
}



