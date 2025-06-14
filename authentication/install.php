<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

echo "Running Fortify installation...\n";

// Step 1: Install Fortify via Composer
echo "Installing Fortify via Composer...\n";
exec('composer require laravel/fortify');

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
// Add any additional installation steps you need, such as copying custom views or controllers.
// Example:
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

// Step 6: Inform the user that the installation is complete
echo "Authentication extension installed successfully.\n";
