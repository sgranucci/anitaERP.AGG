<?php

/**
 * Genera Manual de Usuario Tesorería Crown (Word + PDF) con capturas.
 * Solo aplica en instalación EMPRESA=CROWN.
 * Ejecutar: php docs/manual-tesoreria-crown/generar.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require dirname(__DIR__).'/manuales/generar_manual_base.php';

use App\Services\Caja\ManualTesoreriaCrownService;

generarManualDocumento([
    'service' => ManualTesoreriaCrownService::class,
    'config' => 'manual_tesoreria_crown',
    'img_dir' => 'docs/manual-tesoreria-crown/img',
    'out_dir' => __DIR__,
    'base_name' => 'Manual_Usuario_AnitaERP_Tesoreria_Crown',
    'css' => dirname(__DIR__).'/manual-contable/estilos-pdf.css',
]);
