<?php

$dir = new RecursiveDirectoryIterator(__DIR__ . '/../app/Modules');
$iterator = new RecursiveIteratorIterator($dir);

foreach ($iterator as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;
    
    $path = $file->getPathname();
    $content = file_get_contents($path);
    
    if (!str_contains($content, 'extends Model')) continue;

    // 1. Remove all internal trait usages
    $content = preg_replace('/^\s*use\s+BelongsToTenant;\s*$/m', '', $content);
    $content = preg_replace('/^\s*use\s+HasFiscalYear;\s*$/m', '', $content);
    $content = preg_replace('/^\s*use\s+SoftDeletes;\s*$/m', '', $content);
    $content = preg_replace('/^\s*use\s+HasFactory;\s*$/m', '', $content);
    $content = preg_replace('/^\s*use\s+SoftDeletes,\s+HasFiscalYear;\s*$/m', '', $content);

    // 2. Ensure imports exist
    if (!str_contains($content, 'use App\Core\Traits\BelongsToTenant;')) {
        $content = str_replace('namespace ', "use App\Core\Traits\BelongsToTenant;\nnamespace ", $content);
    }
    if (!str_contains($content, 'use App\Core\Traits\HasFiscalYear;')) {
        $content = str_replace('namespace ', "use App\Core\Traits\HasFiscalYear;\nnamespace ", $content);
    }

    // 3. Re-inject traits at class start
    if (preg_match('/class\s+\w+.*\s+\{/', $content, $matches, PREG_OFFSET_CAPTURE)) {
        $classOpenPos = $matches[0][1] + strlen($matches[0][0]);
        
        $traits = ['BelongsToTenant'];
        if (preg_match('/\/(Purchase|Sales|Accounting|Payments)\//i', $path)) {
            $traits[] = 'HasFiscalYear';
        }
        if (str_contains($content, 'use Illuminate\Database\Eloquent\SoftDeletes;')) {
            $traits[] = 'SoftDeletes';
        }
        if (str_contains($content, 'use Illuminate\Database\Eloquent\Factories\HasFactory;')) {
            $traits[] = 'HasFactory';
        }
        
        $traitStr = "\n    use " . implode(', ', array_unique($traits)) . ";\n";
        $content = substr_replace($content, $traitStr, $classOpenPos, 0);
    }
    
    // Fix the namespace/import order
    $content = str_replace("use App\Core\Traits\BelongsToTenant;\nuse App\Core\Traits\HasFiscalYear;\nnamespace ", "namespace ", $content);
    $content = preg_replace('/namespace\s+([^;]+);/', "namespace $1;\n\nuse App\Core\Traits\BelongsToTenant;\nuse App\Core\Traits\HasFiscalYear;", $content);

    // Final dedupe of imports
    $lines = explode("\n", $content);
    $lines = array_unique($lines); // Risky, but let's see
    // Actually, don't use array_unique on the whole file.
    
    file_put_contents($path, $content);
    echo "Cleaned: $path\n";
}
