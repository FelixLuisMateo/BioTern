<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
biotern_boot_session(isset($conn) ? $conn : null);

$role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if (!in_array($role, ['admin', 'coordinator'], true)) {
    header('Location: homepage.php');
    exit;
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function companies_table_exists(mysqli $conn, string $table): bool
{
    $escaped = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$escaped}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function company_normalize_key(string $value): string
{
    $value = function_exists('mb_strtolower')
        ? trim(mb_strtolower($value, 'UTF-8'))
        : trim(strtolower($value));
    $value = preg_replace('/\s+/', ' ', $value);
    return (string)$value;
}

function company_display_name(?string $value): string
{
    $value = trim((string)$value);
    return $value !== '' ? $value : 'Unnamed Company';
}

function company_datetime_label(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return 'Not yet synced';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('M d, Y h:i A', $timestamp);
}

function company_progress_pct($rendered, $required): int
{
    $required = (float)$required;
    $rendered = (float)$rendered;
    if ($required <= 0) {
        return 0;
    }

    $pct = (int)round(($rendered / $required) * 100);
    if ($pct < 0) {
        return 0;
    }
    if ($pct > 100) {
        return 100;
    }
    return $pct;
}

function company_status_tone(string $status): string
{
    $status = strtolower(trim($status));
    if ($status === 'completed') {
        return 'is-success';
    }
    if ($status === 'ongoing') {
        return 'is-primary';
    }
    if ($status === 'pending') {
        return 'is-warning';
    }
    if ($status === 'cancelled') {
        return 'is-danger';
    }
    return 'is-muted';
}

function company_track_label(string $track): string
{
    $track = strtolower(trim($track));
    return $track === 'external' ? 'External' : 'Internal';
}

function resolve_profile_image_url(string $profilePath, int $userId = 0): ?string
{
    $resolved = biotern_avatar_public_src($profilePath, $userId);
    return $resolved !== '' ? $resolved : null;
}

$ensureCompaniesSql = "CREATE TABLE IF NOT EXISTS ojt_partner_companies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_lookup_key VARCHAR(255) NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    company_address TEXT DEFAULT NULL,
    supervisor_name VARCHAR(255) DEFAULT NULL,
    supervisor_position VARCHAR(255) DEFAULT NULL,
    company_representative VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_company_lookup (company_lookup_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
$companyTableReady = (bool)$conn->query($ensureCompaniesSql);
$internshipsTableReady = companies_table_exists($conn, 'internships');

$search = trim((string)($_GET['q'] ?? ''));
$sort = strtolower(trim((string)($_GET['sort'] ?? 'updated')));
$selectedCompanyParam = trim((string)($_GET['company'] ?? ''));
$allowedSorts = ['updated', 'name', 'interns'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'updated';
}

$companyMap = [];

if ($companyTableReady) {
    $partnerResult = $conn->query("
        SELECT
            id,
            company_lookup_key,
            company_name,
            company_address,
            supervisor_name,
            supervisor_position,
            company_representative,
            created_at,
            updated_at
        FROM ojt_partner_companies
        ORDER BY company_name ASC, id DESC
    ");

    if ($partnerResult) {
        while ($row = $partnerResult->fetch_assoc()) {
            $companyName = trim((string)($row['company_name'] ?? ''));
            $key = company_normalize_key($companyName);
            if ($key === '') {
                continue;
            }

            $companyMap[$key] = [
                'key' => $key,
                'partner_company_id' => (int)($row['id'] ?? 0),
                'company_lookup_key' => trim((string)($row['company_lookup_key'] ?? '')),
                'company_name' => $companyName,
                'company_address' => trim((string)($row['company_address'] ?? '')),
                'supervisor_name' => trim((string)($row['supervisor_name'] ?? '')),
                'supervisor_position' => trim((string)($row['supervisor_position'] ?? '')),
                'company_representative' => trim((string)($row['company_representative'] ?? '')),
                'created_at' => trim((string)($row['created_at'] ?? '')),
                'updated_at' => trim((string)($row['updated_at'] ?? '')),
                'intern_count' => 0,
                'ongoing_count' => 0,
                'latest_activity' => trim((string)($row['updated_at'] ?? '')),
                'has_partner_record' => true,
            ];
        }
    }
}

if ($internshipsTableReady) {
    $internshipAggregateSql = "
        SELECT
            LOWER(TRIM(COALESCE(i.company_name, ''))) AS company_key,
            MAX(TRIM(COALESCE(i.company_name, ''))) AS company_name,
            MAX(TRIM(COALESCE(i.company_address, ''))) AS company_address,
            COUNT(*) AS intern_count,
            SUM(CASE WHEN i.status = 'ongoing' THEN 1 ELSE 0 END) AS ongoing_count,
            MAX(i.updated_at) AS latest_activity
        FROM internships i
        INNER JOIN (
            SELECT student_id, MAX(id) AS latest_id
            FROM internships
            WHERE deleted_at IS NULL
            GROUP BY student_id
        ) latest ON latest.latest_id = i.id
        WHERE i.deleted_at IS NULL
          AND TRIM(COALESCE(i.company_name, '')) <> ''
        GROUP BY LOWER(TRIM(COALESCE(i.company_name, '')))
    ";
    $internshipAggregateResult = $conn->query($internshipAggregateSql);
    if ($internshipAggregateResult) {
        while ($row = $internshipAggregateResult->fetch_assoc()) {
            $key = trim((string)($row['company_key'] ?? ''));
            if ($key === '') {
                $key = company_normalize_key((string)($row['company_name'] ?? ''));
            }
            if ($key === '') {
                continue;
            }

            if (!isset($companyMap[$key])) {
                $companyMap[$key] = [
                    'key' => $key,
                    'partner_company_id' => 0,
                    'company_lookup_key' => '',
                    'company_name' => trim((string)($row['company_name'] ?? '')),
                    'company_address' => trim((string)($row['company_address'] ?? '')),
                    'supervisor_name' => '',
                    'supervisor_position' => '',
                    'company_representative' => '',
                    'created_at' => '',
                    'updated_at' => '',
                    'intern_count' => 0,
                    'ongoing_count' => 0,
                    'latest_activity' => '',
                    'has_partner_record' => false,
                ];
            }

            $companyMap[$key]['company_name'] = $companyMap[$key]['company_name'] !== '' ? $companyMap[$key]['company_name'] : trim((string)($row['company_name'] ?? ''));
            $companyMap[$key]['company_address'] = $companyMap[$key]['company_address'] !== '' ? $companyMap[$key]['company_address'] : trim((string)($row['company_address'] ?? ''));
            $companyMap[$key]['intern_count'] = (int)($row['intern_count'] ?? 0);
            $companyMap[$key]['ongoing_count'] = (int)($row['ongoing_count'] ?? 0);
            $companyMap[$key]['latest_activity'] = trim((string)($row['latest_activity'] ?? ''));

            if ($companyMap[$key]['updated_at'] === '' && $companyMap[$key]['latest_activity'] !== '') {
                $companyMap[$key]['updated_at'] = $companyMap[$key]['latest_activity'];
            }
        }
    }
}

$companies = array_values($companyMap);
$totalOngoingInterns = 0;
$companiesWithProfiles = 0;
foreach ($companies as $company) {
    $totalOngoingInterns += (int)($company['ongoing_count'] ?? 0);
    if (trim((string)($company['supervisor_name'] ?? '')) !== '' || trim((string)($company['company_representative'] ?? '')) !== '') {
        $companiesWithProfiles++;
    }
}

if ($search !== '') {
    $needle = company_normalize_key($search);
    $companies = array_values(array_filter($companies, static function (array $company) use ($needle): bool {
        $haystack = company_normalize_key(implode(' ', [
            (string)($company['company_name'] ?? ''),
            (string)($company['company_address'] ?? ''),
            (string)($company['supervisor_name'] ?? ''),
            (string)($company['supervisor_position'] ?? ''),
            (string)($company['company_representative'] ?? ''),
        ]));

        return $haystack !== '' && strpos($haystack, $needle) !== false;
    }));
}

usort($companies, static function (array $a, array $b) use ($sort): int {
    if ($sort === 'name') {
        return strcasecmp(company_display_name($a['company_name'] ?? ''), company_display_name($b['company_name'] ?? ''));
    }

    if ($sort === 'interns') {
        $internCompare = (int)($b['intern_count'] ?? 0) <=> (int)($a['intern_count'] ?? 0);
        if ($internCompare !== 0) {
            return $internCompare;
        }
    }

    $aTime = strtotime((string)($a['latest_activity'] ?: $a['updated_at'] ?: $a['created_at'] ?: '')) ?: 0;
    $bTime = strtotime((string)($b['latest_activity'] ?: $b['updated_at'] ?: $b['created_at'] ?: '')) ?: 0;
    if ($aTime !== $bTime) {
        return $bTime <=> $aTime;
    }

    return strcasecmp(company_display_name($a['company_name'] ?? ''), company_display_name($b['company_name'] ?? ''));
});

$selectedCompany = null;
$selectedCompanyKey = '';
if ($selectedCompanyParam !== '') {
    $normalizedSelectedKey = company_normalize_key($selectedCompanyParam);
    foreach ($companies as $company) {
        if ($company['key'] === $selectedCompanyParam || $company['key'] === $normalizedSelectedKey) {
            $selectedCompany = $company;
            $selectedCompanyKey = $company['key'];
            break;
        }
    }
}
if ($selectedCompany === null && $companies !== []) {
    $selectedCompany = $companies[0];
    $selectedCompanyKey = (string)$selectedCompany['key'];
}

$companyInterns = [];
if ($selectedCompany !== null && $internshipsTableReady) {
    $selectedKey = (string)$selectedCompany['key'];
    $internSql = "
        SELECT
            s.id AS student_record_id,
            s.user_id,
            s.student_id,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.email,
            COALESCE(NULLIF(u.profile_picture, ''), NULLIF(s.profile_picture, '')) AS profile_picture,
            COALESCE(NULLIF(TRIM(s.assignment_track), ''), 'internal') AS assignment_track,
            COALESCE(NULLIF(sec.code, ''), NULLIF(sec.name, ''), '') AS section_code,
            COALESCE(sec.name, '') AS section_name,
            COALESCE(c.name, '') AS course_name,
            i.id AS internship_id,
            COALESCE(i.status, '') AS internship_status,
            COALESCE(i.type, '') AS internship_type,
            COALESCE(i.position, '') AS position,
            COALESCE(i.start_date, '') AS start_date,
            COALESCE(i.end_date, '') AS end_date,
            COALESCE(i.required_hours, 0) AS required_hours,
            COALESCE(i.rendered_hours, 0) AS rendered_hours
        FROM students s
        INNER JOIN (
            SELECT i_full.*
            FROM internships i_full
            INNER JOIN (
                SELECT student_id, MAX(id) AS latest_id
                FROM internships
                WHERE deleted_at IS NULL
                GROUP BY student_id
            ) i_latest ON i_latest.latest_id = i_full.id
            WHERE i_full.deleted_at IS NULL
        ) i ON i.student_id = s.id
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN courses c ON s.course_id = c.id
        WHERE LOWER(TRIM(COALESCE(i.company_name, ''))) = ?
        ORDER BY
            CASE
                WHEN i.status = 'ongoing' THEN 0
                WHEN i.status = 'pending' THEN 1
                WHEN i.status = 'completed' THEN 2
                ELSE 3
            END,
            s.last_name ASC,
            s.first_name ASC
    ";
    $internStmt = $conn->prepare($internSql);
    if ($internStmt) {
        $internStmt->bind_param('s', $selectedKey);
        $internStmt->execute();
        $internResult = $internStmt->get_result();
        while ($internResult && ($row = $internResult->fetch_assoc())) {
            $sectionLabel = biotern_format_section_label((string)($row['section_code'] ?? ''), (string)($row['section_name'] ?? ''));
            if ($sectionLabel === '') {
                $sectionLabel = '-';
            }

            $profileUrl = resolve_profile_image_url((string)($row['profile_picture'] ?? ''), (int)($row['user_id'] ?? 0));
            $requiredHours = (int)($row['required_hours'] ?? 0);
            $renderedHours = (int)($row['rendered_hours'] ?? 0);

            $row['display_name'] = trim(implode(' ', array_filter([
                (string)($row['first_name'] ?? ''),
                (string)($row['middle_name'] ?? ''),
                (string)($row['last_name'] ?? ''),
            ])));
            $row['section_label'] = $sectionLabel;
            $row['profile_url'] = $profileUrl;
            $row['progress_pct'] = company_progress_pct($renderedHours, $requiredHours);
            $companyInterns[] = $row;
        }
        $internStmt->close();
    }
}

$page_title = 'Companies';
$page_body_class = 'companies-page';
$page_styles = [
    'assets/css/modules/management/management-companies.css',
];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Companies</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Academic</li>
                    <li class="breadcrumb-item">Companies</li>
                </ul>
            </div>
            <div class="page-header-right ms-auto">
                <a href="companies.php" class="btn btn-outline-primary">
                    <i class="feather-refresh-cw me-2"></i>
                    <span>Refresh</span>
                </a>
            </div>
        </div>

        <div class="main-content">
            <div class="row g-3 companies-stat-grid">
                <div class="col-12 col-md-4">
                    <article class="card companies-stat-card h-100">
                        <div class="card-body">
                            <span class="companies-stat-label">Companies</span>
                            <h3 class="companies-stat-value"><?php echo count($companies); ?></h3>
                            <p class="companies-stat-note mb-0">Distinct company records merged from partner profiles and internship assignments.</p>
                        </div>
                    </article>
                </div>
                <div class="col-12 col-md-4">
                    <article class="card companies-stat-card h-100">
                        <div class="card-body">
                            <span class="companies-stat-label">Ongoing Interns</span>
                            <h3 class="companies-stat-value"><?php echo (int)$totalOngoingInterns; ?></h3>
                            <p class="companies-stat-note mb-0">Students currently attached to company assignments with active internship status.</p>
                        </div>
                    </article>
                </div>
                <div class="col-12 col-md-4">
                    <article class="card companies-stat-card h-100">
                        <div class="card-body">
                            <span class="companies-stat-label">With Company Profile</span>
                            <h3 class="companies-stat-value"><?php echo (int)$companiesWithProfiles; ?></h3>
                            <p class="companies-stat-note mb-0">Companies that already have supervisor or representative information saved.</p>
                        </div>
                    </article>
                </div>
            </div>

            <div class="card stretch stretch-full companies-shell-card">
                <div class="card-header companies-shell-header">
                    <div>
                        <h5 class="card-title mb-1">Company Directory</h5>
                        <p class="companies-shell-copy mb-0">Browse companies on the left, then inspect one company’s profile and its assigned interns on the right.</p>
                    </div>
                    <form method="get" class="companies-toolbar" action="companies.php">
                        <input type="hidden" name="company" value="<?php echo h($selectedCompanyKey); ?>">
                        <label class="companies-toolbar-field">
                            <span class="visually-hidden">Search companies</span>
                            <input type="search" class="form-control" name="q" value="<?php echo h($search); ?>" placeholder="Search company, address, supervisor">
                        </label>
                        <label class="companies-toolbar-field companies-toolbar-select">
                            <span class="visually-hidden">Sort companies</span>
                            <select class="form-select" name="sort">
                                <option value="updated" <?php echo $sort === 'updated' ? 'selected' : ''; ?>>Recently Updated</option>
                                <option value="interns" <?php echo $sort === 'interns' ? 'selected' : ''; ?>>Most Interns</option>
                                <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Company Name</option>
                            </select>
                        </label>
                        <button type="submit" class="btn btn-primary">Apply</button>
                    </form>
                </div>
                <div class="card-body companies-shell-body">
                    <div class="companies-layout">
                        <aside class="companies-list-panel">
                            <div class="companies-list-panel-head">
                                <h6 class="mb-1">All Companies</h6>
                                <p class="mb-0"><?php echo count($companies); ?> result(s)</p>
                            </div>
                            <?php if ($companies === []): ?>
                                <div class="companies-empty-state companies-list-empty-state">
                                    <div class="companies-empty-icon"><i class="feather-briefcase"></i></div>
                                    <h6>No companies found</h6>
                                    <p class="mb-0">Add internship company names or import partner companies to populate this directory.</p>
                                </div>
                            <?php else: ?>
                                <div class="companies-list-scroll">
                                    <?php foreach ($companies as $company): ?>
                                        <?php
                                        $isActive = $selectedCompanyKey !== '' && $selectedCompanyKey === (string)$company['key'];
                                        $companyHref = 'companies.php?' . http_build_query([
                                            'q' => $search,
                                            'sort' => $sort,
                                            'company' => $company['key'],
                                        ]);
                                        ?>
                                        <a href="<?php echo h($companyHref); ?>" class="companies-list-item<?php echo $isActive ? ' is-active' : ''; ?>">
                                            <div class="companies-list-item-main">
                                                <div class="companies-list-item-copy">
                                                    <h6 class="mb-1"><?php echo h(company_display_name($company['company_name'] ?? '')); ?></h6>
                                                    <p class="mb-0"><?php echo h(trim((string)($company['supervisor_name'] ?? '')) !== '' ? (string)$company['supervisor_name'] : 'No supervisor profile yet'); ?></p>
                                                </div>
                                                <span class="companies-list-count"><?php echo (int)($company['intern_count'] ?? 0); ?></span>
                                            </div>
                                            <div class="companies-list-meta">
                                                <span><?php echo (int)($company['ongoing_count'] ?? 0); ?> ongoing</span>
                                                <span><?php echo h(company_datetime_label((string)($company['latest_activity'] ?: $company['updated_at'] ?: ''))); ?></span>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </aside>

                        <section class="companies-detail-panel">
                            <?php if (!$companyTableReady && !$internshipsTableReady): ?>
                                <div class="companies-empty-state">
                                    <div class="companies-empty-icon"><i class="feather-alert-circle"></i></div>
                                    <h6>Company data source unavailable</h6>
                                    <p class="mb-0">The page is ready, but the supporting company and internship tables are not accessible right now.</p>
                                </div>
                            <?php elseif ($selectedCompany === null): ?>
                                <div class="companies-empty-state">
                                    <div class="companies-empty-icon"><i class="feather-briefcase"></i></div>
                                    <h6>Select a company</h6>
                                    <p class="mb-0">Choose a company from the list to view its profile and the students assigned there.</p>
                                </div>
                            <?php else: ?>
                                <div class="companies-detail-head">
                                    <div>
                                        <div class="companies-detail-badges">
                                            <span class="companies-detail-badge"><?php echo h(company_display_name($selectedCompany['company_name'] ?? '')); ?></span>
                                            <?php if (!empty($selectedCompany['has_partner_record'])): ?>
                                                <span class="companies-detail-badge is-neutral">Profile Linked</span>
                                            <?php endif; ?>
                                        </div>
                                        <h4 class="companies-detail-title mb-1"><?php echo h(company_display_name($selectedCompany['company_name'] ?? '')); ?></h4>
                                        <p class="companies-detail-subtitle mb-0"><?php echo h(trim((string)($selectedCompany['company_address'] ?? '')) !== '' ? (string)$selectedCompany['company_address'] : 'No company address saved yet.'); ?></p>
                                    </div>
                                    <div class="companies-detail-metrics">
                                        <div class="companies-mini-stat">
                                            <span>Interns</span>
                                            <strong><?php echo (int)($selectedCompany['intern_count'] ?? 0); ?></strong>
                                        </div>
                                        <div class="companies-mini-stat">
                                            <span>Ongoing</span>
                                            <strong><?php echo (int)($selectedCompany['ongoing_count'] ?? 0); ?></strong>
                                        </div>
                                        <div class="companies-mini-stat">
                                            <span>Updated</span>
                                            <strong><?php echo h(company_datetime_label((string)($selectedCompany['latest_activity'] ?: $selectedCompany['updated_at'] ?: ''))); ?></strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3 companies-detail-grid">
                                    <div class="col-12 col-lg-4">
                                        <article class="companies-info-card h-100">
                                            <h6 class="mb-3">Company Information</h6>
                                            <dl class="companies-info-list mb-0">
                                                <div>
                                                    <dt>Supervisor</dt>
                                                    <dd><?php echo h(trim((string)($selectedCompany['supervisor_name'] ?? '')) !== '' ? (string)$selectedCompany['supervisor_name'] : 'Not provided'); ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Supervisor Position</dt>
                                                    <dd><?php echo h(trim((string)($selectedCompany['supervisor_position'] ?? '')) !== '' ? (string)$selectedCompany['supervisor_position'] : 'Not provided'); ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Representative</dt>
                                                    <dd><?php echo h(trim((string)($selectedCompany['company_representative'] ?? '')) !== '' ? (string)$selectedCompany['company_representative'] : 'Not provided'); ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Address</dt>
                                                    <dd><?php echo h(trim((string)($selectedCompany['company_address'] ?? '')) !== '' ? (string)$selectedCompany['company_address'] : 'Not provided'); ?></dd>
                                                </div>
                                            </dl>
                                        </article>
                                    </div>
                                    <div class="col-12 col-lg-8">
                                        <article class="companies-intern-card h-100">
                                            <div class="companies-intern-card-head">
                                                <div>
                                                    <h6 class="mb-1">Student Interns</h6>
                                                    <p class="mb-0">Students whose latest internship record is attached to this company.</p>
                                                </div>
                                                <span class="companies-intern-count"><?php echo count($companyInterns); ?> student(s)</span>
                                            </div>

                                            <?php if ($companyInterns === []): ?>
                                                <div class="companies-empty-state companies-intern-empty-state">
                                                    <div class="companies-empty-icon"><i class="feather-users"></i></div>
                                                    <h6>No student interns linked yet</h6>
                                                    <p class="mb-0">This company exists in the directory, but no latest internship record currently points to it.</p>
                                                </div>
                                            <?php else: ?>
                                                <div class="companies-intern-list">
                                                    <?php foreach ($companyInterns as $intern): ?>
                                                        <article class="companies-intern-item">
                                                            <div class="companies-intern-primary">
                                                                <div class="companies-intern-avatar">
                                                                    <?php if (!empty($intern['profile_url'])): ?>
                                                                        <img src="<?php echo h((string)$intern['profile_url']); ?>" alt="<?php echo h((string)$intern['display_name']); ?>">
                                                                    <?php else: ?>
                                                                        <span><?php echo h(strtoupper(substr((string)($intern['first_name'] ?? 'S'), 0, 1))); ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="companies-intern-copy">
                                                                    <div class="companies-intern-name-row">
                                                                        <h6 class="mb-0"><?php echo h((string)$intern['display_name']); ?></h6>
                                                                        <span class="companies-status-pill <?php echo h(company_status_tone((string)($intern['internship_status'] ?? ''))); ?>">
                                                                            <?php echo h(ucfirst((string)($intern['internship_status'] ?? 'Unknown'))); ?>
                                                                        </span>
                                                                    </div>
                                                                    <p class="mb-0">
                                                                        <?php echo h((string)($intern['course_name'] ?? '-')); ?>
                                                                        <span class="companies-dot">•</span>
                                                                        <?php echo h((string)($intern['section_label'] ?? '-')); ?>
                                                                        <span class="companies-dot">•</span>
                                                                        <?php echo h(company_track_label((string)($intern['assignment_track'] ?? 'internal'))); ?>
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div class="companies-intern-meta">
                                                                <div class="companies-intern-chip-row">
                                                                    <span class="companies-meta-chip"><?php echo h(trim((string)($intern['position'] ?? '')) !== '' ? (string)$intern['position'] : 'Position pending'); ?></span>
                                                                    <span class="companies-meta-chip"><?php echo h(trim((string)($intern['start_date'] ?? '')) !== '' ? date('M d, Y', strtotime((string)$intern['start_date'])) : 'Start date pending'); ?></span>
                                                                </div>
                                                                <div class="companies-progress-row">
                                                                    <div class="companies-progress-track">
                                                                        <span style="width: <?php echo (int)($intern['progress_pct'] ?? 0); ?>%"></span>
                                                                    </div>
                                                                    <div class="companies-progress-copy">
                                                                        <span><?php echo (int)($intern['rendered_hours'] ?? 0); ?> / <?php echo (int)($intern['required_hours'] ?? 0); ?> hrs</span>
                                                                        <strong><?php echo (int)($intern['progress_pct'] ?? 0); ?>%</strong>
                                                                    </div>
                                                                </div>
                                                                <div class="companies-intern-actions">
                                                                    <a href="students-view.php?id=<?php echo (int)($intern['student_record_id'] ?? 0); ?>" class="btn btn-sm btn-outline-primary">Student</a>
                                                                    <a href="ojt-view.php?id=<?php echo (int)($intern['student_record_id'] ?? 0); ?>" class="btn btn-sm btn-outline-secondary">OJT</a>
                                                                </div>
                                                            </div>
                                                        </article>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </article>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php
include 'includes/footer.php';
