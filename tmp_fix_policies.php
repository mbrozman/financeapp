<?php
$dir = __DIR__ . '/app/Policies';
$files = scandir($dir);

foreach ($files as $file) {
    if (str_ends_with($file, '.php')) {
        $path = "$dir/$file";
        $content = file_get_contents($path);
        
        // Replace !$user->isSuperAdmin() with $user->isSuperAdmin()
        $newContent = str_replace('!$user->isSuperAdmin()', '$user->isSuperAdmin()', $content);
        
        if ($content !== $newContent) {
            file_put_contents($path, $newContent);
            echo "Fixed $file\n";
        }
    }
}
