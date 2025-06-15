<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

echo "\033[34mRunning Laravel Fortify installation...\033[0m\n";

// Define the suffixes for the Fortify-related migrations that we need to check
$fortifyMigrationSuffixes = [
    'add_two_factor_columns_to_users_table', // Fortify-specific migration
];

// Get a list of all migration files in the migrations directory
$migrationPath = database_path('migrations');
$files = File::files($migrationPath);

// Filter the files that match the Laravel Fortify migration suffixes
$existingMigrations = [];
foreach ($files as $file) {
    foreach ($fortifyMigrationSuffixes as $suffix) {
        if (strpos($file->getFilename(), $suffix) !== false) {
            $existingMigrations[] = $file->getFilename(); // Migration file found
            break;
        }
    }
}

// Check if Laravel Fortify migration already exists
$fortifyMigrationExists = !empty($existingMigrations);

// **Check if Laravel Fortify is installed via Composer**
$fortifyInstalled = false;
exec('composer show laravel/fortify', $composerOutput, $status);

if ($status === 0) {
    $fortifyInstalled = true;
}

if ($fortifyInstalled) {
    echo "\033[34mLaravel Fortify is already installed (skipping fortify:install).\033[0m\n";
} else {
    echo "\033[34mLaravel Fortify is not installed. Installing Laravel Fortify...\033[0m\n";
    exec('composer require laravel/fortify', $composerOutput, $status);
    
    if ($status !== 0) {
        echo "\033[34mError: Laravel Fortify installation failed via Composer.\033[0m\n";
        echo implode("\n", $composerOutput);
        exit(1);
    } else {
        echo "\033[34mLaravel Fortify installed successfully.\033[0m\n";
    }
}

// **Skip fortify:install if migration file already exists**
if (!$fortifyMigrationExists) {
    echo "\033[34mRunning fortify:install...\033[0m\n";
    $fortifyInstallCommand = PHP_BINARY . ' artisan fortify:install --ansi';
    exec($fortifyInstallCommand, $execOutput, $execStatus);

    if ($execStatus !== 0) {
        Log::error("Error running fortify:install: " . implode("\n", $execOutput));
        echo "\033[34mError running fortify:install: " . implode("\n", $execOutput) . "\033[0m\n";
        exit(1);
    } else {
        echo "\033[34mLaravel Fortify installation command executed successfully.\033[0m\n";
    }
} else {
    echo "\033[34mMigration files already exist. Do you want to reset and reapply the migrations?\033[0m\n";
}

// **WARNING message in red**
echo "\033[31mWARNING! RESETTING WILL ERASE ALL DATA, INCLUDING TABLES AND RELATIONSHIPS!\033[0m\n"; 

// Ask user if they want to reset the database (clear all data)
echo "\033[38;5;214mDo you want to erase all data, including tables and relationships? \033[0m"; // Orange question

// Color Y = Yes in green, N = No in red
echo "\033[32m(Y = Yes)\033[0m / \033[31m(N = No)\033[0m: ";

$response = strtoupper(trim(fgets(STDIN)));

// Handle the user's response with color-coded output
if ($response === 'Y') {
    // If user agrees to reset database, drop all tables and reapply migrations
    echo "\033[34mRunning migrate:fresh to drop all tables and reapply migrations...\033[0m\n";
    Artisan::call('migrate:fresh');
    echo "\033[34mDatabase has been reset and migrations reapplied.\033[0m\n";
} elseif ($response === 'N') {
} else {
    echo "\033[34mInvalid response. Exiting...\033[0m\n";
    exit(1);
}

// **Publishing Fortify assets, views, and config only if Fortify is installed**
if ($fortifyInstalled) {
    echo "\033[34mPublishing Laravel Fortify assets, views, and config...\033[0m\n";
    Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'config']);
    Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'views']);
    Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'assets']);
}

// Clear and optimize Laravel service cache and config cache
echo "\033[34mClearing and optimizing Laravel caches...\033[0m\n"; // Added message before clearing
Artisan::call('optimize:clear'); // Clears config, route, view caches and compiled services
Artisan::call('config:clear');   // Explicitly clear config cache again for good measure
Artisan::call('cache:clear');    // Clear application cache
Artisan::call('view:clear');     // Clear view cache
// Confirming that caches are cleared and optimized
echo "\033[34mLaravel cache clearing and optimization completed successfully.\033[0m\n";
