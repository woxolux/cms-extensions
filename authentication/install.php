<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

echo "Running Fortify installation...\n";

// Define the suffixes for the Fortify-related migrations that we need to check
$fortifyMigrationSuffixes = [
    'add_two_factor_columns_to_users_table', // Fortify-specific migration
];

// Get a list of all migration files in the migrations directory
$migrationPath = database_path('migrations');
$files = File::files($migrationPath);

// Filter the files that match the Fortify migration suffixes
$existingMigrations = [];
foreach ($files as $file) {
    foreach ($fortifyMigrationSuffixes as $suffix) {
        if (strpos($file->getFilename(), $suffix) !== false) {
            $existingMigrations[] = $file->getFilename(); // Migration file found
            break;
        }
    }
}

// Check if Fortify migration already exists
$fortifyMigrationExists = !empty($existingMigrations);

// **Check if Fortify is installed via Composer**
echo "Checking if Fortify is installed via Composer...\n";
$composerOutput = [];
exec('composer show laravel/fortify', $composerOutput, $status);

$fortifyInstalled = $status === 0;

if (!$fortifyInstalled) {
    echo "Fortify is not installed. Installing Fortify...\n";
    exec('composer require laravel/fortify', $composerOutput, $status);
    
    if ($status !== 0) {
        echo "Error: Fortify installation failed via Composer.\n";
        echo implode("\n", $composerOutput);
        exit(1);
    } else {
        echo "Fortify installed successfully.\n";
    }
}

// **Skip fortify:install if migration file already exists**
if ($fortifyMigrationExists) {
    echo "Skipping fortify:install. Migration file already exists.\n";
} else {
    // **Run fortify:install only if migration file doesn't exist**
    if ($fortifyInstalled) {
        echo "Running fortify:install...\n";
        $fortifyInstallCommand = PHP_BINARY . ' artisan fortify:install --ansi';
        exec($fortifyInstallCommand, $execOutput, $execStatus);

        if ($execStatus !== 0) {
            Log::error("Error running fortify:install: " . implode("\n", $execOutput));
            echo "Error running fortify:install: " . implode("\n", $execOutput) . "\n";
            exit(1);
        } else {
            echo "Fortify installation command executed successfully.\n";
        }
    }
}

// Ask user if they want to reset the database (clear all data)
echo "\033[31mWARNING: ALL DATA WILL BE RESET (DROPPED) INCLUDING TABLES AND RELATIONSHIPS\033[0m. Are you sure you want to proceed? (Y/N): ";
$response = strtoupper(trim(fgets(STDIN)));

if ($response === 'Y') {
    // If user agrees to reset database, drop all tables and reapply migrations
    echo "Running migrate:fresh to drop all tables and reapply migrations...\n";
    Artisan::call('migrate:fresh');
    echo "Database has been reset and migrations reapplied.\n";
} elseif ($response === 'N') {
    echo "Skipping database reset...\n";
} else {
    echo "Invalid response. Exiting...\n";
    exit(1);
}

// Clear and optimize Laravel service cache and config cache
echo "Clearing and optimizing Laravel service cache...\n";
Artisan::call('optimize:clear'); // Clears config, route, view caches and compiled services
Artisan::call('config:clear');   // Explicitly clear config cache again for good measure
Artisan::call('cache:clear');    // Clear application cache
Artisan::call('view:clear');     // Clear view cache
echo "Laravel caches cleared and optimized.\n";
