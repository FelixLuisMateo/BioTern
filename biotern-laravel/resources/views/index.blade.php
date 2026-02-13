<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern | Home</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{url('frontend/assets/images/favicon.ico') }}" />
    <link rel="stylesheet" type="text/css" href="{{ url('frontend/assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ url('frontend/assets/vendors/css/vendors.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ url('frontend/assets/css/theme.min.css') }}">
    <style>
        /* Homepage-only centering overrides */
        html, body { height: 100%; }
        body { display: flex; flex-direction: column; min-height: 100vh; }
        /* Hide app sidebar if present */
        .nxl-navigation { display: none !important; }
        /* Left-align main content (hero on left side) - fit viewport */
        main.nxl-container { flex: 1 0 auto; display: flex; align-items: center; justify-content: flex-start; padding: 0.5rem; }
        .nxl-content { width: 60%; max-width: 900px; margin: 0; overflow: hidden; }
        /* Ensure hero column doesn't force extra spacing from theme */
        .col-12.d-flex.justify-content-center.align-items-center { min-height: auto !important; }

        /* Reduce header vertical padding to minimize space */
        .nxl-header { padding-top: 4px !important; padding-bottom: 4px !important; }
        .nxl-header .header-wrapper { padding-top: 0 !important; padding-bottom: 0 !important; }

        /* Reduce footer padding to fit viewport */
        footer.footer { padding: 0.5rem !important; margin: 0 !important; }
    </style>
</head>
<body>
    <!-- Header (top bar) -->
    <header class="nxl-header">
        <div class="header-wrapper">
            <div class="header-left d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                    <div class="hamburger hamburger--arrowturn">
                        <div class="hamburger-box"><div class="hamburger-inner"></div></div>
                    </div>
                </a>
            </div>
            <div class="header-right ms-auto">
                <div class="d-flex align-items-center">
                    <!-- Sign In / Sign Up buttons (temporary links) -->
                    <div class="d-none d-md-flex ms-3">
                        <a href="{{ route('login.show') }}" class="btn btn-outline-primary btn-sm me-2">Sign In</a>
                        <a href="{{ url('auth-register-creative.php') }}" class="btn btn-primary btn-sm">Sign Up</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main content -->
    <main class="nxl-container">
        <div class="nxl-content">
            <div class="row">
                <div class="col-12 d-flex justify-content-center align-items-center" style="min-height:60vh;">
                    <div class="col-12 d-flex align-items-center">
                    <div class="text-center">
                        <h1 class="display-6">Welcome to Biotern</h1>
                        <p class="lead">Welcome to Biotern, your Biometric Internship Monitoring and Management System</p>
                        <p class="text-muted">Manage interns, supervisors, coordinators, attendance and reports in one place.</p>
                        <!-- main action buttons removed as requested -->
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="{{ url('frontend/assets/vendors/js/vendors.min.js') }}"></script>
    <script src="{{ url('frontend/assets/js/common-init.min.js') }}"></script>
    <script src="{{ url('frontend/assets/js/theme-customizer-init.min.js') }}"></script>
</body>
</html>
