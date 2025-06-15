<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class FortifyInstaller extends Migration
{
    // Define the specific migration class names for Fortify
    private $fortifyMigrationClasses = [
        'AddTwoFactorColumnsToUsersTable', // Migration class name (without timestamp prefix)
    ];

    public function __construct()
    {
        $this->installFortify();
        $this->handleMigrations();
    }

    /**
     * Install Fortify if it's not already installed via Composer.
     */
    private function installFortify()
    {
        echo "Checking if Fortify is installed via Composer...\n";

        exec('composer show laravel/fortify', $composerOutput, $status);

        if ($status !== 0) {
            echo "Fortify not installed. Installing via Composer...\n";
            exec('composer require laravel/fortify', $composerOutput, $status);
            if ($status !== 0) {
                echo "Error installing Fortify.\n" . implode("\n", $composerOutput);
                exit(1);
            } else {
                echo "Fortify installed successfully.\n";
            }
        } else {
            echo "Fortify is already installed.\n";
        }
    }

    /**
     * Handle missing migrations and reset/reapply them.
     */
    private function handleMigrations()
    {
        echo "Running Fortify installation...\n";

        $appliedMigrations = $this->getAppliedMigrations();
        $missingMigrations = $this->getMissingMigrations($appliedMigrations);

        // If any Fortify migrations are missing, proceed to reapply them
        if (!empty($missingMigrations)) {
            echo "Required Fortify migrations are missing. Proceeding with installation...\n";
            $this->resetMigrations();
            $this->runMigrations();
        } else {
            echo "Fortify migrations are already applied.\n";
        }

        // Publish Fortify assets, views, and config
        echo "Publishing Fortify assets, views, and config...\n";
        Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'config']);
        Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'views']);
        Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'assets']);

        // Run 'composer install' to ensure dependencies and autoloader are up-to-date
        echo "Running 'composer install'...\n";
        exec('composer install', $composerOutput, $status);
        if ($status !== 0) {
            echo "Warning: 'composer install' failed.\n" . implode("\n", $composerOutput);
        } else {
            echo "'composer install' completed.\n";
        }

        // Clear application caches
        echo "Clearing caches...\n";
        Artisan::call('optimize:clear');
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        echo "Caches cleared.\n";

        // Run 'fortify:install' command
        echo "Running 'fortify:install'...\n";
        $cmd = PHP_BINARY . ' artisan fortify:install --ansi';
        exec($cmd, $output, $status);
        if ($status !== 0) {
            echo "Error running 'fortify:install'.\n" . implode("\n", $output);
            exit(1);
        } else {
            echo "'fortify:install' executed successfully.\n";
            echo implode("\n", $output);
        }

        echo "Fortify installation process completed.\n";
    }

    /**
     * Get the list of applied migrations from the database.
     */
    private function getAppliedMigrations()
    {
        echo "Getting list of applied migrations from the database...\n";
        return DB::table('migrations')->pluck('migration')->toArray();
    }

    /**
     * Check for missing migrations by comparing with the applied migrations.
     */
    private function getMissingMigrations($appliedMigrations)
    {
        echo "Checking for missing Fortify migrations...\n";

        return array_filter($this->fortifyMigrationClasses, function ($class) use ($appliedMigrations) {
            return !in_array($class, $appliedMigrations);
        });
    }

    /**
     * Reset all migrations to ensure we can reapply them.
     */
    private function resetMigrations()
    {
        echo "Resetting migrations...\n";
        Artisan::call('migrate:reset');
        echo "Migrations have been reset.\n";
    }

    /**
     * Run the migrations to apply missing ones.
     */
    private function runMigrations()
    {
        echo "Running migrations...\n";
        Artisan::call('migrate');
        echo "Migrations reapplied.\n";
    }

    /**
     * Migration for adding two-factor authentication columns to the users table.
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable();
            }

            if (!Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable();
            }
        });
    }

    /**
     * Rollback the migration by dropping the columns added.
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes']);
        });
    }
}

// Run the FortifyInstaller to start the process
new FortifyInstaller();
