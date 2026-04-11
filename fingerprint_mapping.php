
<?php
// Compatibility loader: always serve the canonical fingerprint mapping page.
$canonicalPage = __DIR__ . '/BioTern/pages/fingerprint_mapping.php';
if (is_file($canonicalPage)) {
    require $canonicalPage;
    exit;
}

$legacyRouter = __DIR__ . '/BioTern/legacy_router.php';
if (is_file($legacyRouter)) {
    $_GET['file'] = 'fingerprint_mapping.php';
    require $legacyRouter;
    exit;
}

http_response_code(500);
echo 'Fingerprint mapping page is unavailable.';
?>
