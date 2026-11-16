<?php

/**
 * Genera el manual de avisos por módulo (Word, PDF y HTML).
 * Ejecutar: php docs/manual-modulo-aviso/generar.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require dirname(__DIR__).'/manuales/generar_manual_base.php';

generarManualDocumento([
    'service' => App\Services\Configuracion\ManualModuloAvisoService::class,
    'config' => 'manual_modulo_aviso',
    'img_dir' => 'docs/manual-modulo-aviso/img',
    'out_dir' => __DIR__,
    'base_name' => 'Manual_Usuario_AnitaERP_Avisos_Modulo',
    'css' => dirname(__DIR__).'/manual-suscripciones/estilos-pdf.css',
]);
