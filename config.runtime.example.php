<?php
// Copy this file to config.runtime.php on each installation, then edit that
// installation's own values. Never commit config.runtime.php to GitHub.
return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'your_local_database_name',
    'db_user' => 'root',
    'db_pass' => '',
    'timezone' => 'Asia/Manila',
    'app_name' => 'E-commerce Inventory Management System',
    'upload_dir' => __DIR__ . '/uploads',
    // Optional external tools: leave empty if they are available on PATH.
    'pdftotext_path' => '',
    'pdftoppm_path' => '',
    'tesseract_path' => '',
];
