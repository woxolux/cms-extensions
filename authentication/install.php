<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

echo "Running Fortify installation...\n";

// Step 1: Install Fortify via Composer (specify version)
$fortifyVersion = '1.26.*'; // Specify the exact version of Fortify
echo "Installing Fortify version {$fortifyVersion} via Composer...\n";
exec("composer require laravel/fortify:{$fortifyVersion}", $output, $status);

if ($status !== 0) {
    echo "Error: Fortify installation failed via Composer.\n";
    echo implode("\n", $output);
    exit(1);
} else {
    echo "Fortify {$fortifyVersion} installed successfully via Composer.\n";
}

// Step 2: Ensure the Fortify service provider is registered in `config/app.php`
echo "Registering Fortify service provider...\n";
$serviceProvider = "Laravel\\Fortify\\FortifyServiceProvider::class";

// Get the path to the `config/app.php` file
$appConfigPath = base_path('config/app.php');

// Ensure the service provider is registered
if (File::exists($appConfigPath)) {
    $configContents = File::get($appConfigPath);
    if (strpos($configContents, $serviceProvider) === false) {
        // Add the service provider to the `providers` array
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

// Step 3: Publish Fortify assets, views, and config
echo "Publishing Fortify assets, views, and config...\n";
Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'config']);
Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'views']);
Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'assets']);

// Step 4: Run the Fortify installation command
echo "Running fortify:install...\n";
try {
    Artisan::call('fortify:install');
    echo "Fortify installation completed successfully.\n";
} catch (Exception $e) {
    echo "Error running fortify:install: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 5: Check if database migrations are needed
echo "Checking if database migrations are needed...\n";

// Define a list of Fortify migration files
$fortifyMigrations = [
    '2020_12_20_123456_create_fortify_tables.php', // Example Fortify migration name
    '2020_12_20_123457_add_two_factor_columns.php', // Another example
];

// Get a list of all applied migrations from the migrations table
$appliedMigrations = DB::table('migrations')->pluck('migration')->toArray();

// Check if Fortify migrations have already been run
$missingMigrations = array_diff($fortifyMigrations, $appliedMigrations);

if (empty($missingMigrations)) {
    echo "Fortify migrations already applied. Skipping migrations.\n";
} else {
    // Step 5a: Check if the specific column exists in the users table
    if (Schema::hasColumn('users', 'two_factor_secret')) {
        echo "Column 'two_factor_secret' already exists in the 'users' table. Skipping migration for this column.\n";
    } else {
        // Run the migrations if needed
        echo "Running database migrations...\n";
        try {
            Artisan::call('migrate');
            echo "Database migrations completed successfully.\n";
        } catch (Exception $e) {
            echo "Error running migrations: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
}

// Step 6: Clean up temporary installation files (if any)
$installTempPath = storage_path('app/private/cms-extensions-main');
if (File::exists($installTempPath)) {
    File::deleteDirectory($installTempPath);
    echo "Temporary installation files cleaned up.\n";
}

// Step 7: Output the installed version of Fortify (without the asterisk)
echo "Installed Fortify version: ";
exec('composer show laravel/fortify --latest', $versionOutput);
$version = '';
foreach ($versionOutput as $line) {
    if (strpos($line, 'versions') !== false) {
        // Extract the version number from the line
        preg_match('/v(\d+\.\d+\.\d+)/', $line, $matches);
        if (!empty($matches)) {
            $version = $matches[0];
            break;
        }
    }
}
echo $version . "\n";

// Final message
echo "Fortify installation process completed.\n";
