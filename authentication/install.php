<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

echo "Running Fortify installation...\n";

// Check if Fortify migration has already been run (you can customize this based on your needs)
$fortifyMigrations = [
    '2020_12_20_123456_create_fortify_tables.php', // Example Fortify migration name
    '2020_12_20_123457_add_two_factor_columns.php', // Another example
];

// Get a list of all applied migrations
$appliedMigrations = DB::table('migrations')->pluck('migration')->toArray();

// Check if any Fortify migrations are missing
$missingMigrations = array_diff($fortifyMigrations, $appliedMigrations);

if (empty($missingMigrations)) {
    echo "Fortify migrations have already been applied. Skipping Fortify installation...\n";
} else {
    // If migrations aren't applied, install Fortify
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

    // Run fortify:install only if migrations are not already applied
    echo "Running fortify:install...\n";
    try {
        Artisan::call('fortify:install');
        echo "Fortify installation completed successfully.\n";
    } catch (Exception $e) {
        echo "Error running fortify:install: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Publish Fortify assets, views, and config
echo "Publishing Fortify assets, views, and config...\n";
Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'config']);
Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'views']);
Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'assets']);

// Final message
echo "Fortify installation process completed.\n";
