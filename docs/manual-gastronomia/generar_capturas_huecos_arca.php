<?php

/**
 * Regenera capturas huecos ARCA vía el motor compartido de mockups.
 * Preferir: php artisan manual:generar-mockups gastronomia
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$result = app(\App\Support\Manuales\ManualMockupGeneradorService::class)->generar('gastronomia');
echo "Generadas: {$result['generadas']}\n";
foreach ($result['errores'] as $e) {
    fwrite(STDERR, $e.PHP_EOL);
}
exit($result['errores'] === [] ? 0 : 1);
