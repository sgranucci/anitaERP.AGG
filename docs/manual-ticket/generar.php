<?php

/**
 * Genera el manual de tickets (Word, PDF y HTML).
 * Ejecutar: php docs/manual-ticket/generar.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require dirname(__DIR__).'/manuales/generar_manual_base.php';

generarManualDocumento([
    'service' => App\Services\Ticket\ManualTicketService::class,
    'config' => 'manual_ticket',
    'img_dir' => 'docs/manual-ticket/img',
    'out_dir' => __DIR__,
    'base_name' => 'Manual_Usuario_AnitaERP_Tickets',
    'css' => dirname(__DIR__).'/manual-suscripciones/estilos-pdf.css',
]);
