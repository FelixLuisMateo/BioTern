<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

$ops_helpers = dirname(__DIR__) . '/lib/ops_helpers.php';
if (file_exists($ops_helpers)) {
    require_once $ops_helpers;
    if (function_exists('require_roles_page')) {
        require_roles_page(['admin', 'coordinator', 'supervisor']);
    }
}

function pending_masterlist_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function pending_masterlist_table_exists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

$page_title = 'BioTern || Pending Masterlist Students';
$search = trim((string)($_GET['q'] ?? ''));
$schoolYearFilter = trim((string)($_GET['school_year'] ?? ''));
$semesterFilter = trim((string)($_GET['semester'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));

$rows = [];
$schoolYears = [];
$semesters = [];
$statuses = [];
$totalMasterlistRows = 0;
$pendingCount = 0;
$matchedAccountCount = 0;
$companiesCount = 0;

if (isset($conn) && $conn instanceof mysqli && pending_masterlist_table_exists($conn, 'ojt_masterlist')) {
    $countSql = "
        SELECT
            COUNT(*) AS total_rows,
            SUM(CASE WHEN s.id IS NULL THEN 1 ELSE 0 END) AS pending_rows,
            SUM(CASE WHEN s.id IS NULL THEN 0 ELSE 1 END) AS matched_rows,
            COUNT(DISTINCT CASE WHEN s.id IS NULL AND TRIM(COALESCE(ml.company_name, '')) <> '' THEN TRIM(ml.company_name) END) AS pending_companies
        FROM ojt_masterlist ml
        LEFT JOIN students s
            ON TRIM(COALESCE(s.student_id, '')) COLLATE utf8mb4_unicode_ci = TRIM(COALESCE(ml.student_no, '')) COLLATE utf8mb4_unicode_ci
    ";
    $countRes = $conn->query($countSql);
    if ($countRes instanceof mysqli_result) {
        $countRow = $countRes->fetch_assoc() ?: [];
        $totalMasterlistRows = (int)($countRow['total_rows'] ?? 0);
        $pendingCount = (int)($countRow['pending_rows'] ?? 0);
        $matchedAccountCount = (int)($countRow['matched_rows'] ?? 0);
        $companiesCount = (int)($countRow['pending_companies'] ?? 0);
        $countRes->close();
    }

    foreach ([
        'school_year' => &$schoolYears,
        'semester' => &$semesters,
        'status' => &$statuses,
    ] as $column => &$bucket) {
        $res = $conn->query("SELECT DISTINCT TRIM(COALESCE({$column}, '')) AS value FROM ojt_masterlist WHERE TRIM(COALESCE({$column}, '')) <> '' ORDER BY value ASC");
        if ($res instanceof mysqli_result) {
            while ($option = $res->fetch_assoc()) {
                $bucket[] = (string)($option['value'] ?? '');
            }
            $res->close();
        }
    }
    unset($bucket);

    $sql = "
        SELECT
            ml.id,
            ml.school_year,
            ml.semester,
            ml.student_no,
            ml.student_name,
            ml.contact_no,
            ml.section,
            ml.company_name,
            ml.company_address,
            ml.supervisor_name,
            ml.supervisor_position,
            ml.company_representative,
            ml.status,
            ml.source_workbook,
            ml.source_sheet,
            ml.source_row_number,
            ml.updated_at
        FROM ojt_masterlist ml
        LEFT JOIN students s
            ON TRIM(COALESCE(s.student_id, '')) COLLATE utf8mb4_unicode_ci = TRIM(COALESCE(ml.student_no, '')) COLLATE utf8mb4_unicode_ci
        WHERE s.id IS NULL
    ";
    $types = '';
    $params = [];
    if ($search !== '') {
        $sql .= " AND (ml.student_no LIKE ? OR ml.student_name LIKE ? OR ml.section LIKE ? OR ml.company_name LIKE ? OR ml.supervisor_name LIKE ?)";
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like, $like);
        $types .= 'sssss';
    }
    if ($schoolYearFilter !== '') {
        $sql .= " AND TRIM(COALESCE(ml.school_year, '')) = ?";
        $params[] = $schoolYearFilter;
        $types .= 's';
    }
    if ($semesterFilter !== '') {
        $sql .= " AND TRIM(COALESCE(ml.semester, '')) = ?";
        $params[] = $semesterFilter;
        $types .= 's';
    }
    if ($statusFilter !== '') {
        $sql .= " AND TRIM(COALESCE(ml.status, '')) = ?";
        $params[] = $statusFilter;
        $types .= 's';
    }
    $sql .= " ORDER BY ml.school_year DESC, ml.semester ASC, ml.section ASC, ml.student_name ASC, ml.id ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($params !== []) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
    }
}

