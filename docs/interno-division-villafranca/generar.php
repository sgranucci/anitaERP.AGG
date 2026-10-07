<?php

/**
 * Ejemplar completo para Windows, en el NAS.
 * Ejecutar: php docs/interno-division-villafranca/generar.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require dirname(__DIR__).'/manuales/generar_manual_base.php';

$outDir = '/NAS/bierzo/manuales';
if (! is_dir($outDir) && ! mkdir($outDir, 0777, true) && ! is_dir($outDir)) {
    fwrite(STDERR, "No se pudo crear {$outDir}\n");
    exit(1);
}

generarManualDocumento([
    'service' => App\Services\Ventas\ManualDivisionVillafrancaService::class,
    'config' => 'manual_division_villafranca',
    'img_dir' => 'docs/manual-facturacion-bierzo/img',
    'out_dir' => $outDir,
    'base_name' => 'Manual_Completo_Facturacion_Division_Villafranca_EL_BIERZO',
    'css' => dirname(__DIR__).'/manual-contable/estilos-pdf.css',
]);
