<?php

require_once __DIR__ . '/ops_helpers.php';

function eval_table_has_column(mysqli $conn, string $table, string $column): bool
{
    $table_safe = str_replace('`', '``', $table);
    $stmt = $conn->prepare("SHOW COLUMNS FROM `{$table_safe}` LIKE ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

function ensure_evaluation_unlock_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS evaluation_unlocks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            internship_id INT NULL,
            is_unlocked TINYINT(1) NOT NULL DEFAULT 0,
            unlocked_at DATETIME NULL,
            unlocked_by INT NULL,
            unlock_source VARCHAR(30) NOT NULL DEFAULT 'manual',
            unlock_notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function get_evaluation_unlock_state(mysqli $conn, int $student_id): array
{
    ensure_evaluation_unlock_table($conn);
    $stmt = $conn->prepare("SELECT * FROM evaluation_unlocks WHERE student_id = ? LIMIT 1");
    if (!$stmt) {
        return ['is_unlocked' => false];
    }
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return ['is_unlocked' => false];
    }
    return [
        'is_unlocked' => ((int)($row['is_unlocked'] ?? 0) === 1),
        'unlocked_at' => (string)($row['unlocked_at'] ?? ''),
        'unlock_source' => (string)($row['unlock_source'] ?? ''),
        'unlock_notes' => (string)($row['unlock_notes'] ?? ''),
        'unlocked_by' => (int)($row['unlocked_by'] ?? 0),
        'internship_id' => (int)($row['internship_id'] ?? 0),
    ];
}

function upsert_evaluation_unlock(
    mysqli $conn,
    int $student_id,
    int $internship_id,
    bool $is_unlocked,
    int $actor_user_id,
    string $source,
    string $notes = ''
): bool {
    ensure_evaluation_unlock_table($conn);
    $flag = $is_unlocked ? 1 : 0;
    $stmt = $conn->prepare(
        "INSERT INTO evaluation_unlocks
            (student_id, internship_id, is_unlocked, unlocked_at, unlocked_by, unlock_source, unlock_notes, created_at, updated_at)
         VALUES (?, NULLIF(?, 0), ?, CASE WHEN ? = 1 THEN NOW() ELSE NULL END, NULLIF(?, 0), ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            internship_id = VALUES(internship_id),
            is_unlocked = VALUES(is_unlocked),
            unlocked_at = CASE WHEN VALUES(is_unlocked) = 1 THEN NOW() ELSE NULL END,
            unlocked_by = VALUES(unlocked_by),
            unlock_source = VALUES(unlock_source),
            unlock_notes = VALUES(unlock_notes),
            updated_at = NOW()"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param(
        'iiiiiss',
        $student_id,
        $internship_id,
        $flag,
        $flag,
        $actor_user_id,
        $source,
        $notes
    );
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

function set_evaluation_unlock_override(mysqli $conn, int $student_id, bool $is_unlocked, int $actor_user_id, string $notes = ''): array
{
    $internship_id = 0;
    if (table_exists($conn, 'internships')) {
        $stmt = $conn->prepare("SELECT id FROM internships WHERE student_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $student_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $internship_id = (int)($row['id'] ?? 0);
        }
    }
    $ok = upsert_evaluation_unlock(
        $conn,
        $student_id,
        $internship_id,
        $is_unlocked,
        $actor_user_id,
        'manual',
        $notes !== '' ? $notes : ($is_unlocked ? 'Manual unlock by coordinator/admin' : 'Manual lock by coordinator/admin')
    );
    return ['ok' => $ok, 'is_unlocked' => $is_unlocked, 'internship_id' => $internship_id];
}

function notify_once(mysqli $conn, int $user_id, string $title, string $message): void
{
    if ($user_id <= 0 || !table_exists($conn, 'notifications')) {
        return;
    }
    $has_title = eval_table_has_column($conn, 'notifications', 'title');
    $has_message = eval_table_has_column($conn, 'notifications', 'message');
    $has_type = eval_table_has_column($conn, 'notifications', 'type');
    $has_data = eval_table_has_column($conn, 'notifications', 'data');
    $has_created = eval_table_has_column($conn, 'notifications', 'created_at');

    if ($has_title && $has_message && $has_created) {
        $check = $conn->prepare(
            "SELECT id FROM notifications
             WHERE user_id = ? AND title = ? AND message = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
             LIMIT 1"
        );
        if ($check) {
            $check->bind_param('iss', $user_id, $title, $message);
            $check->execute();
            $dup = $check->get_result()->fetch_assoc();
            $check->close();
            if ($dup) {
                return;
            }
        }
    }
    create_notification($conn, $user_id, $title, $message);
}

function evaluate_and_finalize_student(mysqli $conn, int $student_id, int $actor_user_id = 0): array
{
    $state = [
        'eligible' => false,
        'auto_unlocked' => false,
        'internship_completed' => false,
        'is_unlocked' => false,
        'reasons' => [],
        'docs' => [],
        'attendance' => ['pending' => 0, 'rejected' => 0],
        'internship_id' => 0,
    ];

    $student_stmt = $conn->prepare(
        "SELECT id, user_id, first_name, last_name, assignment_track, internal_total_hours_remaining, external_total_hours_remaining
         FROM students WHERE id = ? LIMIT 1"
    );
    if (!$student_stmt) {
        $state['reasons'][] = 'Student lookup failed.';
        return $state;
    }
    $student_stmt->bind_param('i', $student_id);
    $student_stmt->execute();
    $student = $student_stmt->get_result()->fetch_assoc();
    $student_stmt->close();
    if (!$student) {
        $state['reasons'][] = 'Student not found.';
        return $state;
    }

    $internship = null;
    if (table_exists($conn, 'internships')) {
        $intern_stmt = $conn->prepare(
            "SELECT id, status, required_hours, rendered_hours, completion_percentage, supervisor_id, coordinator_id
             FROM internships
             WHERE student_id = ?
             ORDER BY updated_at DESC, id DESC
             LIMIT 1"
        );
        if ($intern_stmt) {
            $intern_stmt->bind_param('i', $student_id);
            $intern_stmt->execute();
            $internship = $intern_stmt->get_result()->fetch_assoc();
            $intern_stmt->close();
        }
    }
    if (!$internship) {
        $state['reasons'][] = 'No internship record found.';
        return $state;
    }
    $state['internship_id'] = (int)($internship['id'] ?? 0);

    $track = strtolower((string)($student['assignment_track'] ?? 'internal'));
    $remaining = ($track === 'external')
        ? (int)($student['external_total_hours_remaining'] ?? 0)
        : (int)($student['internal_total_hours_remaining'] ?? 0);
    if ($remaining > 0) {
        $state['reasons'][] = 'Remaining hours are not yet zero.';
    }

    $required = (float)($internship['required_hours'] ?? 0);
    $rendered = (float)($internship['rendered_hours'] ?? 0);
    $completion = (float)($internship['completion_percentage'] ?? 0);
    if ($completion <= 0 && $required > 0) {
        $completion = ($rendered / $required) * 100;
    }
    if ($completion < 100) {
        $state['reasons'][] = 'Internship completion is below 100%.';
    }

    $pending = 0;
    $rejected = 0;
    if (table_exists($conn, 'attendances')) {
        $att_stmt = $conn->prepare(
            "SELECT
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count
             FROM attendances
             WHERE student_id = ?"
        );
        if ($att_stmt) {
            $att_stmt->bind_param('i', $student_id);
            $att_stmt->execute();
            $att = $att_stmt->get_result()->fetch_assoc();
            $att_stmt->close();
            $pending = (int)($att['pending_count'] ?? 0);
            $rejected = (int)($att['rejected_count'] ?? 0);
        }
    }
    $state['attendance']['pending'] = $pending;
    $state['attendance']['rejected'] = $rejected;
    if ($pending > 0 || $rejected > 0) {
        $state['reasons'][] = 'Pending/rejected attendance exists.';
    }

    $open_session = false;
    if (table_exists($conn, 'attendances')) {
        $today = date('Y-m-d');
        $open_stmt = $conn->prepare(
            "SELECT morning_time_in, morning_time_out, afternoon_time_in, afternoon_time_out
             FROM attendances
             WHERE student_id = ? AND attendance_date = ?
             ORDER BY id DESC LIMIT 1"
        );
        if ($open_stmt) {
            $open_stmt->bind_param('is', $student_id, $today);
            $open_stmt->execute();
            $row = $open_stmt->get_result()->fetch_assoc();
            $open_stmt->close();
            if ($row) {
                $open_session = (!empty($row['morning_time_in']) && empty($row['morning_time_out']))
                    || (!empty($row['afternoon_time_in']) && empty($row['afternoon_time_out']));
            }
        }
    }
    if ($open_session) {
        $state['reasons'][] = 'Student is currently clocked in.';
    }

    $user_id = (int)($student['user_id'] ?? 0);
    $docs = ['application' => false, 'endorsement' => false, 'moa' => false, 'dau_moa' => false];
    if ($user_id > 0) {
        $doc_tables = [
            'application' => 'application_letter',
            'endorsement' => 'endorsement_letter',
            'moa' => 'moa',
            'dau_moa' => 'dau_moa',
        ];
        foreach ($doc_tables as $key => $table) {
            if (!table_exists($conn, $table)) {
                continue;
            }
            $tbl = str_replace('`', '``', $table);
            $doc_stmt = $conn->prepare("SELECT id FROM `{$tbl}` WHERE user_id = ? LIMIT 1");
            if ($doc_stmt) {
                $doc_stmt->bind_param('i', $user_id);
                $doc_stmt->execute();
                $docs[$key] = (bool)$doc_stmt->get_result()->fetch_assoc();
                $doc_stmt->close();
            }
        }
    }
    $state['docs'] = $docs;
    $docs_ok = $docs['application'] && $docs['endorsement'] && ($docs['moa'] || $docs['dau_moa']);
    if (!$docs_ok) {
        $state['reasons'][] = 'Required OJT documents are incomplete.';
    }

    $state['eligible'] = empty($state['reasons']);
    $existing_unlock = get_evaluation_unlock_state($conn, $student_id);
    $state['is_unlocked'] = (bool)($existing_unlock['is_unlocked'] ?? false);

    if (!$state['eligible']) {
        return $state;
    }

    $state['internship_completed'] = false;
    $intern_status = strtolower((string)($internship['status'] ?? ''));
    if ($intern_status !== 'completed' && $state['internship_id'] > 0) {
        $complete_stmt = $conn->prepare(
            "UPDATE internships
             SET status = 'completed',
                 completion_percentage = CASE WHEN completion_percentage < 100 THEN 100 ELSE completion_percentage END,
                 rendered_hours = CASE WHEN required_hours > rendered_hours THEN required_hours ELSE rendered_hours END,
                 updated_at = NOW()
             WHERE id = ?"
        );
        if ($complete_stmt) {
            $iid = (int)$state['internship_id'];
            $complete_stmt->bind_param('i', $iid);
            $state['internship_completed'] = (bool)$complete_stmt->execute();
            $complete_stmt->close();
        }
    }

    if (!$state['is_unlocked']) {
        $state['auto_unlocked'] = upsert_evaluation_unlock(
            $conn,
            $student_id,
            (int)$state['internship_id'],
            true,
            $actor_user_id,
            'auto',
            'Auto unlocked after OJT completion gate passed.'
        );
        $state['is_unlocked'] = $state['auto_unlocked'];
    }

    if ($state['auto_unlocked']) {
        $student_name = trim((string)($student['first_name'] ?? '') . ' ' . (string)($student['last_name'] ?? ''));
        if ($student_name === '') {
            $student_name = 'Student #' . $student_id;
        }
        $title = 'Evaluation Form Unlocked';
        $message = $student_name . ' has completed OJT requirements. Evaluation form is now unlocked.';
        if ($user_id > 0) {
            notify_once($conn, $user_id, $title, 'Your evaluation form is now unlocked. Please coordinate with your supervisor.');
        }
        $sup_user_id = (int)($internship['supervisor_id'] ?? 0);
        $coor_user_id = (int)($internship['coordinator_id'] ?? 0);
        if ($sup_user_id > 0) {
            notify_once($conn, $sup_user_id, $title, $message);
        }
        if ($coor_user_id > 0 && $coor_user_id !== $sup_user_id) {
            notify_once($conn, $coor_user_id, $title, $message);
        }
    }

    return $state;
}


