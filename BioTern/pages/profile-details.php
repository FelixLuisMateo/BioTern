<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
require_once dirname(__DIR__) . '/lib/company_profiles.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
require_once dirname(__DIR__) . '/lib/notifications.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: auth-login-cover.php');
    exit;
}

$user = null;
$stmt = $conn->prepare('SELECT id, name, username, email, password, role, is_active, profile_picture, created_at FROM users WHERE id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$user) {
    header('Location: auth-login-cover.php?logout=1');
    exit;
}

biotern_notifications_ensure_table($conn);

function profile_details_preview(string $value, int $limit = 72): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    if ($value === '') {
        return '';
    }
    return strlen($value) > $limit ? substr($value, 0, $limit - 3) . '...' : $value;
}

function profile_details_value(?string $value, string $fallback = 'Not yet available'): string
{
    $value = trim((string)$value);
    return $value !== '' ? $value : $fallback;
}

function profile_details_format_date(?string $value, string $fallback = 'Not yet available'): string
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    return $timestamp !== false ? date('M d, Y', $timestamp) : $fallback;
}

function profile_details_table_exists(mysqli $conn, string $table): bool
{
    $safeTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function profile_details_build_person_name(?string $firstName, ?string $middleName, ?string $lastName): string
{
    $parts = [];
    $firstName = trim((string)$firstName);
    $middleName = trim((string)$middleName);
    $lastName = trim((string)$lastName);

    if ($firstName !== '') {
        $parts[] = $firstName;
    }
    if ($middleName !== '') {
        $parts[] = $middleName;
    }
    if ($lastName !== '') {
        $parts[] = $lastName;
    }

    return trim(implode(' ', $parts));
}

$profile_flash_message = '';
$profile_flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profile_action = (string)($_POST['action'] ?? '');

    if ($profile_action === 'update_student_profile') {
        $currentRole = strtolower(trim((string)($user['role'] ?? $_SESSION['role'] ?? '')));
        if ($currentRole !== 'student') {
            $profile_flash_message = 'Only student accounts can update student profile details here.';
            $profile_flash_type = 'warning';
        } else {
            $studentRecordId = (int)($_POST['student_record_id'] ?? 0);
            $phone = trim((string)($_POST['phone'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $dateOfBirth = trim((string)($_POST['date_of_birth'] ?? ''));
            $gender = trim((string)($_POST['gender'] ?? ''));
            $emergencyContact = trim((string)($_POST['emergency_contact'] ?? ''));

            if ($dateOfBirth !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth) !== 1) {
                $profile_flash_message = 'Birth date must use a valid date.';
                $profile_flash_type = 'warning';
            } else {
                $updateSql = "UPDATE students
                    SET phone = ?, address = ?, date_of_birth = ?, gender = ?, emergency_contact = ?
                    WHERE ";
                if ($studentRecordId > 0) {
                    $updateSql .= "id = ? LIMIT 1";
                } else {
                    $updateSql .= "user_id = ? LIMIT 1";
                }

                $studentUpdateStmt = $conn->prepare($updateSql);
                if (!$studentUpdateStmt) {
                    $profile_flash_message = 'Could not prepare student profile update.';
                    $profile_flash_type = 'danger';
                } else {
                    $dateParam = $dateOfBirth !== '' ? $dateOfBirth : null;
                    $recordTarget = $studentRecordId > 0 ? $studentRecordId : $userId;
                    $studentUpdateStmt->bind_param('sssssi', $phone, $address, $dateParam, $gender, $emergencyContact, $recordTarget);
                    if ($studentUpdateStmt->execute()) {
                        $profile_flash_message = 'Student profile details updated successfully.';
                        $profile_flash_type = 'success';
                    } else {
                        $profile_flash_message = 'Failed to update student profile details.';
                        $profile_flash_type = 'danger';
                    }
                    $studentUpdateStmt->close();
                }
            }
        }
    } elseif ($profile_action === 'upload_profile_picture') {
        if (!isset($_FILES['profile_picture']) || !is_array($_FILES['profile_picture'])) {
            $profile_flash_message = 'Please choose an image file.';
            $profile_flash_type = 'warning';
        } else {
            $file = $_FILES['profile_picture'];
            if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $profile_flash_message = 'Upload failed. Please try again.';
                $profile_flash_type = 'danger';
            } else {
                $tmp = (string)($file['tmp_name'] ?? '');
                $size = (int)($file['size'] ?? 0);
                $name = (string)($file['name'] ?? '');
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

                $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
                $mime = $finfo ? (string)finfo_file($finfo, $tmp) : '';
                if ($finfo) {
                    unset($finfo);
                }

                if ($size <= 0 || $size > (3 * 1024 * 1024)) {
                    $profile_flash_message = 'Image must be less than 3MB.';
                    $profile_flash_type = 'warning';
                } elseif (!in_array($ext, $allowedExt, true) || ($mime !== '' && !in_array($mime, $allowedMime, true))) {
                    $profile_flash_message = 'Only JPG, PNG, WEBP, or GIF images are allowed.';
                    $profile_flash_type = 'warning';
                } else {
                    $uploadDirFs = dirname(__DIR__) . '/assets/images/avatar/uploads';
                    if (!is_dir($uploadDirFs)) {
                        @mkdir($uploadDirFs, 0777, true);
                    }

                    $safeName = 'user_' . $userId . '_' . time() . '.' . $ext;
                    $destFs = $uploadDirFs . '/' . $safeName;
                    $destRel = 'assets/images/avatar/uploads/' . $safeName;

                    if (!@move_uploaded_file($tmp, $destFs)) {
                        $profile_flash_message = 'Failed to save uploaded file.';
                        $profile_flash_type = 'danger';
                    } else {
                        $oldPath = biotern_avatar_normalize_path((string)($user['profile_picture'] ?? ''));
                        biotern_avatar_sync_profile_path($conn, $userId, $destRel);
                        $freshPath = 'db-avatar';
                        $freshStmt = $conn->prepare('SELECT profile_picture FROM users WHERE id = ? LIMIT 1');
                        if ($freshStmt) {
                            $freshStmt->bind_param('i', $userId);
                            $freshStmt->execute();
                            $freshRow = $freshStmt->get_result()->fetch_assoc() ?: null;
                            $freshStmt->close();
                            $freshPath = trim((string)($freshRow['profile_picture'] ?? $freshPath));
                        }

                        $_SESSION['profile_picture'] = $freshPath !== '' ? $freshPath : $destRel;
                        $user['profile_picture'] = $_SESSION['profile_picture'];
                        $profile_flash_message = 'Profile picture updated successfully.';
                        $profile_flash_type = 'success';

                        if ($oldPath !== '' && strpos($oldPath, 'assets/images/avatar/uploads/') === 0) {
                            $oldFs = dirname(__DIR__) . '/' . $oldPath;
                            if (is_file($oldFs)) {
                                @unlink($oldFs);
                            }
                        }
                    }
                }
            }
        }
    } elseif ($profile_action === 'change_password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $storedPasswordHash = (string)($user['password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $profile_flash_message = 'Please fill in all password fields.';
            $profile_flash_type = 'warning';
        } elseif (!password_verify($currentPassword, $storedPasswordHash)) {
            $profile_flash_message = 'Current password is incorrect.';
            $profile_flash_type = 'danger';
        } elseif ($newPassword !== $confirmPassword) {
            $profile_flash_message = 'New password and confirmation do not match.';
            $profile_flash_type = 'warning';
        } elseif (strlen($newPassword) < 8) {
            $profile_flash_message = 'New password must be at least 8 characters.';
            $profile_flash_type = 'warning';
        } elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/\d/', $newPassword)) {
            $profile_flash_message = 'Use at least one uppercase letter, one lowercase letter, and one number.';
            $profile_flash_type = 'warning';
        } elseif (password_verify($newPassword, $storedPasswordHash)) {
            $profile_flash_message = 'New password must be different from your current password.';
            $profile_flash_type = 'warning';
        } else {
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $passwordStmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ? LIMIT 1');
            if (!$passwordStmt) {
                $profile_flash_message = 'Could not prepare password update. Please try again.';
                $profile_flash_type = 'danger';
            } else {
                $passwordStmt->bind_param('si', $newPasswordHash, $userId);
                if ($passwordStmt->execute()) {
                    $user['password'] = $newPasswordHash;
                    $profile_flash_message = 'Password changed successfully.';
                    $profile_flash_type = 'success';
                } else {
                    $profile_flash_message = 'Failed to update password. Please try again.';
                    $profile_flash_type = 'danger';
                }
                $passwordStmt->close();
            }
        }
    }
}

