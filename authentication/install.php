<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

echo "Running Fortify installation...\n";

// Define the suffixes for the Fortify-related migrations (empty now since we're removing the check)
$fortifyMigrationSuffixes = [
    // No migrations to check for now
];

// Get a list of all applied migrations from the database
$appliedMigrations = DB::table('migrations')->pluck('migration')->toArray();

// Log the applied migrations for further debug
echo "Applied migrations: " . implode(', ', $appliedMigrations) . "\n";

// Check if 'two_factor_secret' column already exists in the 'users' table
echo "Checking if 'two_factor_secret' column exists in 'users' table...\n";
if (Schema::hasColumn('users', 'two_factor_secret')) {
    echo "'two_factor_secret' column already exists. Skipping migration for adding it.\n";
} else {
    echo "'two_factor_secret' column does not exist. It will be added during migration.\n";
}

// Prompt user to reset Fortify migrations if required
echo "Do you want to reset and apply Fortify migrations? (Y/N): ";
$response = strtoupper(trim(fgets(STDIN)));

if ($response === 'Y') {
    echo "Deleting Fortify migration files...\n";

    // Define the path to the migrations directory
    $migrationPath = database_path('migrations');
    $files = File::files($migrationPath);

    // Loop through the files and delete ONLY Fortify-related migration files (if any exist)
    foreach ($files as $file) {
        // No migration suffix to match anymore, so you can skip this block or leave it if there are other checks
        echo "No specific migrations to delete.\n";
    }

    // Reset migrations
    echo "Resetting migrations...\n";
    Artisan::call('migrate:reset');
    echo "Migrations have been reset.\n";

    // Run migrations again, ensuring 'two_factor_secret' column is not added twice
    echo "Running migrations...\n";
    Artisan::call('migrate', [], $exitCode);
    
    if ($exitCode !== 0) {
        echo "Error occurred while running migrations.\n";
        exit(1);
    }

    echo "Migrations have been successfully reapplied.\n";

} elseif ($response === 'N') {
    echo "Skipping Fortify migration reset...\n";
} else {
    echo "Invalid response. Exiting...\n";
    exit(1);
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

if ($status !== 0) {
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

// Run 'composer install' to ensure all dependencies are correctly installed and autoloader is fully consistent
echo "Running 'composer install' to ensure all dependencies are met and autoloader is up-to-date...\n";
exec('composer install', $composerOutput, $status);

if ($status !== 0) {
    echo "Warning: 'composer install' failed. Some dependencies might be missing or autoloader issues persist.\n";
    echo implode("\n", $composerOutput) . "\n";
} else {
    echo "Composer dependencies and autoloader verified.\n";
}

// Clear and optimize Laravel's internal service cache and config cache
echo "Clearing and optimizing Laravel service cache...\n";
Artisan::call('optimize:clear'); // Clears config, route, view caches and compiled services
Artisan::call('config:clear');   // Explicitly clear config cache again for good measure
Artisan::call('cache:clear');    // Clear application cache
Artisan::call('view:clear');     // Clear view cache
echo "Laravel caches cleared and optimized.\n";

// Run fortify:install in a separate PHP process
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
