<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Services\Ventas\Gastronomia\GastronomiaCierreJornadaFacturaProcesoEmisionService;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaRecuperacionSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class GastronomiaRecuperarFacturasProcesoCierre extends Command
{
    protected $signature = 'gastronomia:recuperar-facturas-proceso-cierre
                            {--jornada-id= : jornada_gastronomia_id}
                            {--puntoventa=24 : puntoventa_id de emisión}
                            {--fecha-factura= : Y-m-d (default: fecha jornada)}
                            {--solo-limpiar : Solo archiva snapshot y borra ventas ERP}
                            {--solo-reemitir : Solo re-emite (snapshot ya limpio con recuperación)}
                            {--yes : Sin confirmación interactiva}';

    protected $description = 'Limpia facturas erróneas del cierre Waitry y re-emite desde snapshot (mismas comandas y numeración)';

    public function handle(
        GastronomiaCierreJornadaFacturaProcesoEmisionService $emisionService,
        LimpiarVentasPruebaGastronomia $limpiarVentas,
    ): int {
        $jornadaId = (int) $this->option('jornada-id');
        if ($jornadaId <= 0) {
            $this->error('Indique --jornada-id=ID.');

            return self::FAILURE;
        }

        $usuarioId = (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('No se pudo autenticar usuario para la operación.');

            return self::FAILURE;
        }

        $jornada = JornadaGastronomia::query()->find($jornadaId);
        if ($jornada === null) {
            $this->error('Jornada '.$jornadaId.' inexistente.');

            return self::FAILURE;
        }

        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', $jornadaId)
            ->first();
        if ($snapshot === null) {
            $this->error('No hay snapshot de proceso para la jornada '.$jornadaId.'.');

            return self::FAILURE;
        }

        $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
        $recuperacion = CierreJornadaProcesoFacturaRecuperacionSupport::datosRecuperacionDesdePayload($payload);
        if ($recuperacion === null) {
            $this->error('No hay factura_proceso_emision ni recuperación en el snapshot.');

            return self::FAILURE;
        }

        $ventaIds = CierreJornadaProcesoFacturaRecuperacionSupport::ventaIdsDesdeRecuperacion($recuperacion);
        $this->info('Jornada #'.$jornadaId.' · '.count($recuperacion['facturas'] ?? []).' lote(s) · ventas ERP: '.implode(', ', $ventaIds));

        foreach ($recuperacion['facturas'] ?? [] as $fac) {
            if (! is_array($fac)) {
                continue;
            }
            $this->line(sprintf(
                '  lote %s → %s ($ %s)',
                $fac['lote'] ?? '?',
                $fac['factura'] ?? '?',
                number_format((float) ($fac['total'] ?? 0), 2, '.', ''),
            ));
        }

        $soloLimpiar = (bool) $this->option('solo-limpiar');
        $soloReemitir = (bool) $this->option('solo-reemitir');
        if ($soloLimpiar && $soloReemitir) {
            $this->error('Use solo uno de --solo-limpiar o --solo-reemitir.');

            return self::FAILURE;
        }

        $ejecutarLimpieza = ! $soloReemitir;
        $ejecutarReemision = ! $soloLimpiar;

        if (! $this->option('yes') && ! $this->confirm('¿Continuar?', true)) {
            $this->comment('Cancelado.');

            return self::SUCCESS;
        }

        if ($ejecutarLimpieza) {
            if (! isset($payload['factura_proceso_emision_recuperacion'])) {
                $payload['factura_proceso_emision_recuperacion'] = $recuperacion;
            }
            unset($payload['factura_proceso_emision']);
            $snapshot->payload = $payload;
            $snapshot->save();
            $this->info('Snapshot: emisión activa archivada en factura_proceso_emision_recuperacion.');

            foreach ($ventaIds as $ventaId) {
                try {
                    $limpiarVentas->eliminarVentaPorId($ventaId);
                    $this->line('  OK borrada venta_id='.$ventaId);
                } catch (\Throwable $e) {
                    $this->error('  FALLÓ venta_id='.$ventaId.': '.$e->getMessage());

                    return self::FAILURE;
                }
            }
        }

        if ($ejecutarReemision) {
            $puntoventaId = (int) $this->option('puntoventa');
            $fechaFactura = trim((string) ($this->option('fecha-factura') ?? ''));
            if ($fechaFactura === '') {
                $fechaFactura = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
            }

            try {
                $resultado = $emisionService->reemitirDesdeRecuperacionSnapshot(
                    $jornadaId,
                    $puntoventaId,
                    $fechaFactura,
                );
            } catch (\Throwable $e) {
                $this->error('Re-emisión falló: '.$e->getMessage());

                return self::FAILURE;
            }

            $this->newLine();
            $this->info($resultado['mensaje'] ?? 'Re-emisión completada.');
            foreach ($resultado['facturas'] ?? [] as $fac) {
                $this->line('  '.($fac['factura'] ?? '').' venta_id='.($fac['venta_id'] ?? ''));
            }
        }

        return self::SUCCESS;
    }
}
