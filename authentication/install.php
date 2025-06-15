<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class FortifyInstaller
{
    // Define the specific migration class names for Fortify that we need to check
    private $fortifyMigrationClasses = [
        'AddTwoFactorColumnsToUsersTable', // Migration class name (without timestamp prefix)
    ];

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

    // Check for missing Fortify migrations based on class names
    private function getMissingMigrations($appliedMigrations)
    {
        $this->echoOutput("Checking for missing Fortify migrations...");

        return array_filter($this->fortifyMigrationClasses, function ($class) use ($appliedMigrations) {
            // Check if the migration class is already applied (i.e., it exists in the applied migrations list)
            return !in_array($class, $appliedMigrations);
        });
    }

    // Handle missing migrations
    private function handleMissingMigrations($missingMigrations)
    {
        $this->echoOutput("Resetting migrations and reappling missing ones...");

        // Reset migrations and reapply them
        $this->resetMigrations();
        $this->runMigrations();
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
