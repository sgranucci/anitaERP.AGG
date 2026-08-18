<?php

/**
 * Genera Manual de Usuario Stock gastronómico / fórmulas / insumos (Word + PDF).
 * Ejecutar: php docs/manual-stock-gastronomia/generar.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require dirname(__DIR__).'/manuales/generar_manual_base.php';

generarManualDocumento([
    'service' => App\Services\Stock\ManualStockGastronomiaService::class,
    'config' => 'manual_stock_gastronomia',
    'img_dir' => 'docs/manual-stock-gastronomia/img',
    'out_dir' => __DIR__,
    'base_name' => 'Manual_Usuario_AnitaERP_Stock_Gastronomia',
]);
