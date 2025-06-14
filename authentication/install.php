<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "Running Fortify installation...\n";

// Define the suffixes for the migrations we need to check (for example)
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
        // Check if the migration ends with the required suffix
        if (substr($migration, -strlen($suffix)) === $suffix) {
            return false;  // Migration with the required suffix exists
        }
    }
    return true;  // Migration with the required suffix is missing
});

// If no migrations are missing, proceed to ask if user wants to reset
if (empty($missingMigrations)) {
    echo "Required migrations have already been applied.\n";
} else {
    // If migrations aren't applied, we proceed to installation
    echo "Required migrations are missing. Proceeding with Fortify installation...\n";
}

// Ask user if they want to reset migrations
echo "Do you want to reset the migrations? (Y/N): ";
$response = trim(fgets(STDIN));  // Read user input

if (strtoupper($response) === 'Y') {
    // If user says 'Y', run migrate:reset
    echo "Resetting migrations...\n";
    Artisan::call('migrate:reset');
    echo "Migrations have been reset.\n";

    // Proceed with Fortify installation
    echo "Proceeding with Fortify installation...\n";
    installFortify();
} elseif (strtoupper($response) === 'N') {
    // If user says 'N', skip installation
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
    // Install Fortify via Composer
    echo "Installing Fortify via Composer...\n";
    exec('composer require laravel/fortify', $output, $status);

    if ($status !== 0) {
        echo "Error: Fortify installation failed via Composer.\n";
        echo implode("\n", $output);
        exit(1);
    } else {
        echo "Fortify installed successfully via Composer.\n";
    }

    // Register the service provider in config/app.php
    echo "Registering Fortify service provider...\n";
    $serviceProvider = "Laravel\\Fortify\\FortifyServiceProvider::class";
    $appConfigPath = base_path('config/app.php');

    if (File::exists($appConfigPath)) {
        $configContents = File::get($appConfigPath);
        if (strpos($configContents, $serviceProvider) === false) {
            $configContents = preg_replace(
                "/'providers' => \[.*\],/s",
                "'providers' => [\n        $serviceProvider,\n    ],",
                $configContents
            );
            File::put($appConfigPath, $configContents);
            echo "Fortify service provider registered in config/app.php.\n";
        } else {
            echo "Fortify service provider is already registered.\n";
        }
    } else {
        echo "Could not find config/app.php. Please ensure it's in the right location.\n";
        exit(1);
    }

    // Run fortify:install
    echo "Running fortify:install...\n";
    try {
        Artisan::call('fortify:install');
        echo "Fortify installation completed successfully.\n";
    } catch (Exception $e) {
        echo "Error running fortify:install: " . $e->getMessage() . "\n";
        exit(1);
    }
}
