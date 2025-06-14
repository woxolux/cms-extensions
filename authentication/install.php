<?php

use Illuminate\Support\Facades\Artisan;

echo "Installing Authentication Extension...\n";

// Check if Fortify is installed
if (!class_exists(\Laravel\Fortify\FortifyServiceProvider::class)) {
    echo "Installing Fortify...\n";
    
    // Run composer command to install Fortify via shell_exec()
    $composerInstall = shell_exec('composer require laravel/fortify');
    
    // Check for errors in composer install
    if ($composerInstall === null) {
        echo "Error installing Fortify. Please ensure Composer is installed.\n";
        exit(1);
    }

    echo "Fortify installed successfully.\n";
}

// Run Fortify install command
echo "Running Fortify:install...\n";
Artisan::call('fortify:install');

// Run migrations for the authentication extension
echo "Running migrations...\n";
Artisan::call('migrate');

// Publish Fortify views if needed
echo "Publishing Fortify views...\n";
Artisan::call('vendor:publish', [
    '--provider' => 'Laravel\Fortify\FortifyServiceProvider',
    '--tag' => 'views',
]);

echo "Authentication installation completed successfully.\n";
