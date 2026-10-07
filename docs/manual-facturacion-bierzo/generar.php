<?php

/**
 * Manual de facturación, remitos, COT y certificados (El Bierzo).
 * Ejecutar: php docs/manual-facturacion-bierzo/generar.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require dirname(__DIR__).'/manuales/generar_manual_base.php';

generarManualDocumento([
    'service' => App\Services\Ventas\ManualFacturacionBierzoService::class,
    'config' => 'manual_facturacion_bierzo',
    'img_dir' => 'docs/manual-facturacion-bierzo/img',
    'out_dir' => __DIR__,
    'base_name' => 'Manual_Usuario_AnitaERP_Facturacion_Remitos_COT_EL_BIERZO',
    'css' => dirname(__DIR__).'/manual-contable/estilos-pdf.css',
]);
