<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FixNullSafeAuthorization extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-null-safe-authorization';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fixes null-safe authorization in resources and policies';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $resourcesPath = app_path('Filament/Resources');
        $files = \Illuminate\Support\Facades\File::allFiles($resourcesPath);
        
        // Fix for all other resources
        $targetResource = 'return ! auth()->user()->is_superadmin;';
        $replacementResource = 'return (bool) auth()->user()?->is_superadmin === false;';
        
        foreach ($files as $file) {
            $content = file_get_contents($file->getRealPath());
            
            // Fix for UserResource
            if ($file->getFilename() === 'UserResource.php') {
                $newContent = str_replace(
                    'return auth()->user()->is_superadmin;',
                    'return (bool) auth()->user()?->is_superadmin;',
                    $content
                );
                if ($newContent !== $content) {
                    file_put_contents($file->getRealPath(), $newContent);
                    $this->info('Fixed UserResource');
                }
                continue;
            }

            if (str_contains($content, $targetResource)) {
                $newContent = str_replace($targetResource, $replacementResource, $content);
                file_put_contents($file->getRealPath(), $newContent);
                $this->info('Fixed Resource: ' . $file->getFilename());
            }
        }

        // Fix for Policies
        $policiesPath = app_path('Policies');
        if (is_dir($policiesPath)) {
            $files = \Illuminate\Support\Facades\File::allFiles($policiesPath);
            foreach ($files as $file) {
                $content = file_get_contents($file->getRealPath());
                $newContent = str_replace('!auth()->user()->is_superadmin', '!$user->is_superadmin', $content);
                if ($newContent !== $content) {
                    file_put_contents($file->getRealPath(), $newContent);
                    $this->info('Fixed Policy: ' . $file->getFilename());
                }
            }
        }
    }
}
