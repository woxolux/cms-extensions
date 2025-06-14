<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

class InstallAuthentication extends Command
{
    protected $signature = 'random-cms-extension:install Authentication';
    protected $description = 'Install Laravel Fortify and custom authentication views';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Starting installation of Authentication extension...');

        // Step 1: Install Laravel Fortify via Composer
        $this->info('Installing Laravel Fortify...');
        $this->installFortify();

        // Step 2: Install Fortify by running its install command
        $this->info('Running Fortify installation...');
        $this->runArtisanCommand('fortify:install');

        // Step 3: Run migrations
        $this->info('Running database migrations...');
        $this->runArtisanCommand('migrate');

        // Step 4: Copy authentication views
        $this->info('Copying custom authentication views...');
        $this->copyViews();

        // Final success message
        $this->info('Authentication extension installed successfully!');
    }

    /**
     * Install Fortify via Composer
     */
    protected function installFortify()
    {
        $exitCode = $this->runComposerCommand('composer require laravel/fortify');
        if ($exitCode !== 0) {
            $this->error('Error installing Laravel Fortify. Please check your Composer configuration.');
            exit(1);
        }
    }

    /**
     * Run an Artisan command programmatically.
     *
     * @param string $command
     * @return int
     */
    protected function runArtisanCommand($command)
    {
        $this->info("Running artisan command: {$command}");
        $exitCode = \Artisan::call($command);
        if ($exitCode !== 0) {
            $this->error("Failed to execute artisan command: {$command}");
            exit(1);
        }
    }

    /**
     * Run a Composer command programmatically.
     *
     * @param string $command
     * @return int
     */
    protected function runComposerCommand($command)
    {
        $this->info("Running composer command: {$command}");
        $exitCode = shell_exec($command);
        if ($exitCode !== 0) {
            $this->error("Failed to execute composer command: {$command}");
            exit(1);
        }
        return $exitCode;
    }

    /**
     * Copy custom authentication views into the correct directory.
     */
    protected function copyViews()
    {
        $viewsSource = base_path('extensions/authentication/resources/views');
        $viewsTarget = resource_path('views/authentication');  // Ensure views target directory exists

        if (File::exists($viewsSource)) {
            File::ensureDirectoryExists($viewsTarget);
            File::copyDirectory($viewsSource, $viewsTarget);
            $this->info("Custom authentication views copied to {$viewsTarget}");
        } else {
            $this->error("Custom authentication views not found in {$viewsSource}. Please ensure the files exist.");
        }
    }
}
