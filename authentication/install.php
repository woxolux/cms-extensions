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

// Check if any Fortify migration files are missing
$missingMigrations = array_diff($fortifyMigrationSuffixes, array_map(function ($filename) use ($fortifyMigrationSuffixes) {
    foreach ($fortifyMigrationSuffixes as $suffix) {
        if (strpos($filename, $suffix) !== false) {
            return $suffix;
        }
    }
    return null;
}, $existingMigrations));

// **Skip the prompt if migrations are already applied**

echo "Checking if Fortify migrations are already applied...\n";
$migrationsApplied = !empty($existingMigrations);

if ($migrationsApplied) {
    echo "Fortify migrations have already been applied.\n";
    // Skip the migration prompt
} else {
    echo "Fortify migrations are missing.\n";
    echo "Do you want to apply the migrations now? (Y/N): ";
    $response = strtoupper(trim(fgets(STDIN)));

    if ($response === 'Y') {
        echo "Running migrations...\n";
        Artisan::call('migrate'); // Apply the migrations
        echo "Migrations applied successfully.\n";
    } elseif ($response === 'N') {
        echo "Skipping migration...\n";
    } else {
        echo "Invalid response. Exiting...\n";
        exit(1);
    }
}

// Always proceed to publish Fortify assets, views, and config
echo "Publishing Fortify assets, views, and config...\n";
Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'config']);
Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'views']);
Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'assets']);

// Check if Fortify is already installed (via Composer)
echo "Checking if Fortify is installed via Composer...\n";
$composerOutput = [];
exec('composer show laravel/fortify', $composerOutput, $status);

$fortifyInstalled = $status === 0;

if (!$fortifyInstalled) {
    echo "Fortify is not installed. Installing Fortify via Composer...\n";
    exec('composer require laravel/fortify', $composerOutput, $status);

    if ($status !== 0) {
        echo "Error: Fortify installation failed via Composer.\n";
        echo implode("\n", $composerOutput);
        exit(1);
    } else {
        echo "Fortify installed successfully via Composer.\n";
    }
} else {
    echo "Fortify is already installed.\n";
}

// Clear and optimize Laravel's internal service cache and config cache
echo "Clearing and optimizing Laravel service cache...\n";
Artisan::call('optimize:clear'); // Clears config, route, view caches and compiled services
Artisan::call('config:clear');   // Explicitly clear config cache again for good measure
Artisan::call('cache:clear');    // Clear application cache
Artisan::call('view:clear');     // Clear view cache
echo "Laravel caches cleared and optimized.\n";

// **Skip fortify:install if migrations are not being reset**
echo "Running fortify:install...\n";
$fortifyInstallCommand = PHP_BINARY . ' artisan fortify:install --ansi';
exec($fortifyInstallCommand, $execOutput, $execStatus);

if ($execStatus !== 0) {
    Log::error("Error running fortify:install in separate process: " . implode("\n", $execOutput));
    echo "Error running fortify:install: " . implode("\n", $execOutput) . "\n";
    exit(1);
} else {
    echo "Fortify installation command executed successfully in separate process.\n";
    echo implode("\n", $execOutput) . "\n"; // Output the result of the Fortify install command
}
