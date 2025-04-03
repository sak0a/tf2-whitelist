<?php
/**
 * PHPMailer Setup File
 *
 * This file contains functions to check if PHPMailer is installed
 * and to install it if needed using Composer or manual installation.
 */

/**
 * Check if PHPMailer is installed
 * @return bool True if PHPMailer is installed, false otherwise
 */
function checkPHPMailer() {
    // Check for class existence first (in case it's been autoloaded)
    if (class_exists('PHPMailer\PHPMailer\PHPMailer', true)) {
        return true;
    }

    // Check in vendor directory (if using Composer)
    $vendor_path = dirname(dirname(__FILE__)) . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
    if (file_exists($vendor_path)) {
        require_once $vendor_path;
        return true;
    }

    // Check in lib directory (if manually installed)
    $lib_path = dirname(dirname(__FILE__)) . '/lib/phpmailer/src/PHPMailer.php';
    if (file_exists($lib_path)) {
        require_once $lib_path;
        return true;
    }

    return false;
}

/**
 * Check if Composer is installed
 * @return bool True if Composer is installed, false otherwise
 */
function checkComposer() {
    $output = null;
    $return_var = null;
    exec('composer --version 2>&1', $output, $return_var);
    return $return_var === 0;
}

/**
 * Install PHPMailer using Composer
 * @return bool True if installation successful, false otherwise
 */
function installPHPMailerWithComposer() {
    if (!checkComposer()) {
        return false;
    }

    // Change to root directory
    $root_dir = dirname(dirname(__FILE__));
    chdir($root_dir);

    // Create composer.json if it doesn't exist
    if (!file_exists('composer.json')) {
        $composer_json = [
            'require' => [
                'phpmailer/phpmailer' => '^6.8'
            ],
            'autoload' => [
                'classmap' => ['vendor/phpmailer/phpmailer/']
            ]
        ];
        file_put_contents('composer.json', json_encode($composer_json, JSON_PRETTY_PRINT));
    } else {
        // Update existing composer.json
        $composer_json = json_decode(file_get_contents('composer.json'), true);
        $composer_json['require']['phpmailer/phpmailer'] = '^6.8';
        if (!isset($composer_json['autoload'])) {
            $composer_json['autoload'] = [];
        }
        if (!isset($composer_json['autoload']['classmap'])) {
            $composer_json['autoload']['classmap'] = [];
        }
        if (!in_array('vendor/phpmailer/phpmailer/', $composer_json['autoload']['classmap'])) {
            $composer_json['autoload']['classmap'][] = 'vendor/phpmailer/phpmailer/';
        }
        file_put_contents('composer.json', json_encode($composer_json, JSON_PRETTY_PRINT));
    }

    // Run composer install
    $output = null;
    $return_var = null;
    exec('composer install 2>&1', $output, $return_var);

    // Generate autoloader
    exec('composer dump-autoload -o 2>&1', $output, $return_var);

    // Create a simple loader file in the root
    $loader_content = <<<'EOT'
<?php
// Load PHPMailer classes
require_once __DIR__ . '/vendor/autoload.php';
EOT;
    file_put_contents($root_dir . '/phpmailer-loader.php', $loader_content);

    return $return_var === 0;
}

/**
 * Manually install PHPMailer by downloading the files
 * @return bool True if installation successful, false otherwise
 */
function installPHPMailerManually() {
    // Define the root directory and lib directory
    $root_dir = dirname(dirname(__FILE__));
    $lib_dir = $root_dir . '/lib';
    $phpmailer_dir = $lib_dir . '/phpmailer';

    // Create directories if they don't exist
    if (!is_dir($lib_dir)) {
        mkdir($lib_dir, 0755, true);
    }

    if (!is_dir($phpmailer_dir)) {
        mkdir($phpmailer_dir, 0755, true);
    }

    // Download the latest PHPMailer release
    $zip_url = 'https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.8.0.zip';
    $zip_file = $lib_dir . '/phpmailer.zip';

    // Use cURL to download the file
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $zip_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $zip_data = curl_exec($ch);
    curl_close($ch);

    if (!$zip_data) {
        return false;
    }

    // Save the zip file
    file_put_contents($zip_file, $zip_data);

    // Extract the zip file
    $zip = new ZipArchive;
    if ($zip->open($zip_file) === TRUE) {
        $zip->extractTo($lib_dir);
        $zip->close();

        // The extracted folder will be named PHPMailer-6.8.0
        // Rename it to phpmailer
        if (is_dir($lib_dir . '/PHPMailer-6.8.0')) {
            // Copy files from the extracted directory to our phpmailer directory
            copy_directory($lib_dir . '/PHPMailer-6.8.0', $phpmailer_dir);

            // Remove the extracted directory
            delete_directory($lib_dir . '/PHPMailer-6.8.0');
        }

        // Remove the zip file
        unlink($zip_file);

        // Create a simple loader file in the root
        $loader_content = <<<'EOT'
<?php
// Define the path to PHPMailer classes
require_once __DIR__ . '/lib/phpmailer/src/Exception.php';
require_once __DIR__ . '/lib/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/lib/phpmailer/src/SMTP.php';

// Add to config.php
if (!class_exists('PHPMailer\PHPMailer\PHPMailer', false)) {
    require_once __DIR__ . '/phpmailer-loader.php';
}
EOT;
        file_put_contents($root_dir . '/phpmailer-loader.php', $loader_content);

        return true;
    }

    return false;
}

/**
 * Helper function to copy a directory recursively
 * @param string $source Source directory
 * @param string $dest Destination directory
 * @return bool True if successful, false otherwise
 */
function copy_directory($source, $dest) {
    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }

    $dir = opendir($source);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $src = $source . '/' . $file;
            $dst = $dest . '/' . $file;

            if (is_dir($src)) {
                copy_directory($src, $dst);
            } else {
                copy($src, $dst);
            }
        }
    }
    closedir($dir);
    return true;
}

/**
 * Helper function to delete a directory recursively
 * @param string $dir Directory to delete
 * @return bool True if successful, false otherwise
 */
function delete_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? delete_directory($path) : unlink($path);
    }

    return rmdir($dir);
}

/**
 * Check and install PHPMailer if needed
 * @return string Status message
 */
function setupPHPMailer() {
    if (checkPHPMailer()) {
        return "PHPMailer is already installed and available.";
    }

    // Add PHPMailer loader to config.php if it exists
    $root_dir = dirname(dirname(__FILE__));
    $config_file = $root_dir . '/config.php';

    if (file_exists($config_file)) {
        $config_content = file_get_contents($config_file);

        // Check if PHPMailer loader is already included
        if (strpos($config_content, 'phpmailer-loader.php') === false) {
            // Add the loader include at the beginning of the file
            $loader_include = "<?php\n// Include PHPMailer loader\nif (file_exists(__DIR__ . '/phpmailer-loader.php')) {\n    require_once __DIR__ . '/phpmailer-loader.php';\n}\n\n";
            $config_content = preg_replace('/^<\?php/', $loader_include, $config_content, 1);
            file_put_contents($config_file, $config_content);
        }
    }

    // Try installing with Composer first
    if (checkComposer()) {
        if (installPHPMailerWithComposer()) {
            return "PHPMailer has been successfully installed using Composer.";
        }
    }

    // If Composer installation fails, try manual installation
    if (installPHPMailerManually()) {
        return "PHPMailer has been successfully installed manually.";
    }

    return "Failed to install PHPMailer. Please install it manually.";
}

// If the script is being run directly, execute setup
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    echo setupPHPMailer();
}