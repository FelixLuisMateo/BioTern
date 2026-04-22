<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
require_once dirname(__DIR__) . '/lib/company_profiles.php';
biotern_boot_session(isset($conn) ? $conn : null);

$role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if (!in_array($role, ['admin', 'coordinator', 'supervisor'], true)) {
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
    return strtolower(trim($track)) === 'external' ? 'External' : 'Internal';
}

function company_initials(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return 'CO';
    }

    $parts = preg_split('/\s+/', $value) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $letters .= strtoupper(substr($part, 0, 1));
        if (strlen($letters) >= 2) {
            break;
        }
    }

    return $letters !== '' ? $letters : 'CO';
}

function resolve_profile_image_url(string $profilePath, int $userId = 0): ?string
{
    $resolved = biotern_avatar_public_src($profilePath, $userId);
    return $resolved !== '' ? $resolved : null;
}

$companyTableReady = biotern_company_profiles_ensure_table($conn);
$internshipsTableReady = companies_table_exists($conn, 'internships');

$companyFlash = $_SESSION['companies_flash'] ?? null;
unset($_SESSION['companies_flash']);

$companyForm = [
    'company_name' => '',
    'company_address' => '',
    'supervisor_name' => '',
    'supervisor_position' => '',
    'company_representative' => '',
    'company_representative_position' => '',
];
$companyFormErrors = [];
$showAddCompanyModal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'save_company') {
    $companyForm = [
        'company_name' => trim((string)($_POST['company_name'] ?? '')),
        'company_address' => trim((string)($_POST['company_address'] ?? '')),
        'supervisor_name' => trim((string)($_POST['supervisor_name'] ?? '')),
        'supervisor_position' => trim((string)($_POST['supervisor_position'] ?? '')),
        'company_representative' => trim((string)($_POST['company_representative'] ?? '')),
        'company_representative_position' => trim((string)($_POST['company_representative_position'] ?? '')),
    ];
    $returnSearch = trim((string)($_POST['return_q'] ?? ''));
    $returnSort = strtolower(trim((string)($_POST['return_sort'] ?? 'updated')));
    if (!in_array($returnSort, ['updated', 'name', 'interns'], true)) {
        $returnSort = 'updated';
    }

    if (!$companyTableReady) {
        $companyFormErrors[] = 'The company table is not available right now.';
    }
    if ($companyForm['company_name'] === '') {
        $companyFormErrors[] = 'Company name is required.';
    }

    $uploadedCompanyPicture = '';
    if ($companyFormErrors === [] && isset($_FILES['company_profile_picture']) && is_array($_FILES['company_profile_picture'])) {
        $uploadError = (int)($_FILES['company_profile_picture']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_NO_FILE) {
            if ($uploadError !== UPLOAD_ERR_OK) {
                $companyFormErrors[] = 'The company image upload failed.';
            } else {
                $tmpName = (string)($_FILES['company_profile_picture']['tmp_name'] ?? '');
                $originalName = (string)($_FILES['company_profile_picture']['name'] ?? 'company-image');
                $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (!in_array($extension, $allowedExtensions, true)) {
                    $companyFormErrors[] = 'Use a JPG, PNG, WEBP, or GIF image for the company profile.';
                } else {
                    $uploadDirAbsolute = dirname(__DIR__) . '/uploads/company-profiles';
                    if (!is_dir($uploadDirAbsolute)) {
                        @mkdir($uploadDirAbsolute, 0775, true);
                    }

                    if (!is_dir($uploadDirAbsolute) || !is_writable($uploadDirAbsolute)) {
                        $companyFormErrors[] = 'The company profile image folder is not writable.';
                    } else {
                        try {
                            $randomSuffix = bin2hex(random_bytes(6));
                        } catch (Throwable $e) {
                            $randomSuffix = substr(md5((string)microtime(true)), 0, 12);
                        }

                        $targetFileName = 'company-' . date('YmdHis') . '-' . $randomSuffix . '.' . $extension;
                        $targetAbsolute = $uploadDirAbsolute . '/' . $targetFileName;
                        if (!@move_uploaded_file($tmpName, $targetAbsolute)) {
                            $companyFormErrors[] = 'Unable to move the uploaded company image.';
                        } else {
                            $uploadedCompanyPicture = 'uploads/company-profiles/' . $targetFileName;
                        }
                    }
                }
            }
        }
    }

    if ($companyFormErrors === []) {
        $lookupKey = biotern_company_profile_lookup_key($companyForm['company_name'], $companyForm['company_address']);
        if ($lookupKey === '') {
            $companyFormErrors[] = 'Unable to generate a valid company lookup key.';
        } else {
            $saveStmt = $conn->prepare("
                INSERT INTO ojt_partner_companies (
                    company_lookup_key,
                    company_name,
                    company_address,
                    supervisor_name,
                    supervisor_position,
                    company_representative,
                    company_representative_position,
                    company_profile_picture,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    company_name = VALUES(company_name),
                    company_address = VALUES(company_address),
                    supervisor_name = VALUES(supervisor_name),
                    supervisor_position = VALUES(supervisor_position),
                    company_representative = VALUES(company_representative),
                    company_representative_position = VALUES(company_representative_position),
                    company_profile_picture = CASE
                        WHEN VALUES(company_profile_picture) IS NULL OR VALUES(company_profile_picture) = '' THEN company_profile_picture
                        ELSE VALUES(company_profile_picture)
                    END,
                    updated_at = NOW()
            ");

            if (!$saveStmt) {
                $companyFormErrors[] = 'Unable to save the company right now.';
            } else {
                $saveStmt->bind_param(
                    'ssssssss',
                    $lookupKey,
                    $companyForm['company_name'],
                    $companyForm['company_address'],
                    $companyForm['supervisor_name'],
                    $companyForm['supervisor_position'],
                    $companyForm['company_representative'],
                    $companyForm['company_representative_position'],
                    $uploadedCompanyPicture
                );

                if ($saveStmt->execute()) {
                    $_SESSION['companies_flash'] = [
                        'type' => 'success',
                        'message' => 'Company profile saved successfully.',
                    ];
                    $saveStmt->close();

                    $redirectParams = [
                        'company' => biotern_company_profile_normalized_name($companyForm['company_name']),
                        'sort' => $returnSort,
                    ];
                    if ($returnSearch !== '') {
                        $redirectParams['q'] = $returnSearch;
                    }

                    header('Location: companies.php?' . http_build_query($redirectParams));
                    exit;
                }

                $companyFormErrors[] = 'Saving failed: ' . $saveStmt->error;
                $saveStmt->close();
            }
        }
    }

    $showAddCompanyModal = true;
}

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
            company_representative_position,
            company_profile_picture,
            created_at,
            updated_at
        FROM ojt_partner_companies
        ORDER BY company_name ASC, id DESC
    ");

    if ($partnerResult) {
        while ($row = $partnerResult->fetch_assoc()) {
            $companyName = trim((string)($row['company_name'] ?? ''));
            $key = biotern_company_profile_normalized_name($companyName);
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
                'company_representative_position' => trim((string)($row['company_representative_position'] ?? '')),
                'company_profile_picture' => trim((string)($row['company_profile_picture'] ?? '')),
                'company_profile_picture_src' => biotern_company_profile_public_src((string)($row['company_profile_picture'] ?? '')),
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
                $key = biotern_company_profile_normalized_name((string)($row['company_name'] ?? ''));
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
                    'company_representative_position' => '',
                    'company_profile_picture' => '',
                    'company_profile_picture_src' => '',
                    'created_at' => '',
                    'updated_at' => '',
                    'intern_count' => 0,
                    'ongoing_count' => 0,
                    'latest_activity' => '',
                    'has_partner_record' => false,
                ];
            }

            if ($companyMap[$key]['company_name'] === '') {
                $companyMap[$key]['company_name'] = trim((string)($row['company_name'] ?? ''));
            }
            if ($companyMap[$key]['company_address'] === '') {
                $companyMap[$key]['company_address'] = trim((string)($row['company_address'] ?? ''));
            }
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
    if (
        trim((string)($company['supervisor_name'] ?? '')) !== ''
        || trim((string)($company['company_representative'] ?? '')) !== ''
        || trim((string)($company['company_profile_picture'] ?? '')) !== ''
    ) {
        $companiesWithProfiles++;
    }
}

if ($search !== '') {
    $needle = biotern_company_profile_normalized_name($search);
    $companies = array_values(array_filter($companies, static function (array $company) use ($needle): bool {
        $haystack = biotern_company_profile_normalized_name(implode(' ', [
            (string)($company['company_name'] ?? ''),
            (string)($company['company_address'] ?? ''),
            (string)($company['supervisor_name'] ?? ''),
            (string)($company['supervisor_position'] ?? ''),
            (string)($company['company_representative'] ?? ''),
            (string)($company['company_representative_position'] ?? ''),
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
    $normalizedSelectedKey = biotern_company_profile_normalized_name($selectedCompanyParam);
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

            $row['display_name'] = trim(implode(' ', array_filter([
                (string)($row['first_name'] ?? ''),
                (string)($row['middle_name'] ?? ''),
                (string)($row['last_name'] ?? ''),
            ])));
            $row['section_label'] = $sectionLabel;
            $row['profile_url'] = resolve_profile_image_url((string)($row['profile_picture'] ?? ''), (int)($row['user_id'] ?? 0));
            $row['progress_pct'] = company_progress_pct((int)($row['rendered_hours'] ?? 0), (int)($row['required_hours'] ?? 0));
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
                    <li class="breadcrumb-item">Internship</li>
                    <li class="breadcrumb-item">Companies</li>
                </ul>
            </div>
            <div class="page-header-right ms-auto">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
                    <i class="feather-plus me-2"></i>
                    <span>Add Company</span>
                </button>
                <a href="companies.php" class="btn btn-outline-primary">
                    <i class="feather-refresh-cw me-2"></i>
                    <span>Refresh</span>
                </a>
            </div>
        </div>

        <div class="main-content">
            <?php if (is_array($companyFlash) && !empty($companyFlash['message'])): ?>
                <div class="alert alert-<?php echo h((string)($companyFlash['type'] ?? 'info')); ?> companies-page-alert">
                    <?php echo h((string)$companyFlash['message']); ?>
                </div>
            <?php endif; ?>
            <?php if ($companyFormErrors !== []): ?>
                <div class="alert alert-danger companies-page-alert">
                    <?php echo h($companyFormErrors[0]); ?>
                </div>
            <?php endif; ?>

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
                            <p class="companies-stat-note mb-0">Students currently attached to active company internship records.</p>
                        </div>
                    </article>
                </div>
                <div class="col-12 col-md-4">
                    <article class="card companies-stat-card h-100">
                        <div class="card-body">
                            <span class="companies-stat-label">Profiled Companies</span>
                            <h3 class="companies-stat-value"><?php echo (int)$companiesWithProfiles; ?></h3>
                            <p class="companies-stat-note mb-0">Companies that already include people, image, or richer profile information.</p>
                        </div>
                    </article>
                </div>
            </div>

            <div class="card stretch stretch-full companies-shell-card">
                <div class="card-header companies-shell-header">
                    <div>
                        <h5 class="card-title mb-1">Company Directory</h5>
                        <p class="companies-shell-copy mb-0">Browse companies on the left, inspect one company on the right, or open its full profile popup with the student list.</p>
                    </div>
                    <form method="get" class="companies-toolbar" action="companies.php">
                        <input type="hidden" name="company" value="<?php echo h($selectedCompanyKey); ?>">
                        <label class="companies-toolbar-field">
                            <span class="visually-hidden">Search companies</span>
                            <input type="search" class="form-control" name="q" value="<?php echo h($search); ?>" placeholder="Search company, address, representative">
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
                                    <p class="mb-0">Add company profiles or assign company names in internship records to populate this directory.</p>
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
                                                <div class="companies-list-thumb">
                                                    <?php if (!empty($company['company_profile_picture_src'])): ?>
                                                        <img src="<?php echo h((string)$company['company_profile_picture_src']); ?>" alt="<?php echo h(company_display_name($company['company_name'] ?? '')); ?>">
                                                    <?php else: ?>
                                                        <span><?php echo h(company_initials((string)($company['company_name'] ?? ''))); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="companies-list-item-copy">
                                                    <h6 class="mb-1"><?php echo h(company_display_name($company['company_name'] ?? '')); ?></h6>
                                                    <p class="mb-0">
                                                        <?php
                                                        $listContact = trim((string)($company['company_representative'] ?? ''));
                                                        if ($listContact === '') {
                                                            $listContact = trim((string)($company['supervisor_name'] ?? ''));
                                                        }
                                                        echo h($listContact !== '' ? $listContact : 'No company contact profile yet');
                                                        ?>
                                                    </p>
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
                                    <p class="mb-0">The page is ready, but the company and internship tables are not accessible right now.</p>
                                </div>
                            <?php elseif ($selectedCompany === null): ?>
                                <div class="companies-empty-state">
                                    <div class="companies-empty-icon"><i class="feather-briefcase"></i></div>
                                    <h6>Select a company</h6>
                                    <p class="mb-0">Choose a company from the list to view its profile and the students assigned there.</p>
                                </div>
                            <?php else: ?>
                                <div class="companies-detail-head">
                                    <div class="companies-detail-primary">
                                        <div class="companies-detail-thumb">
                                            <?php if (!empty($selectedCompany['company_profile_picture_src'])): ?>
                                                <img src="<?php echo h((string)$selectedCompany['company_profile_picture_src']); ?>" alt="<?php echo h(company_display_name($selectedCompany['company_name'] ?? '')); ?>">
                                            <?php else: ?>
                                                <span><?php echo h(company_initials((string)($selectedCompany['company_name'] ?? ''))); ?></span>
                                            <?php endif; ?>
                                        </div>
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
                                    </div>
                                    <div class="companies-detail-actions">
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewCompanyProfileModal">
                                            <i class="feather-eye me-2"></i>
                                            <span>Open Profile</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="row g-3 companies-detail-grid">
                                    <div class="col-12 col-lg-4">
                                        <article class="companies-info-card h-100">
                                            <h6 class="mb-3">Company Information</h6>
                                            <dl class="companies-info-list mb-0">
                                                <div>
                                                    <dt>Representative</dt>
                                                    <dd><?php echo h(trim((string)($selectedCompany['company_representative'] ?? '')) !== '' ? (string)$selectedCompany['company_representative'] : 'Not provided'); ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Representative Position</dt>
                                                    <dd><?php echo h(trim((string)($selectedCompany['company_representative_position'] ?? '')) !== '' ? (string)$selectedCompany['company_representative_position'] : 'Not provided'); ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Supervisor</dt>
                                                    <dd><?php echo h(trim((string)($selectedCompany['supervisor_name'] ?? '')) !== '' ? (string)$selectedCompany['supervisor_name'] : 'Not provided'); ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Supervisor Position</dt>
                                                    <dd><?php echo h(trim((string)($selectedCompany['supervisor_position'] ?? '')) !== '' ? (string)$selectedCompany['supervisor_position'] : 'Not provided'); ?></dd>
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
                                                                        <span class="companies-dot">|</span>
                                                                        <?php echo h((string)($intern['section_label'] ?? '-')); ?>
                                                                        <span class="companies-dot">|</span>
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

<div class="modal fade" id="addCompanyModal" tabindex="-1" aria-labelledby="addCompanyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content companies-modal">
            <form method="post" action="companies.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_company">
                <input type="hidden" name="return_q" value="<?php echo h($search); ?>">
                <input type="hidden" name="return_sort" value="<?php echo h($sort); ?>">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="addCompanyModalLabel">Add Company</h5>
                        <p class="companies-modal-copy mb-0">Save a reusable company profile for the company directory and document autofill.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="company_profile_picture">Company Profile Picture</label>
                            <input
                                type="file"
                                class="form-control"
                                id="company_profile_picture"
                                name="company_profile_picture"
                                accept=".jpg,.jpeg,.png,.webp,.gif,image/*"
                            >
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="company_name">Company Name</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo h($companyForm['company_name']); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="company_address">Company Address</label>
                            <textarea class="form-control" id="company_address" name="company_address" rows="3"><?php echo h($companyForm['company_address']); ?></textarea>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold" for="company_representative">Company Representative</label>
                            <input type="text" class="form-control" id="company_representative" name="company_representative" value="<?php echo h($companyForm['company_representative']); ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold" for="company_representative_position">Representative Position</label>
                            <input type="text" class="form-control" id="company_representative_position" name="company_representative_position" value="<?php echo h($companyForm['company_representative_position']); ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold" for="supervisor_name">Supervisor Name</label>
                            <input type="text" class="form-control" id="supervisor_name" name="supervisor_name" value="<?php echo h($companyForm['supervisor_name']); ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold" for="supervisor_position">Supervisor Position</label>
                            <input type="text" class="form-control" id="supervisor_position" name="supervisor_position" value="<?php echo h($companyForm['supervisor_position']); ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Company</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($selectedCompany !== null): ?>
<div class="modal fade" id="viewCompanyProfileModal" tabindex="-1" aria-labelledby="viewCompanyProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content companies-modal companies-profile-modal">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="viewCompanyProfileModalLabel">Company Profile</h5>
                    <p class="companies-modal-copy mb-0">Profile view for <?php echo h(company_display_name($selectedCompany['company_name'] ?? '')); ?> with the current student intern list.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="companies-profile-hero">
                    <div class="companies-profile-hero-thumb">
                        <?php if (!empty($selectedCompany['company_profile_picture_src'])): ?>
                            <img src="<?php echo h((string)$selectedCompany['company_profile_picture_src']); ?>" alt="<?php echo h(company_display_name($selectedCompany['company_name'] ?? '')); ?>">
                        <?php else: ?>
                            <span><?php echo h(company_initials((string)($selectedCompany['company_name'] ?? ''))); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="companies-profile-hero-copy">
                        <h3 class="mb-1"><?php echo h(company_display_name($selectedCompany['company_name'] ?? '')); ?></h3>
                        <p class="mb-2"><?php echo h(trim((string)($selectedCompany['company_address'] ?? '')) !== '' ? (string)$selectedCompany['company_address'] : 'No company address saved yet.'); ?></p>
                        <div class="companies-profile-chip-row">
                            <span class="companies-meta-chip"><?php echo (int)($selectedCompany['intern_count'] ?? 0); ?> interns</span>
                            <span class="companies-meta-chip"><?php echo (int)($selectedCompany['ongoing_count'] ?? 0); ?> ongoing</span>
                            <span class="companies-meta-chip"><?php echo h(company_datetime_label((string)($selectedCompany['latest_activity'] ?: $selectedCompany['updated_at'] ?: ''))); ?></span>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-12 col-lg-4">
                        <article class="companies-info-card h-100">
                            <h6 class="mb-3">Company Information</h6>
                            <dl class="companies-info-list mb-0">
                                <div>
                                    <dt>Representative</dt>
                                    <dd><?php echo h(trim((string)($selectedCompany['company_representative'] ?? '')) !== '' ? (string)$selectedCompany['company_representative'] : 'Not provided'); ?></dd>
                                </div>
                                <div>
                                    <dt>Representative Position</dt>
                                    <dd><?php echo h(trim((string)($selectedCompany['company_representative_position'] ?? '')) !== '' ? (string)$selectedCompany['company_representative_position'] : 'Not provided'); ?></dd>
                                </div>
                                <div>
                                    <dt>Supervisor</dt>
                                    <dd><?php echo h(trim((string)($selectedCompany['supervisor_name'] ?? '')) !== '' ? (string)$selectedCompany['supervisor_name'] : 'Not provided'); ?></dd>
                                </div>
                                <div>
                                    <dt>Supervisor Position</dt>
                                    <dd><?php echo h(trim((string)($selectedCompany['supervisor_position'] ?? '')) !== '' ? (string)$selectedCompany['supervisor_position'] : 'Not provided'); ?></dd>
                                </div>
                            </dl>
                        </article>
                    </div>
                    <div class="col-12 col-lg-8">
                        <article class="companies-intern-card h-100">
                            <div class="companies-intern-card-head">
                                <div>
                                    <h6 class="mb-1">Assigned Students</h6>
                                    <p class="mb-0">Same live list used by the company directory detail panel.</p>
                                </div>
                                <span class="companies-intern-count"><?php echo count($companyInterns); ?> student(s)</span>
                            </div>

                            <?php if ($companyInterns === []): ?>
                                <div class="companies-empty-state companies-intern-empty-state">
                                    <div class="companies-empty-icon"><i class="feather-users"></i></div>
                                    <h6>No student interns linked yet</h6>
                                    <p class="mb-0">This company profile is ready, but there are no current student internship rows attached to it.</p>
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
                                                        <span class="companies-dot">|</span>
                                                        <?php echo h((string)($intern['section_label'] ?? '-')); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="companies-intern-actions">
                                                <a href="students-view.php?id=<?php echo (int)($intern['student_record_id'] ?? 0); ?>" class="btn btn-sm btn-outline-primary">Student</a>
                                                <a href="ojt-view.php?id=<?php echo (int)($intern['student_record_id'] ?? 0); ?>" class="btn btn-sm btn-outline-secondary">OJT</a>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
include 'includes/footer.php';
if ($showAddCompanyModal):
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalElement = document.getElementById('addCompanyModal');
    if (!modalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        return;
    }
    bootstrap.Modal.getOrCreateInstance(modalElement).show();
});
</script>
<?php
endif;
