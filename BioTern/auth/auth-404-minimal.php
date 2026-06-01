<?php
$status_code = (int)($_SERVER['REDIRECT_STATUS'] ?? 404);
if ($status_code < 400 || $status_code > 599) {
    $status_code = 404;
}
http_response_code($status_code);

$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$redirect_url = str_replace('\\', '/', (string)($_SERVER['REDIRECT_URL'] ?? ''));
$request_uri_path = str_replace('\\', '/', (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? ''));
$project_root = '/';
foreach ([$script_name, $redirect_url, $request_uri_path] as $root_candidate) {
    $project_pos = stripos($root_candidate, '/BioTern/BioTern/');
    if ($project_pos !== false) {
        $project_root = substr($root_candidate, 0, $project_pos) . '/BioTern/BioTern/';
        break;
    }
}
if ($project_root === '/') {
    $script_dir = rtrim(str_replace('\\', '/', dirname($script_name)), '/');
    if (strtolower((string)basename($script_dir)) === 'auth') {
        $script_dir = rtrim(str_replace('\\', '/', dirname($script_dir)), '/');
    }
    $project_root = ($script_dir === '' || $script_dir === '.') ? '/' : $script_dir . '/';
}
$asset_prefix = $project_root;
$route_prefix = $project_root;
$error_titles = [
    400 => 'Bad request',
    401 => 'Sign in required',
    403 => 'Access unavailable',
    404 => 'Page not found',
    500 => 'Something went wrong',
];
$error_title = $error_titles[$status_code] ?? 'Request unavailable';
$requested_path = trim((string)($_GET['requested'] ?? ''));
if ($requested_path === '') {
    $requested_path = (string)($_SERVER['REDIRECT_URL'] ?? ($_SERVER['REQUEST_URI'] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="ACT 2A Group 5">
    <!--! The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags !-->
    <!--! BEGIN: Apps Title-->
    <title>BioTern || <?php echo (int)$status_code; ?></title>
    <!--! END:  Apps Title-->
    <!--! BEGIN: Favicon-->
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon.ico">
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/theme-preload-init.min.js"></script>
    <!--! END: Favicon-->
    <!--! BEGIN: Bootstrap CSS-->
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/bootstrap.min.css">
    <!--! END: Bootstrap CSS-->
    <!--! BEGIN: Vendors CSS-->
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/css/vendors.min.css">
    <!--! END: Vendors CSS-->
    <!--! BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/smacss.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/modules/auth/auth-404-minimal-page.css">
    <!--! END: Custom CSS-->
    <!--! HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries !-->
    <!--! WARNING: Respond.js doesn"t work if you view the page via file: !-->
    <!--[if lt IE 9]>
			<script src="https:oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
			<script src="https:oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
		<![endif]-->
</head>

<body>
    <!--! ================================================================ !-->
    <!--! [Start] Main Content !-->
    <!--! ================================================================ !-->
    <main class="auth-minimal-wrapper">
        <div class="auth-minimal-inner">
            <div class="minimal-card-wrapper">
                <div class="card mb-4 mt-5 mx-4 mx-sm-0 position-relative auth-minimal-card">
                    <div class="wd-50 bg-white p-2 rounded-circle shadow-lg position-absolute translate-middle top-0 start-50 auth-minimal-logo-badge">
                        <img src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/logo-abbr.png" alt="" class="img-fluid">
                    </div>
                    <div class="card-body p-sm-5 text-center auth-minimal-body">
                        <h2 class="fw-bolder mb-4 auth-minimal-code"><?php echo htmlspecialchars((string)$status_code, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <h4 class="fw-bold mb-2"><?php echo htmlspecialchars($error_title, ENT_QUOTES, 'UTF-8'); ?></h4>
                        <p class="fs-12 fw-medium text-muted">We could not open that request. Check the URL, go back, or return to the dashboard.</p>
                        <?php if ($requested_path !== ''): ?>
                            <p class="auth-minimal-reference">Requested: <?php echo htmlspecialchars($requested_path, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <div class="mt-4 d-grid gap-2">
                            <a href="javascript:history.back()" class="btn btn-outline-secondary w-100">Go Back</a>
                            <a href="<?php echo htmlspecialchars($route_prefix, ENT_QUOTES, 'UTF-8'); ?>index.php" class="btn btn-light-brand w-100">Back Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <!--! ================================================================ !-->
    <!--! [End] Main Content !-->
    <!--! ================================================================ !-->
    <!--! ================================================================ !-->
    <!--! Footer Script !-->
    <!--! ================================================================ !-->
    <!--! BEGIN: Vendors JS !-->
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/js/vendors.min.js"></script>
    <!-- vendors.min.js {always must need to be top} -->
    <!--! END: Vendors JS !-->
    <!--! BEGIN: Apps Init  !-->
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/common-init.min.js"></script>
    <!--! END: Apps Init !-->
    <!--! BEGIN: Theme Customizer  !-->
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/theme-customizer-init.min.js"></script>
    <!--! END: Theme Customizer !-->
</body>

</html>




