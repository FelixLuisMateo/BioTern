<?php
// Optional PHP logic (example)
$year = date("Y");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern || Home</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            height: 100vh;
            display: flex;
            flex-direction: column;
            color: white;
            background: linear-gradient(135deg, #0f3460 0%, #1a5276 50%, #2c3e50 100%);
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(100, 200, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(51, 153, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        /* Top Navigation */
        .top-bar {
            display: flex;
            justify-content: flex-end;
            padding: 20px 40px;
        }

        .top-bar a {
            text-decoration: none;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.5);
            padding: 10px 24px;
            border-radius: 30px;
            transition: 0.3s ease;
            font-weight: 500;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
        }

        .top-bar a:hover {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            border-color: white;
            box-shadow: 0 8px 32px rgba(100, 200, 255, 0.3);
        }

        /* Light mode adjustments */
        @media (prefers-color-scheme: light) {
            body {
                background: linear-gradient(135deg, #e8f4f8 0%, #d4e9f7 50%, #c5ddf0 100%);
            }

            body::before {
                background: 
                    radial-gradient(circle at 20% 50%, rgba(0, 150, 220, 0.08) 0%, transparent 50%),
                    radial-gradient(circle at 80% 80%, rgba(100, 200, 255, 0.08) 0%, transparent 50%);
            }

            .top-bar a {
                color: #0f3460;
                border: 2px solid rgba(15, 52, 96, 0.3);
                background: rgba(255, 255, 255, 0.6);
            }

            .top-bar a:hover {
                background: rgba(255, 255, 255, 0.9);
                color: #0f3460;
                box-shadow: 0 8px 32px rgba(0, 100, 180, 0.2);
            }

            .center-content h1 {
                color: #0f3460;
                text-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .center-content p {
                color: #1a5276;
            }
        }

        /* Center Content */
        .center-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            flex-direction: column;
            position: relative;
            z-index: 1;
        }

        .logo-section {
            margin-bottom: 30px;
        }

        .logo-section img {
            max-width: 120px;
            height: auto;
            animation: logoFloat 3s ease-in-out infinite;
            filter: drop-shadow(0 8px 16px rgba(100, 200, 255, 0.3));
        }

        @keyframes logoFloat {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-15px);
            }
        }

        .center-content h1 {
            font-size: 48px;
            margin-bottom: 20px;
            text-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .apply-btn {
            text-decoration: none;
            background: linear-gradient(135deg, #00d4ff 0%, #0099ff 100%);
            color: white;
            padding: 14px 40px;
            border-radius: 30px;
            font-size: 18px;
            transition: 0.3s ease;
            font-weight: 600;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 153, 255, 0.3);
            cursor: pointer;
        }

        .apply-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0, 153, 255, 0.5);
        }

        @media (prefers-color-scheme: light) {
            .apply-btn {
                background: linear-gradient(135deg, #0099ff 0%, #0077cc 100%);
                color: white;
                box-shadow: 0 10px 30px rgba(0, 153, 255, 0.2);
            }

            .apply-btn:hover {
                transform: translateY(-3px);
                box-shadow: 0 15px 40px rgba(0, 100, 180, 0.3);
            }
        }

        footer {
            text-align: center;
            padding: 15px;
            font-size: 14px;
            opacity: 0.8;
        }

        @media (max-width: 600px) {
            .center-content h1 {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>

    <!-- Top Sign In Button -->
    <div class="top-bar">
        <a href="auth-login-cover.php" class="btn btn-outline-primary btn-sm me-2 text-muted">Sign In</a>
    </div>

    <!-- Center Welcome Section -->
    <div class="center-content">
        <div class="logo-section">
            <img src="assets/images/auth/auth-cover-login-bg.png" alt="BioTern Logo">
        </div>
        <h1 style="color: #ffffff;">Welcome to BioTern</h1>
        <p class="lead text-muted" style="color: #ffffff;">Your Biometric Internship Monitoring and Management System</p>
        <p class="text-muted">Manage interns, supervisors, coordinators, attendance and reports in one place.</p>
        <br>
        <a href="auth-register-creative.php" class="apply-btn">Apply Now</a>
    </div>

    <footer>
        &copy; <?php echo $year; ?> All Rights Reserved
    </footer>

    <script>
        // Optional small animation effect
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelector(".center-content").style.opacity = 0;
            setTimeout(function() {
                document.querySelector(".center-content").style.transition = "1s";
                document.querySelector(".center-content").style.opacity = 1;
            }, 200);
        });
    </script>
    <script src="assets/vendors/js/vendors.min.js"></script>
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/theme-customizer-init.min.js"></script>

</body>
</html>
