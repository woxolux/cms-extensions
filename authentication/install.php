<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

echo "Running Fortify installation...\n";

// Define the suffixes for the Fortify-related migrations that we need to check
$fortifyMigrationSuffixes = [
    'add_two_factor_columns_to_users_table',
];

// Get a list of all applied migrations from the database
$appliedMigrations = DB::table('migrations')->pluck('migration')->toArray();

// Check if all required Fortify migrations (based on suffixes) are already applied
$missingMigrations = array_filter($fortifyMigrationSuffixes, function ($suffix) use ($appliedMigrations) {
    $isMissing = true;
    foreach ($appliedMigrations as $migration) {
        // Extract filename from full path
        $filename = basename($migration);

        // Remove timestamp prefix (first 17 characters: e.g., '2025_06_15_004225_')
        $migrationName = substr($filename, 17);

        // Remove the '.php' extension
        $migrationName = str_replace('.php', '', $migrationName);

        // Optional: trim leading underscores if present
        $migrationName = ltrim($migrationName, '_');

        // Check if the migration name contains the suffix
        if (strpos($migrationName, $suffix) !== false) {
            $isMissing = false;
            break;
        }
    }
    return $isMissing;
});

// If migrations are missing, proceed to installation
if (!empty($missingMigrations)) {
    echo "Required Fortify migrations are missing. Proceeding with installation...\n";
} else {
    echo "Fortify migrations are already applied.\n";
}

// Prompt user for reset
echo "Do you want to reset and reapply the missing migrations? (Y/N): ";
$response = trim(fgets(STDIN));

if (strtoupper($response) === 'Y') {
    echo "Deleting Fortify migration files...\n";

    // Path to migrations folder
    $migrationPath = database_path('migrations');

    // Get all migration files
    $files = File::files($migrationPath);

    // Delete only Fortify-related migration files
    foreach ($files as $file) {
        foreach ($fortifyMigrationSuffixes as $suffix) {
            if (strpos($file->getFilename(), $suffix) !== false) {
                echo "Deleting file: " . $file->getFilename() . "\n";
                File::delete($file);
            }
        }
    }

    // Reset migrations
    echo "Resetting migrations...\n";
    Artisan::call('migrate:reset');
    echo "Migrations have been reset.\n";

    // Run migrations again
    echo "Running migrations...\n";
    Artisan::call('migrate');
    echo "Migrations reapplied.\n";

    // Continue to publish Fortify assets, views, and config
    echo "Publishing Fortify assets, views, and config...\n";
    Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'config']);
    Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'views']);
    Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'assets']);

    // Check if Fortify is installed via Composer
    echo "Checking if Fortify is installed via Composer...\n";
    $composerOutput = [];
    exec('composer show laravel/fortify', $composerOutput, $status);

    if ($status !== 0) {
        echo "Fortify not installed. Installing via Composer...\n";
        exec('composer require laravel/fortify', $composerOutput, $status);
        if ($status !== 0) {
            echo "Error installing Fortify.\n" . implode("\n", $composerOutput);
            exit(1);
        } else {
            echo "Fortify installed successfully.\n";
        }
    } else {
        echo "Fortify is already installed.\n";
    }

    // Run 'composer install' to ensure dependencies and autoloader are up-to-date
    echo "Running 'composer install'...\n";
    exec('composer install', $composerOutput, $status);
    if ($status !== 0) {
        echo "Warning: 'composer install' failed.\n" . implode("\n", $composerOutput);
    } else {
        echo "'composer install' completed.\n";
    }

    // Clear and optimize cache
    echo "Clearing caches...\n";
    Artisan::call('optimize:clear');
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('view:clear');
    echo "Caches cleared.\n";

    // Run 'fortify:install' in separate process
    echo "Running 'fortify:install'...\n";
    $cmd = PHP_BINARY . ' artisan fortify:install --ansi';
    exec($cmd, $output, $status);
    if ($status !== 0) {
        echo "Error running 'fortify:install'.\n" . implode("\n", $output);
        exit(1);
    } else {
        echo "'fortify:install' executed successfully.\n";
        echo implode("\n", $output);
    }

    echo "Fortify installation process completed.\n";

} elseif (strtoupper($response) === 'N') {
    // Skip migration reset but still proceed with Fortify installation (without creating migrations)
    echo "Skipping migration reset.\n";

    // Continue to publish Fortify assets, views, and config
    echo "Publishing Fortify assets, views, and config...\n";
    Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'config']);
    Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'views']);
    Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'assets']);

    // Check if Fortify is installed via Composer
    echo "Checking if Fortify is installed via Composer...\n";
    $composerOutput = [];
    exec('composer show laravel/fortify', $composerOutput, $status);

    if ($status !== 0) {
        echo "Fortify not installed. Installing via Composer...\n";
        exec('composer require laravel/fortify', $composerOutput, $status);
        if ($status !== 0) {
            echo "Error installing Fortify.\n" . implode("\n", $composerOutput);
            exit(1);
        } else {
            echo "Fortify installed successfully.\n";
        }
    } else {
        echo "Fortify is already installed.\n";
    }

    // **Skip composer install** if Fortify is already installed
    echo "Skipping 'composer install' as Fortify is already installed.\n";

    // Clear and optimize cache
    echo "Clearing caches...\n";
    Artisan::call('optimize:clear');
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('view:clear');
    echo "Caches cleared.\n";

    echo "Fortify installation process completed.\n";
} else {
    echo "Invalid response. Exiting...\n";
    exit(1);
}
