<?php

namespace App\Console\Commands;

use App\Services\Configuracion\ArbolReemplazoFirmanteService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Restaura firmantes cuyo reemplazo tenía vence_el y ya pasó el último día inclusive.
 * Programado a las 00:05: cae efectivamente a las 00:00 del día siguiente al tope.
 */
class RestaurarReemplazosFirmanteVencidos extends Command
{
    protected $signature = 'configuracion:restaurar-reemplazos-firmante-vencidos
                            {--fecha= : Fecha de referencia YYYY-MM-DD (default: hoy)}
                            {--dry-run : Solo lista los logs que restauraría}';

    protected $description = 'Restaura titulares de árbol cuyo reemplazo venció (vence_el < hoy)';

    public function __construct(
        private ArbolReemplazoFirmanteService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $fechaOpt = trim((string) $this->option('fecha'));
        $referencia = $fechaOpt !== ''
            ? Carbon::createFromFormat('Y-m-d', $fechaOpt)->startOfDay()
            : Carbon::now();

        if ($this->option('dry-run')) {
            $hoy = $referencia->copy()->startOfDay()->toDateString();
            $pendientes = \Illuminate\Support\Facades\DB::table('arbol_reemplazo_firmante_log')
                ->where('operacion', 'reemplazo')
                ->whereNotNull('vence_el')
                ->whereNull('restaurado_at')
                ->whereDate('vence_el', '<', $hoy)
                ->orderBy('id')
                ->get(['id', 'usuario_origen_id', 'usuario_destino_id', 'vence_el']);

            $this->info('Referencia: '.$referencia->toDateTimeString().' — pendientes: '.$pendientes->count());
            foreach ($pendientes as $row) {
                $this->line(sprintf(
                    '  log #%d titular=%d suplente=%d vence_el=%s',
                    $row->id,
                    $row->usuario_origen_id,
                    $row->usuario_destino_id,
                    $row->vence_el
                ));
            }

            return self::SUCCESS;
        }

        $resultado = $this->service->procesarVencimientos($referencia);
        $this->info(sprintf(
            'Procesados: %d — OK: %d — Errores: %d',
            $resultado['procesados'],
            $resultado['ok'],
            count($resultado['errores'])
        ));
        foreach ($resultado['detalle'] as $item) {
            $estado = ($item['ok'] ?? false) ? 'OK' : 'ERROR';
            $this->line(sprintf(
                '  [%s] log #%d titular=%d vence_el=%s — %s',
                $estado,
                $item['log_id'] ?? 0,
                $item['titular_id'] ?? 0,
                $item['vence_el'] ?? '',
                $item['mensaje'] ?? ''
            ));
        }
        foreach ($resultado['errores'] as $err) {
            $this->error($err);
        }

        return count($resultado['errores']) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
