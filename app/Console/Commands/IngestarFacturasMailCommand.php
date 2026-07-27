<?php

namespace App\Console\Commands;

use App\Services\Compras\ComprobanteProveedorMailIngestaService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Ingesta de facturas de proveedor desde la casilla de correo
 * (config/precarga_comprobante_mail.php). Encola un job por mensaje
 * con adjuntos PDF; el pipeline PDF+IA arma la precarga.
 */
class IngestarFacturasMailCommand extends Command
{
    protected $signature = 'compras:ingestar-facturas-mail
        {--limite= : Máximo de mensajes a leer en esta corrida}
        {--dry-run : Lista mensajes y OC detectadas sin encolar ni marcar nada}';

    protected $description = 'Lee la casilla de facturas de proveedor y encola la creación de precargas vía PDF+IA';

    public function handle(ComprobanteProveedorMailIngestaService $ingesta): int
    {
        $limite = $this->option('limite') !== null ? max(1, (int) $this->option('limite')) : null;
        $dryRun = (bool) $this->option('dry-run');

        try {
            $resumen = $ingesta->procesarCasilla($limite, $dryRun);
        } catch (Throwable $e) {
            $this->error('Error leyendo la casilla: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Mensajes: %d — encolados: %d, ya procesados: %d, ignorados sin PDF: %d%s',
            $resumen['mensajes'],
            $resumen['encolados'],
            $resumen['ya_procesados'],
            $resumen['ignorados'],
            $dryRun ? ' [dry-run: sin cambios]' : '',
        ));

        foreach ($resumen['detalle'] as $detalle) {
            $ocs = [];
            foreach ((array) ($detalle['adjuntos'] ?? []) as $adjunto) {
                $ocs[] = sprintf(
                    '%s → OC %s%s',
                    $adjunto['nombre'],
                    $adjunto['numero_oc'] ?? 'sin detectar',
                    ! empty($adjunto['ya_procesado']) ? ' (ya procesado)' : '',
                );
            }
            $this->line(sprintf(
                '  [%s] %s | %s | %s',
                $detalle['accion'],
                $detalle['remitente'],
                $detalle['asunto'],
                $ocs !== [] ? implode(' ; ', $ocs) : 'sin PDF',
            ));
        }

        return self::SUCCESS;
    }
}
