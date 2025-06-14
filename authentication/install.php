<?php

// Check if the class already exists before declaring it
if (!class_exists('App\Console\Commands\InstallExtension')) {
    class InstallExtension extends \Illuminate\Console\Command
    {
        protected $signature = 'random-cms-extension:install {name}';
        protected $description = 'Install extension from cms-extensions monorepo';

        public function handle()
        {
            $cmsName = env('CMS_NAME', 'CMS');
            $name = strtolower($this->argument('name'));
            $githubName = env('CMS_GITHUB_NAME');
            $extensionFolder = env('CMS_EXTENSION_FOLDER', 'cms-extensions');

            if (empty($githubName)) {
                $this->error('CMS_GITHUB_NAME is not set in your .env file. Please add it!');
                return 1;
            }

            $this->info("Launching the installation process for the {$cmsName} extension '{$name}'.");

            // Define paths
            $repoZipUrl = "https://github.com/{$githubName}/{$extensionFolder}/archive/refs/heads/main.zip";
            $extensionsDir = storage_path('app/private/extensions');  // Path to store extensions
            $tempZipPath = $extensionsDir . '/cms-extensions-main.zip';
            $tempExtractPath = $extensionsDir . "/{$name}";  // Path to extract the extension

            // Ensure the 'private/extensions' directory exists
            if (!File::exists($extensionsDir)) {
                File::makeDirectory($extensionsDir, 0777, true); // Create 'extensions' directory if not exists
            }

            // Download the ZIP content
            $zipContent = $this->downloadZip($repoZipUrl);

            if ($zipContent === false) {
                $this->error("Failed to download the ZIP file from the repository. Please check the GitHub URL or the repository's availability.");
                return 1;
            }

            // Save the ZIP file to the extensions folder
            file_put_contents($tempZipPath, $zipContent);

            // Initialize ZipArchive to extract the ZIP file
            $zip = new ZipArchive;
            if ($zip->open($tempZipPath) === TRUE) {
                // Delete the previous extracted contents (if any) and create a fresh folder
                if (File::exists($tempExtractPath)) {
                    File::deleteDirectory($tempExtractPath);
                }
                File::makeDirectory($tempExtractPath);

                // Extract the ZIP file content to the 'extensions/{name}' folder
                $zip->extractTo($tempExtractPath);
                $zip->close();

                $this->info("{$cmsName} extension '{$name}' successfully downloaded and extracted.");
                $this->info("Installing {$cmsName} extension '{$name}'...");

                // Define path to the extracted extension folder
                $extractedExtensionPath = $tempExtractPath . "/{$extensionFolder}-main/{$name}";
                if (!File::exists($extractedExtensionPath)) {
                    $this->error("Extension folder '{$name}' not found in the repository.");
                    return 1;
                }

                // Define the final installation path for the extension inside 'private/extensions'
                $installPath = storage_path("app/private/extensions/{$name}");
                File::ensureDirectoryExists($installPath);

                // Copy directories and files from extracted extension to the installation path
                $this->copyFiles($extractedExtensionPath, $installPath, $name);

                // Run the install script if it exists
                $installScriptPath = $extractedExtensionPath . '/install.php';
                if (file_exists($installScriptPath)) {
                    $this->info("Running install.php script for '{$name}' extension...");
                    include $installScriptPath;
                }

                // Delete the extracted files and temporary ZIP file after installation
                File::deleteDirectory($tempExtractPath);
                unlink($tempZipPath);

                // Clean up the 'extensions' folder inside 'private' if necessary
                $this->cleanupExtensionsFolder();

                $this->info("{$cmsName} extension '{$name}' installed successfully.");
                return 0;
            } else {
                $this->error("Failed to install extension.");
                return 1;
            }
        }

        private function downloadZip($url)
        {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                $this->error("CURL Error: " . curl_error($ch));
                curl_close($ch);
                return false;
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode != 200) {
                $this->error("Failed to fetch the repository. HTTP Status Code: " . $httpCode);
                return false;
            }

            return $response;
        }

        private function copyFiles($source, $destination, $name)
        {
            // Copy directories
            $allDirs = File::directories($source);
            foreach ($allDirs as $dir) {
                if (basename($dir) !== 'resources') {
                    File::copyDirectory($dir, $destination . '/' . basename($dir));
                }
            }

            // Copy files
            $allFiles = File::files($source);
            foreach ($allFiles as $file) {
                File::copy($file->getPathname(), $destination . '/' . $file->getFilename());
            }

            // Copy view files if they exist
            $extensionViewsPath = $source . '/resources/views';
            if (File::exists($extensionViewsPath)) {
                $this->info("Copying view files for extension '{$name}' into resources/views...");
                $viewsTargetPath = resource_path('views');
                File::ensureDirectoryExists($viewsTargetPath);
                File::copyDirectory($extensionViewsPath, $viewsTargetPath);
            }

            // Copy controller files if they exist
            $extensionControllersPath = $source . '/app/Http/Controllers';
            if (File::exists($extensionControllersPath)) {
                $this->info("Copying controllers for extension '{$name}'...");
                File::ensureDirectoryExists(app_path('Http/Controllers'));
                File::copyDirectory($extensionControllersPath, app_path('Http/Controllers'));
            }
        }

        private function cleanupExtensionsFolder()
        {
            $extensionsPath = storage_path('app/private/extensions');

            // Always delete the 'extensions' folder if needed
            if (File::exists($extensionsPath)) {
                $this->info("Deleting temporary extension files...");

                // Delete the 'extensions' folder regardless of whether it's empty or not
                File::deleteDirectory($extensionsPath);

                $this->info("Temporary extension files have been deleted successfully.");
            }
        }
    }
}
