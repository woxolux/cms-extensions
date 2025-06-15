<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "Running Fortify installation...\n";

// Define the suffixes for the Fortify-related migrations that we need to check
$fortifyMigrationSuffixes = [
    'add_two_factor_columns_to_users_table', // Fortify-specific migration (without timestamp prefix)
];

// Get a list of all applied migrations from the database
$appliedMigrations = DB::table('migrations')->pluck('migration')->toArray();

// Log the applied migrations for further debug
echo "Applied migrations: " . implode(', ', $appliedMigrations) . "\n";

// Check if all required Fortify migrations (based on suffixes) are already applied
$missingMigrations = array_filter($fortifyMigrationSuffixes, function ($suffix) use ($appliedMigrations) {
    // Check if any migration filename contains the required suffix
    $isMissing = true;
    foreach ($appliedMigrations as $migration) {
        // Extract the migration name by removing the timestamp (first part of the filename)
        $migrationName = substr($migration, 17); // Remove the timestamp part (assuming it's 17 characters long)
        
        // Log each comparison for debug
        echo "Comparing migration: $migrationName with suffix: $suffix\n";

        if (strpos($migrationName, $suffix) !== false) {
            $isMissing = false;  // Fortify migration found, not missing
            break;
        }
    }
    return $isMissing;
});

// Log missing migrations for further debug
echo "Missing Fortify migrations: " . implode(', ', $missingMigrations) . "\n";

// If migrations are missing, proceed to installation
if (!empty($missingMigrations)) {
    echo "Required Fortify migrations are missing. Proceeding with Fortify installation...\n";
} else {
    echo "Fortify migrations have already been applied.\n";
}

// Prompt user to reset Fortify migrations if required
echo "Do you want to reset and apply the missing Fortify migrations? (Y/N): ";
$response = trim(fgets(STDIN));

if (strtoupper($response) === 'Y') {
    echo "Deleting Fortify migration files...\n";
    
    // Define the path to the migrations directory
    $migrationPath = database_path('migrations');
    
    // Get all files in the migrations folder
    $files = File::files($migrationPath);
    
    // Loop through the files and delete ONLY Fortify-related migration files
    foreach ($files as $file) {
        foreach ($fortifyMigrationSuffixes as $suffix) {
            if (strpos($file->getFilename(), $suffix) !== false) {
                echo "Deleting file: " . $file->getFilename() . "\n";
                File::delete($file);  // Delete the file
            }
        }
    }

    // Reset migrations (this will remove all migrated data and tables)
    echo "Resetting migrations...\n";
    Artisan::call('migrate:reset');
    echo "Migrations have been reset.\n";

    // Run migrations again (this will apply all migrations, including the Fortify ones)
    echo "Running migrations...\n";
    Artisan::call('migrate');
    echo "Migrations have been successfully reapplied.\n";
} elseif (strtoupper($response) === 'N') {
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
    $composerCommand = 'composer';
    exec("{$composerCommand} require laravel/fortify", $composerOutput, $status);

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

// Always run 'composer install' to ensure all packages are correctly installed
// and the autoloader is fully consistent after any require operations.
echo "Running 'composer install' to ensure all dependencies are met and autoloader is up-to-date...\n";
$composerCommand = 'composer';
$composerOutput = [];
exec("{$composerCommand} install", $composerOutput, $status);
if ($status !== 0) {
    echo "Warning: 'composer install' failed. Some dependencies might be missing or autoloader issues persist.\n";
    echo implode("\n", $composerOutput) . "\n";
} else {
    echo "Composer dependencies and autoloader verified.\n";
}

// Clear and optimize Laravel's internal service cache and config cache.
echo "Clearing and optimizing Laravel service cache...\n";
Artisan::call('optimize:clear'); // Clears config, route, view caches and compiled services
Artisan::call('config:clear');   // Explicitly clear config cache again for good measure
Artisan::call('cache:clear');    // Clear application cache
Artisan::call('view:clear');     // Clear view cache
echo "Laravel caches cleared and optimized.\n";

// CRITICAL CHANGE: Run fortify:install in a separate PHP process.
// This ensures a fresh Laravel application instance is booted,
// which will correctly recognize the newly installed Fortify service provider.
echo "Running fortify:install in a separate Artisan process...\n";
// Use PHP_BINARY for robustness to find the PHP executable
$fortifyInstallCommand = PHP_BINARY . ' artisan fortify:install --ansi';
$execOutput = [];
$execStatus = 0;
exec($fortifyInstallCommand, $execOutput, $execStatus);

if ($execStatus !== 0) {
    Log::error("Error running fortify:install in separate process: " . implode("\n", $execOutput));
    echo "Error running fortify:install: " . implode("\n", $execOutput) . "\n";
    exit(1);
} else {
    echo "Fortify installation command executed successfully in separate process.\n";
    echo implode("\n", $execOutput) . "\n"; // Output the result of the Fortify install command
}

echo "Fortify installation process completed.\n";
