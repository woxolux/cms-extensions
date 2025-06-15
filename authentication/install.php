<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "Running Fortify installation...\n";

// Define the suffixes for the Fortify-related migrations that we need to check
$fortifyMigrationSuffixes = [
    'add_two_factor_columns_to_users_table', // Fortify-specific migration
];

// Get a list of all applied migrations from the database
$appliedMigrations = DB::table('migrations')->pluck('migration')->toArray();

// Check if all required Fortify migrations (based on suffixes) are already applied
$missingMigrations = array_filter($fortifyMigrationSuffixes, function ($suffix) use ($appliedMigrations) {
    // Check if any migration filename contains the required suffix
    foreach ($appliedMigrations as $migration) {
        // Get the migration name by removing the timestamp (first 17 characters)
        $migrationName = substr($migration, 17); // This will remove the timestamp, leaving the migration name

        // If migration name contains the suffix, it's not missing
        if (strpos($migrationName, $suffix) !== false) {
            return false;
        }
    }
    return true;
});

// Log missing migrations for further debug
echo "Missing Fortify migrations: " . implode(', ', $missingMigrations) . "\n";

// If migrations are missing, prompt for resetting and applying them
if (!empty($missingMigrations)) {
    echo "Required Fortify migrations are missing. Do you want to reset and apply the missing Fortify migrations? (Y/N): ";

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
} else {
    echo "Fortify migrations have already been applied.\n";
}

// Always proceed to publish Fortify assets, views, and config
echo "Publishing Fortify assets, views, and config...\n";
Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'co]()
