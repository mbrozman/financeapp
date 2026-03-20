<?php
$dir = __DIR__ . '/app/Policies';
$files = scandir($dir);

foreach ($files as $file) {
    if (str_ends_with($file, '.php')) {
        $path = "$dir/$file";
        $content = file_get_contents($path);
        
        // Replacement: replace existing isSuperAdmin checks with (isSuperAdmin || isAdmin)
        // We handle both the buggy !$user->isSuperAdmin() and the previous "fix" $user->isSuperAdmin()
        
        $newContent = str_replace('!$user->isSuperAdmin()', '($user->isSuperAdmin() || $user->isAdmin())', $content);
        $newContent = str_replace('return $user->isSuperAdmin();', 'return $user->isSuperAdmin() || $user->isAdmin();', $newContent);
        
        if ($content !== $newContent) {
            file_put_contents($path, $newContent);
            echo "Fixed $file\n";
        }
    }
}
