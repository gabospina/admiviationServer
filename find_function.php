<?php
// find_function.php - Search for updateCsrfToken function
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "🔍 Searching for updateCsrfToken function...\n";
echo "==========================================\n\n";

function searchForFunction($dir = '.', $functionName = 'updateCsrfToken') {
    $foundIn = [];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'js') {
            $content = file_get_contents($file->getPathname());
            if (strpos($content, $functionName) !== false) {
                $foundIn[] = $file->getPathname();
                echo "✅ FOUND in: " . $file->getPathname() . "\n";
                
                // Show the line where it's found
                $lines = explode("\n", $content);
                foreach ($lines as $lineNumber => $line) {
                    if (strpos($line, $functionName) !== false) {
                        echo "   Line " . ($lineNumber + 1) . ": " . trim($line) . "\n";
                    }
                }
                echo "\n";
            }
        }
    }
    
    return $foundIn;
}

$results = searchForFunction('.', 'updateCsrfToken');

echo "\n📊 SEARCH COMPLETE:\n";
echo "Found in " . count($results) . " file(s)\n";

if (count($results) === 0) {
    echo "\n⚠️  Function not found. Searching for similar patterns...\n";
    
    // Search for similar patterns
    $similarPatterns = [
        'updateCsrfToken',
        'update_csrf_token',
        'csrfToken',
        'csrf_token',
        'new_csrf_token'
    ];
    
    foreach ($similarPatterns as $pattern) {
        echo "\nSearching for: $pattern\n";
        searchForFunction('.', $pattern);
    }
}

echo "</pre>";
?>