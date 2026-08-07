<?php declare(strict_types=1);

$autoloadCandidates = [
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../../../vendor/autoload.php',
];

foreach ($autoloadCandidates as $autoload) {
    if (\file_exists($autoload)) {
        require $autoload;
        break;
    }
}

if (!\class_exists(\Shopware\Core\Framework\Plugin::class)) {
    throw new \RuntimeException('Shopware vendor autoload.php not found.');
}

spl_autoload_register(static function (string $class): void {
    $map = [
        'WakoPluginAdminToolbar\\Tests\\Unit\\' => __DIR__ . '/',
        'WakoPluginAdminToolbar\\' => __DIR__ . '/../../src/',
    ];

    foreach ($map as $prefix => $basePath) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relativeClass = substr($class, \strlen($prefix));
        $file = $basePath . str_replace('\\', '/', $relativeClass) . '.php';

        if (\file_exists($file)) {
            require $file;
        }

        return;
    }
});
