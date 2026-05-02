<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
require_once dirname(__DIR__) . '/lib/company_profiles.php';
require_once dirname(__DIR__) . '/lib/ojt_masterlist_import.php';
require_once dirname(__DIR__) . '/tools/excel-workbook-reader.php';
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

function company_current_school_year(): string
{
    $year = (int)date('Y');
    $month = (int)date('n');
    $startYear = $month >= 7 ? $year : ($year - 1);
    return sprintf('%d-%d', $startYear, $startYear + 1);
}

function company_normalize_text(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/\s+/', ' ', $value);
    return (string)$value;
}

function company_student_lookup_key(string $value): string
{
    return (string)preg_replace('/[^a-z0-9]+/', '', strtolower(trim($value)));
}

function company_extract_location_label(?string $address): string
{
    $normalized = company_normalize_text((string)$address);
    if ($normalized === '') {
        return '';
    }

    $knownLocations = [
        'Angeles City' => ['angeles city', 'city of angeles', 'angeles, pampanga', 'angeles'],
        'Mabalacat City' => ['mabalacat city', 'mabalacat, pampanga', 'mabalacat'],
        'Porac' => ['porac, pampanga', 'porac'],
        'Magalang' => ['magalang, pampanga', 'magalang'],
        'Bamban' => ['bamban, tarlac', 'bamban'],
        'San Fernando' => ['city of san fernando', 'san fernando, pampanga', 'san fernando'],
        'Mexico' => ['mexico, pampanga', 'mexico'],
        'Arayat' => ['arayat, pampanga', 'arayat'],
        'Bacolor' => ['bacolor, pampanga', 'bacolor'],
        'Candaba' => ['candaba, pampanga', 'candaba'],
        'Floridablanca' => ['floridablanca, pampanga', 'floridablanca'],
        'Guagua' => ['guagua, pampanga', 'guagua'],
        'Lubao' => ['lubao, pampanga', 'lubao'],
        'Macabebe' => ['macabebe, pampanga', 'macabebe'],
        'Minalin' => ['minalin, pampanga', 'minalin'],
        'Apalit' => ['apalit, pampanga', 'apalit'],
        'Santa Ana' => ['sta ana, pampanga', 'santa ana, pampanga', 'sta. ana', 'santa ana'],
        'Santa Rita' => ['sta rita, pampanga', 'santa rita, pampanga', 'sta. rita', 'santa rita'],
        'Santo Tomas' => ['sto tomas, pampanga', 'santo tomas, pampanga', 'sto. tomas', 'santo tomas'],
        'San Luis' => ['san luis, pampanga', 'san luis'],
        'Clark Freeport Zone' => ['clark freeport zone', 'clark'],
    ];

    foreach ($knownLocations as $label => $needles) {
        foreach ($needles as $needle) {
            if (strpos($normalized, company_normalize_text($needle)) !== false) {
                return $label;
            }
        }
    }

    if (preg_match('/\b([a-z][a-z .-]+ city)\b/i', $normalized, $matches)) {
        return ucwords(trim((string)$matches[1]));
    }

    return '';
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
$openModalId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'save_company') {
    $editingCompanyId = (int)($_POST['company_id'] ?? 0);
    $originalCompanyKey = biotern_company_profile_normalized_name((string)($_POST['original_company_key'] ?? ''));
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
            $savedOk = false;

            if ($editingCompanyId > 0) {
                $existingPicture = '';
                $existingStmt = $conn->prepare("SELECT company_profile_picture FROM ojt_partner_companies WHERE id = ? LIMIT 1");
                if ($existingStmt) {
                    $existingStmt->bind_param('i', $editingCompanyId);
                    $existingStmt->execute();
                    $existingRow = $existingStmt->get_result()->fetch_assoc() ?: null;
                    $existingPicture = trim((string)($existingRow['company_profile_picture'] ?? ''));
                    $existingStmt->close();
                }

                if ($uploadedCompanyPicture === '') {
                    $uploadedCompanyPicture = $existingPicture;
                }

                $saveStmt = $conn->prepare("
                    UPDATE ojt_partner_companies
                    SET
                        company_lookup_key = ?,
                        company_name = ?,
                        company_address = ?,
                        supervisor_name = ?,
                        supervisor_position = ?,
                        company_representative = ?,
                        company_representative_position = ?,
                        company_profile_picture = ?,
                        updated_at = NOW()
                    WHERE id = ?
                    LIMIT 1
                ");

                if (!$saveStmt) {
                    $companyFormErrors[] = 'Unable to update the company right now.';
                } else {
                    $saveStmt->bind_param(
                        'ssssssssi',
                        $lookupKey,
                        $companyForm['company_name'],
                        $companyForm['company_address'],
                        $companyForm['supervisor_name'],
                        $companyForm['supervisor_position'],
                        $companyForm['company_representative'],
                        $companyForm['company_representative_position'],
                        $uploadedCompanyPicture,
                        $editingCompanyId
                    );
                    $savedOk = $saveStmt->execute();
                    if (!$savedOk) {
                        $companyFormErrors[] = 'Saving failed: ' . $saveStmt->error;
                    }
                    $saveStmt->close();
                }

                if ($savedOk && $originalCompanyKey !== '') {
                    $syncStmt = $conn->prepare("
                        UPDATE internships
                        SET company_name = ?, company_address = ?, updated_at = NOW()
                        WHERE deleted_at IS NULL
                          AND LOWER(TRIM(COALESCE(company_name, ''))) = ?
                    ");
                    if ($syncStmt) {
                        $syncStmt->bind_param(
                            'sss',
                            $companyForm['company_name'],
                            $companyForm['company_address'],
                            $originalCompanyKey
                        );
                        $syncStmt->execute();
                        $syncStmt->close();
                    }
                }
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
                    $savedOk = $saveStmt->execute();
                    if (!$savedOk) {
                        $companyFormErrors[] = 'Saving failed: ' . $saveStmt->error;
                    }
                    $saveStmt->close();
                }
            }

            if ($savedOk) {
                $_SESSION['companies_flash'] = [
                    'type' => 'success',
                    'message' => $editingCompanyId > 0
                        ? 'Company profile updated successfully.'
                        : 'Company profile saved successfully.',
                ];

                $redirectParams = [
                    'company' => biotern_company_profile_normalized_name($companyForm['company_name']),
                    'sort' => $returnSort,
                ];
                if ($returnSearch !== '') {
                    $redirectParams['q'] = $returnSearch;
                }
                $returnSchoolYear = trim((string)($_POST['return_school_year'] ?? ''));
                $returnLocation = trim((string)($_POST['return_location'] ?? ''));
                $returnCourseId = (int)($_POST['return_course_id'] ?? 0);
                $returnSectionId = (int)($_POST['return_section_id'] ?? 0);
                if ($returnSchoolYear !== '') {
                    $redirectParams['school_year'] = $returnSchoolYear;
                }
                if ($returnLocation !== '') {
                    $redirectParams['location'] = $returnLocation;
                }
                if ($returnCourseId > 0) {
                    $redirectParams['course_id'] = $returnCourseId;
                }
                if ($returnSectionId > 0) {
                    $redirectParams['section_id'] = $returnSectionId;
                }

                header('Location: companies.php?' . http_build_query($redirectParams));
                exit;
            }
        }
    }

    $openModalId = $editingCompanyId > 0 ? 'viewCompanyProfileModal' : 'addCompanyModal';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'link_company_student') {
    $selectedCompanyName = trim((string)($_POST['selected_company_name'] ?? ''));
    $selectedCompanyAddress = trim((string)($_POST['selected_company_address'] ?? ''));
    $selectedCompanyKeyPost = biotern_company_profile_normalized_name((string)($_POST['selected_company_key'] ?? ''));
    $selectedStudentId = (int)($_POST['student_id'] ?? 0);
    $returnSearch = trim((string)($_POST['return_q'] ?? ''));
    $returnSort = strtolower(trim((string)($_POST['return_sort'] ?? 'updated')));
    $returnSchoolYear = trim((string)($_POST['return_school_year'] ?? ''));
    $returnLocation = trim((string)($_POST['return_location'] ?? ''));
    $returnCourseId = (int)($_POST['return_course_id'] ?? 0);
    $returnSectionId = (int)($_POST['return_section_id'] ?? 0);

    if ($selectedCompanyName === '' || $selectedStudentId <= 0) {
        $_SESSION['companies_flash'] = [
            'type' => 'danger',
            'message' => 'Please choose a student before linking the company.',
        ];
    } else {
        $studentStmt = $conn->prepare("
            SELECT
                id,
                user_id,
                course_id,
                department_id,
                supervisor_id,
                coordinator_id,
                school_year,
                assignment_track,
                internal_total_hours,
                external_total_hours
            FROM students
            WHERE id = ?
            LIMIT 1
        ");

        $studentRow = null;
        if ($studentStmt) {
            $studentStmt->bind_param('i', $selectedStudentId);
            $studentStmt->execute();
            $studentRow = $studentStmt->get_result()->fetch_assoc() ?: null;
            $studentStmt->close();
        }

        if (!$studentRow) {
            $_SESSION['companies_flash'] = [
                'type' => 'danger',
                'message' => 'Student record not found.',
            ];
        } else {
            $track = strtolower(trim((string)($studentRow['assignment_track'] ?? 'internal')));
            if (!in_array($track, ['internal', 'external'], true)) {
                $track = 'internal';
            }
            $requiredHours = $track === 'external'
                ? (int)($studentRow['external_total_hours'] ?? 0)
                : (int)($studentRow['internal_total_hours'] ?? 0);
            if ($requiredHours <= 0) {
                $requiredHours = $track === 'external' ? 250 : 600;
            }
            $schoolYearValue = trim((string)($studentRow['school_year'] ?? ''));
            if ($schoolYearValue === '') {
                $schoolYearValue = company_current_school_year();
            }

            $latestInternshipStmt = $conn->prepare("
                SELECT id
                FROM internships
                WHERE student_id = ? AND deleted_at IS NULL
                ORDER BY updated_at DESC, id DESC
                LIMIT 1
            ");

            $latestInternshipId = 0;
            if ($latestInternshipStmt) {
                $latestInternshipStmt->bind_param('i', $selectedStudentId);
                $latestInternshipStmt->execute();
                $latestInternshipRow = $latestInternshipStmt->get_result()->fetch_assoc() ?: null;
                $latestInternshipId = (int)($latestInternshipRow['id'] ?? 0);
                $latestInternshipStmt->close();
            }

            $linkOk = false;
            if ($latestInternshipId > 0) {
                $linkStmt = $conn->prepare("
                    UPDATE internships
                    SET company_name = ?, company_address = ?, updated_at = NOW()
                    WHERE id = ?
                    LIMIT 1
                ");
                if ($linkStmt) {
                    $linkStmt->bind_param('ssi', $selectedCompanyName, $selectedCompanyAddress, $latestInternshipId);
                    $linkOk = $linkStmt->execute();
                    $linkStmt->close();
                }
            } else {
                $insertStmt = $conn->prepare("
                    INSERT INTO internships (
                        student_id,
                        course_id,
                        department_id,
                        coordinator_id,
                        supervisor_id,
                        type,
                        company_name,
                        company_address,
                        status,
                        school_year,
                        required_hours,
                        rendered_hours,
                        completion_percentage,
                        created_at,
                        updated_at
                    ) VALUES (?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), ?, ?, ?, 'ongoing', ?, ?, 0, 0, NOW(), NOW())
                ");
                if ($insertStmt) {
                    $courseId = (int)($studentRow['course_id'] ?? 0);
                    $departmentId = (int)($studentRow['department_id'] ?? 0);
                    $coordinatorId = (int)($studentRow['coordinator_id'] ?? 0);
                    $supervisorId = (int)($studentRow['supervisor_id'] ?? 0);
                    $insertStmt->bind_param(
                        'iiiiissssi',
                        $selectedStudentId,
                        $courseId,
                        $departmentId,
                        $coordinatorId,
                        $supervisorId,
                        $track,
                        $selectedCompanyName,
                        $selectedCompanyAddress,
                        $schoolYearValue,
                        $requiredHours
                    );
                    $linkOk = $insertStmt->execute();
                    $insertStmt->close();
                }
            }

            $_SESSION['companies_flash'] = [
                'type' => $linkOk ? 'success' : 'danger',
                'message' => $linkOk
                    ? 'Student linked to this company successfully.'
                    : 'Unable to link the selected student right now.',
            ];
        }
    }

    $redirectParams = [
        'company' => $selectedCompanyKeyPost,
        'sort' => in_array($returnSort, ['updated', 'name', 'interns'], true) ? $returnSort : 'updated',
    ];
    if ($returnSearch !== '') {
        $redirectParams['q'] = $returnSearch;
    }
    if ($returnSchoolYear !== '') {
        $redirectParams['school_year'] = $returnSchoolYear;
    }
    $returnSemester = trim((string)($_POST['return_semester'] ?? ''));
    if ($returnSemester !== '') {
        $redirectParams['semester'] = $returnSemester;
    }
    if ($returnLocation !== '') {
        $redirectParams['location'] = $returnLocation;
    }
    if ($returnCourseId > 0) {
        $redirectParams['course_id'] = $returnCourseId;
    }
    if ($returnSectionId > 0) {
        $redirectParams['section_id'] = $returnSectionId;
    }

    header('Location: companies.php?' . http_build_query($redirectParams));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'unlink_company_student') {
    $selectedCompanyKeyPost = biotern_company_profile_normalized_name((string)($_POST['selected_company_key'] ?? ''));
    $selectedStudentId = (int)($_POST['student_id'] ?? 0);
    $selectedInternshipId = (int)($_POST['internship_id'] ?? 0);
    $returnSearch = trim((string)($_POST['return_q'] ?? ''));
    $returnSort = strtolower(trim((string)($_POST['return_sort'] ?? 'updated')));
    $returnSchoolYear = trim((string)($_POST['return_school_year'] ?? ''));
    $returnSemester = trim((string)($_POST['return_semester'] ?? ''));
    $returnLocation = trim((string)($_POST['return_location'] ?? ''));
    $returnCourseId = (int)($_POST['return_course_id'] ?? 0);
    $returnSectionId = (int)($_POST['return_section_id'] ?? 0);

    $unlinkOk = false;
    if ($selectedInternshipId > 0) {
        $unlinkStmt = $conn->prepare("
            UPDATE internships
            SET company_name = '', company_address = '', updated_at = NOW()
            WHERE id = ? AND deleted_at IS NULL
            LIMIT 1
        ");
        if ($unlinkStmt) {
            $unlinkStmt->bind_param('i', $selectedInternshipId);
            $unlinkOk = $unlinkStmt->execute();
            $unlinkStmt->close();
        }
    } elseif ($selectedStudentId > 0) {
        $unlinkStmt = $conn->prepare("
            UPDATE internships
            SET company_name = '', company_address = '', updated_at = NOW()
            WHERE student_id = ? AND deleted_at IS NULL
            ORDER BY updated_at DESC, id DESC
            LIMIT 1
        ");
        if ($unlinkStmt) {
            $unlinkStmt->bind_param('i', $selectedStudentId);
            $unlinkOk = $unlinkStmt->execute();
            $unlinkStmt->close();
        }
    }

    $_SESSION['companies_flash'] = [
        'type' => $unlinkOk ? 'success' : 'danger',
        'message' => $unlinkOk
            ? 'Student removed from this company successfully.'
            : 'Unable to remove the student from this company right now.',
    ];

    $redirectParams = [
        'company' => $selectedCompanyKeyPost,
        'sort' => in_array($returnSort, ['updated', 'name', 'interns'], true) ? $returnSort : 'updated',
    ];
    if ($returnSearch !== '') {
        $redirectParams['q'] = $returnSearch;
    }
    if ($returnSchoolYear !== '') {
        $redirectParams['school_year'] = $returnSchoolYear;
    }
    if ($returnSemester !== '') {
        $redirectParams['semester'] = $returnSemester;
    }
    if ($returnLocation !== '') {
        $redirectParams['location'] = $returnLocation;
    }
    if ($returnCourseId > 0) {
        $redirectParams['course_id'] = $returnCourseId;
    }
    if ($returnSectionId > 0) {
        $redirectParams['section_id'] = $returnSectionId;
    }

    header('Location: companies.php?' . http_build_query($redirectParams));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'import_external_template') {
    $returnSearch = trim((string)($_POST['return_q'] ?? ''));
    $returnSort = strtolower(trim((string)($_POST['return_sort'] ?? 'updated')));
    $returnSchoolYear = trim((string)($_POST['return_school_year'] ?? ''));
    $returnSemester = trim((string)($_POST['return_semester'] ?? ''));
    $returnLocation = trim((string)($_POST['return_location'] ?? ''));
    $returnCourseId = (int)($_POST['return_course_id'] ?? 0);
    $returnSectionId = (int)($_POST['return_section_id'] ?? 0);

    if (!in_array($role, ['admin', 'coordinator'], true)) {
        $_SESSION['companies_flash'] = [
            'type' => 'danger',
            'message' => 'Only admins and coordinators can import the external students template.',
        ];
    } elseif (!isset($_FILES['external_template_file']) || !is_array($_FILES['external_template_file'])) {
        $_SESSION['companies_flash'] = [
            'type' => 'danger',
            'message' => 'Choose an External Students Template workbook first.',
        ];
    } else {
        $uploadError = (int)($_FILES['external_template_file']['error'] ?? UPLOAD_ERR_NO_FILE);
        $tmpName = (string)($_FILES['external_template_file']['tmp_name'] ?? '');
        $originalName = trim((string)($_FILES['external_template_file']['name'] ?? 'External Students Template.xlsx'));
        if ($uploadError !== UPLOAD_ERR_OK || $tmpName === '' || !(is_uploaded_file($tmpName) || is_file($tmpName))) {
            $_SESSION['companies_flash'] = [
                'type' => 'danger',
                'message' => 'External template upload failed. Please choose the .xlsx file again.',
            ];
        } else {
            $workbookError = '';
            $rows = ojt_import_load_workbook_rows($tmpName, $originalName, $workbookError);
            if ($rows === []) {
                $_SESSION['companies_flash'] = [
                    'type' => 'danger',
                    'message' => $workbookError !== '' ? $workbookError : 'Unable to read the External Students Template workbook.',
                ];
            } elseif (!biotern_ojt_masterlist_header_present($rows)) {
                $_SESSION['companies_flash'] = [
                    'type' => 'danger',
                    'message' => 'Upload the External Students Template format with student_no, school_year, student_name, section, company_name, supervisor_name, and status columns.',
                ];
            } else {
                $importErrors = [];
                $imported = biotern_ojt_masterlist_import_rows($conn, $rows, $originalName, 'external', $importErrors);
                $_SESSION['companies_flash'] = [
                    'type' => $imported > 0 ? 'success' : 'warning',
                    'message' => 'External Students Template import finished. Rows saved: ' . $imported . ($importErrors !== [] ? ' Some rows need review: ' . implode(' ', array_slice($importErrors, 0, 3)) : ''),
                ];
            }
        }
    }

    $redirectParams = [
        'sort' => in_array($returnSort, ['updated', 'name', 'interns'], true) ? $returnSort : 'updated',
    ];
    if ($returnSearch !== '') {
        $redirectParams['q'] = $returnSearch;
    }
    if ($returnSchoolYear !== '') {
        $redirectParams['school_year'] = $returnSchoolYear;
    }
    if ($returnSemester !== '') {
        $redirectParams['semester'] = $returnSemester;
    }
    if ($returnLocation !== '') {
        $redirectParams['location'] = $returnLocation;
    }
    if ($returnCourseId > 0) {
        $redirectParams['course_id'] = $returnCourseId;
    }
    if ($returnSectionId > 0) {
        $redirectParams['section_id'] = $returnSectionId;
    }

    header('Location: companies.php?' . http_build_query($redirectParams));
    exit;
}

$search = trim((string)($_GET['q'] ?? ''));
$sort = strtolower(trim((string)($_GET['sort'] ?? 'updated')));
$selectedCompanyParam = trim((string)($_GET['company'] ?? ''));
$filterSchoolYear = trim((string)($_GET['school_year'] ?? ''));
$filterSemester = trim((string)($_GET['semester'] ?? ''));
$filterLocation = trim((string)($_GET['location'] ?? ''));
$filterCourseId = (int)($_GET['course_id'] ?? 0);
$filterSectionId = (int)($_GET['section_id'] ?? 0);
$printTarget = strtolower(trim((string)($_GET['print'] ?? '')));
$allowedSorts = ['updated', 'name', 'interns'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'updated';
}
if (!in_array($printTarget, ['', 'companies', 'students'], true)) {
    $printTarget = '';
}

$externalTemplateQuery = array_filter([
    'type' => 'external',
    'school_year' => $filterSchoolYear,
    'semester' => $filterSemester,
    'course_id' => $filterCourseId > 0 ? (string)$filterCourseId : '',
    'section_id' => $filterSectionId > 0 ? (string)$filterSectionId : '',
    'search' => $search,
], static fn($value): bool => $value !== '' && $value !== null);
$externalTemplateExportUrl = 'export-ojt-list.php?' . http_build_query($externalTemplateQuery);

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

$companyInternshipsByKey = [];
$companyInternshipSeenByKey = [];
$courseFilterOptions = [];
$sectionFilterOptions = [];
$schoolYearFilterOptions = [];
$semesterFilterOptions = [];
$locationFilterOptions = [];
$studentLinkOptions = [];

if ($internshipsTableReady) {
    $latestInternshipsSql = "
        SELECT
            s.id AS student_record_id,
            s.user_id,
            s.student_id,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.email,
            s.course_id,
            s.section_id,
            COALESCE(NULLIF(TRIM(s.assignment_track), ''), 'internal') AS assignment_track,
            COALESCE(s.internal_total_hours, 0) AS internal_total_hours,
            s.internal_total_hours_remaining AS internal_total_hours_remaining,
            COALESCE(s.external_total_hours, 0) AS external_total_hours,
            s.external_total_hours_remaining AS external_total_hours_remaining,
            COALESCE(NULLIF(TRIM(s.school_year), ''), NULLIF(TRIM(i.school_year), ''), '') AS school_year,
            COALESCE(NULLIF(TRIM(s.semester), ''), NULLIF(TRIM(i.semester), ''), '') AS semester,
            COALESCE(NULLIF(u.profile_picture, ''), NULLIF(s.profile_picture, '')) AS profile_picture,
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
            COALESCE(i.rendered_hours, 0) AS rendered_hours,
            COALESCE(i.company_name, '') AS company_name,
            COALESCE(i.company_address, '') AS company_address,
            COALESCE(i.updated_at, '') AS latest_activity
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
        ORDER BY s.last_name ASC, s.first_name ASC
    ";

    $latestInternshipsResult = $conn->query($latestInternshipsSql);
    if ($latestInternshipsResult) {
        while ($row = $latestInternshipsResult->fetch_assoc()) {
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
            $track = strtolower(trim((string)($row['internship_type'] ?? '')));
            if (!in_array($track, ['internal', 'external'], true)) {
                $track = strtolower(trim((string)($row['assignment_track'] ?? 'internal')));
            }
            if (!in_array($track, ['internal', 'external'], true)) {
                $track = 'external';
            }
            $track = 'external';
            $row['resolved_track'] = $track;

            $requiredHours = (int)($row['required_hours'] ?? 0);
            if ($requiredHours <= 0) {
                $requiredHours = (int)($row['external_total_hours'] ?? 0);
            }
            if ($requiredHours <= 0) {
                $requiredHours = 250;
            }

            $remainingHoursRaw = $row['external_total_hours_remaining'] ?? null;
            $remainingHours = $remainingHoursRaw !== null && $remainingHoursRaw !== ''
                ? max(0, (int)$remainingHoursRaw)
                : null;
            $renderedHours = (int)($row['rendered_hours'] ?? 0);
            if ($renderedHours <= 0 && $remainingHours !== null) {
                $renderedHours = max(0, $requiredHours - $remainingHours);
            }
            $renderedHours = min($requiredHours, max(0, $renderedHours));

            $row['required_hours'] = $requiredHours;
            $row['rendered_hours'] = $renderedHours;
            $row['progress_pct'] = company_progress_pct($renderedHours, $requiredHours);

            $companyKey = biotern_company_profile_normalized_name((string)($row['company_name'] ?? ''));
            if ($companyKey !== '') {
                if (!isset($companyMap[$companyKey])) {
                    $companyMap[$companyKey] = [
                        'key' => $companyKey,
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

                if ($companyMap[$companyKey]['company_name'] === '') {
                    $companyMap[$companyKey]['company_name'] = trim((string)($row['company_name'] ?? ''));
                }
                if ($companyMap[$companyKey]['company_address'] === '') {
                    $companyMap[$companyKey]['company_address'] = trim((string)($row['company_address'] ?? ''));
                }

                if (!isset($companyInternshipsByKey[$companyKey])) {
                    $companyInternshipsByKey[$companyKey] = [];
                }
                $companyInternshipsByKey[$companyKey][] = $row;
                if (!isset($companyInternshipSeenByKey[$companyKey])) {
                    $companyInternshipSeenByKey[$companyKey] = [];
                }
                $studentNoSeen = company_student_lookup_key((string)($row['student_id'] ?? ''));
                if ($studentNoSeen !== '') {
                    $companyInternshipSeenByKey[$companyKey][$studentNoSeen] = true;
                }

                $locationLabel = company_extract_location_label((string)($companyMap[$companyKey]['company_address'] ?? $row['company_address'] ?? ''));
                if ($locationLabel !== '') {
                    $locationFilterOptions[$locationLabel] = $locationLabel;
                }
            }

            $courseId = (int)($row['course_id'] ?? 0);
            $courseName = trim((string)($row['course_name'] ?? ''));
            if ($courseId > 0 && $courseName !== '') {
                $courseFilterOptions[$courseId] = $courseName;
            }

            $sectionId = (int)($row['section_id'] ?? 0);
            if ($sectionId > 0 && $sectionLabel !== '') {
                $sectionFilterOptions[$sectionId] = $sectionLabel;
            }

            $schoolYear = trim((string)($row['school_year'] ?? ''));
            if ($schoolYear !== '') {
                $schoolYearFilterOptions[$schoolYear] = $schoolYear;
            }
            $semester = trim((string)($row['semester'] ?? ''));
            if ($semester !== '') {
                $semesterFilterOptions[$semester] = $semester;
            }
        }
    }

    $studentLinkSql = "
        SELECT
            s.id,
            s.student_id,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.assignment_track,
            s.school_year,
            s.semester,
            s.course_id,
            s.section_id,
            c.name AS course_name,
            sec.code AS section_code,
            sec.name AS section_name,
            COALESCE(i.company_name, '') AS current_company_name
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN (
            SELECT i_full.student_id, i_full.company_name
            FROM internships i_full
            INNER JOIN (
                SELECT student_id, MAX(id) AS latest_id
                FROM internships
                WHERE deleted_at IS NULL
                  AND LOWER(COALESCE(type, 'external')) = 'external'
                GROUP BY student_id
            ) latest ON latest.latest_id = i_full.id
            WHERE i_full.deleted_at IS NULL
              AND LOWER(COALESCE(i_full.type, 'external')) = 'external'
        ) i ON i.student_id = s.id
        WHERE s.deleted_at IS NULL
        ORDER BY
            CASE WHEN LOWER(COALESCE(s.assignment_track, 'internal')) = 'external' THEN 0 ELSE 1 END,
            s.last_name ASC,
            s.first_name ASC
    ";
    $studentLinkResult = $conn->query($studentLinkSql);
    if ($studentLinkResult) {
        while ($row = $studentLinkResult->fetch_assoc()) {
            $row['section_label'] = biotern_format_section_label((string)($row['section_code'] ?? ''), (string)($row['section_name'] ?? ''));
            $studentLinkOptions[] = $row;
        }
    }

    $studentFilterSql = "
        SELECT
            s.school_year,
            s.semester,
            s.course_id,
            s.section_id,
            c.name AS course_name,
            sec.code AS section_code,
            sec.name AS section_name
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE s.deleted_at IS NULL
    ";
    $studentFilterResult = $conn->query($studentFilterSql);
    if ($studentFilterResult) {
        while ($row = $studentFilterResult->fetch_assoc()) {
            $schoolYear = trim((string)($row['school_year'] ?? ''));
            if ($schoolYear !== '') {
                $schoolYearFilterOptions[$schoolYear] = $schoolYear;
            }
            $semester = trim((string)($row['semester'] ?? ''));
            if ($semester !== '') {
                $semesterFilterOptions[$semester] = $semester;
            }
            $courseId = (int)($row['course_id'] ?? 0);
            $courseName = trim((string)($row['course_name'] ?? ''));
            if ($courseId > 0 && $courseName !== '') {
                $courseFilterOptions[$courseId] = $courseName;
            }
            $sectionId = (int)($row['section_id'] ?? 0);
            $sectionLabel = biotern_format_section_label((string)($row['section_code'] ?? ''), (string)($row['section_name'] ?? ''));
            if ($sectionId > 0 && $sectionLabel !== '') {
                $sectionFilterOptions[$sectionId] = $sectionLabel;
            }
        }
    }
}

if (companies_table_exists($conn, 'ojt_masterlist')) {
    $masterlistSql = "
        SELECT
            ml.id AS masterlist_id,
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
            ml.updated_at,
            s.id AS student_record_id,
            s.user_id,
            s.student_id,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.email,
            s.course_id,
            s.section_id,
            COALESCE(NULLIF(s.assignment_track, ''), 'external') AS assignment_track,
            COALESCE(s.external_total_hours, 250) AS external_total_hours,
            s.external_total_hours_remaining AS external_total_hours_remaining,
            COALESCE(NULLIF(u.profile_picture, ''), NULLIF(s.profile_picture, '')) AS profile_picture,
            COALESCE(NULLIF(sec.code, ''), NULLIF(sec.name, ''), '') AS section_code,
            COALESCE(sec.name, '') AS section_name,
            COALESCE(c.name, '') AS course_name
        FROM ojt_masterlist ml
        LEFT JOIN students s
            ON TRIM(COALESCE(s.student_id, '')) COLLATE utf8mb4_unicode_ci = TRIM(COALESCE(ml.student_no, '')) COLLATE utf8mb4_unicode_ci
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN courses c ON s.course_id = c.id
        WHERE TRIM(COALESCE(ml.company_name, '')) <> ''
        ORDER BY ml.section ASC, ml.student_name ASC, ml.id ASC
    ";
    $masterlistResult = $conn->query($masterlistSql);
    if ($masterlistResult) {
        while ($row = $masterlistResult->fetch_assoc()) {
            $companyKey = biotern_company_profile_normalized_name((string)($row['company_name'] ?? ''));
            if ($companyKey === '') {
                continue;
            }

            $studentSeenKey = company_student_lookup_key((string)($row['student_no'] ?? ''));
            if ($studentSeenKey !== '' && isset($companyInternshipSeenByKey[$companyKey][$studentSeenKey])) {
                continue;
            }

            $companyName = trim((string)($row['company_name'] ?? ''));
            if (!isset($companyMap[$companyKey])) {
                $companyMap[$companyKey] = [
                    'key' => $companyKey,
                    'partner_company_id' => 0,
                    'company_lookup_key' => '',
                    'company_name' => $companyName,
                    'company_address' => trim((string)($row['company_address'] ?? '')),
                    'supervisor_name' => trim((string)($row['supervisor_name'] ?? '')),
                    'supervisor_position' => trim((string)($row['supervisor_position'] ?? '')),
                    'company_representative' => trim((string)($row['company_representative'] ?? '')),
                    'company_representative_position' => '',
                    'company_profile_picture' => '',
                    'company_profile_picture_src' => '',
                    'created_at' => '',
                    'updated_at' => trim((string)($row['updated_at'] ?? '')),
                    'intern_count' => 0,
                    'ongoing_count' => 0,
                    'latest_activity' => trim((string)($row['updated_at'] ?? '')),
                    'has_partner_record' => false,
                ];
            }
            foreach (['company_address', 'supervisor_name', 'supervisor_position', 'company_representative'] as $fieldName) {
                if (trim((string)($companyMap[$companyKey][$fieldName] ?? '')) === '' && trim((string)($row[$fieldName] ?? '')) !== '') {
                    $companyMap[$companyKey][$fieldName] = trim((string)$row[$fieldName]);
                }
            }

            $sectionLabel = biotern_format_section_label((string)($row['section_code'] ?? ''), (string)($row['section_name'] ?? ''));
            if ($sectionLabel === '') {
                $sectionLabel = trim((string)($row['section'] ?? ''));
            }
            if ($sectionLabel === '') {
                $sectionLabel = '-';
            }

            $displayName = trim(implode(' ', array_filter([
                (string)($row['first_name'] ?? ''),
                (string)($row['middle_name'] ?? ''),
                (string)($row['last_name'] ?? ''),
            ])));
            if ($displayName === '') {
                $displayName = trim((string)($row['student_name'] ?? ''));
            }

            $requiredHours = (int)($row['external_total_hours'] ?? 250);
            if ($requiredHours <= 0) {
                $requiredHours = 250;
            }
            $remainingHoursRaw = $row['external_total_hours_remaining'] ?? null;
            $renderedHours = $remainingHoursRaw !== null && $remainingHoursRaw !== ''
                ? max(0, $requiredHours - max(0, (int)$remainingHoursRaw))
                : 0;

            $row['student_id'] = trim((string)($row['student_id'] ?? '')) !== '' ? (string)$row['student_id'] : (string)($row['student_no'] ?? '');
            $row['display_name'] = $displayName !== '' ? $displayName : 'Unnamed Student';
            $row['section_label'] = $sectionLabel;
            $row['profile_url'] = resolve_profile_image_url((string)($row['profile_picture'] ?? ''), (int)($row['user_id'] ?? 0));
            $row['resolved_track'] = 'external';
            $row['required_hours'] = $requiredHours;
            $row['rendered_hours'] = min($requiredHours, max(0, $renderedHours));
            $row['progress_pct'] = company_progress_pct((int)$row['rendered_hours'], $requiredHours);
            $row['internship_id'] = 0;
            $row['internship_status'] = trim((string)($row['status'] ?? '')) !== '' ? strtolower(trim((string)$row['status'])) : 'ongoing';
            $row['internship_type'] = 'external';
            $row['position'] = trim((string)($row['supervisor_position'] ?? ''));
            $row['start_date'] = '';
            $row['end_date'] = '';
            $row['latest_activity'] = trim((string)($row['updated_at'] ?? ''));
            $row['source_type'] = 'masterlist';

            if (!isset($companyInternshipsByKey[$companyKey])) {
                $companyInternshipsByKey[$companyKey] = [];
            }
            $companyInternshipsByKey[$companyKey][] = $row;
            if ($studentSeenKey !== '') {
                $companyInternshipSeenByKey[$companyKey][$studentSeenKey] = true;
            }

            $courseId = (int)($row['course_id'] ?? 0);
            $courseName = trim((string)($row['course_name'] ?? ''));
            if ($courseId > 0 && $courseName !== '') {
                $courseFilterOptions[$courseId] = $courseName;
            }
            $sectionId = (int)($row['section_id'] ?? 0);
            if ($sectionId > 0 && $sectionLabel !== '') {
                $sectionFilterOptions[$sectionId] = $sectionLabel;
            }
            $schoolYear = trim((string)($row['school_year'] ?? ''));
            if ($schoolYear !== '') {
                $schoolYearFilterOptions[$schoolYear] = $schoolYear;
            }
            $semester = trim((string)($row['semester'] ?? ''));
            if ($semester !== '') {
                $semesterFilterOptions[$semester] = $semester;
            }
            $locationLabel = company_extract_location_label((string)($companyMap[$companyKey]['company_address'] ?? ''));
            if ($locationLabel !== '') {
                $locationFilterOptions[$locationLabel] = $locationLabel;
            }
        }
    }
}

foreach ($companyMap as $companyOptionRow) {
    $companyLocationOption = company_extract_location_label((string)($companyOptionRow['company_address'] ?? ''));
    if ($companyLocationOption !== '') {
        $locationFilterOptions[$companyLocationOption] = $companyLocationOption;
    }
}

ksort($courseFilterOptions, SORT_NATURAL | SORT_FLAG_CASE);
asort($sectionFilterOptions, SORT_NATURAL | SORT_FLAG_CASE);
krsort($schoolYearFilterOptions, SORT_NATURAL);
asort($semesterFilterOptions, SORT_NATURAL | SORT_FLAG_CASE);
asort($locationFilterOptions, SORT_NATURAL | SORT_FLAG_CASE);

$hasStudentFilters = $filterSchoolYear !== '' || $filterSemester !== '' || $filterCourseId > 0 || $filterSectionId > 0;
$companies = [];
foreach ($companyMap as $key => $company) {
    $companyRows = $companyInternshipsByKey[$key] ?? [];
    $companyLocation = company_extract_location_label((string)($company['company_address'] ?? ''));
    $filteredRows = array_values(array_filter($companyRows, static function (array $row) use ($filterSchoolYear, $filterSemester, $filterCourseId, $filterSectionId): bool {
        if ($filterSchoolYear !== '' && trim((string)($row['school_year'] ?? '')) !== $filterSchoolYear) {
            return false;
        }
        if ($filterSemester !== '' && trim((string)($row['semester'] ?? '')) !== $filterSemester) {
            return false;
        }
        if ($filterCourseId > 0 && (int)($row['course_id'] ?? 0) !== $filterCourseId) {
            return false;
        }
        if ($filterSectionId > 0 && (int)($row['section_id'] ?? 0) !== $filterSectionId) {
            return false;
        }
        return true;
    }));

    $matchesLocation = $filterLocation === '' || $companyLocation === $filterLocation;

    $company['location_label'] = $companyLocation;
    $company['all_interns'] = $companyRows;
    $company['filtered_interns'] = $filteredRows;
    $company['intern_count'] = count($filteredRows);
    $company['ongoing_count'] = count(array_filter($filteredRows, static function (array $row): bool {
        return strtolower(trim((string)($row['internship_status'] ?? ''))) === 'ongoing';
    }));

    $latestActivity = trim((string)($company['updated_at'] ?? ''));
    foreach ($filteredRows as $row) {
        $candidate = trim((string)($row['latest_activity'] ?? ''));
        if ($candidate !== '' && (strtotime($candidate) ?: 0) > (strtotime($latestActivity) ?: 0)) {
            $latestActivity = $candidate;
        }
    }
    $company['latest_activity'] = $latestActivity;

    $matchesSearch = true;
    if ($search !== '') {
        $needle = biotern_company_profile_normalized_name($search);
        $haystack = biotern_company_profile_normalized_name(implode(' ', [
            (string)($company['company_name'] ?? ''),
            (string)($company['company_address'] ?? ''),
            (string)($company['supervisor_name'] ?? ''),
            (string)($company['supervisor_position'] ?? ''),
            (string)($company['company_representative'] ?? ''),
            (string)($company['company_representative_position'] ?? ''),
            $companyLocation,
        ]));
        $matchesSearch = $needle === '' || ($haystack !== '' && strpos($haystack, $needle) !== false);
    }

    if (!$matchesSearch) {
        continue;
    }

    if (!$matchesLocation) {
        continue;
    }

    if ($hasStudentFilters && $filteredRows === []) {
        continue;
    }

    $companies[] = $company;
}

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
if ($selectedCompany !== null) {
    $companyInterns = array_values($selectedCompany['filtered_interns'] ?? []);
}

if ($printTarget !== '') {
    if ($printTarget === 'students' && $selectedCompany === null) {
        http_response_code(404);
        echo 'No company selected for printing.';
        exit;
    }
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo h($printTarget === 'students' ? 'Company Student List' : 'Company Lists'); ?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        .print-head { display: grid; grid-template-columns: 92px 1fr 92px; align-items: center; border-bottom: 1.5px solid #2f6fca; padding: 6px 0 10px; margin-bottom: 18px; color: #0b4aa2; }
        .print-head img { width: 74px; height: 74px; object-fit: contain; justify-self: center; }
        .print-head-copy { text-align: center; }
        .print-head h2 { margin: 0 0 4px; font-size: 18px; letter-spacing: 0; font-weight: 700; color: #0b4aa2; }
        .print-head div { font-size: 12px; color: #0b4aa2; line-height: 1.35; }
        .print-head .print-tel { font-size: 15px; margin-top: 3px; }
        .print-head-spacer { width: 92px; height: 1px; }
        .print-title { margin: 16px 0 8px; text-align: center; font-weight: 700; font-size: 15px; }
        .print-meta { margin-bottom: 8px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; table-layout: fixed; }
        th, td { border: 1px solid #1f2937; padding: 6px 7px; vertical-align: top; word-break: break-word; }
        th { background: #f3f4f6; font-weight: 700; }
        .col-index { width: 36px; text-align: center; }
        small { display: block; color: #4b5563; margin-top: 2px; }
        @media print { body { margin: 12mm; } }
    </style>
</head>
<body>
    <div class="print-head">
        <img src="assets/images/ccstlogo.png" alt="CCST">
        <div class="print-head-copy">
            <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
            <div>SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
            <div class="print-tel">Telefax No.: (045) 624-0215</div>
        </div>
        <div class="print-head-spacer" aria-hidden="true"></div>
    </div>
    <?php if ($printTarget === 'companies'): ?>
        <div class="print-title">COMPANY LISTS</div>
        <div class="print-meta"><strong>FILTER:</strong> <?php echo h(($filterSchoolYear !== '' ? $filterSchoolYear : 'All School Years') . ' / ' . ($filterSemester !== '' ? $filterSemester : 'All Semesters') . ' / ' . ($filterLocation !== '' ? $filterLocation : 'All Locations')); ?></div>
        <table>
            <thead>
                <tr>
                    <th class="col-index">#</th>
                    <th>COMPANY NAME</th>
                    <th>ADDRESS</th>
                    <th>SUPERVISOR</th>
                    <th>REPRESENTATIVE</th>
                    <th>STUDENTS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $index => $company): ?>
                    <tr>
                        <td class="col-index"><?php echo (int)$index + 1; ?></td>
                        <td><?php echo h(company_display_name((string)($company['company_name'] ?? ''))); ?></td>
                        <td><?php echo h(trim((string)($company['company_address'] ?? '')) !== '' ? (string)$company['company_address'] : 'Not provided'); ?></td>
                        <td>
                            <?php echo h(trim((string)($company['supervisor_name'] ?? '')) !== '' ? (string)$company['supervisor_name'] : 'Not provided'); ?>
                            <?php if (trim((string)($company['supervisor_position'] ?? '')) !== ''): ?><small><?php echo h((string)$company['supervisor_position']); ?></small><?php endif; ?>
                        </td>
                        <td>
                            <?php echo h(trim((string)($company['company_representative'] ?? '')) !== '' ? (string)$company['company_representative'] : 'Not provided'); ?>
                            <?php if (trim((string)($company['company_representative_position'] ?? '')) !== ''): ?><small><?php echo h((string)$company['company_representative_position']); ?></small><?php endif; ?>
                        </td>
                        <td><?php echo (int)($company['intern_count'] ?? 0); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="print-title">COMPANY STUDENT LIST</div>
        <div class="print-meta"><strong>COMPANY:</strong> <?php echo h(company_display_name((string)($selectedCompany['company_name'] ?? ''))); ?></div>
        <div class="print-meta"><strong>SUPERVISOR:</strong> <?php echo h(trim((string)($selectedCompany['supervisor_name'] ?? '')) !== '' ? (string)$selectedCompany['supervisor_name'] : 'Not provided'); ?></div>
        <table>
            <thead>
                <tr>
                    <th class="col-index">#</th>
                    <th>STUDENT NO.</th>
                    <th>LAST NAME</th>
                    <th>FIRST NAME</th>
                    <th>MIDDLE NAME</th>
                    <th>COURSE / SECTION</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($companyInterns === []): ?>
                    <tr><td class="col-index">1</td><td colspan="6">No students matched the current filter for this company.</td></tr>
                <?php else: ?>
                    <?php foreach ($companyInterns as $index => $intern): ?>
                        <tr>
                            <td class="col-index"><?php echo (int)$index + 1; ?></td>
                            <td><?php echo h((string)($intern['student_id'] ?? '')); ?></td>
                            <td><?php echo h((string)($intern['last_name'] ?? '')); ?></td>
                            <td><?php echo h((string)($intern['first_name'] ?? '')); ?></td>
                            <td><?php echo h((string)($intern['middle_name'] ?? '')); ?></td>
                            <td><?php echo h(trim((string)($intern['course_name'] ?? '-') . ' / ' . (string)($intern['section_label'] ?? '-'))); ?></td>
                            <td><?php echo h(ucfirst((string)($intern['internship_status'] ?? 'Unknown'))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
    <?php
    exit;
}

$page_title = 'Companies';
$page_body_class = 'companies-page' . ($printTarget !== '' ? (' companies-print-' . $printTarget) : '');
$page_styles = [
    'assets/css/modules/management/management-companies.css',
    'assets/css/modules/management/management-students.css',
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
            <div class="page-header-right ms-auto companies-page-header-actions">
                <form method="get" class="companies-toolbar companies-page-header-toolbar" action="companies.php">
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
                </form>
                <a href="<?php echo h('companies.php?' . http_build_query([
                    'q' => $search,
                    'sort' => $sort,
                    'company' => $selectedCompanyKey,
                    'school_year' => $filterSchoolYear,
                    'semester' => $filterSemester,
                    'location' => $filterLocation,
                    'course_id' => $filterCourseId > 0 ? $filterCourseId : null,
                    'section_id' => $filterSectionId > 0 ? $filterSectionId : null,
                    'print' => 'companies',
                ])); ?>" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                    <i class="feather-printer me-2"></i>
                    <span>Print Companies</span>
                </a>
                <a href="<?php echo h($externalTemplateExportUrl); ?>" class="btn btn-outline-primary">
                    <i class="feather-download me-2"></i>
                    <span>Export External Template</span>
                </a>
                <?php if (in_array($role, ['admin', 'coordinator'], true)): ?>
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importExternalTemplateModal">
                        <i class="feather-upload-cloud me-2"></i>
                        <span>Import External Template</span>
                    </button>
                <?php endif; ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
                    <i class="feather-plus me-2"></i>
                    <span>Add Company</span>
                </button>
                <a href="companies.php" class="btn btn-outline-primary">
                    <i class="feather-refresh-cw me-2"></i>
                    <span>Refresh</span>
                </a>
                <a href="<?php echo h('companies.php?' . http_build_query([
                    'q' => $search,
                    'sort' => $sort,
                    'company' => $selectedCompanyKey,
                ])); ?>" class="btn btn-outline-secondary">
                    <i class="feather-rotate-ccw me-2"></i>
                    <span>Reset Filters</span>
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

            <form method="get" action="companies.php" class="companies-filter-bar">
                <input type="hidden" name="company" value="<?php echo h($selectedCompanyKey); ?>">
                <input type="hidden" name="q" value="<?php echo h($search); ?>">
                <input type="hidden" name="sort" value="<?php echo h($sort); ?>">
                <div class="companies-filter-fields">
                    <label class="companies-filter-field">
                        <span class="visually-hidden">School Year</span>
                        <select class="form-select form-select-sm" id="companiesFilterSchoolYear" name="school_year">
                            <option value="">School year</option>
                            <?php foreach ($schoolYearFilterOptions as $schoolYearOption): ?>
                                <option value="<?php echo h($schoolYearOption); ?>" <?php echo $filterSchoolYear === $schoolYearOption ? 'selected' : ''; ?>><?php echo h($schoolYearOption); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="companies-filter-field">
                        <span class="visually-hidden">Semester</span>
                        <select class="form-select form-select-sm" id="companiesFilterSemester" name="semester">
                            <option value="">Semester</option>
                            <?php foreach ($semesterFilterOptions as $semesterOption): ?>
                                <option value="<?php echo h($semesterOption); ?>" <?php echo $filterSemester === $semesterOption ? 'selected' : ''; ?>><?php echo h($semesterOption); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="companies-filter-field">
                        <span class="visually-hidden">Location</span>
                        <select class="form-select form-select-sm" id="companiesFilterLocation" name="location">
                            <option value="">Location</option>
                            <?php foreach ($locationFilterOptions as $locationOption): ?>
                                <option value="<?php echo h($locationOption); ?>" <?php echo $filterLocation === $locationOption ? 'selected' : ''; ?>><?php echo h($locationOption); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="companies-filter-field">
                        <span class="visually-hidden">Course</span>
                        <select class="form-select form-select-sm" id="companiesFilterCourse" name="course_id">
                            <option value="0">Course</option>
                            <?php foreach ($courseFilterOptions as $courseIdOption => $courseNameOption): ?>
                                <option value="<?php echo (int)$courseIdOption; ?>" <?php echo $filterCourseId === (int)$courseIdOption ? 'selected' : ''; ?>><?php echo h($courseNameOption); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="companies-filter-field">
                        <span class="visually-hidden">Section</span>
                        <select class="form-select form-select-sm" id="companiesFilterSection" name="section_id">
                            <option value="0">Section</option>
                            <?php foreach ($sectionFilterOptions as $sectionIdOption => $sectionLabelOption): ?>
                                <option value="<?php echo (int)$sectionIdOption; ?>" <?php echo $filterSectionId === (int)$sectionIdOption ? 'selected' : ''; ?>><?php echo h($sectionLabelOption); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </form>

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
                                            'school_year' => $filterSchoolYear,
                                            'semester' => $filterSemester,
                                            'location' => $filterLocation,
                                            'course_id' => $filterCourseId > 0 ? $filterCourseId : null,
                                            'section_id' => $filterSectionId > 0 ? $filterSectionId : null,
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
                                            <h4 class="companies-detail-title mb-1"><?php echo h(company_display_name($selectedCompany['company_name'] ?? '')); ?></h4>
                                            <p class="companies-detail-subtitle mb-0"><?php echo h(trim((string)($selectedCompany['company_address'] ?? '')) !== '' ? (string)$selectedCompany['company_address'] : 'No company address saved yet.'); ?></p>
                                        </div>
                                    </div>
                                    <div class="companies-detail-actions">
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#linkStudentModal">
                                            <i class="feather-user-plus me-2"></i>
                                            <span>Add Student</span>
                                        </button>
                                        <a href="<?php echo h('companies.php?' . http_build_query([
                                            'q' => $search,
                                            'sort' => $sort,
                                            'company' => $selectedCompanyKey,
                                            'school_year' => $filterSchoolYear,
                                            'semester' => $filterSemester,
                                            'location' => $filterLocation,
                                            'course_id' => $filterCourseId > 0 ? $filterCourseId : null,
                                            'section_id' => $filterSectionId > 0 ? $filterSectionId : null,
                                            'print' => 'students',
                                        ])); ?>" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                                            <i class="feather-printer me-2"></i>
                                            <span>Print Students</span>
                                        </a>
                                        <a href="document_application.php?company=<?php echo urlencode((string)$selectedCompanyKey); ?>" class="btn btn-outline-secondary">
                                            <i class="feather-file-text me-2"></i>
                                            <span>Application</span>
                                        </a>
                                        <a href="document_endorsement.php?company=<?php echo urlencode((string)$selectedCompanyKey); ?>" class="btn btn-outline-primary">
                                            <i class="feather-send me-2"></i>
                                            <span>Endorsement</span>
                                        </a>
                                        <a href="document_moa.php?company=<?php echo urlencode((string)$selectedCompanyKey); ?>" class="btn btn-outline-secondary">
                                            <i class="feather-briefcase me-2"></i>
                                            <span>MOA</span>
                                        </a>
                                        <a href="document_dau_moa.php?company=<?php echo urlencode((string)$selectedCompanyKey); ?>" class="btn btn-outline-secondary">
                                            <i class="feather-map-pin me-2"></i>
                                            <span>DAU MOA</span>
                                        </a>
                                        <button type="button" class="btn btn-outline-primary companies-action-wide" data-bs-toggle="modal" data-bs-target="#viewCompanyProfileModal">
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
                                                                        <?php echo h(company_track_label((string)($intern['resolved_track'] ?? $intern['internship_type'] ?? 'external'))); ?>
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
                                                                    <form method="post" action="companies.php" class="companies-inline-action-form">
                                                                        <input type="hidden" name="action" value="unlink_company_student">
                                                                        <input type="hidden" name="selected_company_key" value="<?php echo h((string)$selectedCompanyKey); ?>">
                                                                        <input type="hidden" name="student_id" value="<?php echo (int)($intern['student_record_id'] ?? 0); ?>">
                                                                        <input type="hidden" name="internship_id" value="<?php echo (int)($intern['internship_id'] ?? 0); ?>">
                                                                        <input type="hidden" name="return_q" value="<?php echo h($search); ?>">
                                                                        <input type="hidden" name="return_sort" value="<?php echo h($sort); ?>">
                                                                        <input type="hidden" name="return_school_year" value="<?php echo h($filterSchoolYear); ?>">
                                                                        <input type="hidden" name="return_semester" value="<?php echo h($filterSemester); ?>">
                                                                        <input type="hidden" name="return_location" value="<?php echo h($filterLocation); ?>">
                                                                        <input type="hidden" name="return_course_id" value="<?php echo (int)$filterCourseId; ?>">
                                                                        <input type="hidden" name="return_section_id" value="<?php echo (int)$filterSectionId; ?>">
                                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                                                    </form>
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
</main>

<?php if (in_array($role, ['admin', 'coordinator'], true)): ?>
<div class="modal fade" id="importExternalTemplateModal" tabindex="-1" aria-labelledby="importExternalTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content companies-modal">
            <form method="post" action="companies.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_external_template">
                <input type="hidden" name="return_q" value="<?php echo h($search); ?>">
                <input type="hidden" name="return_sort" value="<?php echo h($sort); ?>">
                <input type="hidden" name="return_school_year" value="<?php echo h($filterSchoolYear); ?>">
                <input type="hidden" name="return_semester" value="<?php echo h($filterSemester); ?>">
                <input type="hidden" name="return_location" value="<?php echo h($filterLocation); ?>">
                <input type="hidden" name="return_course_id" value="<?php echo (int)$filterCourseId; ?>">
                <input type="hidden" name="return_section_id" value="<?php echo (int)$filterSectionId; ?>">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="importExternalTemplateModalLabel">Import External Students Template</h5>
                        <p class="companies-modal-copy mb-0">Upload the External Students Template workbook to refresh company assignments and company profile details.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="external_template_file">External Students Template.xlsx</label>
                        <input
                            type="file"
                            class="form-control"
                            id="external_template_file"
                            name="external_template_file"
                            accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                            required
                        >
                    </div>
                    <div class="small text-muted">
                        Duplicate student numbers are merged into the imported masterlist; non-duplicate rows are added and become visible in this company list.
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="download-external-template.php" class="btn btn-outline-secondary">Download Blank Template</a>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import Template</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="addCompanyModal" tabindex="-1" aria-labelledby="addCompanyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content companies-modal">
            <form method="post" action="companies.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_company">
                <input type="hidden" name="return_q" value="<?php echo h($search); ?>">
                <input type="hidden" name="return_sort" value="<?php echo h($sort); ?>">
                <input type="hidden" name="return_school_year" value="<?php echo h($filterSchoolYear); ?>">
                <input type="hidden" name="return_semester" value="<?php echo h($filterSemester); ?>">
                <input type="hidden" name="return_location" value="<?php echo h($filterLocation); ?>">
                <input type="hidden" name="return_course_id" value="<?php echo (int)$filterCourseId; ?>">
                <input type="hidden" name="return_section_id" value="<?php echo (int)$filterSectionId; ?>">
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
            <form method="post" action="companies.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_company">
                <input type="hidden" name="company_id" value="<?php echo (int)($selectedCompany['partner_company_id'] ?? 0); ?>">
                <input type="hidden" name="original_company_key" value="<?php echo h((string)($selectedCompany['key'] ?? '')); ?>">
                <input type="hidden" name="return_q" value="<?php echo h($search); ?>">
                <input type="hidden" name="return_sort" value="<?php echo h($sort); ?>">
                <input type="hidden" name="return_school_year" value="<?php echo h($filterSchoolYear); ?>">
                <input type="hidden" name="return_semester" value="<?php echo h($filterSemester); ?>">
                <input type="hidden" name="return_location" value="<?php echo h($filterLocation); ?>">
                <input type="hidden" name="return_course_id" value="<?php echo (int)$filterCourseId; ?>">
                <input type="hidden" name="return_section_id" value="<?php echo (int)$filterSectionId; ?>">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="viewCompanyProfileModalLabel">Company Profile</h5>
                        <p class="companies-modal-copy mb-0">Update the saved company information here. Student document autofill and linked company views will use this profile.</p>
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
                            <?php if (!empty($selectedCompany['location_label'])): ?>
                                <span class="companies-meta-chip"><?php echo h((string)$selectedCompany['location_label']); ?></span>
                            <?php endif; ?>
                            <span class="companies-meta-chip"><?php echo h(company_datetime_label((string)($selectedCompany['latest_activity'] ?: $selectedCompany['updated_at'] ?: ''))); ?></span>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <a href="document_application.php?company=<?php echo urlencode((string)$selectedCompanyKey); ?>" class="btn btn-outline-secondary btn-sm">Application</a>
                            <a href="document_endorsement.php?company=<?php echo urlencode((string)$selectedCompanyKey); ?>" class="btn btn-outline-primary btn-sm">Endorsement</a>
                            <a href="document_moa.php?company=<?php echo urlencode((string)$selectedCompanyKey); ?>" class="btn btn-outline-secondary btn-sm">MOA</a>
                            <a href="document_dau_moa.php?company=<?php echo urlencode((string)$selectedCompanyKey); ?>" class="btn btn-outline-secondary btn-sm">DAU MOA</a>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-12 col-lg-4">
                        <article class="companies-info-card h-100">
                            <h6 class="mb-3">Company Information</h6>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="edit_company_profile_picture">Company Profile Picture</label>
                                    <input
                                        type="file"
                                        class="form-control"
                                        id="edit_company_profile_picture"
                                        name="company_profile_picture"
                                        accept=".jpg,.jpeg,.png,.webp,.gif,image/*"
                                    >
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="edit_company_name">Company Name</label>
                                    <input type="text" class="form-control" id="edit_company_name" name="company_name" value="<?php echo h((string)($selectedCompany['company_name'] ?? '')); ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="edit_company_address">Company Address</label>
                                    <textarea class="form-control" id="edit_company_address" name="company_address" rows="3"><?php echo h((string)($selectedCompany['company_address'] ?? '')); ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="edit_company_representative">Representative</label>
                                    <input type="text" class="form-control" id="edit_company_representative" name="company_representative" value="<?php echo h((string)($selectedCompany['company_representative'] ?? '')); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="edit_company_representative_position">Representative Position</label>
                                    <input type="text" class="form-control" id="edit_company_representative_position" name="company_representative_position" value="<?php echo h((string)($selectedCompany['company_representative_position'] ?? '')); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="edit_supervisor_name">Supervisor</label>
                                    <input type="text" class="form-control" id="edit_supervisor_name" name="supervisor_name" value="<?php echo h((string)($selectedCompany['supervisor_name'] ?? '')); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="edit_supervisor_position">Supervisor Position</label>
                                    <input type="text" class="form-control" id="edit_supervisor_position" name="supervisor_position" value="<?php echo h((string)($selectedCompany['supervisor_position'] ?? '')); ?>">
                                </div>
                            </div>
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Company Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="linkStudentModal" tabindex="-1" aria-labelledby="linkStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content companies-modal">
            <form method="post" action="companies.php">
                <input type="hidden" name="action" value="link_company_student">
                <input type="hidden" name="selected_company_key" value="<?php echo h((string)$selectedCompanyKey); ?>">
                <input type="hidden" name="selected_company_name" value="<?php echo h((string)($selectedCompany['company_name'] ?? '')); ?>">
                <input type="hidden" name="selected_company_address" value="<?php echo h((string)($selectedCompany['company_address'] ?? '')); ?>">
                <input type="hidden" name="return_q" value="<?php echo h($search); ?>">
                <input type="hidden" name="return_sort" value="<?php echo h($sort); ?>">
                <input type="hidden" name="return_school_year" value="<?php echo h($filterSchoolYear); ?>">
                <input type="hidden" name="return_semester" value="<?php echo h($filterSemester); ?>">
                <input type="hidden" name="return_location" value="<?php echo h($filterLocation); ?>">
                <input type="hidden" name="return_course_id" value="<?php echo (int)$filterCourseId; ?>">
                <input type="hidden" name="return_section_id" value="<?php echo (int)$filterSectionId; ?>">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="linkStudentModalLabel">Add Student To Company</h5>
                        <p class="companies-modal-copy mb-0">This links the selected student's latest internship record to <?php echo h(company_display_name((string)($selectedCompany['company_name'] ?? ''))); ?> so the company also appears in the student's profile pages.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="student_id" id="linkStudentSelectedId" value="">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold" for="linkStudentSearch">Search</label>
                            <input type="search" class="form-control" id="linkStudentSearch" placeholder="Student no, name">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold" for="linkStudentSchoolYearFilter">School Year</label>
                            <select class="form-select" id="linkStudentSchoolYearFilter">
                                <option value="">All school years</option>
                                <?php foreach ($schoolYearFilterOptions as $schoolYearOption): ?>
                                    <option value="<?php echo h($schoolYearOption); ?>"><?php echo h($schoolYearOption); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label fw-semibold" for="linkStudentSemesterFilter">Semester</label>
                            <select class="form-select" id="linkStudentSemesterFilter">
                                <option value="">All semesters</option>
                                <?php foreach ($semesterFilterOptions as $semesterOption): ?>
                                    <option value="<?php echo h($semesterOption); ?>"><?php echo h($semesterOption); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label fw-semibold" for="linkStudentCourseFilter">Course</label>
                            <select class="form-select" id="linkStudentCourseFilter">
                                <option value="">All courses</option>
                                <?php foreach ($courseFilterOptions as $courseIdOption => $courseNameOption): ?>
                                    <option value="<?php echo (int)$courseIdOption; ?>"><?php echo h($courseNameOption); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label fw-semibold" for="linkStudentSectionFilter">Section</label>
                            <select class="form-select" id="linkStudentSectionFilter">
                                <option value="">All sections</option>
                                <?php foreach ($sectionFilterOptions as $sectionIdOption => $sectionLabelOption): ?>
                                    <option value="<?php echo (int)$sectionIdOption; ?>"><?php echo h($sectionLabelOption); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive companies-link-student-table-wrap">
                        <table class="table table-hover align-middle mb-0 companies-link-student-table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Student No</th>
                                    <th>Name</th>
                                    <th>Course / Section</th>
                                    <th>Track</th>
                                    <th>School Year</th>
                                    <th>Semester</th>
                                    <th>Current Company</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentLinkOptions as $studentOption): ?>
                                    <tr
                                        data-link-student-row
                                        data-student-id="<?php echo (int)($studentOption['id'] ?? 0); ?>"
                                        data-search="<?php echo h(strtolower(trim((string)($studentOption['student_id'] ?? '') . ' ' . (string)($studentOption['first_name'] ?? '') . ' ' . (string)($studentOption['middle_name'] ?? '') . ' ' . (string)($studentOption['last_name'] ?? '') . ' ' . (string)($studentOption['course_name'] ?? '') . ' ' . (string)($studentOption['section_label'] ?? '')))); ?>"
                                        data-school-year="<?php echo h((string)($studentOption['school_year'] ?? '')); ?>"
                                        data-semester="<?php echo h((string)($studentOption['semester'] ?? '')); ?>"
                                        data-course-id="<?php echo (int)($studentOption['course_id'] ?? 0); ?>"
                                        data-section-id="<?php echo (int)($studentOption['section_id'] ?? 0); ?>"
                                    >
                                        <td>
                                            <input class="form-check-input" type="radio" name="link_student_pick" value="<?php echo (int)($studentOption['id'] ?? 0); ?>">
                                        </td>
                                        <td><?php echo h((string)($studentOption['student_id'] ?? '-')); ?></td>
                                        <td><?php echo h(trim((string)($studentOption['last_name'] ?? '') . ', ' . (string)($studentOption['first_name'] ?? '') . ' ' . (string)($studentOption['middle_name'] ?? ''))); ?></td>
                                        <td>
                                            <div><?php echo h((string)($studentOption['course_name'] ?? '-')); ?></div>
                                            <small class="text-muted"><?php echo h((string)($studentOption['section_label'] ?? '-')); ?></small>
                                        </td>
                                        <td><?php echo h(company_track_label((string)($studentOption['assignment_track'] ?? 'internal'))); ?></td>
                                        <td><?php echo h(trim((string)($studentOption['school_year'] ?? '')) !== '' ? (string)$studentOption['school_year'] : '-'); ?></td>
                                        <td><?php echo h(trim((string)($studentOption['semester'] ?? '')) !== '' ? (string)$studentOption['semester'] : '-'); ?></td>
                                        <td><?php echo h(trim((string)($studentOption['current_company_name'] ?? '')) !== '' ? (string)$studentOption['current_company_name'] : 'No company yet'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Link Student</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<section class="student-list-print-sheet app-students-print-sheet companies-print-sheet companies-print-sheet--companies" aria-hidden="true">
    <img class="crest" src="assets/images/ccstlogo.png" alt="crest" data-hide-onerror="1">
    <div class="header">
        <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
        <div class="meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
        <div class="tel">Telefax No.: (045) 624-0215</div>
    </div>
    <div class="print-title">COMPANY LISTS</div>
    <div class="print-meta"><strong>FILTER:</strong> <?php echo h(($filterSchoolYear !== '' ? $filterSchoolYear : 'All School Years') . ' / ' . ($filterSemester !== '' ? $filterSemester : 'All Semesters') . ' / ' . ($filterLocation !== '' ? $filterLocation : 'All Locations')); ?></div>
    <table>
        <thead>
            <tr>
                <th class="col-index">#</th>
                <th>COMPANY NAME</th>
                <th>ADDRESS</th>
                <th>SUPERVISOR</th>
                <th>REPRESENTATIVE</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($companies as $index => $company): ?>
                <tr>
                    <td class="col-index"><?php echo (int)$index + 1; ?></td>
                    <td><?php echo h(company_display_name((string)($company['company_name'] ?? ''))); ?></td>
                    <td><?php echo h(trim((string)($company['company_address'] ?? '')) !== '' ? (string)$company['company_address'] : 'Not provided'); ?></td>
                    <td><?php echo h(trim((string)($company['supervisor_name'] ?? '')) !== '' ? (string)$company['supervisor_name'] : 'Not provided'); ?></td>
                    <td><?php echo h(trim((string)($company['company_representative'] ?? '')) !== '' ? (string)$company['company_representative'] : 'Not provided'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php if ($selectedCompany !== null): ?>
<section class="student-list-print-sheet app-students-print-sheet companies-print-sheet companies-print-sheet--students" aria-hidden="true">
    <img class="crest" src="assets/images/ccstlogo.png" alt="crest" data-hide-onerror="1">
    <div class="header">
        <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
        <div class="meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
        <div class="tel">Telefax No.: (045) 624-0215</div>
    </div>
    <div class="print-title">COMPANY STUDENT LIST</div>
    <div class="print-meta"><strong>SECTION:</strong> <?php echo h(company_display_name((string)($selectedCompany['company_name'] ?? ''))); ?></div>
    <div class="print-meta"><strong>ADVISER:</strong> <?php echo h(trim((string)($selectedCompany['supervisor_name'] ?? '')) !== '' ? (string)$selectedCompany['supervisor_name'] : 'Not provided'); ?></div>
    <table>
        <thead>
            <tr>
                <th class="col-index">#</th>
                <th>STUDENT NO.</th>
                <th>LAST NAME</th>
                <th>FIRST NAME</th>
                <th>MIDDLE NAME</th>
                <th>REMARKS</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($companyInterns === []): ?>
                <tr>
                    <td class="col-index">1</td>
                    <td colspan="5">No students matched the current filter for this company.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($companyInterns as $index => $intern): ?>
                    <tr>
                        <td class="col-index"><?php echo (int)$index + 1; ?></td>
                        <td><?php echo h((string)($intern['student_id'] ?? '')); ?></td>
                        <td><?php echo h((string)($intern['last_name'] ?? '')); ?></td>
                        <td><?php echo h((string)($intern['first_name'] ?? '')); ?></td>
                        <td><?php echo h((string)($intern['middle_name'] ?? '')); ?></td>
                        <td><?php echo h(ucfirst((string)($intern['internship_status'] ?? 'Unknown'))); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var headerFilterForm = document.querySelector('.companies-page-header-toolbar');
    var headerSearchInput = headerFilterForm ? headerFilterForm.querySelector('input[name="q"]') : null;
    var headerSortSelect = headerFilterForm ? headerFilterForm.querySelector('select[name="sort"]') : null;
    var headerSearchTimer = null;
    var selectedInput = document.getElementById('linkStudentSelectedId');
    var searchInput = document.getElementById('linkStudentSearch');
    var schoolYearFilter = document.getElementById('linkStudentSchoolYearFilter');
    var semesterFilter = document.getElementById('linkStudentSemesterFilter');
    var courseFilter = document.getElementById('linkStudentCourseFilter');
    var sectionFilter = document.getElementById('linkStudentSectionFilter');
    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-link-student-row]'));
    var filterForm = document.querySelector('.companies-filter-bar');
    var filterFields = filterForm ? Array.prototype.slice.call(filterForm.querySelectorAll('select')) : [];

    function submitForm(form) {
        if (!form) return;
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    if (headerSearchInput) {
        headerSearchInput.addEventListener('input', function () {
            if (headerSearchTimer) {
                window.clearTimeout(headerSearchTimer);
            }
            headerSearchTimer = window.setTimeout(function () {
                submitForm(headerFilterForm);
            }, 350);
        });
        headerSearchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                submitForm(headerFilterForm);
            }
        });
    }

    if (headerSortSelect) {
        headerSortSelect.addEventListener('change', function () {
            submitForm(headerFilterForm);
        });
    }

    filterFields.forEach(function (field) {
        field.addEventListener('change', function () {
            submitForm(filterForm);
        });
    });

    function applyStudentLinkFilters() {
        var search = (searchInput && searchInput.value ? searchInput.value : '').toLowerCase().trim();
        var schoolYear = schoolYearFilter ? schoolYearFilter.value.trim() : '';
        var semester = semesterFilter ? semesterFilter.value.trim() : '';
        var courseId = courseFilter ? courseFilter.value.trim() : '';
        var sectionId = sectionFilter ? sectionFilter.value.trim() : '';
        rows.forEach(function (row) {
            var matches = true;
            var haystack = (row.getAttribute('data-search') || '').toLowerCase();
            if (search !== '' && haystack.indexOf(search) === -1) matches = false;
            if (schoolYear !== '' && (row.getAttribute('data-school-year') || '') !== schoolYear) matches = false;
            if (semester !== '' && (row.getAttribute('data-semester') || '') !== semester) matches = false;
            if (courseId !== '' && (row.getAttribute('data-course-id') || '') !== courseId) matches = false;
            if (sectionId !== '' && (row.getAttribute('data-section-id') || '') !== sectionId) matches = false;
            row.style.display = matches ? '' : 'none';
        });
    }

    function selectStudentRow(row) {
        if (!selectedInput || !row) return;
        var studentId = row.getAttribute('data-student-id') || '';
        selectedInput.value = studentId;
        rows.forEach(function (item) {
            item.classList.toggle('is-selected', item === row);
            var radio = item.querySelector('input[type="radio"]');
            if (radio) radio.checked = item === row;
        });
    }

    rows.forEach(function (row) {
        row.addEventListener('click', function (event) {
            if (event.target && event.target.closest('input, button, a, select, label')) {
                if (event.target.matches('input[type="radio"]')) {
                    selectStudentRow(row);
                }
                return;
            }
            selectStudentRow(row);
        });
    });

    [searchInput, schoolYearFilter, semesterFilter, courseFilter, sectionFilter].forEach(function (input) {
        if (input) input.addEventListener('input', applyStudentLinkFilters);
        if (input) input.addEventListener('change', applyStudentLinkFilters);
    });
});
</script>

<?php
include 'includes/footer.php';
if ($openModalId !== '' || $printTarget !== ''):
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if ($openModalId !== ''): ?>
    var modalElement = document.getElementById(<?php echo json_encode($openModalId); ?>);
    if (modalElement && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
    <?php endif; ?>
    <?php if ($printTarget !== ''): ?>
    window.print();
    <?php endif; ?>
});
</script>
<?php
endif;
