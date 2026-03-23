<?php
// Root dashboard entrypoint so canonical URL works in XAMPP subfolder installs.
chdir(__DIR__);
require __DIR__ . '/pages/homepage.php';