$studentProfile = null;
$studentInternshipProfile = null;
$currentRole = strtolower(trim((string)($user['role'] ?? $_SESSION['role'] ?? '')));
if ($currentRole === 'student') {
    $studentStmt = $conn->prepare("SELECT s.id, s.student_id, s.first_name, s.middle_name, s.last_name, s.email AS student_email, s.phone, s.address,
        s.date_of_birth, s.gender, s.emergency_contact, s.status AS student_status,
        c.name AS course_name, d.name AS department_name, sec.code AS section_code, sec.name AS section_name
        FROM students s
        LEFT JOIN courses c ON c.id = s.course_id
        LEFT JOIN departments d ON d.id = s.department_id
        LEFT JOIN sections sec ON sec.id = s.section_id
        WHERE s.user_id = ?
        LIMIT 1");
    if ($studentStmt) {
        $studentStmt->bind_param('i', $userId);
        $studentStmt->execute();
        $studentProfile = $studentStmt->get_result()->fetch_assoc();
        $studentStmt->close();
    }

    if (!$studentProfile) {
        $fallbackEmail = trim((string)($user['email'] ?? ''));
        $fallbackName = trim((string)($user['name'] ?? ''));
        $fallbackStmt = $conn->prepare(
            "SELECT s.id, s.student_id, s.first_name, s.middle_name, s.last_name, s.email AS student_email, s.phone, s.address,
                    s.date_of_birth, s.gender, s.emergency_contact, s.status AS student_status,
                    c.name AS course_name, d.name AS department_name, sec.code AS section_code, sec.name AS section_name
             FROM students s
             LEFT JOIN courses c ON c.id = s.course_id
             LEFT JOIN departments d ON d.id = s.department_id
             LEFT JOIN sections sec ON sec.id = s.section_id
             WHERE ((? <> '' AND LOWER(COALESCE(s.email, '')) = LOWER(?))
                 OR (? <> '' AND LOWER(TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')))) = LOWER(?)))
             ORDER BY
                CASE
                    WHEN (? <> '' AND LOWER(COALESCE(s.email, '')) = LOWER(?)) THEN 0
                    WHEN (? <> '' AND LOWER(TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')))) = LOWER(?)) THEN 1
                    ELSE 2
                END
             LIMIT 1"
        );

        if ($fallbackStmt) {
            $fallbackStmt->bind_param(
                'ssssssss',
                $fallbackEmail,
                $fallbackEmail,
                $fallbackName,
                $fallbackName,
                $fallbackEmail,
                $fallbackEmail,
                $fallbackName,
                $fallbackName
            );
            $fallbackStmt->execute();
            $studentProfile = $fallbackStmt->get_result()->fetch_assoc() ?: null;
            $fallbackStmt->close();
        }
    }

    if (is_array($studentProfile) && !empty($studentProfile['id'])) {
        $internshipStmt = $conn->prepare("
            SELECT company_name, company_address, position, status, start_date, end_date
            FROM internships
            WHERE student_id = ? AND deleted_at IS NULL
            ORDER BY updated_at DESC, id DESC
            LIMIT 1
        ");
        if ($internshipStmt) {
            $studentRecordId = (int)($studentProfile['id'] ?? 0);
            $internshipStmt->bind_param('i', $studentRecordId);
            $internshipStmt->execute();
            $studentInternshipProfile = $internshipStmt->get_result()->fetch_assoc() ?: null;
            $internshipStmt->close();
        }

        $internshipCompanyName = trim((string)($studentInternshipProfile['company_name'] ?? ''));
        if ($internshipCompanyName !== '') {
            $companyProfile = biotern_company_profile_fetch_by_name($conn, $internshipCompanyName);
            if ($companyProfile) {
                if (trim((string)($companyProfile['company_name'] ?? '')) !== '') {
                    $studentInternshipProfile['company_name'] = trim((string)$companyProfile['company_name']);
                }
                if (trim((string)($companyProfile['company_address'] ?? '')) !== '') {
                    $studentInternshipProfile['company_address'] = trim((string)$companyProfile['company_address']);
                }
                if (trim((string)($companyProfile['company_representative'] ?? '')) !== '') {
                    $studentInternshipProfile['company_representative'] = trim((string)$companyProfile['company_representative']);
                }
                if (trim((string)($companyProfile['company_representative_position'] ?? '')) !== '') {
                    $studentInternshipProfile['company_representative_position'] = trim((string)$companyProfile['company_representative_position']);
                }
                if (trim((string)($companyProfile['supervisor_name'] ?? '')) !== '') {
                    $studentInternshipProfile['supervisor_name'] = trim((string)$companyProfile['supervisor_name']);
                }
                if (trim((string)($companyProfile['supervisor_position'] ?? '')) !== '') {
                    $studentInternshipProfile['supervisor_position'] = trim((string)$companyProfile['supervisor_position']);
                }
            }
        }
    }
}

$roleProfileTitle = '';
$roleProfileFields = [];
$roleProfileEmptyMessage = '';
if ($currentRole !== 'student') {
    if ($currentRole === 'coordinator') {
        $roleProfileTitle = 'Coordinator Profile';
        if (profile_details_table_exists($conn, 'coordinators')) {
            $coordinatorStmt = $conn->prepare("
                SELECT c.id, c.first_name, c.middle_name, c.last_name, c.email, c.phone, c.office_location, c.bio,
                       c.is_active, c.created_at, c.updated_at,
                       d.name AS department_name, d.code AS department_code, d.department_head
                FROM coordinators c
                LEFT JOIN departments d ON d.id = c.department_id
                WHERE c.user_id = ?
                LIMIT 1
            ");
            $coordinatorProfile = null;
            if ($coordinatorStmt) {
                $coordinatorStmt->bind_param('i', $userId);
                $coordinatorStmt->execute();
                $coordinatorProfile = $coordinatorStmt->get_result()->fetch_assoc() ?: null;
                $coordinatorStmt->close();
            }

            if (is_array($coordinatorProfile)) {
                $courseLabels = [];
                if (profile_details_table_exists($conn, 'coordinator_courses') && profile_details_table_exists($conn, 'courses')) {
                    $coordinatorCoursesStmt = $conn->prepare("
                        SELECT crs.code, crs.name
                        FROM coordinator_courses cc
                        INNER JOIN courses crs ON crs.id = cc.course_id
                        WHERE cc.coordinator_user_id = ?
                        ORDER BY crs.name ASC
                    ");
                    if ($coordinatorCoursesStmt) {
                        $coordinatorCoursesStmt->bind_param('i', $userId);
                        $coordinatorCoursesStmt->execute();
                        $coursesResult = $coordinatorCoursesStmt->get_result();
                        while ($courseRow = $coursesResult->fetch_assoc()) {
                            $courseCode = trim((string)($courseRow['code'] ?? ''));
                            $courseName = trim((string)($courseRow['name'] ?? ''));
                            $courseLabels[] = trim($courseCode . ($courseName !== '' ? ' - ' . $courseName : ''));
                        }
                        $coordinatorCoursesStmt->close();
                    }
                }

                $coordinatorName = profile_details_build_person_name(
                    (string)($coordinatorProfile['first_name'] ?? ''),
                    (string)($coordinatorProfile['middle_name'] ?? ''),
                    (string)($coordinatorProfile['last_name'] ?? '')
                );
                $coordinatorStatus = ((int)($coordinatorProfile['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive';
                $coordinatorDepartment = trim((string)($coordinatorProfile['department_name'] ?? ''));
                $departmentCode = trim((string)($coordinatorProfile['department_code'] ?? ''));
                if ($coordinatorDepartment !== '' && $departmentCode !== '') {
                    $coordinatorDepartment .= ' (' . $departmentCode . ')';
                }

                $roleProfileFields = [
                    ['label' => 'Coordinator Name', 'value' => profile_details_value($coordinatorName, profile_details_value((string)($user['name'] ?? '')))],
                    ['label' => 'Department', 'value' => profile_details_value($coordinatorDepartment)],
                    ['label' => 'Assigned Courses', 'value' => !empty($courseLabels) ? implode(', ', $courseLabels) : 'No assigned courses yet', 'full' => true],
                    ['label' => 'Office Location', 'value' => profile_details_value((string)($coordinatorProfile['office_location'] ?? ''))],
                    ['label' => 'Phone', 'value' => profile_details_value((string)($coordinatorProfile['phone'] ?? ''))],
                    ['label' => 'Coordinator Email', 'value' => profile_details_value((string)($coordinatorProfile['email'] ?? ($user['email'] ?? '')))],
                    ['label' => 'Department Head', 'value' => profile_details_value((string)($coordinatorProfile['department_head'] ?? ''))],
                    ['label' => 'Profile Status', 'value' => $coordinatorStatus],
                    ['label' => 'Created', 'value' => profile_details_format_date((string)($coordinatorProfile['created_at'] ?? ''))],
                    ['label' => 'Last Updated', 'value' => profile_details_format_date((string)($coordinatorProfile['updated_at'] ?? ''))],
                    ['label' => 'Bio', 'value' => profile_details_value((string)($coordinatorProfile['bio'] ?? '')), 'full' => true],
                ];
            } else {
                $roleProfileEmptyMessage = 'No linked coordinator profile record was found for this account yet.';
            }
        } else {
            $roleProfileEmptyMessage = 'Coordinator profile table is not available in this database.';
        }
    } elseif ($currentRole === 'supervisor') {
        $roleProfileTitle = 'Supervisor Profile';
        if (profile_details_table_exists($conn, 'supervisors')) {
            $supervisorStmt = $conn->prepare("
                SELECT s.id, s.first_name, s.middle_name, s.last_name, s.email, s.phone, s.specialization, s.bio,
                       s.is_active, s.office_location, s.created_at, s.updated_at,
                       d.name AS department_name, d.code AS department_code, d.department_head,
                       c.name AS course_name, c.code AS course_code
                FROM supervisors s
                LEFT JOIN departments d ON d.id = s.department_id
                LEFT JOIN courses c ON c.id = s.course_id
                WHERE s.user_id = ?
                LIMIT 1
            ");
            $supervisorProfile = null;
            if ($supervisorStmt) {
                $supervisorStmt->bind_param('i', $userId);
                $supervisorStmt->execute();
                $supervisorProfile = $supervisorStmt->get_result()->fetch_assoc() ?: null;
                $supervisorStmt->close();
            }

            if (is_array($supervisorProfile)) {
                $supervisorName = profile_details_build_person_name(
                    (string)($supervisorProfile['first_name'] ?? ''),
                    (string)($supervisorProfile['middle_name'] ?? ''),
                    (string)($supervisorProfile['last_name'] ?? '')
                );
                $supervisorDepartment = trim((string)($supervisorProfile['department_name'] ?? ''));
                $supervisorDepartmentCode = trim((string)($supervisorProfile['department_code'] ?? ''));
                if ($supervisorDepartment !== '' && $supervisorDepartmentCode !== '') {
                    $supervisorDepartment .= ' (' . $supervisorDepartmentCode . ')';
                }
                $supervisorCourseCode = trim((string)($supervisorProfile['course_code'] ?? ''));
                $supervisorCourseName = trim((string)($supervisorProfile['course_name'] ?? ''));
                $supervisorCourseLabel = trim($supervisorCourseCode . ($supervisorCourseName !== '' ? ' - ' . $supervisorCourseName : ''));
                $supervisorStatus = ((int)($supervisorProfile['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive';

                $roleProfileFields = [
                    ['label' => 'Supervisor Name', 'value' => profile_details_value($supervisorName, profile_details_value((string)($user['name'] ?? '')))],
                    ['label' => 'Department', 'value' => profile_details_value($supervisorDepartment)],
                    ['label' => 'Course', 'value' => profile_details_value($supervisorCourseLabel)],
                    ['label' => 'Specialization', 'value' => profile_details_value((string)($supervisorProfile['specialization'] ?? ''))],
                    ['label' => 'Office Location', 'value' => profile_details_value((string)($supervisorProfile['office_location'] ?? ''))],
                    ['label' => 'Phone', 'value' => profile_details_value((string)($supervisorProfile['phone'] ?? ''))],
                    ['label' => 'Supervisor Email', 'value' => profile_details_value((string)($supervisorProfile['email'] ?? ($user['email'] ?? '')))],
                    ['label' => 'Department Head', 'value' => profile_details_value((string)($supervisorProfile['department_head'] ?? ''))],
                    ['label' => 'Profile Status', 'value' => $supervisorStatus],
                    ['label' => 'Created', 'value' => profile_details_format_date((string)($supervisorProfile['created_at'] ?? ''))],
                    ['label' => 'Last Updated', 'value' => profile_details_format_date((string)($supervisorProfile['updated_at'] ?? ''))],
                    ['label' => 'Bio', 'value' => profile_details_value((string)($supervisorProfile['bio'] ?? '')), 'full' => true],
                ];
            } else {
                $roleProfileEmptyMessage = 'No linked supervisor profile record was found for this account yet.';
            }
        } else {
            $roleProfileEmptyMessage = 'Supervisor profile table is not available in this database.';
        }
    } elseif ($currentRole === 'admin') {
        $roleProfileTitle = 'Admin Profile';
        if (profile_details_table_exists($conn, 'admin')) {
            $adminStmt = $conn->prepare("
                SELECT a.id, a.first_name, a.middle_name, a.institution_email_address, a.phone_number,
                       a.admin_level, a.admin_position, a.username, a.email,
                       d.name AS department_name, d.code AS department_code, d.department_head
                FROM admin a
                LEFT JOIN departments d ON d.id = a.department_id
                WHERE a.user_id = ?
                LIMIT 1
            ");
            $adminProfile = null;
            if ($adminStmt) {
                $adminStmt->bind_param('i', $userId);
                $adminStmt->execute();
                $adminProfile = $adminStmt->get_result()->fetch_assoc() ?: null;
                $adminStmt->close();
            }

            if (is_array($adminProfile)) {
                $adminName = profile_details_build_person_name(
                    (string)($adminProfile['first_name'] ?? ''),
                    (string)($adminProfile['middle_name'] ?? ''),
                    ''
                );
                $adminDepartment = trim((string)($adminProfile['department_name'] ?? ''));
                $adminDepartmentCode = trim((string)($adminProfile['department_code'] ?? ''));
                if ($adminDepartment !== '' && $adminDepartmentCode !== '') {
                    $adminDepartment .= ' (' . $adminDepartmentCode . ')';
                }

                $roleProfileFields = [
                    ['label' => 'Admin Name', 'value' => profile_details_value($adminName, profile_details_value((string)($user['name'] ?? '')))],
                    ['label' => 'Admin Level', 'value' => profile_details_value((string)($adminProfile['admin_level'] ?? ''))],
                    ['label' => 'Admin Position', 'value' => profile_details_value((string)($adminProfile['admin_position'] ?? ''))],
                    ['label' => 'Department', 'value' => profile_details_value($adminDepartment)],
                    ['label' => 'Department Head', 'value' => profile_details_value((string)($adminProfile['department_head'] ?? ''))],
                    ['label' => 'Institution Email', 'value' => profile_details_value((string)($adminProfile['institution_email_address'] ?? ''))],
                    ['label' => 'Phone', 'value' => profile_details_value((string)($adminProfile['phone_number'] ?? ''))],
                    ['label' => 'Admin Username', 'value' => profile_details_value((string)($adminProfile['username'] ?? ($user['username'] ?? '')))],
                    ['label' => 'Admin Email', 'value' => profile_details_value((string)($adminProfile['email'] ?? ($user['email'] ?? '')))],
                ];
            } else {
                $roleProfileEmptyMessage = 'No linked admin profile record was found for this account yet.';
            }
        } else {
            $roleProfileEmptyMessage = 'Admin profile table is not available in this database.';
        }
    } else {
        $roleProfileTitle = 'Role Profile';
        $roleProfileEmptyMessage = 'No extended role profile is configured for this account role yet.';
    }
}

$lastLoginAt = null;
$loginStmt = $conn->prepare('SELECT created_at FROM login_logs WHERE user_id = ? AND status = ? ORDER BY created_at DESC LIMIT 1');
if ($loginStmt) {
    $status = 'success';
    $loginStmt->bind_param('is', $userId, $status);
    $loginStmt->execute();
    $lastLogin = $loginStmt->get_result()->fetch_assoc();
    $lastLoginAt = $lastLogin['created_at'] ?? null;
    $loginStmt->close();
}

$displayName = trim((string)($user['name'] ?? 'BioTern User'));
if ($displayName === '') {
    $displayName = 'BioTern User';
}

$nameParts = preg_split('/\s+/', $displayName) ?: [];
$initials = '';
if (!empty($nameParts[0])) {
    $initials .= strtoupper(substr((string)$nameParts[0], 0, 1));
}
if (!empty($nameParts[1])) {
    $initials .= strtoupper(substr((string)$nameParts[1], 0, 1));
}
if ($initials === '') {
    $initials = 'BT';
}

$profile_picture_src = biotern_avatar_resolve_existing_path((string)($user['profile_picture'] ?? ''));
$profile_avatar_src = biotern_avatar_public_src((string)($user['profile_picture'] ?? ''), $userId);

$memberSinceDisplay = '-';
if (!empty($user['created_at'])) {
    $ts = strtotime((string)$user['created_at']);
    if ($ts !== false) {
        $memberSinceDisplay = date('M d, Y h:i A', $ts);
    }
}

$lastLoginDisplay = 'No login record yet';
if (!empty($lastLoginAt)) {
    $ts = strtotime((string)$lastLoginAt);
    if ($ts !== false) {
        $lastLoginDisplay = date('M d, Y h:i A', $ts);
    }
}

$notificationUnreadCount = biotern_notifications_count_unread($conn, $userId);
$notificationTotalCount = 0;
$notificationTotalStmt = $conn->prepare('SELECT COUNT(*) AS total FROM notifications WHERE user_id = ?');
if ($notificationTotalStmt) {
    $notificationTotalStmt->bind_param('i', $userId);
    $notificationTotalStmt->execute();
    $notificationTotal = $notificationTotalStmt->get_result()->fetch_assoc();
    $notificationTotalCount = (int)($notificationTotal['total'] ?? 0);
    $notificationTotalStmt->close();
}

$loginEventCount = 0;
$loginCountStmt = $conn->prepare('SELECT COUNT(*) AS total FROM login_logs WHERE user_id = ?');
if ($loginCountStmt) {
    $loginCountStmt->bind_param('i', $userId);
    $loginCountStmt->execute();
    $loginCount = $loginCountStmt->get_result()->fetch_assoc();
    $loginEventCount = (int)($loginCount['total'] ?? 0);
    $loginCountStmt->close();
}

$auditEventCount = 0;
$auditCheck = $conn->query("SHOW TABLES LIKE 'audit_logs'");
if ($auditCheck instanceof mysqli_result && $auditCheck->num_rows > 0) {
    $auditCountStmt = $conn->prepare('SELECT COUNT(*) AS total FROM audit_logs WHERE user_id = ?');
    if ($auditCountStmt) {
        $auditCountStmt->bind_param('i', $userId);
        $auditCountStmt->execute();
        $auditCount = $auditCountStmt->get_result()->fetch_assoc();
        $auditEventCount = (int)($auditCount['total'] ?? 0);
        $auditCountStmt->close();
    }
}

$accountSecurityState = ((int)($user['is_active'] ?? 0) === 1) ? 'Protected' : 'Restricted';
$roleWorkspaceLabel = ucfirst((string)($user['role'] ?? 'user')) . ' Workspace';
$contactPhone = trim((string)($studentProfile['phone'] ?? ''));
$contactAddress = trim((string)($studentProfile['address'] ?? ''));
$studentSectionDisplay = biotern_format_section_label(
    (string)($studentProfile['section_code'] ?? ''),
    (string)($studentProfile['section_name'] ?? '')
);
$studentStatusRaw = trim((string)($studentProfile['student_status'] ?? ''));
$studentStatusDisplay = match (strtolower($studentStatusRaw)) {
    '1', 'true', 'active', 'approved' => 'Active',
    '0', 'false', 'inactive', 'rejected' => 'Inactive',
    'pending' => 'Pending',
    default => profile_details_value($studentStatusRaw),
};
$studentGenderDisplay = trim((string)($studentProfile['gender'] ?? '')) !== ''
    ? ucwords(strtolower(trim((string)($studentProfile['gender'] ?? ''))))
    : 'Not yet available';

$page_title = 'BioTern || Profile Details';
$page_body_class = 'apps-account-page';
$page_styles = [
    'assets/css/modules/pages/page-profile-details.css',
];
$page_scripts = [
    'assets/js/modules/pages/profile-details-page.js',
];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Profile Details</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item">Profile Details</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <button type="button" class="btn btn-sm btn-light-brand page-header-actions-toggle" aria-expanded="false" aria-controls="profileActionsMenu">
            <i class="feather-grid me-1"></i>
            <span>Actions</span>
        </button>
        <div class="page-header-actions" id="profileActionsMenu">
            <div class="dashboard-actions-panel">
                <div class="dashboard-actions-meta">
                    <span class="text-muted fs-12">Quick Actions</span>
                </div>
                <div class="dashboard-actions-grid page-header-right-items-wrapper">
                    <a class="btn btn-light-brand" href="profile-details.php"><i class="feather-user me-2"></i>Profile Details</a>
                    <a class="btn btn-light-brand" href="account-settings.php" data-profile-account-settings-link><i class="feather-settings me-2"></i>Settings</a>
                    <a class="btn btn-light-brand" href="activity-feed.php"><i class="feather-activity me-2"></i>Activity Feed</a>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="main-content d-flex">
    <div class="content-area w-100" data-scrollbar-target="#psScrollbarInit">
        <div class="content-area-body p-3 profile-shell">
            <?php if ($profile_flash_message !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($profile_flash_type, ENT_QUOTES, 'UTF-8'); ?> py-2 mb-3">
                <?php echo htmlspecialchars($profile_flash_message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php endif; ?>

            <div class="profile-hero">
                <div class="profile-persona">
                    <div class="profile-avatar"><?php if ($profile_avatar_src !== ''): ?><img src="<?php echo htmlspecialchars($profile_avatar_src, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile Picture"><?php else: ?><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></div>
                    <div>
                        <h6 class="profile-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></h6>
                        <span class="profile-role"><?php echo htmlspecialchars(ucfirst((string)($user['role'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>

                <div class="profile-kpis">
                    <div class="profile-kpi">
                        <div class="profile-kpi-label">Account Status</div>
                        <div class="profile-kpi-value"><?php echo ((int)($user['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive'; ?></div>
                    </div>
                    <div class="profile-kpi">
                        <div class="profile-kpi-label">Member Since</div>
                        <div class="profile-kpi-value"><?php echo htmlspecialchars($memberSinceDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="profile-kpi">
                        <div class="profile-kpi-label">Last Login</div>
                        <div class="profile-kpi-value"><?php echo htmlspecialchars($lastLoginDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="profile-kpi">
                        <div class="profile-kpi-label">Username</div>
                        <div class="profile-kpi-value"><?php echo htmlspecialchars((string)($user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>
            </div>

            <div class="profile-summary-grid">
                <div class="profile-summary-card">
                    <div class="profile-summary-label">Unread Notifications</div>
                    <div class="profile-summary-value"><?php echo (int)$notificationUnreadCount; ?></div>
                    <div class="profile-summary-note"><?php echo (int)$notificationTotalCount; ?> total alerts saved in your account.</div>
                </div>
                <div class="profile-summary-card">
                    <div class="profile-summary-label">Activity Events</div>
                    <div class="profile-summary-value"><?php echo (int)($loginEventCount + $auditEventCount + $notificationTotalCount); ?></div>
                    <div class="profile-summary-note"><?php echo (int)$loginEventCount; ?> login records and <?php echo (int)$auditEventCount; ?> tracked changes.</div>
                </div>
                <div class="profile-summary-card">
                    <div class="profile-summary-label">Workspace</div>
                    <div class="profile-summary-value" style="font-size: 18px; line-height: 1.25;"><?php echo htmlspecialchars($roleWorkspaceLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="profile-summary-note">Your profile tools, alerts, and account actions are linked here.</div>
                </div>
                <div class="profile-summary-card">
                    <div class="profile-summary-label">Security</div>
                    <div class="profile-summary-value" style="font-size: 18px; line-height: 1.25;"><?php echo htmlspecialchars($accountSecurityState, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="profile-summary-note">Password reset, profile photo, and account status controls are available below.</div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <div class="row g-3 profile-account-role-grid">
                        <div class="<?php echo $currentRole !== 'student' ? 'col-xl-6' : 'col-12'; ?>">
                            <div class="card profile-panel">
                                <div class="card-header">
                                    <h6 class="mb-0">My Account</h6>
                                </div>
                                <div class="card-body">
                                    <div class="profile-grid">
                                        <div class="profile-field">
                                            <div class="profile-field-label">Full Name</div>
                                            <div class="profile-field-value"><?php echo htmlspecialchars((string)($user['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                        <div class="profile-field">
                                            <div class="profile-field-label">Username</div>
                                            <div class="profile-field-value"><?php echo htmlspecialchars((string)($user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                        <div class="profile-field full">
                                            <div class="profile-field-label">Email</div>
                                            <div class="profile-field-value"><?php echo htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                        <div class="profile-field">
                                            <div class="profile-field-label">Role</div>
                                            <div class="profile-field-value"><?php echo htmlspecialchars(ucfirst((string)($user['role'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                        <div class="profile-field">
                                            <div class="profile-field-label">Status</div>
                                            <div class="profile-field-value"><?php echo ((int)($user['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive'; ?></div>
                                        </div>
                                        <div class="profile-field">
                                            <div class="profile-field-label">Last Login</div>
                                            <div class="profile-field-value"><?php echo htmlspecialchars($lastLoginDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                        <div class="profile-field">
                                            <div class="profile-field-label">Member Since</div>
                                            <div class="profile-field-value"><?php echo htmlspecialchars($memberSinceDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($currentRole !== 'student'): ?>
                        <div class="col-xl-6">
                            <div class="card profile-panel">
                                <div class="card-header">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($roleProfileTitle !== '' ? $roleProfileTitle : 'Role Profile', ENT_QUOTES, 'UTF-8'); ?></h6>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($roleProfileFields)): ?>
                                    <div class="profile-grid">
                                        <?php foreach ($roleProfileFields as $roleField): ?>
                                        <?php
                                        $fieldLabel = (string)($roleField['label'] ?? '');
                                        $fieldValue = (string)($roleField['value'] ?? '');
                                        $fieldFull = (bool)($roleField['full'] ?? false);
                                        ?>
                                        <div class="profile-field<?php echo $fieldFull ? ' full' : ''; ?>">
                                            <div class="profile-field-label"><?php echo htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="profile-field-value"><?php echo nl2br(htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8')); ?></div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="profile-field">
                                        <div class="profile-field-label">Status</div>
                                        <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value($roleProfileEmptyMessage, 'No additional role profile details found.'), ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (is_array($studentProfile)): ?>
                    <div class="card profile-panel mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">Student Profile</h6>
                        </div>
                        <div class="card-body">
                            <div class="profile-grid">
                                <div class="profile-field">
                                    <div class="profile-field-label">Student ID</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value((string)($studentProfile['student_id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Student Status</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars($studentStatusDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Course</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value((string)($studentProfile['course_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Department</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value((string)($studentProfile['department_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Section</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value($studentSectionDisplay, 'Not yet assigned'), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field full">
                                    <div class="profile-field-label">Current Company</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value((string)($studentInternshipProfile['company_name'] ?? ''), 'No company linked yet'), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field full">
                                    <div class="profile-field-label">Company Address</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value((string)($studentInternshipProfile['company_address'] ?? ''), 'No company address saved yet'), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Representative</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value((string)($studentInternshipProfile['company_representative'] ?? ''), profile_details_value((string)($studentInternshipProfile['supervisor_name'] ?? ''), 'Not yet available')), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Representative Position</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value((string)($studentInternshipProfile['company_representative_position'] ?? ''), profile_details_value((string)($studentInternshipProfile['supervisor_position'] ?? ''), 'Not yet available')), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Phone</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value((string)($studentProfile['phone'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Student Email</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value((string)($studentProfile['student_email'] ?? ($user['email'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Birth Date</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_format_date((string)($studentProfile['date_of_birth'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Gender</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars($studentGenderDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field full">
                                    <div class="profile-field-label">Emergency Contact</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value((string)($studentProfile['emergency_contact'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field full">
                                    <div class="profile-field-label">Address</div>
                                    <div class="profile-field-value"><?php echo nl2br(htmlspecialchars(profile_details_value((string)($studentProfile['address'] ?? '')), ENT_QUOTES, 'UTF-8')); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card profile-panel mt-3" id="student-personal-edit">
                        <div class="card-header">
                            <h6 class="mb-0">Edit Personal Details</h6>
                        </div>
                        <div class="card-body">
                            <p class="profile-action-note mb-3">Students can update personal details here. Profile picture remains available in Account Settings.</p>
                            <form method="post">
                                <input type="hidden" name="action" value="update_student_profile">
                                <input type="hidden" name="student_record_id" value="<?php echo (int)($studentProfile['id'] ?? 0); ?>">
                                <div class="profile-grid profile-edit-grid">
                                    <div class="profile-field">
                                        <label class="profile-field-label" for="date_of_birth">Birth Date</label>
                                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars((string)($studentProfile['date_of_birth'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div class="profile-field">
                                        <label class="profile-field-label" for="gender">Gender</label>
                                        <select id="gender" name="gender" class="form-control">
                                            <option value="">Select gender</option>
                                            <option value="Male" <?php echo strcasecmp((string)($studentProfile['gender'] ?? ''), 'Male') === 0 ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo strcasecmp((string)($studentProfile['gender'] ?? ''), 'Female') === 0 ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo strcasecmp((string)($studentProfile['gender'] ?? ''), 'Other') === 0 ? 'selected' : ''; ?>>Other</option>
                                            <option value="Prefer not to say" <?php echo strcasecmp((string)($studentProfile['gender'] ?? ''), 'Prefer not to say') === 0 ? 'selected' : ''; ?>>Prefer not to say</option>
                                        </select>
                                    </div>
                                    <div class="profile-field">
                                        <label class="profile-field-label" for="phone">Phone</label>
                                        <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars((string)($studentProfile['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter phone number">
                                    </div>
                                    <div class="profile-field">
                                        <label class="profile-field-label" for="emergency_contact">Emergency Contact</label>
                                        <input type="text" id="emergency_contact" name="emergency_contact" class="form-control" value="<?php echo htmlspecialchars((string)($studentProfile['emergency_contact'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter emergency contact">
                                    </div>
                                    <div class="profile-field full">
                                        <label class="profile-field-label" for="address">Address</label>
                                        <textarea id="address" name="address" class="form-control" rows="3" placeholder="Enter complete address"><?php echo htmlspecialchars((string)($studentProfile['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>
                                </div>
                                <div class="profile-edit-actions">
                                    <button type="submit" class="btn btn-primary">Save Personal Details</button>
                                    <a href="account-settings.php" class="btn btn-outline-secondary" data-profile-account-settings-link>Change Profile Picture</a>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</div>
</div>
</main>

<?php include 'includes/footer.php'; ?>

