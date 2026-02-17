<?php
// Compatibility wrapper: route old/new links to the application letter generator.
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? ('?' . $_SERVER['QUERY_STRING'])
    : '';
header('Location: generate_application_letter.php' . $qs);
exit;
?>
