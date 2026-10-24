<?php
/**
 * Shared configuration loader. Safe to commit to GitHub.
 * Runtime credentials are unique to each installation and never tracked by Git.
 * Set ECOM_CONFIG_FILE to use a private PHP config outside the web root.
 */
$configFile = getenv('ECOM_CONFIG_FILE');
if (!is_string($configFile) || trim($configFile) === '') {
    $configFile = __DIR__ . '/config.runtime.php';
}

if (!is_file($configFile)) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(503);
    }
    error_log('Ecom inventory: per-installation config file is missing.');
    throw new RuntimeException('Application configuration is unavailable.');
}

$settings = require $configFile;
if (!is_array($settings)) {
    throw new RuntimeException('Application configuration has an invalid format.');
}

foreach (['db_host', 'db_port', 'db_name', 'db_user', 'db_pass', 'timezone', 'app_name', 'upload_dir', 'pdftotext_path', 'pdftoppm_path', 'tesseract_path'] as $key) {
    if (!array_key_exists($key, $settings)) {
        error_log('Ecom inventory: missing configuration key ' . $key);
        throw new RuntimeException('Application configuration is incomplete.');
    }
}
foreach (['db_host', 'db_name', 'db_user', 'timezone'] as $key) {
    if (!is_string($settings[$key]) || trim($settings[$key]) === '') {
        throw new RuntimeException('Application configuration is incomplete.');
    }
}
if (!is_numeric($settings['db_port'])) {
    throw new RuntimeException('Application database port is invalid.');
}

return $settings;
