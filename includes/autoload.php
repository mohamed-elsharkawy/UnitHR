<?php
// UnitHR Autoloader for external libraries

// PSR SimpleCache autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Psr\\SimpleCache\\';
    $baseDir = __DIR__ . '/../assets/psr/simple-cache/src/';
    
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// PhpSpreadsheet autoloader
spl_autoload_register(function ($class) {
    $prefix = 'PhpOffice\\PhpSpreadsheet\\';
    $baseDir = __DIR__ . '/../assets/PhpSpreadsheet/';
    
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// DomPDF autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Dompdf\\';
    
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, strlen($prefix));
    
    // Check src/ directory first
    $file = __DIR__ . '/../assets/dompdf/dompdf/src/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
        return;
    }
    
    // Check lib/ directory
    $file = __DIR__ . '/../assets/dompdf/dompdf/lib/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
        return;
    }
});

// php-font-lib autoloader (DomPDF dependency)
spl_autoload_register(function ($class) {
    $prefix = 'FontLib\\';
    $baseDir = __DIR__ . '/../assets/dompdf/php-font-lib/src/FontLib/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// php-svg-lib autoloader (DomPDF dependency)
spl_autoload_register(function ($class) {
    $prefix = 'Svg\\';
    $baseDir = __DIR__ . '/../assets/dompdf/php-svg-lib/src/Svg/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// ZipStream autoloader (PhpSpreadsheet XLSX writer dependency)
spl_autoload_register(function ($class) {
    $prefix = 'ZipStream\\';
    $baseDir = __DIR__ . '/../assets/zipstream/src/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Composer Pcre autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Composer\\Pcre\\';
    $baseDir = __DIR__ . '/../assets/composer-pcre/src/';
    
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Masterminds HTML5 autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Masterminds\\';
    $baseDir = __DIR__ . '/../assets/masterminds/html5/src/';
    
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});
?>
