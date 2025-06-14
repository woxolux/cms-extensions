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
Artisan::call
