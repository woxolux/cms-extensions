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

    // Always run 'composer install' to ensure all packages (including dev dependencies like Collision)
    // are correctly installed and the autoloader is fully consistent.
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

    // CRUCIAL CHANGE: Run fortify:install in a separate PHP process.
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

// Define the suffix for the main Fortify migration
$fortifyMigrationSuffix = '_create_two_factor_authentication_tables';

// Get a list of all applied migrations
$appliedMigrations = DB::table('migrations')->pluck('migration')->toArray();

// Check if the main Fortify migration is applied
$isFortifyMigrationApplied = false;
foreach ($appliedMigrations as $migration) {
    if (str_ends_with($migration, $fortifyMigrationSuffix)) {
        $isFortifyMigrationApplied = true;
        break;
    }
}

if ($isFortifyMigrationApplied) {
    echo "Fortify-specific migration ('{$fortifyMigrationSuffix}') has already been applied.\n";
    // If it's applied, we still give the option to reset for re-installation
    echo "Do you want to reset and re-install Fortify's migrations? (Y/N): ";
    $response = trim(fgets(STDIN));
    if (strtoupper($response) === 'Y') {
        echo "Proceeding with Fortify migration reset and re-installation...\n";
        deleteFortifyMigrations(); // Call the new deletion function
        Artisan::call('migrate:reset');
        echo "Migrations have been reset.\n";
        Artisan::call('migrate');
        echo "Migrations have been successfully reapplied.\n";
        installFortify();
    } elseif (strtoupper($response) === 'N') {
        echo "Skipping Fortify migration reset. Proceeding with Fortify installation (if not fully configured)...\n";
        // Even if not resetting, still run installFortify to ensure assets/config etc. are published
        installFortify();
    } else {
        echo "Invalid response. Exiting installation script.\n";
        exit(1);
    }
} else {
    echo "Fortify-specific migration ('{$fortifyMigrationSuffix}') is missing. Proceeding with Fortify installation.\n";
    // If it's missing, no need to ask about resetting unless it's for *all* migrations
    // For simplicity, if it's missing, just go straight to installFortify
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

