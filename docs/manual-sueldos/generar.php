<?php

/**
 * Genera Manual de Usuario Módulo Sueldos (Word + PDF) con capturas.
 * Ejecutar: php docs/manual-sueldos/generar.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require dirname(__DIR__).'/manuales/generar_manual_base.php';

use App\Services\Sueldos\ManualSueldosService;

generarManualDocumento([
    'service' => ManualSueldosService::class,
    'config' => 'manual_sueldos',
    'img_dir' => 'docs/manual-sueldos/img',
    'out_dir' => __DIR__,
    'base_name' => 'Manual_Usuario_AnitaERP_Modulo_Sueldos',
    'css' => dirname(__DIR__).'/manual-contable/estilos-pdf.css',
]);
