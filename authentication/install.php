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

// Initialize $response with a default value to avoid undefined errors
$response = null;  // Default value

// **Conditionally show missing migrations message only if migrations are missing**
if (!empty($missingMigrations)) {
    echo "Missing Fortify migrations: " . implode(', ', $missingMigrations) . "\n";
    echo "Required Fortify migrations are missing. Proceeding with Fortify installation...\n";
} else {
    // Migrations are already applied, prompt for reset option
    echo "Fortify migrations have already been applied.\n";
    echo "Do you want to reset and apply the migrations again? (Y/N): ";
    $response = strtoupper(trim(fgets(STDIN)));

    if ($response === 'Y') {
        echo "Reusing existing Fortify migration files...\n";
        
        // Manually rollback the migrations (without deleting any files)
        echo "Rolling back migrations...\n";
        Artisan::call('migrate:rollback'); // Rollback the latest batch of migrations
        echo "Migrations rolled back successfully.\n";

        // Run migrations again (this will use the existing migration files)
        echo "Running migrations...\n";
        Artisan::call('migrate'); // Apply the migrations again
        echo "Migrations have been successfully reapplied.\n";
    } elseif ($response === 'N') {
        echo "Skipping Fortify migration reset...\n";
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

// **Skip composer install if Fortify is installed and migrations were not reset**
if ($response !== 'N') {
    echo "Running 'composer install' to ensure all dependencies are met and autoloader is up-to-date...\n";
    exec('composer install', $composerOutput, $status);

    if ($status !== 0) {
        echo "Warning: 'composer install' failed. Some dependencies might be missing or autoloader issues persist.\n";
        echo implode("\n", $composerOutput) . "\n";
    } else {
        echo "Composer dependencies and autoloader verified.\n";
    }
} else {
    echo "Skipping Composer install. Dependencies and autoloader are up-to-date.\n";
}

// Clear and optimize Laravel's internal service cache and config cache
echo "Clearing and optimizing Laravel service cache...\n";
Artisan::call('optimize:clear'); // Clears config, route, view caches and compiled services
Artisan::call('config:clear');   // Explicitly clear config cache again for good measure
Artisan::call('cache:clear');    // Clear application cache
Artisan::call('view:clear');     // Clear view cache
echo "Laravel caches cleared and optimized.\n";

// **Critical change:** Skip fortify:install if migrations are not being reset
if ($response !== 'N') {
    echo "Running fortify:install in a separate Artisan process...\n";
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
} else {
    echo "Skipping fortify:install command.\n";
}
