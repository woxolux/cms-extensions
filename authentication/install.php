<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

// Display message indicating that the installation process of Laravel Fortify is starting
echo "\033[34mRunning Laravel Fortify installation...\033[0m\n";

// Define the suffixes for the Fortify-related migrations that we need to check
$fortifyMigrationSuffixes = [
    'add_two_factor_columns_to_users_table', // Fortify-specific migration
];

// Get a list of all migration files in the migrations directory
$migrationPath = database_path('migrations');  // Get the path to the migrations directory
$files = File::files($migrationPath);          // Get all files in the migrations directory

// Filter the files that match the Laravel Fortify migration suffixes
$existingMigrations = [];
foreach ($files as $file) {
    foreach ($fortifyMigrationSuffixes as $suffix) {
        if (strpos($file->getFilename(), $suffix) !== false) {
            // If migration file matches the Fortify suffix, add it to the list of existing migrations
            $existingMigrations[] = $file->getFilename();
            break;
        }
    }
}

// Check if Laravel Fortify migration already exists (if migrations are already in place)
$fortifyMigrationExists = !empty($existingMigrations); // If there are any matching migrations, Fortify has been set up

// **Check if Laravel Fortify is installed via Composer**
$fortifyInstalled = false;
exec('composer show laravel/fortify', $composerOutput, $status); // Run composer command to check if Fortify is installed

if ($status === 0) {
    $fortifyInstalled = true; // If the composer command was successful, Fortify is installed
}

// **If Laravel Fortify is installed**
if ($fortifyInstalled) {
    echo "\033[34mLaravel Fortify is already installed (skipping fortify:install)...\033[0m\n";
} else {
    // **If Laravel Fortify is NOT installed, install it via Composer**
    echo "\033[34mLaravel Fortify is not installed. Installing Laravel Fortify...\033[0m\n";
    exec('composer require laravel/fortify', $composerOutput, $status);  // Install Fortify using Composer
    
    // Check if the installation was successful
    if ($status !== 0) {
        // If Composer installation fails, log and display the error
        echo "\033[34mError: Laravel Fortify installation failed via Composer.\033[0m\n";
        echo implode("\n", $composerOutput); // Output the Composer error message
        exit(1); // Exit with error code 1 if installation fails
    } else {
        // If installation is successful, confirm the success
        echo "\033[34mLaravel Fortify installed successfully.\033[0m\n";
    }
}

// **If no migration files exist (first run), automatically install and run migrations**
if (!$fortifyMigrationExists) {
    echo "\033[34mRunning fortify:install and fresh migration (first run)...\033[0m\n";

    // Run fortify:install command to set up Fortify (registration, authentication, etc.)
    $fortifyInstallCommand = PHP_BINARY . ' artisan fortify:install --ansi';
    exec($fortifyInstallCommand, $execOutput, $execStatus);  // Execute the artisan command

    // Check if the installation was successful
    if ($execStatus !== 0) {
        Log::error("Error running fortify:install: " . implode("\n", $execOutput));  // Log any error
        echo "\033[34mError running fortify:install: " . implode("\n", $execOutput) . "\033[0m\n";  // Display error message
        exit(1);  // Exit with error code 1 if fortify:install fails
    } else {
        echo "\033[34mLaravel Fortify installation command executed successfully.\033[0m\n";  // Success message
    }

    // Automatically perform a fresh migration (since no migrations exist)
    echo "\033[34mRunning migrate:fresh to drop all tables and reapply migrations...\033[0m\n";
    Artisan::call('migrate:fresh');  // Run the fresh migration command to reset the database
    echo "\033[34mDatabase has been reset and migrations reapplied.\033[0m\n";  // Success message for migration
} else {
    // **If migration files already exist (subsequent runs), ask user if they want to reset and reapply migrations**
    echo "\033[34mMigration files already exist. Do you want to reset and reapply the migrations?\033[0m\n";

    // **WARNING message in red**
    echo "\033[31mWARNING! RESETTING WILL ERASE ALL DATA, INCLUDING TABLES AND RELATIONSHIPS!\033[0m\n"; 

    // Ask user if they want to reset the database (clear all data)
    echo "\033[38;5;214mDo you want to erase all data, including tables and relationships? \033[0m"; // Orange question

    // Color Y = Yes in green, N = No in red
    echo "\033[32m(Y = Yes)\033[0m / \033[31m(N = No)\033[0m: ";

    // Start a loop to keep asking until a valid response is given
    while (true) {
        $response = strtoupper(trim(fgets(STDIN)));  // Get and normalize the user input

        // Check if response is valid
        if ($response === 'Y') {
            // If user agrees to reset database, drop all tables and reapply migrations
            echo "\033[34mRunning migrate:fresh to drop all tables and reapply migrations...\033[0m\n";
            Artisan::call('migrate:fresh');  // Run migrate:fresh to drop and reapply migrations
            echo "\033[34mDatabase has been reset and migrations reapplied.\033[0m\n";  // Success message
            break; // Exit the loop after successful action
        } elseif ($response === 'N') {
            echo "\033[34mSkipping database reset and migrations.\033[0m\n";  // Inform user if skipping
            break; // Exit the loop if user chooses not to reset
        } else {
           // If input is invalid, prompt user again
           echo "\033[34mInvalid response. Please enter '\033[32mY\033[34m' or '\033[31mN\033[34m'\033[0m: ";  
        }
    }
}

// **Publishing Fortify assets, views, and config only if Fortify is installed**
if ($fortifyInstalled) {
    echo "\033[34mPublishing Laravel Fortify assets, views, and config...\033[0m\n";
    // Publish the configuration file
    Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'config']);
    // Publish the views for Fortify
    Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'views']);
    // Publish the assets (CSS, JS, etc.) for Fortify
    Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'assets']);
}

// Clear and optimize Laravel service cache and config cache
echo "\033[34mClearing and optimizing Laravel caches...\033[0m\n"; // Display message before clearing caches
Artisan::call('optimize:clear'); // Clears config, route, view caches, and compiled services
Artisan::call('config:clear');   // Explicitly clear config cache again for good measure
Artisan::call('cache:clear');    // Clear application cache
Artisan::call('view:clear');     // Clear view cache

// Confirm that the cache has been cleared and optimized
echo "\033[34mLaravel cache clearing and optimization completed successfully.\033[0m\n";

// Display a closing message to indicate the script has finished executing
echo "\033[34mClosing extended installation script.\033[0m\n";
