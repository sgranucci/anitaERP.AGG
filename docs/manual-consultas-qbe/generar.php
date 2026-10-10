<?php

/**
 * Manual de consultas QBE (pago a proveedores). Texto y tablas, sin capturas.
 * Ejecutar: php docs/manual-consultas-qbe/generar.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require dirname(__DIR__).'/manuales/generar_manual_base.php';

use App\Services\Compras\ManualConsultasQbeService;

generarManualDocumento([
    'service' => ManualConsultasQbeService::class,
    'config' => 'manual_consultas_qbe',
    'img_dir' => 'docs/manual-consultas-qbe/img',
    'out_dir' => __DIR__,
    'base_name' => 'Manual_Usuario_AnitaERP_Consultas_Pagos',
    'css' => dirname(__DIR__).'/manual-contable/estilos-pdf.css',
]);
