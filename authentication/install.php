<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "Running Fortify installation...\n";

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
        // Even if already listed, ensure packages are installed/updated just in case
        echo "Running 'composer install --no-dev' to ensure all dependencies are met...\n";
        $composerOutput = [];
        exec("{$composerCommand} install --no-dev", $composerOutput, $status); // Use --no-dev as we're in a production context potentially
        if ($status !== 0) {
            echo "Warning: 'composer install --no-dev' failed. Some dependencies might be missing.\n";
            echo implode("\n", $composerOutput) . "\n";
        } else {
            echo "Composer dependencies re-verified.\n";
        }
    }

    // Regardless of whether it was just added or already existed,
    // ensure autoloader is dumped and caches are cleared.
    echo "Updating Composer autoloader...\n";
    $composerOutput = []; // Reset output for dump-autoload
    exec("{$composerCommand} dump-autoload", $composerOutput, $status);
    if ($status !== 0) {
        echo "Error: Composer dump-autoload failed.\n";
        echo implode("\n", $composerOutput) . "\n";
        exit(1);
    }
    echo "Composer autoloader updated successfully.\n";

    echo "Clearing and optimizing Laravel service cache...\n";
    Artisan::call('optimize:clear'); // This often includes config, route, view caches and compiled services
    Artisan::call('config:clear');   // Explicitly clear config cache again for good measure
    echo "Laravel service cache cleared.\n";

    // Re-include the autoloader for the current process
    // This ensures the running PHP script recognizes the newly available classes.
    $autoloadPath = base_path('vendor/autoload.php');
    if (file_exists($autoloadPath)) {
        echo "Re-including Composer autoloader for current process...\n";
        require $autoloadPath;
        echo "Composer autoloader re-included.\n";
    } else {
        echo "Warning: Composer autoloader file not found at {$autoloadPath}.\n";
    }

    // Crucial step: Manually register FortifyServiceProvider for the current application instance.
    // This ensures `fortify:install` is discoverable immediately.
    echo "Attempting to register Fortify service provider for current execution...\n";
    $app = app(); // Get the current Laravel application instance
    $provider = 'Laravel\\Fortify\\FortifyServiceProvider';

    // Only register if the app hasn't been fully bootstrapped or the provider isn't found
    if (!$app->hasBeenBootstrapped() || !$app->getProvider($provider)) {
        $app->register($provider);
        echo "FortifyServiceProvider registered for current runtime.\n";
    } else {
        echo "FortifyServiceProvider already active for current runtime.\n";
    }

    // Check if the 'fortify:install' command exists before running it
    // This check is now more likely to pass after manual provider registration and autoloader dump
    $commands = Artisan::all();
    if (!isset($commands['fortify:install'])) {
        echo "Error: 'fortify:install' command still not found after provider registration. This indicates a deeper issue.\n";
        exit(1);
    }

    // Run fortify:install
    echo "Running fortify:install...\n";
    try {
        Artisan::call('fortify:install');
        echo "Fortify installation command executed successfully.\n";
    } catch (Exception $e) {
        Log::error("Error running fortify:install: " . $e->getMessage());
        echo "Error running fortify:install: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Define the suffixes for the migrations we need to check
$requiredMigrationSuffixes = [
    '_add_two_factor_columns_to_users_table',
    '_create_users_table',
    '_create_cache_table',
    '_create_jobs_table',
];

// Get a list of all applied migrations
$appliedMigrations = DB::table('migrations')->pluck('migration')->toArray();

// Check if all required migrations (based on suffixes) are already applied
$missingMigrations = array_filter($requiredMigrationSuffixes, function ($suffix) use ($appliedMigrations) {
    foreach ($appliedMigrations as $migration) {
        if (str_ends_with($migration, $suffix)) { // Using str_ends_with for cleaner check
            return false;
        }
    }
    return true;
});

// If migrations are missing, proceed to installation
if (!empty($missingMigrations)) {
    echo "Required migrations are missing. Proceeding with Fortify installation or reset...\n";
} else {
    echo "Required migrations have already been applied.\n";
}

// Ask user if they want to reset migrations
echo "Do you want to reset the Fortify-related migrations and re-run all migrations? (Y/N): ";
$response = trim(fgets(STDIN)); // STDIN is required for interactive input

if (strtoupper($response) === 'Y') {
    echo "Deleting Fortify-related migration files...\n";

    // Define the path to the migrations directory
    $migrationPath = database_path('migrations');

    // Get all files in the migrations folder
    $files = File::files($migrationPath);

    // Loop through the files and delete those matching the Fortify migration suffix
    foreach ($files as $file) {
        if (strpos($file->getFilename(), '_create_two_factor_authentication_tables.php') !== false ||
            strpos($file->getFilename(), '_add_two_factor_columns_to_users_table.php') !== false) {
            echo "Deleting file: " . $file->getFilename() . "\n";
            File::delete($file); // Delete the file
        }
    }

    // Reset migrations
    echo "Resetting all migrations...\n";
    Artisan::call('migrate:reset');
    echo "Migrations have been reset.\n";

    // **Run migrate after reset** to reapply all migrations
    echo "Running all pending migrations...\n";
    Artisan::call('migrate');
    echo "All migrations have been successfully reapplied.\n";

    // Proceed with Fortify installation after ensuring migrations are clean
    installFortify();
} elseif (strtoupper($response) === 'N') {
    echo "Skipping Fortify-related migration reset. Proceeding with Fortify installation (if not already installed)....\n";
    // Even if not resetting, still try to install Fortify if it's missing
    installFortify();
} else {
    echo "Invalid response. Exiting installation script.\n";
    exit(1);
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
