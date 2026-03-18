<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HideWidgetsFromSuperadmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:hide-widgets-from-superadmin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function handle()
    {
        $widgetsPath = app_path('Filament/Widgets');
        $files = \Illuminate\Support\Facades\File::allFiles($widgetsPath);
        
        foreach ($files as $file) {
            $content = file_get_contents($file->getRealPath());
            
            if (!str_contains($content, 'public static function canView(): bool')) {
                // Skontrolovať či je to class Widget
                if (preg_match('/class ([A-Za-z]+) extends/', $content, $matches)) {
                    $className = $matches[1];
                    
                    // Nahradenie class definície
                    $replacement = "class {$className} extends \\$2\n{\n    public static function canView(): bool\n    {\n        return !auth()->user() || !auth()->user()->isSuperAdmin();\n    }\n";
                    $newContent = preg_replace('/class '.$className.' extends ([a-zA-Z0-9_\\\\]+)\s*\{/', $replacement, $content);
                    
                    file_put_contents($file->getRealPath(), $newContent);
                    $this->info('Updated widget: ' . $className);
                }
            } else {
                 $this->info('Already updated: ' . $file->getFilename());
            }
        }
        
        // Aktualizovať aj Pages
        $pagesPath = app_path('Filament/Pages');
        $files = \Illuminate\Support\Facades\File::allFiles($pagesPath);
        foreach ($files as $file) {
            $content = file_get_contents($file->getRealPath());
            if (!str_contains($content, 'public static function canAccess(): bool')) {
                if (preg_match('/class ([A-Za-z]+) extends/', $content, $matches)) {
                    $className = $matches[1];
                     $replacement = "class {$className} extends \\$2\n{\n    public static function canAccess(): bool\n    {\n        return !auth()->user() || !auth()->user()->isSuperAdmin();\n    }\n";
                    $newContent = preg_replace('/class '.$className.' extends ([a-zA-Z0-9_\\\\]+)\s*\{/', $replacement, $content);
                    file_put_contents($file->getRealPath(), $newContent);
                    $this->info('Updated page: ' . $className);
                }
            }
        }
    }
}
