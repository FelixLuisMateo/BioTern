<?php
// Report restoration has been disabled.
// The previous script recreated removed report pages (OJT, Project, Timesheets).
// To avoid accidentally restoring pages that were intentionally removed, this
// script no longer writes those files. If you need to restore them later,
// re-enable or reconstruct the content manually.

header('Content-Type: text/plain; charset=utf-8');
echo "Report restoration disabled.\n";
echo "No files were created.\n";
exit;
