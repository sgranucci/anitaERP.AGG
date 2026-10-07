<?php

/**
 * Genera el manual de logística (Word, PDF y HTML).
 * Ejecutar: php docs/manual-logistica/generar.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require dirname(__DIR__).'/manuales/generar_manual_base.php';

generarManualDocumento([
    'service' => App\Services\Logistica\ManualLogisticaService::class,
    'config' => 'manual_logistica',
    'img_dir' => 'docs/manual-logistica/img',
    'out_dir' => __DIR__,
    'base_name' => 'Manual_Usuario_AnitaERP_Logistica',
    'css' => dirname(__DIR__).'/manual-suscripciones/estilos-pdf.css',
]);