include 'includes/header.php';
?>
<main class="nxl-container apps-container apps-email bg-white">
    <div class="nxl-content without-header nxl-full-content">
        <div class="main-content d-flex">
            <div class="content-area" data-scrollbar-target="#psScrollbarInit">
                <div class="page-header">
                    <div class="page-header-left d-flex align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Pending Student Accounts</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="ojt.php">OJT Dashboard</a></li>
                            <li class="breadcrumb-item">Imported Without Account</li>
                        </ul>
                    </div>
                    <div class="page-header-right ms-auto d-flex gap-2">
                        <a href="import-students-excel.php" class="btn btn-sm btn-primary">
                            <i class="feather-upload-cloud me-1"></i>
                            <span>Import Masterlist</span>
                        </a>
                        <a href="ojt.php" class="btn btn-sm btn-outline-secondary">
                            <i class="feather-arrow-left me-1"></i>
                            <span>Back to OJT</span>
                        </a>
                    </div>
                </div>

                <div class="container-fluid py-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="text-muted small text-uppercase fw-semibold">Pending Accounts</div>
                                    <div class="fs-3 fw-bold"><?php echo number_format($pendingCount); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="text-muted small text-uppercase fw-semibold">Filtered List</div>
                                    <div class="fs-3 fw-bold"><?php echo number_format(count($rows)); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="text-muted small text-uppercase fw-semibold">With Accounts</div>
                                    <div class="fs-3 fw-bold"><?php echo number_format($matchedAccountCount); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="text-muted small text-uppercase fw-semibold">Companies</div>
                                    <div class="fs-3 fw-bold"><?php echo number_format($companiesCount); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <form method="get" class="row g-2 align-items-end">
                                <div class="col-lg-4 col-md-6">
                                    <label for="pendingSearch" class="form-label">Search</label>
                                    <input type="search" class="form-control" id="pendingSearch" name="q" value="<?php echo pending_masterlist_h($search); ?>" placeholder="Student no, name, section, company">
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label for="pendingSchoolYear" class="form-label">School Year</label>
                                    <select class="form-select" id="pendingSchoolYear" name="school_year">
                                        <option value="">All</option>
                                        <?php foreach ($schoolYears as $schoolYear): ?>
                                            <option value="<?php echo pending_masterlist_h($schoolYear); ?>" <?php echo $schoolYearFilter === $schoolYear ? 'selected' : ''; ?>><?php echo pending_masterlist_h($schoolYear); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label for="pendingSemester" class="form-label">Semester</label>
                                    <select class="form-select" id="pendingSemester" name="semester">
                                        <option value="">All</option>
                                        <?php foreach ($semesters as $semester): ?>
                                            <option value="<?php echo pending_masterlist_h($semester); ?>" <?php echo $semesterFilter === $semester ? 'selected' : ''; ?>><?php echo pending_masterlist_h($semester); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label for="pendingStatus" class="form-label">Status</label>
                                    <select class="form-select" id="pendingStatus" name="status">
                                        <option value="">All</option>
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?php echo pending_masterlist_h($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo pending_masterlist_h($status); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                                    <a href="masterlist-pending-students.php" class="btn btn-outline-secondary">Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Student</th>
                                            <th>Section</th>
                                            <th>Company</th>
                                            <th>Supervisor</th>
                                            <th>Term</th>
                                            <th>Status</th>
                                            <th>Source</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($rows === []): ?>
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-5">
                                                    <?php echo pending_masterlist_table_exists($conn, 'ojt_masterlist') ? 'No imported students without BioTern accounts found.' : 'No masterlist has been imported yet.'; ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php foreach ($rows as $index => $row): ?>
                                            <tr>
                                                <td class="text-muted"><?php echo $index + 1; ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?php echo pending_masterlist_h($row['student_name'] ?? ''); ?></div>
                                                    <div class="small text-muted">No: <?php echo pending_masterlist_h($row['student_no'] ?: 'Not provided'); ?></div>
                                                    <?php if (trim((string)($row['contact_no'] ?? '')) !== ''): ?>
                                                        <div class="small text-muted">Contact: <?php echo pending_masterlist_h($row['contact_no']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo pending_masterlist_h($row['section'] ?: '-'); ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?php echo pending_masterlist_h($row['company_name'] ?: 'No company'); ?></div>
                                                    <?php if (trim((string)($row['company_address'] ?? '')) !== ''): ?>
                                                        <div class="small text-muted"><?php echo pending_masterlist_h($row['company_address']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo pending_masterlist_h($row['supervisor_name'] ?: 'Not provided'); ?>
                                                    <?php if (trim((string)($row['supervisor_position'] ?? '')) !== ''): ?>
                                                        <div class="small text-muted"><?php echo pending_masterlist_h($row['supervisor_position']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div><?php echo pending_masterlist_h($row['school_year'] ?: '-'); ?></div>
                                                    <div class="small text-muted"><?php echo pending_masterlist_h($row['semester'] ?: '-'); ?></div>
                                                </td>
                                                <td><span class="badge bg-warning text-dark">Pending Account</span></td>
                                                <td>
                                                    <div class="small"><?php echo pending_masterlist_h($row['source_workbook'] ?: 'Masterlist'); ?></div>
                                                    <div class="small text-muted">
                                                        <?php echo pending_masterlist_h($row['source_sheet'] ?: 'Sheet'); ?>
                                                        <?php if ((int)($row['source_row_number'] ?? 0) > 0): ?>
                                                            row <?php echo (int)$row['source_row_number']; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-4 mb-0">
                        These students came from the teacher masterlist only. Once the student registers and their BioTern student number matches the imported student number, they will leave this list and become a normal OJT/student record.
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include 'includes/footer.php'; ?>
<?php if (isset($conn) && $conn instanceof mysqli) { $conn->close(); } ?>
