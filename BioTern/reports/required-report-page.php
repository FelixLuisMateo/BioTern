<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
biotern_boot_session(isset($conn) ? $conn : null);

$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($currentRole !== 'admin') {
    header('Location: homepage.php');
    exit;
}

function rr_esc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rr_table_exists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function rr_column_exists(mysqli $conn, string $table, string $column): bool
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function rr_rows(mysqli $conn, string $sql): array
{
    $rows = [];
    $res = $conn->query($sql);
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function rr_count(mysqli $conn, string $sql): int
{
    $res = $conn->query($sql);
    if ($res instanceof mysqli_result) {
        $row = $res->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }
    return 0;
}

function rr_request_int(string $key): int
{
    return max(0, (int)($_GET[$key] ?? 0));
}

function rr_request_choice(string $key, array $allowed, string $default = ''): string
{
    $value = strtolower(trim((string)($_GET[$key] ?? $default)));
    return in_array($value, $allowed, true) ? $value : $default;
}

function rr_request_date(string $key): string
{
    $value = trim((string)($_GET[$key] ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function rr_where(array $conditions): string
{
    return $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
}

function rr_course_options(mysqli $conn): array
{
    return rr_rows($conn, 'SELECT id, name FROM courses ORDER BY name');
}

function rr_section_options(mysqli $conn): array
{
    $hasSections = rr_table_exists($conn, 'sections');
    if (!$hasSections) {
        return [];
    }
    $hasCode = rr_column_exists($conn, 'sections', 'code');
    $hasName = rr_column_exists($conn, 'sections', 'name');
    $labelSelect = $hasCode && $hasName
        ? "COALESCE(NULLIF(TRIM(code), ''), NULLIF(TRIM(name), ''), CONCAT('Section ', id))"
        : ($hasName ? "COALESCE(NULLIF(TRIM(name), ''), CONCAT('Section ', id))" : "COALESCE(NULLIF(TRIM(code), ''), CONCAT('Section ', id))");

    return rr_rows($conn, "SELECT id, {$labelSelect} AS name FROM sections ORDER BY name");
}

function rr_server_filter_fields(mysqli $conn, string $reportKey): array
{
    $courseOptions = static function () use ($conn): array {
        return array_map(static function (array $row): array {
            return ['value' => (string)(int)($row['id'] ?? 0), 'label' => (string)($row['name'] ?? '')];
        }, rr_course_options($conn));
    };
    $sectionOptions = static function () use ($conn): array {
        return array_map(static function (array $row): array {
            return ['value' => (string)(int)($row['id'] ?? 0), 'label' => (string)($row['name'] ?? '')];
        }, rr_section_options($conn));
    };

    return match ($reportKey) {
        'student-status' => [
            ['name' => 'course_id', 'label' => 'Course', 'type' => 'select', 'all' => 'All Courses', 'options' => $courseOptions()],
            ['name' => 'section_id', 'label' => 'Section', 'type' => 'select', 'all' => 'All Sections', 'options' => $sectionOptions()],
            ['name' => 'report_status', 'label' => 'Status', 'type' => 'select', 'all' => 'All Statuses', 'options' => [
                ['value' => 'ongoing', 'label' => 'Ongoing'],
                ['value' => 'finished', 'label' => 'Finished'],
                ['value' => 'not_started', 'label' => 'Not Started'],
            ]],
        ],
        'attendance-dtr' => [
            ['name' => 'date_from', 'label' => 'Date From', 'type' => 'date'],
            ['name' => 'date_to', 'label' => 'Date To', 'type' => 'date'],
            ['name' => 'attendance_type', 'label' => 'Type', 'type' => 'select', 'all' => 'Internal + External', 'options' => [
                ['value' => 'internal', 'label' => 'Internal'],
                ['value' => 'external', 'label' => 'External'],
            ]],
            ['name' => 'attendance_status', 'label' => 'Status', 'type' => 'select', 'all' => 'All Statuses', 'options' => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'rejected', 'label' => 'Rejected'],
            ]],
        ],
        'hours-completion' => [
            ['name' => 'course_id', 'label' => 'Course', 'type' => 'select', 'all' => 'All Courses', 'options' => $courseOptions()],
            ['name' => 'progress', 'label' => 'Progress', 'type' => 'select', 'all' => 'All Progress', 'options' => [
                ['value' => 'below_50', 'label' => 'Below 50%'],
                ['value' => '50_99', 'label' => '50% - 99%'],
                ['value' => 'completed', 'label' => 'Completed'],
            ]],
        ],
        'unassigned-students' => [
            ['name' => 'course_id', 'label' => 'Course', 'type' => 'select', 'all' => 'All Courses', 'options' => $courseOptions()],
            ['name' => 'section_id', 'label' => 'Section', 'type' => 'select', 'all' => 'All Sections', 'options' => $sectionOptions()],
        ],
        default => [],
    };
}

function rr_filter_current_value(string $name): string
{
    return trim((string)($_GET[$name] ?? ''));
}

function rr_current_page_name(): string
{
    $path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
    $name = basename($path);
    return $name !== '' ? $name : basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
}

function rr_applied_filter_summary(array $fields): array
{
    $summary = [];
    foreach ($fields as $field) {
        $name = (string)($field['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $current = rr_filter_current_value($name);
        if ($current === '') {
            continue;
        }

        $label = (string)($field['label'] ?? rr_filter_label($name));
        $valueLabel = $current;
        if (($field['type'] ?? '') === 'select') {
            foreach ((array)($field['options'] ?? []) as $option) {
                if ((string)($option['value'] ?? '') === $current) {
                    $valueLabel = (string)($option['label'] ?? $current);
                    break;
                }
            }
        }
        $summary[] = $label . ': ' . $valueLabel;
    }

    return $summary ?: ['No server filters applied'];
}

function rr_format_course_section_label(?string $code, ?string $name = null): string
{
    if (function_exists('biotern_section_parts')) {
        $parts = biotern_section_parts($code, $name);
        $program = trim((string)($parts['program'] ?? ''));
        $section = trim((string)($parts['section'] ?? ''));
        if ($program !== '' && $section !== '') {
            return $program . ' ' . $section;
        }
        if ($program !== '') {
            return $program;
        }
        if ($section !== '') {
            return $section;
        }
    }

    return trim((string)($name ?? $code ?? ''));
}

function rr_add_course_section_labels(array $rows): array
{
    foreach ($rows as &$row) {
        $row['section'] = rr_format_course_section_label((string)($row['section_code'] ?? ''), (string)($row['section_name'] ?? ''));
        unset($row['section_code'], $row['section_name']);
    }
    unset($row);
    return $rows;
}

function rr_format_status_label($value): string
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return '-';
    }

    $key = strtolower(str_replace(['_', '-'], ' ', $raw));
    $labels = [
        'not started' => 'Not Started',
        'ongoing' => 'Ongoing',
        'completed' => 'Finished',
        'finished' => 'Finished',
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'draft' => 'Draft',
    ];

    return $labels[$key] ?? ucwords($key);
}

function rr_display_value(string $key, $value): string
{
    $normalizedKey = strtolower(trim($key));
    if ($normalizedKey === 'status' || str_ends_with($normalizedKey, '_status')) {
        return rr_format_status_label($value);
    }

    return (string)$value;
}

function rr_filter_label(string $key): string
{
    return ucwords(str_replace('_', ' ', $key));
}

function rr_filterable_keys(array $rows): array
{
    $preferred = ['status', 'type', 'course', 'section'];
    $available = [];
    foreach ($rows as $row) {
        foreach ($preferred as $key) {
            if (array_key_exists($key, $row)) {
                $available[$key] = true;
            }
        }
    }

    return array_keys($available);
}

function rr_filter_options(array $rows, string $key): array
{
    $options = [];
    foreach ($rows as $row) {
        if (!array_key_exists($key, $row)) {
            continue;
        }
        $label = rr_display_value($key, $row[$key]);
        if ($label === '') {
            continue;
        }
        $options[$label] = true;
    }
    $labels = array_keys($options);
    natcasesort($labels);
    return array_values($labels);
}

function rr_row_search_text(array $row): string
{
    $values = [];
    foreach ($row as $key => $value) {
        if (rr_is_hidden_key((string)$key)) {
            continue;
        }
        $values[] = rr_display_value((string)$key, $value);
    }
    return trim(implode(' ', $values));
}

function rr_is_hidden_key(string $key): bool
{
    return in_array($key, ['student_db_id', 'section_db_id', 'department_db_id', 'row_url'], true);
}

function rr_add_row_links(string $reportKey, array $rows): array
{
    foreach ($rows as &$row) {
        if (!empty($row['row_url'])) {
            continue;
        }

        $studentId = (int)($row['student_db_id'] ?? 0);
        if ($studentId > 0) {
            $row['row_url'] = 'students-view.php?id=' . $studentId;
            continue;
        }

        if ($reportKey === 'section') {
            $sectionId = (int)($row['section_db_id'] ?? 0);
            if ($sectionId > 0) {
                $row['row_url'] = 'students.php?section_id=' . $sectionId;
            }
            continue;
        }

        if ($reportKey === 'department') {
            $departmentId = (int)($row['department_db_id'] ?? 0);
            if ($departmentId > 0) {
                $row['row_url'] = 'students.php?department_id=' . $departmentId;
            }
            continue;
        }

        if ($reportKey === 'company') {
            $company = trim((string)($row['company'] ?? ''));
            if ($company !== '' && strtolower($company) !== 'no company') {
                $row['row_url'] = 'companies.php?q=' . rawurlencode($company);
            }
        }
    }
    unset($row);
    return $rows;
}

function rr_linkable_key(string $key): bool
{
    return in_array(strtolower($key), ['student', 'section', 'department', 'company', 'student/user'], true);
}

function rr_cell_html(string $key, array $row): string
{
    $value = rr_display_value($key, $row[$key] ?? '');
    $url = trim((string)($row['row_url'] ?? ''));
    if ($url !== '' && rr_linkable_key($key)) {
        return '<a class="required-report-row-link" href="' . rr_esc($url) . '">' . rr_esc($value) . '</a>';
    }

    return rr_esc($value);
}

function rr_report_row_state(string $reportKey, array $row): string
{
    if ($reportKey === 'unassigned-students') {
        return 'warning';
    }

    if ($reportKey === 'import-errors') {
        return 'danger';
    }

    $status = strtolower(rr_format_status_label($row['status'] ?? ''));
    if (in_array($status, ['pending', 'not started', 'draft'], true)) {
        return 'warning';
    }
    if ($status === 'rejected') {
        return 'danger';
    }
    if (in_array($status, ['approved', 'finished'], true)) {
        return 'success';
    }

    if ($reportKey === 'hours-completion') {
        $progress = (float)str_replace('%', '', (string)($row['progress'] ?? 0));
        if ($progress >= 100) {
            return 'success';
        }
        if ($progress < 50) {
            return 'danger';
        }
        return 'warning';
    }

    return '';
}

function rr_cell_state(string $key, array $row): string
{
    $normalizedKey = strtolower(trim($key));
    if ($normalizedKey === 'status' || str_ends_with($normalizedKey, '_status')) {
        $status = strtolower(rr_format_status_label($row[$key] ?? ''));
        if (in_array($status, ['pending', 'not started', 'draft'], true)) {
            return 'warning';
        }
        if ($status === 'rejected') {
            return 'danger';
        }
        if (in_array($status, ['approved', 'finished'], true)) {
            return 'success';
        }
        if ($status === 'ongoing') {
            return 'info';
        }
    }

    if ($normalizedKey === 'progress') {
        $progress = (float)str_replace('%', '', (string)($row[$key] ?? 0));
        if ($progress >= 100) {
            return 'success';
        }
        if ($progress < 50) {
            return 'danger';
        }
        return 'warning';
    }

    if (in_array($normalizedKey, ['duplicate_count', 'count'], true) && (int)($row[$key] ?? 0) > 1) {
        return 'danger';
    }

    return '';
}

function rr_cell_classes(string $key, array $row): string
{
    $state = rr_cell_state($key, $row);
    return $state !== '' ? ' class="required-report-cell-' . rr_esc($state) . '"' : '';
}

$requiredReportKey = isset($requiredReportKey) ? (string)$requiredReportKey : '';

$reports = [
    'student-status' => [
        'title' => 'Student Status Report',
        'statement' => 'Ongoing, finished, and not started OJT student status.',
        'columns' => ['Student No', 'Student', 'Course', 'Status'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Ongoing', 'value' => rr_count($conn, "SELECT COUNT(*) AS total FROM internships WHERE status = 'ongoing'")],
                ['label' => 'Finished', 'value' => rr_count($conn, "SELECT COUNT(*) AS total FROM internships WHERE status IN ('completed', 'finished')")],
                ['label' => 'Not Started', 'value' => rr_count($conn, "SELECT COUNT(*) AS total FROM students s LEFT JOIN internships i ON i.student_id = s.id WHERE i.id IS NULL")],
            ];
        },
        'rows' => function (mysqli $conn): array {
            $where = [];
            $courseId = rr_request_int('course_id');
            $sectionId = rr_request_int('section_id');
            $status = rr_request_choice('report_status', ['ongoing', 'finished', 'not_started']);
            if ($courseId > 0) {
                $where[] = 's.course_id = ' . $courseId;
            }
            if ($sectionId > 0) {
                $where[] = 's.section_id = ' . $sectionId;
            }
            if ($status === 'ongoing') {
                $where[] = "i.status = 'ongoing'";
            } elseif ($status === 'finished') {
                $where[] = "i.status IN ('completed', 'finished')";
            } elseif ($status === 'not_started') {
                $where[] = 'i.id IS NULL';
            }
            return rr_rows($conn, "SELECT s.id AS student_db_id, COALESCE(s.student_id, '') AS student_no, TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS student, COALESCE(c.name, '-') AS course, COALESCE(i.status, 'Not Started') AS status FROM students s LEFT JOIN courses c ON c.id = s.course_id LEFT JOIN internships i ON i.id = (SELECT i2.id FROM internships i2 WHERE i2.student_id = s.id ORDER BY i2.id DESC LIMIT 1)" . rr_where($where) . " ORDER BY status, student LIMIT 500");
        },
    ],
    'attendance-dtr' => [
        'title' => 'Attendance Report (DTR)',
        'statement' => 'Internal biometric attendance and external verified/manual attendance.',
        'columns' => ['Type', 'Student', 'Date', 'Hours', 'Status'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Internal Rows', 'value' => rr_table_exists($conn, 'attendances') ? rr_count($conn, 'SELECT COUNT(*) AS total FROM attendances') : 0],
                ['label' => 'External Rows', 'value' => rr_table_exists($conn, 'external_attendance') ? rr_count($conn, 'SELECT COUNT(*) AS total FROM external_attendance') : 0],
                ['label' => 'Pending External', 'value' => rr_table_exists($conn, 'external_attendance') ? rr_count($conn, "SELECT COUNT(*) AS total FROM external_attendance WHERE status = 'pending'") : 0],
            ];
        },
        'rows' => function (mysqli $conn): array {
            $rows = [];
            $type = rr_request_choice('attendance_type', ['internal', 'external']);
            $status = rr_request_choice('attendance_status', ['pending', 'approved', 'rejected']);
            $dateFrom = rr_request_date('date_from');
            $dateTo = rr_request_date('date_to');
            if (($type === '' || $type === 'internal') && rr_table_exists($conn, 'attendances')) {
                $dateField = 'COALESCE(a.attendance_date, DATE(a.created_at))';
                $where = [];
                if ($dateFrom !== '') {
                    $where[] = "{$dateField} >= '" . $conn->real_escape_string($dateFrom) . "'";
                }
                if ($dateTo !== '') {
                    $where[] = "{$dateField} <= '" . $conn->real_escape_string($dateTo) . "'";
                }
                if ($status !== '') {
                    $where[] = "LOWER(COALESCE(a.status, '')) = '" . $conn->real_escape_string($status) . "'";
                }
                $rows = array_merge($rows, rr_rows($conn, "SELECT s.id AS student_db_id, 'Internal' AS type, TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS student, {$dateField} AS report_date, COALESCE(a.total_hours, '') AS hours, COALESCE(a.status, '-') AS status FROM attendances a LEFT JOIN students s ON s.id = a.student_id" . rr_where($where) . " ORDER BY a.id DESC LIMIT 250"));
            }
            if (($type === '' || $type === 'external') && rr_table_exists($conn, 'external_attendance')) {
                $where = [];
                if ($dateFrom !== '') {
                    $where[] = "ea.attendance_date >= '" . $conn->real_escape_string($dateFrom) . "'";
                }
                if ($dateTo !== '') {
                    $where[] = "ea.attendance_date <= '" . $conn->real_escape_string($dateTo) . "'";
                }
                if ($status !== '') {
                    $where[] = "LOWER(COALESCE(ea.status, '')) = '" . $conn->real_escape_string($status) . "'";
                }
                $rows = array_merge($rows, rr_rows($conn, "SELECT s.id AS student_db_id, 'External' AS type, TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS student, ea.attendance_date AS report_date, ea.total_hours AS hours, COALESCE(ea.status, '-') AS status FROM external_attendance ea LEFT JOIN students s ON s.id = ea.student_id" . rr_where($where) . " ORDER BY ea.id DESC LIMIT 250"));
            }
            return $rows;
        },
    ],
    'hours-completion' => [
        'title' => 'Hours Completion Report',
        'statement' => 'Rendered hours compared with required OJT hours.',
        'columns' => ['Student', 'Type', 'Rendered', 'Required', 'Progress'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Programs', 'value' => rr_count($conn, 'SELECT COUNT(*) AS total FROM internships')],
                ['label' => 'Completed', 'value' => rr_count($conn, "SELECT COUNT(*) AS total FROM internships WHERE completion_percentage >= 100 OR status IN ('completed', 'finished')")],
                ['label' => 'In Progress', 'value' => rr_count($conn, "SELECT COUNT(*) AS total FROM internships WHERE status = 'ongoing'")],
            ];
        },
        'rows' => function (mysqli $conn): array {
            $where = [];
            $courseId = rr_request_int('course_id');
            $progress = rr_request_choice('progress', ['below_50', '50_99', 'completed']);
            if ($courseId > 0) {
                $where[] = 's.course_id = ' . $courseId;
            }
            if ($progress === 'below_50') {
                $where[] = 'COALESCE(i.completion_percentage, 0) < 50';
            } elseif ($progress === '50_99') {
                $where[] = 'COALESCE(i.completion_percentage, 0) >= 50 AND COALESCE(i.completion_percentage, 0) < 100';
            } elseif ($progress === 'completed') {
                $where[] = "(COALESCE(i.completion_percentage, 0) >= 100 OR i.status IN ('completed', 'finished'))";
            }
            return rr_rows($conn, "SELECT s.id AS student_db_id, TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS student, i.type, i.rendered_hours, i.required_hours, CONCAT(ROUND(i.completion_percentage, 2), '%') AS progress FROM internships i LEFT JOIN students s ON s.id = i.student_id" . rr_where($where) . " ORDER BY i.completion_percentage DESC, student LIMIT 500");
        },
    ],
    'section' => [
        'title' => 'Section Report',
        'statement' => 'Students grouped by section and status.',
        'columns' => ['Section', 'Total Students', 'Ongoing', 'Finished', 'Not Started'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Students', 'value' => rr_count($conn, 'SELECT COUNT(*) AS total FROM students')],
                ['label' => 'Sections', 'value' => rr_count($conn, 'SELECT COUNT(DISTINCT section_id) AS total FROM students')],
                ['label' => 'With OJT', 'value' => rr_count($conn, 'SELECT COUNT(DISTINCT student_id) AS total FROM internships')],
            ];
        },
        'rows' => function (mysqli $conn): array {
            $hasSections = rr_table_exists($conn, 'sections');
            $hasCode = $hasSections && rr_column_exists($conn, 'sections', 'code');
            $hasName = $hasSections && rr_column_exists($conn, 'sections', 'name');
            $sectionJoin = $hasSections ? 'LEFT JOIN sections sec ON sec.id = s.section_id' : '';
            if ($hasCode && $hasName) {
                $sectionSelect = "COALESCE(NULLIF(TRIM(sec.code), ''), NULLIF(TRIM(sec.name), ''), CONCAT('Section ', s.section_id), 'No Section')";
                $sectionGroup = ', sec.code, sec.name';
            } elseif ($hasName) {
                $sectionSelect = "COALESCE(NULLIF(TRIM(sec.name), ''), CONCAT('Section ', s.section_id), 'No Section')";
                $sectionGroup = ', sec.name';
            } elseif ($hasCode) {
                $sectionSelect = "COALESCE(NULLIF(TRIM(sec.code), ''), CONCAT('Section ', s.section_id), 'No Section')";
                $sectionGroup = ', sec.code';
            } else {
                $sectionSelect = "COALESCE(CONCAT('Section ', s.section_id), 'No Section')";
                $sectionGroup = '';
            }
            $rows = rr_rows($conn, "SELECT s.section_id AS section_db_id, " . ($hasCode ? "COALESCE(sec.code, '')" : "''") . " AS section_code, {$sectionSelect} AS section_name, COUNT(*) AS total_students, SUM(CASE WHEN i.status = 'ongoing' THEN 1 ELSE 0 END) AS ongoing, SUM(CASE WHEN i.status IN ('completed', 'finished') THEN 1 ELSE 0 END) AS finished, SUM(CASE WHEN i.id IS NULL THEN 1 ELSE 0 END) AS not_started FROM students s {$sectionJoin} LEFT JOIN internships i ON i.id = (SELECT i2.id FROM internships i2 WHERE i2.student_id = s.id ORDER BY i2.id DESC LIMIT 1) GROUP BY s.section_id{$sectionGroup} ORDER BY section_name LIMIT 500");
            return rr_add_course_section_labels($rows);
        },
    ],
    'department' => [
        'title' => 'Department Report',
        'statement' => 'Students per department or office with assigned supervisors.',
        'columns' => ['Department', 'Students', 'Supervisors', 'OJT Programs'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Departments', 'value' => rr_count($conn, 'SELECT COUNT(*) AS total FROM departments')],
                ['label' => 'Students', 'value' => rr_count($conn, 'SELECT COUNT(*) AS total FROM students')],
                ['label' => 'Supervisors', 'value' => rr_count($conn, 'SELECT COUNT(*) AS total FROM supervisors')],
            ];
        },
        'rows' => function (mysqli $conn): array {
            return rr_rows($conn, "SELECT s.department_id AS department_db_id, COALESCE(d.name, CONCAT('Department ', s.department_id)) AS department, COUNT(DISTINCT s.id) AS students, COUNT(DISTINCT sv.id) AS supervisors, COUNT(DISTINCT i.id) AS ojt_programs FROM students s LEFT JOIN departments d ON d.id = s.department_id LEFT JOIN supervisors sv ON sv.department_id = d.id LEFT JOIN internships i ON i.student_id = s.id GROUP BY s.department_id, d.name ORDER BY department LIMIT 500");
        },
    ],
    'company' => [
        'title' => 'Company Report (External OJT)',
        'statement' => 'External OJT companies and assigned or past trainees.',
        'columns' => ['Company', 'Trainees', 'Ongoing', 'Completed'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Companies', 'value' => rr_count($conn, "SELECT COUNT(DISTINCT company_name) AS total FROM internships WHERE type = 'external' AND company_name IS NOT NULL AND company_name <> ''")],
                ['label' => 'External Trainees', 'value' => rr_count($conn, "SELECT COUNT(*) AS total FROM internships WHERE type = 'external'")],
                ['label' => 'Completed', 'value' => rr_count($conn, "SELECT COUNT(*) AS total FROM internships WHERE type = 'external' AND status IN ('completed', 'finished')")],
            ];
        },
        'rows' => function (mysqli $conn): array {
            return rr_rows($conn, "SELECT COALESCE(NULLIF(company_name, ''), 'No Company') AS company, COUNT(*) AS trainees, SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) AS ongoing, SUM(CASE WHEN status IN ('completed', 'finished') THEN 1 ELSE 0 END) AS completed FROM internships WHERE type = 'external' GROUP BY COALESCE(NULLIF(company_name, ''), 'No Company') ORDER BY company LIMIT 500");
        },
    ],
    'evaluation' => [
        'title' => 'Evaluation Report',
        'statement' => 'Internal and external student evaluation results.',
        'columns' => ['Student', 'Evaluator', 'Date', 'Score', 'Feedback'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Evaluations', 'value' => rr_table_exists($conn, 'evaluations') ? rr_count($conn, 'SELECT COUNT(*) AS total FROM evaluations WHERE deleted_at IS NULL') : 0],
                ['label' => 'Average Score', 'value' => rr_table_exists($conn, 'evaluations') ? rr_count($conn, 'SELECT ROUND(AVG(score), 0) AS total FROM evaluations WHERE deleted_at IS NULL') : 0],
                ['label' => 'Students Rated', 'value' => rr_table_exists($conn, 'evaluations') ? rr_count($conn, 'SELECT COUNT(DISTINCT student_id) AS total FROM evaluations WHERE deleted_at IS NULL') : 0],
            ];
        },
        'rows' => function (mysqli $conn): array {
            return rr_table_exists($conn, 'evaluations') ? rr_rows($conn, "SELECT s.id AS student_db_id, TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS student, COALESCE(e.evaluator_name, '-') AS evaluator, e.evaluation_date, COALESCE(e.score, '-') AS score, COALESCE(e.feedback, '') AS feedback FROM evaluations e LEFT JOIN students s ON s.id = e.student_id WHERE e.deleted_at IS NULL ORDER BY e.evaluation_date DESC, e.id DESC LIMIT 500") : [];
        },
    ],
    'unassigned-students' => [
        'title' => 'Unassigned Students Report',
        'statement' => 'Registered students not yet assigned to OJT.',
        'columns' => ['Student No', 'Student', 'Course', 'Section'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Unassigned', 'value' => rr_count($conn, 'SELECT COUNT(*) AS total FROM students s LEFT JOIN internships i ON i.student_id = s.id WHERE i.id IS NULL')],
                ['label' => 'Total Students', 'value' => rr_count($conn, 'SELECT COUNT(*) AS total FROM students')],
                ['label' => 'Assigned', 'value' => rr_count($conn, 'SELECT COUNT(DISTINCT student_id) AS total FROM internships')],
            ];
        },
        'rows' => function (mysqli $conn): array {
            $hasSections = rr_table_exists($conn, 'sections');
            $hasCode = $hasSections && rr_column_exists($conn, 'sections', 'code');
            $hasName = $hasSections && rr_column_exists($conn, 'sections', 'name');
            $sectionJoin = $hasSections ? 'LEFT JOIN sections sec ON sec.id = s.section_id' : '';
            if ($hasCode && $hasName) {
                $sectionSelect = "COALESCE(NULLIF(TRIM(sec.code), ''), NULLIF(TRIM(sec.name), ''), CONCAT('Section ', s.section_id), '-')";
            } elseif ($hasName) {
                $sectionSelect = "COALESCE(NULLIF(TRIM(sec.name), ''), CONCAT('Section ', s.section_id), '-')";
            } elseif ($hasCode) {
                $sectionSelect = "COALESCE(NULLIF(TRIM(sec.code), ''), CONCAT('Section ', s.section_id), '-')";
            } else {
                $sectionSelect = "COALESCE(CONCAT('Section ', s.section_id), '-')";
            }
            $where = ['i.id IS NULL'];
            $courseId = rr_request_int('course_id');
            $sectionId = rr_request_int('section_id');
            if ($courseId > 0) {
                $where[] = 's.course_id = ' . $courseId;
            }
            if ($sectionId > 0) {
                $where[] = 's.section_id = ' . $sectionId;
            }
            $rows = rr_rows($conn, "SELECT s.id AS student_db_id, COALESCE(s.student_id, '') AS student_no, TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS student, COALESCE(c.name, '-') AS course, " . ($hasCode ? "COALESCE(sec.code, '')" : "''") . " AS section_code, {$sectionSelect} AS section_name FROM students s LEFT JOIN courses c ON c.id = s.course_id {$sectionJoin} LEFT JOIN internships i ON i.student_id = s.id" . rr_where($where) . " ORDER BY student LIMIT 500");
            return rr_add_course_section_labels($rows);
        },
    ],
    'import-errors' => [
        'title' => 'Duplicate/Import Error Report',
        'statement' => 'Duplicate student numbers and import warning checks.',
        'columns' => ['Issue', 'Student No', 'Duplicate Count', 'Details'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Duplicate IDs', 'value' => rr_count($conn, 'SELECT COUNT(*) AS total FROM (SELECT student_id FROM students GROUP BY student_id HAVING COUNT(*) > 1) d')],
                ['label' => 'Students', 'value' => rr_count($conn, 'SELECT COUNT(*) AS total FROM students')],
                ['label' => 'Imports', 'value' => rr_table_exists($conn, 'ojt_masterlist') ? rr_count($conn, 'SELECT COUNT(*) AS total FROM ojt_masterlist') : 0],
            ];
        },
        'rows' => function (mysqli $conn): array {
            return rr_rows($conn, "SELECT 'Duplicate Student Number' AS issue, COALESCE(student_id, '') AS student_no, COUNT(*) AS duplicate_count, GROUP_CONCAT(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) SEPARATOR ', ') AS details FROM students GROUP BY student_id HAVING COUNT(*) > 1 ORDER BY duplicate_count DESC LIMIT 500");
        },
    ],
    'document' => [
        'title' => 'Document Report',
        'statement' => 'Generated documents per student and completion status.',
        'columns' => ['Student/User', 'Document Type', 'Status', 'Approved By', 'Updated'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Documents', 'value' => rr_table_exists($conn, 'document_workflow') ? rr_count($conn, 'SELECT COUNT(*) AS total FROM document_workflow') : 0],
                ['label' => 'Approved', 'value' => rr_table_exists($conn, 'document_workflow') ? rr_count($conn, "SELECT COUNT(*) AS total FROM document_workflow WHERE status = 'approved'") : 0],
                ['label' => 'Pending/Draft', 'value' => rr_table_exists($conn, 'document_workflow') ? rr_count($conn, "SELECT COUNT(*) AS total FROM document_workflow WHERE status <> 'approved'") : 0],
            ];
        },
        'rows' => function (mysqli $conn): array {
            return rr_table_exists($conn, 'document_workflow') ? rr_rows($conn, "SELECT COALESCE(u.name, u.username, CONCAT('User ', dw.user_id)) AS user_name, dw.doc_type, dw.status, COALESCE(approver.name, '-') AS approved_by_name, dw.updated_at FROM document_workflow dw LEFT JOIN users u ON u.id = dw.user_id LEFT JOIN users approver ON approver.id = dw.approved_by ORDER BY dw.updated_at DESC, dw.id DESC LIMIT 500") : [];
        },
    ],
];

