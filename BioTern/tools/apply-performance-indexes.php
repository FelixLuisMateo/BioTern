<?php

require_once dirname(__DIR__) . '/config/db.php';

if (!($conn instanceof mysqli) || $conn->connect_errno) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

if (!function_exists('biotern_apply_core_performance_indexes')) {
    fwrite(STDERR, "Performance helper is not available.\n");
    exit(1);
}

$summary = biotern_apply_core_performance_indexes($conn);

echo "BioTern performance indexes\n";
echo "Created: " . count($summary['created']) . "\n";
foreach ($summary['created'] as $item) {
    echo "  + {$item}\n";
}

echo "Skipped: " . count($summary['skipped']) . "\n";
foreach ($summary['skipped'] as $item) {
    echo "  - {$item}\n";
}

echo "Failed: " . count($summary['failed']) . "\n";
foreach ($summary['failed'] as $item) {
    echo "  ! {$item}\n";
}

exit(count($summary['failed']) > 0 ? 2 : 0);
