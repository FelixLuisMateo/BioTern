<?php
require_once __DIR__ . '/BioTern/config/db.php';
require_once __DIR__ . '/BioTern/includes/auth-session.php';

biotern_boot_session(isset($conn) ? $conn : null);

// Root entrypoint: logged-in users go straight to the dashboard.
$isLoggedIn = (int)($_SESSION['user_id'] ?? 0) > 0 || !empty($_SESSION['logged_in']);
header('Location: ' . ($isLoggedIn ? 'BioTern/homepage.php' : 'BioTern/index.php'));
exit;
