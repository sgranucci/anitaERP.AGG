<?php

/**
 * Genera la guía rápida de liquidación (Word + PDF + preview HTML).
 * Ejecutar: php docs/guia-rapida-sueldos/generar.php
 * Capturas: php artisan manual:capturar-guia-rapida-sueldos
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require dirname(__DIR__).'/manuales/generar_manual_base.php';

generarManualDocumento([
    'service' => \App\Services\Sueldos\ManualGuiaRapidaSueldosService::class,
    'config' => 'manual_guia_rapida_sueldos',
    'img_dir' => 'docs/guia-rapida-sueldos/img',
    'out_dir' => __DIR__,
    'base_name' => 'Guia_Rapida_Liquidacion_Sueldos',
    'css' => dirname(__DIR__).'/manual-contable/estilos-pdf.css',
]);
