<?php

namespace App\Console\Commands;

use App\Models\Ventas\ComprobanteImpresionLog;
use App\Services\Ventas\ComprobanteImpresionSesionService;
use Illuminate\Console\Command;

class VentasReenviarImpresionPendiente extends Command
{
    protected $signature = 'ventas:reenviar-impresion-pendiente
        {--dry-run : Solo lista los pendientes, no reenvía}
        {--ejecutar : Reenvía copias ARCHIVO pendientes o con error}';

    protected $description = 'Reintenta copias de comprobante archivadas en NAS (nunca papel)';

    public function handle(ComprobanteImpresionSesionService $sesionService): int
    {
        $max = max(1, (int) config('impresion_comprobante.cron_max_intentos', 8));
        $logs = ComprobanteImpresionLog::query()
            ->where('medio', ComprobanteImpresionLog::MEDIO_ARCHIVO)
            ->whereIn('estado', [ComprobanteImpresionLog::ESTADO_PENDIENTE, ComprobanteImpresionLog::ESTADO_ERROR])
            ->where('intentos', '<', $max)
            ->orderBy('id')
            ->get();

        $this->info('Pendientes ARCHIVO: '.$logs->count());
        foreach ($logs as $log) {
            $this->line(sprintf(
                '#%d %s %s copia %s intentos=%d %s',
                $log->id,
                $log->formulario,
                $log->documento_id,
                $log->copia_codigo,
                $log->intentos,
                $log->destino_path
            ));
        }

        if ($this->option('dry-run') || ! $this->option('ejecutar')) {
            $this->comment('Dry-run: no se reenvió nada. Use --ejecutar para persistir reintentos.');

            return self::SUCCESS;
        }

        $ok = 0;
        $error = 0;
        foreach ($logs as $log) {
            $resultado = $sesionService->reintentarLog($log);
            if (! empty($resultado['ok'])) {
                $ok++;
            } else {
                $error++;
            }
        }
        $this->info("Reintentos OK: {$ok} — error: {$error}");

        return self::SUCCESS;
    }
}
