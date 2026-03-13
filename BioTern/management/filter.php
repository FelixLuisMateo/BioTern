<?php
if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = @new mysqli('127.0.0.1', 'root', '', 'biotern_db');
}

$school_years = [];
$courses = [];
$departments = [];

if ($conn && !$conn->connect_errno) {
    $has_school_years = false;
    $res_tables = $conn->query("SHOW TABLES LIKE 'school_years'");
    if ($res_tables && $res_tables->num_rows > 0) {
        $has_school_years = true;
    }

    if ($has_school_years) {
        $res_sy = $conn->query("SELECT id, year FROM school_years ORDER BY year DESC");
        if ($res_sy) {
            while ($row = $res_sy->fetch_assoc()) {
                $school_years[] = $row;
            }
        }
    }

    $res_courses = $conn->query("SELECT id, name FROM courses ORDER BY name ASC");
    if ($res_courses) {
        while ($row = $res_courses->fetch_assoc()) {
            $courses[] = $row;
        }
    }

    $res_departments = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
    if ($res_departments) {
        while ($row = $res_departments->fetch_assoc()) {
            $departments[] = $row;
        }
    }
}

$selected_school_year = (int)($_GET['school_year_id'] ?? 0);
$selected_course = (int)($_GET['course_id'] ?? 0);
$selected_department = (int)($_GET['department_id'] ?? 0);
?>
<div class="filter-bar card mb-3">
    <div class="card-body py-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label mb-1">School Year</label>
                <select name="school_year_id" class="form-select form-select-sm">
                    <option value="">All Years</option>
                    <?php foreach ($school_years as $sy): ?>
                        <option value="<?php echo (int)$sy['id']; ?>" <?php echo ($selected_school_year === (int)$sy['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$sy['year']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1">Course</label>
                <select name="course_id" class="form-select form-select-sm">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo (int)$course['id']; ?>" <?php echo ($selected_course === (int)$course['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$course['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1">Department</label>
                <select name="department_id" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo (int)$dept['id']; ?>" <?php echo ($selected_department === (int)$dept['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$dept['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <a href="<?php echo htmlspecialchars(basename((string)($_SERVER['PHP_SELF'] ?? 'index.php'))); ?>" class="btn btn-light btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>


