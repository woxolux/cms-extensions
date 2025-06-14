<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InstallExtension extends Command
{
    protected $signature = 'random-cms-extension:install {name}';
    protected $description = 'Install extension from cms-extensions monorepo';

    public function handle()
    {
        $cmsName = 'RandomCMS';
        $name = strtolower($this->argument('name'));

        if ($name === 'authentication') {
            $this->info("Installing {$cmsName} authentication extension...");

            // Step 1: Install Fortify via Composer (if not installed already)
            $this->info("Installing Fortify via Composer...");
            exec('composer require laravel/fortify', $output, $status);

            if ($status !== 0) {
                $this->error("Error: Fortify installation failed via Composer.");
                echo implode("\n", $output);
                return 1;
            }

            $this->info("Fortify installed successfully via Composer.");

            // Step 2: Check if migration files already exist to avoid re-creating them
            $migrationFile = database_path('migrations/2020_12_20_123457_add_two_factor_columns.php'); // Replace with actual migration filename

            if (File::exists($migrationFile)) {
                $this->info("Migration file already exists. Skipping migration creation...");
            } else {
                // Step 3: Run fortify:install only if migrations do not exist
                $this->info("Running fortify:install...");
                try {
                    Artisan::call('fortify:install');
                    $this->info("Fortify installed successfully.");
                } catch (\Exception $e) {
                    $this->error("Error running fortify:install: " . $e->getMessage());
                    return 1;
                }
            }

            // Step 4: Register Fortify service provider in config/app.php
            $this->info("Registering Fortify service provider...");
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
                    $this->info("Fortify service provider registered in config/app.php.");
                } else {
                    $this->info("Fortify service provider is already registered.");
                }
            } else {
                $this->error("Could not find config/app.php. Please ensure it's in the right location.");
                return 1;
            }

            // Step 5: Publish Fortify assets, views, and config
            $this->info("Publishing Fortify assets, views, and config...");
            Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'config']);
            Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'views']);
            Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'assets']);

            $this->info("Fortify installation process completed successfully.");
            return 0;
        }

        $this->error("Invalid extension name provided.");
        return 1;
    }
}