if (!isset($reports[$requiredReportKey])) {
    http_response_code(404);
    exit('Report not found');
}

$report = $reports[$requiredReportKey];
$summary = is_callable($report['summary'] ?? null) ? $report['summary']($conn) : [];
$rows = is_callable($report['rows'] ?? null) ? $report['rows']($conn) : [];
$rows = rr_add_row_links($requiredReportKey, $rows);
$columns = (array)($report['columns'] ?? []);
$filterKeys = rr_filterable_keys($rows);
$serverFilterFields = rr_server_filter_fields($conn, $requiredReportKey);
$generatedAt = date('Y-m-d h:i A');
$generatedFileDate = date('Y-m-d');
$appliedFilterSummary = rr_applied_filter_summary($serverFilterFields);

$page_body_class = trim(($page_body_class ?? '') . ' reports-page required-report-page');
$page_styles = array_merge($page_styles ?? [], ['assets/css/modules/reports/reports-shell.css', 'assets/css/modules/reports/reports-required-page.css']);
$page_scripts = array_merge($page_scripts ?? [], ['assets/js/modules/reports/required-report-page.js', 'assets/js/modules/reports/reports-shell-runtime.js']);
$page_title = 'BioTern || ' . (string)$report['title'];
include 'includes/header.php';
?>
<main class="nxl-container">
<div class="nxl-content">
    <div class="page-header page-header-with-middle">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title logs-page-title"><h5 class="m-b-10"><?php echo rr_esc($report['title']); ?></h5></div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                <li class="breadcrumb-item"><a href="index.php">Reports</a></li>
                <li class="breadcrumb-item"><?php echo rr_esc($report['title']); ?></li>
            </ul>
        </div>
        <div class="page-header-middle">
            <p class="page-header-statement"><?php echo rr_esc($report['statement']); ?></p>
        </div>
        <?php ob_start(); ?>
            <a href="reports-admin-logs.php" class="btn btn-outline-primary"><i class="feather-shield me-1"></i>Admin Logs</a>
            <button type="button" class="btn btn-outline-primary" data-required-report-export><i class="feather-download me-1"></i>Export CSV</button>
            <button type="button" class="btn btn-light-brand js-print-report"><i class="feather-printer me-1"></i>Print</button>
        <?php biotern_render_page_header_actions(['menu_id' => 'requiredReportActionsMenu', 'items_html' => ob_get_clean()]); ?>
    </div>

    <div class="main-content pb-5">
        <section class="required-report-print-header" aria-hidden="true">
            <div class="required-report-print-brand">
                <img src="assets/images/ccstlogo.png" alt="CCST Logo">
                <div>
                    <p>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</p>
                    <span>SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</span>
                    <span>Telefax No.: (045) 624-0215</span>
                </div>
            </div>
            <h1><?php echo rr_esc((string)$report['title']); ?></h1>
            <div class="required-report-print-meta">
                <span><strong>Generated:</strong> <?php echo rr_esc($generatedAt); ?></span>
                <span><strong>Rows:</strong> <?php echo count($rows); ?></span>
                <span><strong>Filters:</strong> <?php echo rr_esc(implode(' | ', $appliedFilterSummary)); ?></span>
            </div>
        </section>

        <div class="required-report-summary">
            <?php foreach ($summary as $card): ?>
                <div class="required-report-kpi">
                    <div class="required-report-kpi-label"><?php echo rr_esc($card['label'] ?? 'Total'); ?></div>
                    <div class="required-report-kpi-value"><?php echo rr_esc($card['value'] ?? 0); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="required-report-table-card">
            <?php if ($serverFilterFields): ?>
                <form class="required-report-server-filters" method="get">
                    <div class="required-report-server-title">
                        <span>Report Filters</span>
                        <small>Reloads this report from the database.</small>
                    </div>
                    <?php foreach ($serverFilterFields as $field): ?>
                        <?php
                        $name = (string)($field['name'] ?? '');
                        $type = (string)($field['type'] ?? 'text');
                        $current = rr_filter_current_value($name);
                        ?>
                        <div class="required-report-server-field">
                            <label class="form-label" for="serverFilter<?php echo rr_esc($name); ?>"><?php echo rr_esc((string)($field['label'] ?? rr_filter_label($name))); ?></label>
                            <?php if ($type === 'select'): ?>
                                <select class="form-select" id="serverFilter<?php echo rr_esc($name); ?>" name="<?php echo rr_esc($name); ?>">
                                    <option value=""><?php echo rr_esc((string)($field['all'] ?? 'All')); ?></option>
                                    <?php foreach ((array)($field['options'] ?? []) as $option): ?>
                                        <?php $optionValue = (string)($option['value'] ?? ''); ?>
                                        <option value="<?php echo rr_esc($optionValue); ?>" <?php echo $current === $optionValue ? 'selected' : ''; ?>><?php echo rr_esc((string)($option['label'] ?? $optionValue)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input class="form-control" id="serverFilter<?php echo rr_esc($name); ?>" type="<?php echo rr_esc($type); ?>" name="<?php echo rr_esc($name); ?>" value="<?php echo rr_esc($current); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="required-report-server-actions">
                        <button type="submit" class="btn btn-primary"><i class="feather-filter me-1"></i>Apply</button>
                        <a href="<?php echo rr_esc(rr_current_page_name()); ?>" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            <?php endif; ?>
            <div class="required-report-toolbar">
                <div class="required-report-search">
                    <label class="form-label" for="requiredReportSearch">Search Report</label>
                    <input type="search" class="form-control" id="requiredReportSearch" data-required-report-search placeholder="Search name, status, course...">
                </div>
                <?php foreach ($filterKeys as $filterKey): ?>
                    <?php $filterOptions = rr_filter_options($rows, $filterKey); ?>
                    <?php if ($filterOptions): ?>
                        <div class="required-report-filter">
                            <label class="form-label" for="requiredReportFilter<?php echo rr_esc($filterKey); ?>"><?php echo rr_esc(rr_filter_label($filterKey)); ?></label>
                            <select class="form-select" id="requiredReportFilter<?php echo rr_esc($filterKey); ?>" data-required-report-filter="<?php echo rr_esc($filterKey); ?>">
                                <option value="">All <?php echo rr_esc(rr_filter_label($filterKey)); ?></option>
                                <?php foreach ($filterOptions as $option): ?>
                                    <option value="<?php echo rr_esc($option); ?>"><?php echo rr_esc($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <div class="required-report-toolbar-actions">
                    <span class="required-report-count" data-required-report-count><?php echo count($rows); ?> rows</span>
                    <button type="button" class="btn btn-outline-secondary" data-required-report-reset>Clear</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle" data-required-report-table data-report-title="<?php echo rr_esc($report['title']); ?>" data-report-generated="<?php echo rr_esc($generatedFileDate); ?>" data-report-generated-label="<?php echo rr_esc($generatedAt); ?>" data-report-filters="<?php echo rr_esc(implode(' | ', $appliedFilterSummary)); ?>">
                    <thead>
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <th><?php echo rr_esc($column); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rows): ?>
                            <?php foreach ($rows as $row): ?>
                                <tr
                                    class="<?php echo rr_report_row_state($requiredReportKey, $row) !== '' ? 'required-report-row-' . rr_esc(rr_report_row_state($requiredReportKey, $row)) : ''; ?>"
                                    data-required-report-row
                                    data-search="<?php echo rr_esc(strtolower(rr_row_search_text($row))); ?>"
                                    <?php foreach ($filterKeys as $filterKey): ?>
                                        data-filter-<?php echo rr_esc($filterKey); ?>="<?php echo rr_esc(rr_display_value($filterKey, $row[$filterKey] ?? '')); ?>"
                                    <?php endforeach; ?>
                                >
                                    <?php foreach (array_keys($row) as $key): ?>
                                        <?php if (rr_is_hidden_key((string)$key)) { continue; } ?>
                                        <td<?php echo rr_cell_classes((string)$key, $row); ?>><?php echo rr_cell_html((string)$key, $row); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="required-report-empty-filter d-none" data-required-report-empty>
                                <td colspan="<?php echo max(1, count($columns)); ?>" class="text-center text-muted py-5">No rows match the current filters.</td>
                            </tr>
                        <?php else: ?>
                            <tr><td colspan="<?php echo max(1, count($columns)); ?>" class="text-center text-muted py-5">No records found for this report.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($rows): ?>
                <div class="required-report-pagination" data-required-report-pagination>
                    <span class="required-report-page-summary" data-required-report-page-summary>Showing rows</span>
                    <div class="required-report-page-controls">
                        <label class="form-label mb-0" for="requiredReportPageJump">Go to page</label>
                        <select class="form-select" id="requiredReportPageJump" data-required-report-page-jump></select>
                        <button type="button" class="btn btn-outline-secondary" data-required-report-prev>Prev</button>
                        <button type="button" class="btn btn-outline-secondary" data-required-report-next>Next</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</main>
<?php include 'includes/footer.php'; ?>
