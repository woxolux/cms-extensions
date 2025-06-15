<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "Running Fortify installation...\n";

// Define the required migrations
$requiredMigrations = [
    'add_two_factor_columns_to_users_table',  // Migration name base
    'create_users_table',
    'create_cache_table',
    'create_jobs_table',
];

// ** 1. Fetch the list of applied migrations with raw SQL to be sure of the format **
echo "Fetching applied migrations from the database...\n";
$appliedMigrations = DB::select('SELECT migration FROM migrations');

echo "Applied migrations: \n";
foreach ($appliedMigrations as $migration) {
    echo $migration->migration . "\n"; // Debugging output to check what migrations are applied
}

// ** 2. Check if all required migrations (based on base names) are already applied **
$missingMigrations = array_filter($requiredMigrations, function ($requiredMigration) use ($appliedMigrations) {
    $isMigrationApplied = false;

    foreach ($appliedMigrations as $migration) {
        // Remove the timestamp prefix to get the migration name base (e.g., _add_two_factor_columns_to_users_table)
        $migrationBaseName = preg_replace('/^\d+_/', '', $migration->migration);
        
        // DEBUG: Output each migration being checked against the required base names
        echo "Checking migration base: " . $migrationBaseName . " against required: " . $requiredMigration . "\n";
        
        // Check if the base migration name matches the required one
        if ($migrationBaseName === $requiredMigration) {
            $isMigrationApplied = true;
            break;
        }
    }

    return !$isMigrationApplied;  // If the migration is not applied, return true
});

// ** 3. Output the result of missing migrations **
echo "Missing migrations: \n";
print_r($missingMigrations);

// ** 4. If migrations are missing, proceed with installation **
if (!empty($missingMigrations)) {
    echo "Required migrations are missing. Proceeding with Fortify installation...\n";
} else {
    echo "Required migrations have already been applied.\n";
}

// Ask user if they want to reset migrations
echo "Do you want to reset the migrations? (Y/N): ";
$response = trim(fgets(STDIN));

if (strtoupper($response) === 'Y') {
    echo "Deleting Fortify migration files...\n";
    
    // Define the path to the migrations directory
    $migrationPath = database_path('migrations');
    
    // Get all files in the migrations folder
    $files = File::files($migrationPath);
    
    // Loop through the files and delete those matching the suffix
    foreach ($files as $file) {
        foreach ($missingMigrations as $suffix) {
            if (strpos($file->getFilename(), $suffix) !== false) {
                echo "Deleting file: " . $file->getFilename() . "\n";
                File::delete($file);  // Delete the file
            }
        }
    }

    // Reset migrations
    echo "Resetting migrations...\n";
    Artisan::call('migrate:reset');
    echo "Migrations have been reset.\n";

    // **Run migrate after reset** to reapply all migrations
    echo "Running migrations...\n";
    Artisan::call('migrate');
    echo "Migrations have been successfully reapplied.\n";

    // Proceed with Fortify installation
    echo "Proceeding with Fortify installation...\n";
    installFortify();
} elseif (strtoupper($response) === 'N') {
    echo "Skipping Fortify installation...\n";
} else {
    echo "Invalid response. Exiting...\n";
    exit(1);
}

// Always proceed to publish Fortify assets, views, and config
echo "Publishing Fortify assets, views, and config...\n";
Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'config']);
Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'views']);
Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'assets']);

// Final message
echo "Fortify installation process completed.\n";

// Function to handle Fortify installation
function installFortify()
{
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
    // This forces Laravel to re-read its service provider manifest and compiled services,
    // which should pick up Fortify's service provider and resolve class loading issues.
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
}
