<?php

use Illuminate\Support\Facades\Artisan;

echo "Installing Authentication Extension...\n";

// Install Fortify if not installed already
if (!class_exists(\Laravel\Fortify\FortifyServiceProvider::class)) {
    echo "Installing Fortify...\n";
    Artisan::call('composer require laravel/fortify');
}

// Run Fortify install
echo "Running Fortify:install...\n";
Artisan::call('fortify:install');

// Run migrations for the authentication extension
echo "Running migrations...\n";
Artisan::call('migrate');

// Publish views (if any views need to be published)
echo "Publishing Fortify views...\n";
Artisan::call('vendor:publish', [
    '--provider' => 'Laravel\Fortify\FortifyServiceProvider',
    '--tag' => 'views',
]);

echo "Authentication installation completed successfully.\n";
