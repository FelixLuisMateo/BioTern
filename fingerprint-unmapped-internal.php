<?php
// Compatibility loader: always serve the canonical unmapped internal students page.
$canonicalPage = __DIR__ . '/BioTern/pages/fingerprint-unmapped-internal.php';
if (is_file($canonicalPage)) {
    require $canonicalPage;
    exit;
}

$legacyRouter = __DIR__ . '/BioTern/legacy_router.php';
if (is_file($legacyRouter)) {
    $_GET['file'] = 'fingerprint-unmapped-internal.php';
    require $legacyRouter;
    exit;
}

http_response_code(500);
echo 'Unmapped internal students page is unavailable.';
?>
