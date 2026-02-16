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
            border: 2px solid white;
            padding: 8px 18px;
            border-radius: 25px;
            transition: 0.3s ease;
        }

        .top-bar a:hover {
            background: white;
            color: #333;
        }

        /* Center Content */
        .center-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            flex-direction: column;
        }

        .center-content h1 {
            font-size: 48px;
            margin-bottom: 20px;
        }

        .apply-btn {
            text-decoration: none;
            background: lightblue;
            color: #333;
            padding: 12px 30px;
            border-radius: 30px;
            font-size: 18px;
            transition: 0.3s ease;
        }

        .apply-btn:hover {
            background: #333;
            color: white;
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
        <a href="auth-login-cover.php" class="btn btn-outline-primary btn-sm me-2">Sign In</a>
    </div>

    <!-- Center Welcome Section -->
    <div class="center-content">
        <h1 class="display-6">Welcome to BioTern</h1>
        <p class="lead">Your Biometric Internship Monitoring and Management System</p>
        <p class="text-muted">Manage interns, supervisors, coordinators, attendance and reports in one place.</p>
        <br>
        <a href="register.php" class="apply-btn">Apply Now</a>
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
