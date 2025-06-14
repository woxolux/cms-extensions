<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

echo "Running Fortify installation...\n";

// Step 1: Install Fortify via Composer
echo "Installing Fortify via Composer...\n";
exec('composer require laravel/fortify', $output, $status);

if ($status !== 0) {
    echo "Error: Fortify installation failed via Composer.\n";
    echo implode("\n", $output);
    exit(1);
} else {
    echo "Fortify installed successfully via Composer.\n";
}

// Step 2: Ensure the Fortify service provider is registered in `config/app.php`
echo "Registering Fortify service provider...\n";
$serviceProvider = "Laravel\\Fortify\\FortifyServiceProvider::class";

// Get the path to the `config/app.php` file
$appConfigPath = base_path('config/app.php');

// Ensure the service provider is registered
if (File::exists($appConfigPath)) {
    $configContents = File::get($appConfigPath);
    if (strpos($configContents, $serviceProvider) === false) {
        // Add the service provider to the `providers` array
        $configContents = preg_replace(
            "/'providers' => \[.*\],/s",
            "'providers' => [\n        $serviceProvider,\n    ],",
            $configContents
        );
        File::put($appConfigPath, $configContents);
        echo "Fortify service provider registered in config/app.php.\n";
    } else {
        echo "Fortify service provider is already registered.\n";
    }
} else {
    echo "Could not find config/app.php. Please ensure it's in the right location.\n";
    exit(1);
}

// Step 3: Publish Fortify assets, views, and config
echo "Publishing Fortify assets, views, and config...\n";
Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'config']);
Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'views']);
Artisan::call('vendor:publish', ['--provider' => 'Laravel\\Fortify\\FortifyServiceProvider', '--tag' => 'assets']);

// Step 4: Run the Fortify installation command
echo "Running fortify:install...\n";
try {
    Artisan::call('fortify:install');
    echo "Fortify installed successfully.\n";
} catch (Exception $e) {
    echo "Error running fortify:install: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 5: Set up views and controllers (custom implementation)
echo "Copying custom view files...\n";

// Define the path for the extension view files (assuming they are in the extension's `resources/views` folder)
$extensionViewsPath = base_path('extensions/authentication/resources/views');
$viewsTargetPath = resource_path('views');

// Ensure the views directory exists
if (File::exists($extensionViewsPath)) {
    File::copyDirectory($extensionViewsPath, $viewsTargetPath);
    echo "Custom view files copied successfully.\n";
} else {
    echo "No custom view files found in the extension folder.\n";
}

// Step 6: Set up controllers (custom implementation)
echo "Copying custom controllers...\n";

// Define the path for the extension controllers (assuming they are in the extension's `app/Http/Controllers` folder)
$extensionControllersPath = base_path('extensions/authentication/app/Http/Controllers');
$controllersTargetPath = app_path('Http/Controllers');

// Ensure the controllers directory exists
if (File::exists($extensionControllersPath)) {
    File::copyDirectory($extensionControllersPath, $controllersTargetPath);
    echo "Custom controllers copied successfully.\n";
} else {
    echo "No custom controllers found in the extension folder.\n";
}

// Step 7: Clean up temporary installation files (if any)
echo "Cleaning up temporary installation files...\n";
// Add any cleanup logic you need here, for example deleting temporary files or directories
$installTempPath = storage_path('app/private/cms-extensions-main');
if (File::exists($installTempPath)) {
    File::deleteDirectory($installTempPath);
    echo "Temporary files cleaned up successfully.\n";
} else {
    echo "No temporary installation files to clean up.\n";
}

// Step 8: Inform the user that the installation is complete
echo "Authentication extension installed successfully.\n";
