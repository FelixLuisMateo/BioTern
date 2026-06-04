<?php
// Lightweight dashboard fallback data for homepage.php.
include_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/access_scope.php';

$dashboard_data = [
    'total_students' => 0,
    'active_students' => 0,
    'biometric_students' => 0,
    'total_internships' => 0,
    'active_internships' => 0,
    'completed_internships' => 0,
    'pending_approvals' => 0,
    'approved_attendances' => 0,
    'rejected_attendances' => 0,
    'today_attendance' => 0,
];

// Keep compatibility for legacy template references.
$recent_students = [];
$recent_attendances = [];

if (!isset($conn) || !($conn instanceof mysqli)) {
    return;
}

$dashboard_count = static function (string $sql) use ($conn): int {
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return (int)($row['count'] ?? 0);
};

try {
    $today = date('Y-m-d');
    $isSupervisor = biotern_scope_current_role() === 'supervisor';
    $scopeSi = biotern_scope_student_sql($conn, 's', 'i');
    $studentsFrom = $isSupervisor
        ? "students s LEFT JOIN internships i ON i.student_id = s.id AND i.deleted_at IS NULL"
        : "students s";
    $attendancesFrom = $isSupervisor
        ? "attendances a LEFT JOIN students s ON a.student_id = s.id LEFT JOIN internships i ON i.student_id = s.id AND i.deleted_at IS NULL"
        : "attendances a";
    $internshipsFrom = $isSupervisor
        ? "internships i LEFT JOIN students s ON s.id = i.student_id"
        : "internships i";
    $studentScopeWhere = $isSupervisor ? $scopeSi : '1 = 1';
    $internshipScopeWhere = $isSupervisor ? $scopeSi : '1 = 1';

    $dashboard_data['total_students'] = $dashboard_count("SELECT COUNT(DISTINCT s.id) AS count FROM {$studentsFrom} WHERE {$studentScopeWhere}");
    $dashboard_data['active_students'] = $dashboard_count(
        "SELECT COUNT(DISTINCT s.id) AS count
         FROM students s
         INNER JOIN internships i ON i.student_id = s.id
         WHERE i.status = 'ongoing'
           AND {$scopeSi}"
    );
    $dashboard_data['biometric_students'] = $dashboard_count("SELECT COUNT(DISTINCT s.id) AS count FROM {$studentsFrom} WHERE s.biometric_registered = 1 AND {$studentScopeWhere}");
    $dashboard_data['total_internships'] = $dashboard_count("SELECT COUNT(DISTINCT i.id) AS count FROM {$internshipsFrom} WHERE {$internshipScopeWhere}");
    $dashboard_data['active_internships'] = $dashboard_count("SELECT COUNT(DISTINCT i.id) AS count FROM {$internshipsFrom} WHERE i.status = 'ongoing' AND {$internshipScopeWhere}");
    $dashboard_data['completed_internships'] = $dashboard_count("SELECT COUNT(DISTINCT i.id) AS count FROM {$internshipsFrom} WHERE i.status = 'completed' AND {$internshipScopeWhere}");
    $dashboard_data['pending_approvals'] = $dashboard_count("SELECT COUNT(DISTINCT a.id) AS count FROM {$attendancesFrom} WHERE a.status = 'pending' AND {$studentScopeWhere}");
    $dashboard_data['approved_attendances'] = $dashboard_count("SELECT COUNT(DISTINCT a.id) AS count FROM {$attendancesFrom} WHERE a.status = 'approved' AND {$studentScopeWhere}");
    $dashboard_data['rejected_attendances'] = $dashboard_count("SELECT COUNT(DISTINCT a.id) AS count FROM {$attendancesFrom} WHERE a.status = 'rejected' AND {$studentScopeWhere}");
    $dashboard_data['today_attendance'] = $dashboard_count("SELECT COUNT(DISTINCT a.id) AS count FROM {$attendancesFrom} WHERE DATE(a.attendance_date) = '{$today}' AND {$studentScopeWhere}");

    $recent_attendance_result = $conn->query(
        "SELECT
            a.id,
            a.student_id,
            a.attendance_date,
            a.morning_time_in,
            a.morning_time_out,
            a.status,
            a.created_at,
            s.first_name,
            s.last_name,
            s.student_id AS student_num
         FROM attendances a
         LEFT JOIN students s ON a.student_id = s.id
         " . ($isSupervisor ? "LEFT JOIN internships i ON i.student_id = s.id AND i.deleted_at IS NULL" : "") . "
         WHERE {$studentScopeWhere}
         ORDER BY a.created_at DESC
         LIMIT 10"
    );

    if ($recent_attendance_result && $recent_attendance_result->num_rows > 0) {
        while ($row = $recent_attendance_result->fetch_assoc()) {
            $recent_attendances[] = $row;
        }
    }
} catch (Throwable $e) {
    error_log('Dashboard data fallback fetch error: ' . $e->getMessage());
}
