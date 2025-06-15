<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "Running Fortify installation...\n";

/**
 * Deletes Fortify-related migration files from the database/migrations directory.
 */
function deleteFortifyMigrations()
{
    echo "Deleting Fortify-related migration files...\n";
    $migrationPath = database_path('migrations');
    $files = File::files($migrationPath);
    foreach ($files as $file) {
        // Specific Fortify migrations to look for in filenames
        if (strpos($file->getFilename(), '_create_two_factor_authentication_tables.php') !== false ||
            strpos(basename($file->getFilename()), '_add_two_factor_columns_to_users_table.php') !== false) {
            echo "Deleting file: " . $file->getFilename() . "\n";
            File::delete($file);
        }
    }
}

// Function to handle Fortify installation
function installFortify()
{
    echo "Ensuring Fortify is installed via Composer...\n";
    $composerCommand = 'composer';

    // Check if laravel/fortify is already in composer.json
    $composerJsonPath = base_path('composer.json');
    if (!File::exists($composerJsonPath)) {
        echo "Error: composer.json not found at " . $composerJsonPath . "\n";
        exit(1);
    }

    $composerJsonContent = file_get_contents($composerJsonPath);
    $composerJson = json_decode($composerJsonContent, true);

    $fortifyAlreadyInComposerJson = isset($composerJson['require']['laravel/fortify']) ||
                                    isset($composerJson['require-dev']['laravel/fortify']);

    if (!$fortifyAlreadyInComposerJson) {
        echo "Fortify not found in composer.json. Requiring laravel/fortify...\n";
        $composerOutput = [];
        // The 'require' command will add to composer.json and automatically run update/install
        exec("{$composerCommand} require laravel/fortify", $composerOutput, $status);

        if ($status !== 0) {
            echo "Error: Requiring laravel/fortify failed.\n";
            echo implode("\n", $composerOutput) . "\n";
            exit(1);
        } else {
            echo "laravel/fortify successfully added to composer.json and packages installed/updated.\n";
        }
    } else {
        echo "laravel/fortify is already listed in composer.json.\n";
    }

    // Always run 'composer install' to ensure all packages are correctly installed
    // and the autoloader is fully consistent after any require operations.
    echo "Running 'composer install' to ensure all dependencies are met and autoloader is up-to-date...\n";
    $composerOutput = [];
    exec("{$composerCommand} install", $composerOutput, $status);
    if ($status !== 0) {
        echo "Warning: 'composer install' failed. Some dependencies might be missing or autoloader issues persist.\n";
        echo implode("\n", $composerOutput) . "\n";
        // Do not exit here, allow subsequent steps to attempt recovery if possible
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

// Define the suffixes for the Fortify-related migrations that we need to check
// These are the actual migration names without timestamps that Fortify creates.
$fortifyMigrationSuffixes = [
    '_create_two_factor_authentication_tables', // The main Fortify 2FA table
    'add_two_factor_columns_to_users_table', // The column addition to users table
];

// IMPORTANT: Reconnect to the database to ensure the latest state is read.
DB::reconnect();

// Get a list of all applied migrations from the database
$appliedMigrations = DB::table('migrations')->pluck('migration')->toArray();

// Log the applied migrations for debugging purposes
echo "Applied migrations in DB: " . implode(', ', $appliedMigrations) . "\n";

// Check if all required Fortify migrations (based on suffixes) are already applied
$allFortifyMigrationsApplied = true;
foreach ($fortifyMigrationSuffixes as $suffix) {
    $foundSuffix = false;
    foreach ($appliedMigrations as $appliedMigrationName) {
        // Use str_ends_with to check if the applied migration name from DB ends with the suffix
        // This accounts for the timestamp prefix in the DB entry.
        if (str_ends_with($appliedMigrationName, $suffix)) {
            $foundSuffix = true;
            break;
        }
    }
    if (!$foundSuffix) {
        $allFortifyMigrationsApplied = false;
        // Optional: Log which specific Fortify migration is missing
        echo "Missing Fortify migration suffix: {$suffix}\n";
        break; // Found at least one missing, no need to check further
    }
}


// Adjusted logic for migration check and prompts
if ($allFortifyMigrationsApplied) {
    echo "All Fortify migrations have already been applied.\n";
    echo "Do you want to reset and re-install Fortify's migrations? (Y/N): ";
    $response = trim(fgets(STDIN)); // Get user input

    if (strtoupper($response) === 'Y') {
        echo "Proceeding with Fortify migration reset and re-installation...\n";
        deleteFortifyMigrations();
        Artisan::call('migrate:reset');
        echo "Migrations have been reset.\n";
        Artisan::call('migrate');
        echo "Migrations have been successfully reapplied.\n";
        installFortify(); // Call installFortify again to ensure publishing
    } elseif (strtoupper($response) === 'N' || $response === '') { // Treat empty response as 'N'
        echo "Skipping Fortify migration reset. Fortify assets and config will be published if needed.\n";
        installFortify(); // Call installFortify to ensure publishing
    } else {
        echo "Invalid response. Exiting installation script.\n";
        exit(1);
    }
} else {
    // If not all Fortify migrations are applied, proceed to install them without asking for reset first.
    echo "Required Fortify migrations are missing. Proceeding with Fortify installation.\n";
    installFortify();
}


// Always proceed to publish Fortify assets, views, and config
echo "Publishing Fortify assets, views, and config...\n";
try {
    Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'fortify-config', '--force' => true]);
    Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'fortify-views', '--force' => true]);
    // The 'assets' tag is not typical for Fortify. It usually publishes config, views, and actions.
    // If you have custom assets for Fortify, you might need a different tag or manual copy.
    // Assuming 'fortify-actions' if you meant publish actions.
    // Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'fortify-actions']);
    echo "Fortify config and views published successfully.\n";
} catch (Exception $e) {
    Log::error("Error publishing Fortify assets: " . $e->getMessage());
    echo "Error publishing Fortify assets: " . $e->getMessage() . "\n";
}


// Final message
echo "Fortify installation process completed.\n";
