<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class FortifyInstaller
{
    // Define the suffixes for the Fortify-related migrations that we need to check
    private $fortifyMigrationSuffixes = [
        'add_two_factor_columns_to_users_table',
    ];

    // Path to migrations folder
    private $migrationPath;

    public function __construct()
    {
        $this->migrationPath = database_path('migrations');
    }

    // Run the installation process
    public function install()
    {
        $this->echoOutput("Running Fortify installation...");

        $appliedMigrations = $this->getAppliedMigrations();
        $missingMigrations = $this->getMissingMigrations($appliedMigrations);

        // Proceed with necessary steps based on missing migrations
        if (!empty($missingMigrations)) {
            $this->echoOutput("Required Fortify migrations are missing. Proceeding with installation...");
            $this->handleMissingMigrations($missingMigrations);
        } else {
            $this->echoOutput("Fortify migrations are already applied.");
        }

        // Publish Fortify assets, views, config
        $this->publishFortifyAssets();

        // Install Fortify if not already installed
        $this->installFortify();

        // Run 'composer install' to ensure dependencies and autoloader are up-to-date
        $this->runComposerInstall();

        // Clear caches
        $this->clearCaches();

        // Run 'fortify:install' command
        $this->runFortifyInstallCommand();

        $this->echoOutput("Fortify installation process completed.");
    }

    // Get applied migrations from the database
    private function getAppliedMigrations()
    {
        $this->echoOutput("Getting list of applied migrations from the database...");

        return DB::table('migrations')->pluck('migration')->toArray();
    }

    // Check for missing Fortify migrations
    private function getMissingMigrations($appliedMigrations)
    {
        $this->echoOutput("Checking for missing Fortify migrations...");

        return array_filter($this->fortifyMigrationSuffixes, function ($suffix) use ($appliedMigrations) {
            $isMissing = true;
            $this->echoOutput("Checking for suffix: '$suffix'");

            foreach ($appliedMigrations as $migration) {
                $this->echoOutput("Processing migration filename: $migration");

                $migrationName = $this->extractMigrationName($migration);

                if (strpos($migrationName, $suffix) !== false) {
                    $this->echoOutput("Match found for suffix '$suffix' in migration '$migration'");
                    $isMissing = false;
                    break;
                }
            }

            return $isMissing;
        });
    }

    // Extract migration name after removing timestamp and extension
    private function extractMigrationName($migration)
    {
        $filename = basename($migration);
        $migrationName = substr($filename, 17); // Remove timestamp (assumed length)
        return str_replace('.php', '', ltrim($migrationName, '_')); // Remove extension and trim underscores
    }

    // Handle missing migrations
    private function handleMissingMigrations($missingMigrations)
    {
        $this->echoOutput("Deleting Fortify migration files...");
        $this->deleteFortifyMigrationFiles();

        // Reset migrations and reapply them
        $this->resetMigrations();
        $this->runMigrations();
    }

    // Delete only Fortify-related migration files
    private function deleteFortifyMigrationFiles()
    {
        $files = File::files($this->migrationPath);

        foreach ($files as $file) {
            foreach ($this->fortifyMigrationSuffixes as $suffix) {
                if (strpos($file->getFilename(), $suffix) !== false) {
                    $this->echoOutput("Deleting file: " . $file->getFilename());
                    File::delete($file);
                }
            }
        }
    }

    // Reset migrations
    private function resetMigrations()
    {
        $this->echoOutput("Resetting migrations...");
        Artisan::call('migrate:reset');
        $this->echoOutput("Migrations have been reset.");
    }

    // Run migrations again
    private function runMigrations()
    {
        $this->echoOutput("Running migrations...");
        Artisan::call('migrate');
        $this->echoOutput("Migrations reapplied.");
    }

    // Publish Fortify assets, views, and config
    private function publishFortifyAssets()
    {
        $this->echoOutput("Publishing Fortify assets, views, and config...");
        Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'config']);
        Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'views']);
        Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'assets']);
    }

    // Install Fortify if it's not already installed
    private function installFortify()
    {
        $this->echoOutput("Checking if Fortify is installed via Composer...");

        exec('composer show laravel/fortify', $composerOutput, $status);

        if ($status !== 0) {
            $this->echoOutput("Fortify not installed. Installing via Composer...");
            exec('composer require laravel/fortify', $composerOutput, $status);
            if ($status !== 0) {
                $this->echoError("Error installing Fortify.", $composerOutput);
            } else {
                $this->echoOutput("Fortify installed successfully.");
            }
        } else {
            $this->echoOutput("Fortify is already installed.");
        }
    }

    // Run 'composer install' to ensure dependencies are up-to-date
    private function runComposerInstall()
    {
        $this->echoOutput("Running 'composer install'...");

        exec('composer install', $composerOutput, $status);

        if ($status !== 0) {
            $this->echoWarning("'composer install' failed.", $composerOutput);
        } else {
            $this->echoOutput("'composer install' completed.");
        }
    }

    // Clear application caches
    private function clearCaches()
    {
        $this->echoOutput("Clearing caches...");
        Artisan::call('optimize:clear');
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        $this->echoOutput("Caches cleared.");
    }

    // Run 'fortify:install' command
    private function runFortifyInstallCommand()
    {
        $this->echoOutput("Running 'fortify:install'...");

        $cmd = PHP_BINARY . ' artisan fortify:install --ansi';
        exec($cmd, $output, $status);

        if ($status !== 0) {
            $this->echoError("Error running 'fortify:install'.", $output);
        } else {
            $this->echoOutput("'fortify:install' executed successfully.");
            $this->echoOutput(implode("\n", $output));
        }
    }

    // Output messages to the console
    private function echoOutput($message)
    {
        echo $message . "\n";
    }

    // Output warning messages
    private function echoWarning($message, $output)
    {
        echo "Warning: $message\n" . implode("\n", $output);
    }

    // Output error messages and exit
    private function echoError($message, $output)
    {
        echo "Error: $message\n" . implode("\n", $output);
        exit(1);
    }
}

// Create an instance of the installer and run it
$installer = new FortifyInstaller();
$installer->install();
