<?php
/**
 * Setup script for Coco Instruments API
 * Run this script to create database tables
 * 
 * Usage: php setup.php
 */

// Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line\n");
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

echo "Coco Instruments API Setup\n";
echo "==========================\n\n";

// Check PHP version
echo "Checking PHP version... ";
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die("ERROR: PHP 7.4 or higher is required. Current version: " . PHP_VERSION . "\n");
}
echo "OK (" . PHP_VERSION . ")\n";

// Check required extensions
$requiredExtensions = ['pdo', 'pdo_' . DB_TYPE, 'json', 'openssl'];
foreach ($requiredExtensions as $ext) {
    echo "Checking extension '$ext'... ";
    if (!extension_loaded($ext)) {
        die("ERROR: PHP extension '$ext' is not installed\n");
    }
    echo "OK\n";
}

// Test database connection
echo "\nTesting database connection... ";
try {
    $db = Database::getInstance();
    echo "OK\n";
} catch (Exception $e) {
    die("ERROR: " . $e->getMessage() . "\n");
}

// Create tables
echo "\nCreating database tables...\n";
try {
    $db->createTables();
    echo "✓ All tables created successfully\n";
} catch (Exception $e) {
    die("ERROR: Failed to create tables: " . $e->getMessage() . "\n");
}

// Create test user (optional)
echo "\nWould you like to create a test user? (y/n): ";
$answer = trim(fgets(STDIN));

if (strtolower($answer) === 'y') {
    echo "Email: ";
    $email = trim(fgets(STDIN));
    
    echo "Name: ";
    $name = trim(fgets(STDIN));
    
    echo "Password: ";
    system('stty -echo');
    $password = trim(fgets(STDIN));
    system('stty echo');
    echo "\n";
    
    try {
        require_once __DIR__ . '/auth.php';
        $auth = new Auth($db->getConnection());
        
        $result = $auth->register([
            'email' => $email,
            'name' => $name,
            'password' => $password
        ]);
        
        echo "✓ Test user created successfully\n";
        echo "  Email: " . $result['user']['email'] . "\n";
        echo "  ID: " . $result['user']['id'] . "\n";
        
    } catch (Exception $e) {
        echo "ERROR: Failed to create test user: " . $e->getMessage() . "\n";
    }
}

// Check directory permissions
echo "\nChecking directory permissions...\n";
$directories = [
    dirname(__DIR__) . '/logs' => 'Logs directory',
    dirname(__DIR__) . '/cache' => 'Cache directory',
    dirname(__DIR__) . '/cache/rate_limit' => 'Rate limit cache'
];

foreach ($directories as $dir => $name) {
    echo "  $name: ";
    if (!file_exists($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "Created\n";
        } else {
            echo "ERROR: Could not create\n";
        }
    } else {
        echo "OK\n";
    }
}

// Generate JWT secret if not set
if (JWT_SECRET === 'your-super-secret-jwt-key-change-this-in-production') {
    echo "\n⚠️  WARNING: Using default JWT secret. Please update JWT_SECRET in your .env file\n";
    echo "   Suggested secret: " . base64_encode(random_bytes(32)) . "\n";
}

echo "\n✅ Setup completed successfully!\n";
echo "\nNext steps:\n";
echo "1. Update your .env file with production values\n";
echo "2. Configure your web server to point to the public_html directory\n";
echo "3. Ensure mod_rewrite is enabled (Apache) or configure URL rewriting\n";
echo "4. Test the API by accessing: https://your-domain.com/api/v1/auth/profile\n";
echo "\nFor security:\n";
echo "- Change the JWT_SECRET in your .env file\n";
echo "- Ensure the api directory is not publicly accessible\n";
echo "- Set appropriate file permissions\n";
echo "- Enable HTTPS in production\n";