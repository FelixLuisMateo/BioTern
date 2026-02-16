<!DOCTYPE html>
<html lang="zxx">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="theme_ocean">
    <!--! The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags !-->
    <!--! BEGIN: Apps Title-->
    <title>BioTern || Register Fingerprint</title>
    <!--! END:  Apps Title-->
    <!--! BEGIN: Favicon-->
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <!--! END: Favicon-->
    <!--! BEGIN: Bootstrap CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <!--! END: Bootstrap CSS-->
    <!--! BEGIN: Vendors CSS-->
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <!--! END: Vendors CSS-->
    <!--! BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
    <!--! END: Custom CSS-->
</head>

<body>
    <style>
        /* Center main card vertically and horizontally */
        .auth-minimal-wrapper {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-height: 100vh !important;
            padding: 20px !important;
            background-color: transparent;
        }
        .card.p-sm-5 {
            box-sizing: border-box;
        }
        .fingerprint-section {
            text-align: center;
            padding: 40px 20px;
        }
        .fingerprint-icon {
            font-size: 80px;
            margin: 20px 0;
        }
        .fingerprint-status {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            font-weight: 500;
        }
        .fingerprint-status.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .fingerprint-status.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .fingerprint-status.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
    </style>

    <!--! ================================================================ !-->
    <!--! [Start] Main Content !-->
    <!--! ================================================================ !-->
    <main class="auth-minimal-wrapper">
        <div class="auth-minimal-inner">
            <div class="minimal-card-wrapper">
                <div class="card mb-4 mt-5 mx-2 mx-sm-0 position-relative" style="width: 100%; max-width: 600px; margin: 40px auto;">
                    <div class="wd-50 bg-white p-2 rounded-circle shadow-lg position-absolute translate-middle top-0 start-50">
                        <img src="assets/images/logo-abbr.png" alt="" class="img-fluid">
                    </div>
                    <div class="card-body p-sm-5" style="padding: 50px !important; min-height: auto;">
                        <div class="fingerprint-section">
                            <h2 class="fs-20 fw-bolder mb-2">Fingerprint Registration</h2>
                            <p class="fs-12 fw-medium text-muted mb-4">Register your fingerprint for biometric authentication</p>
                            
                            <div class="fingerprint-icon">
                                👆
                            </div>

                            <div id="status-message" class="fingerprint-status info" style="display: none;">
                                Waiting for fingerprint scan...
                            </div>

                            <div id="error-message" class="fingerprint-status error" style="display: none;">
                            </div>

                            <div id="success-message" class="fingerprint-status success" style="display: none;">
                                Fingerprint registered successfully!
                            </div>

                            <div class="mt-5">
                                <button id="startScanBtn" class="btn btn-lg btn-primary w-100 mb-2" onclick="startFingerprintScan()">
                                    Start Fingerprint Scan
                                </button>
                                <button id="retryBtn" class="btn btn-lg btn-secondary w-100 mb-2" onclick="retryFingerprintScan()" style="display: none;">
                                    Retry Scan
                                </button>
                                <a href="auth-register-creative.php" class="btn btn-lg btn-outline-secondary w-100">
                                    Back to Registration
                                </a>
                            </div>

                            <div class="mt-4 text-muted">
                                <p class="fs-12">
                                    <strong>Note:</strong> Fingerprint registration is optional. You can complete this later from your profile settings.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <!--! ================================================================ !-->
    <!--! [End] Main Content !-->
    <!--! ================================================================ !-->

    <!--! BEGIN: Vendors JS !-->
    <script src="assets/vendors/js/vendors.min.js"></script>
    <!--! END: Vendors JS !-->
    <!--! BEGIN: Apps Init  !-->
    <script src="assets/js/common-init.min.js"></script>
    <!--! END: Apps Init !-->
    <!--! BEGIN: Theme Customizer  !-->
    <script src="assets/js/theme-customizer-init.min.js"></script>
    <!--! END: Theme Customizer !-->

    <script>
        // Fingerprint scanning simulation
        let scanAttempts = 0;
        const maxAttempts = 3;

        function startFingerprintScan() {
            scanAttempts = 0;
            document.getElementById('startScanBtn').disabled = true;
            document.getElementById('error-message').style.display = 'none';
            document.getElementById('success-message').style.display = 'none';
            document.getElementById('status-message').style.display = 'block';
            document.getElementById('status-message').textContent = 'Initializing fingerprint scanner...';

            // Simulate fingerprint device initialization
            setTimeout(() => {
                performFingerprintScan();
            }, 1500);
        }

        function performFingerprintScan() {
            scanAttempts++;
            document.getElementById('status-message').textContent = `Scanning fingerprint... (Attempt ${scanAttempts}/${maxAttempts})`;

            // Simulate scanning process
            setTimeout(() => {
                // Simulate random success/failure (70% success rate for demo)
                const success = Math.random() > 0.3;

                if (success) {
                    showSuccessMessage();
                    submitFingerprintData();
                } else {
                    if (scanAttempts < maxAttempts) {
                        showErrorMessage('Fingerprint not recognized. Please try again.');
                        document.getElementById('retryBtn').style.display = 'block';
                        document.getElementById('startScanBtn').style.display = 'none';
                    } else {
                        showErrorMessage('Maximum scan attempts reached. Please try again later.');
                        document.getElementById('startScanBtn').disabled = false;
                        document.getElementById('retryBtn').style.display = 'none';
                        document.getElementById('startScanBtn').style.display = 'block';
                    }
                }
            }, 3000);
        }

        function retryFingerprintScan() {
            document.getElementById('retryBtn').style.display = 'none';
            document.getElementById('startScanBtn').style.display = 'block';
            document.getElementById('startScanBtn').disabled = false;
            performFingerprintScan();
        }

        function showSuccessMessage() {
            document.getElementById('status-message').style.display = 'none';
            document.getElementById('error-message').style.display = 'none';
            document.getElementById('success-message').style.display = 'block';
            document.getElementById('success-message').textContent = 'Fingerprint registered successfully!';
            document.getElementById('startScanBtn').style.display = 'none';
            document.getElementById('retryBtn').style.display = 'none';
        }

        function showErrorMessage(message) {
            document.getElementById('status-message').style.display = 'none';
            document.getElementById('success-message').style.display = 'none';
            document.getElementById('error-message').style.display = 'block';
            document.getElementById('error-message').textContent = message;
        }

        function submitFingerprintData() {
            // Simulate sending fingerprint data to server
            setTimeout(() => {
                console.log('Fingerprint data submitted to server');
                // In a real scenario, you would send the fingerprint data to a backend service
                // fetch('submit_fingerprint.php', {
                //     method: 'POST',
                //     body: JSON.stringify({ fingerprint_data: fingerprintData })
                // });
            }, 1000);
        }

        // Optional: Check for fingerprint API availability on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Check if WebAuthn/FIDO2 is available (advanced biometric support)
            if (window.PublicKeyCredential !== undefined &&
                navigator.credentials !== undefined) {
                console.log('WebAuthn available - advanced biometric support enabled');
            }
        });
    </script>
</body>

</html>
