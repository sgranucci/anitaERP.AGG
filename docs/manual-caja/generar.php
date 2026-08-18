<?php

/**
 * Genera Manual de Usuario Módulo Caja (Word + PDF) con capturas.
 * Ejecutar: php docs/manual-caja/generar.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require dirname(__DIR__).'/manuales/generar_manual_base.php';

use App\Services\Caja\ManualCajaService;

generarManualDocumento([
    'service' => ManualCajaService::class,
    'config' => 'manual_caja',
    'img_dir' => 'docs/manual-caja/img',
    'out_dir' => __DIR__,
    'base_name' => 'Manual_Usuario_AnitaERP_Modulo_Caja',
    'css' => dirname(__DIR__).'/manual-contable/estilos-pdf.css',
]);
