<?php

/**
 * Genera Manual de Usuario LSD (Word + PDF) con capturas.
 * Ejecutar: php docs/manual-lsd-sueldos/generar.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require dirname(__DIR__).'/manuales/generar_manual_base.php';

use App\Services\Sueldos\ManualLsdSueldosService;

generarManualDocumento([
    'service' => ManualLsdSueldosService::class,
    'config' => 'manual_lsd_sueldos',
    'img_dir' => 'docs/manual-lsd-sueldos/img',
    'out_dir' => __DIR__,
    'base_name' => 'Manual_Usuario_AnitaERP_Libro_Sueldos_Digital',
    'css' => dirname(__DIR__).'/manual-contable/estilos-pdf.css',
]);
