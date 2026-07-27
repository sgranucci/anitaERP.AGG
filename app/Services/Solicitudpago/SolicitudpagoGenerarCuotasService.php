<?php

namespace App\Services\Solicitudpago;

use App\Models\Solicitudpago\Solicitudpago;
use App\Models\Solicitudpago\Solicitudpago_Cuota;
use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Réplica ERP de Anita p-controlsolpm: genera SP hija por cuota pendiente
 * con vencimiento ≤ hoy + N días, madre AUTORIZADA.
 */
class SolicitudpagoGenerarCuotasService
{
    public function __construct(
        private SolicitudpagoRepositoryInterface $repository,
    ) {
    }

    /**
     * @return array{procesadas: int, generadas: int, madres_terminadas: int, errores: list<string>}
     */
    public function ejecutar(?Carbon $hoy = null, ?int $diasAnticipacion = null, bool $dryRun = false): array
    {
        $hoy = ($hoy ?? Carbon::today())->startOfDay();
        $dias = $diasAnticipacion ?? (int) config('solicitudpago.cuotas_dias_anticipacion', 6);
        $limiteVto = $hoy->copy()->addDays(max(0, $dias))->toDateString();

        $resultado = [
            'procesadas' => 0,
            'generadas' => 0,
            'madres_terminadas' => 0,
            'errores' => [],
        ];

        $cuotas = Solicitudpago_Cuota::query()
            ->with(['solicitudpagos.cuentas', 'solicitudpagos.conceptos', 'solicitudpagos.empresas'])
            ->whereNull('solicitudpago_hija_id')
            ->whereDate('fecha_vencimiento', '<=', $limiteVto)
            ->orderBy('fecha_vencimiento')
            ->orderBy('solicitudpago_id')
            ->orderBy('nro_cuota')
            ->get();

        foreach ($cuotas as $cuota) {
            $resultado['procesadas']++;
            $madre = $cuota->solicitudpagos;
            if (! $madre) {
                $resultado['errores'][] = "Cuota {$cuota->id}: sin SP madre";
                continue;
            }
            if ($madre->estado !== SolicitudpagoEstados::AUTORIZADA) {
                continue;
            }

            try {
                if ($dryRun) {
                    $resultado['generadas']++;
                    continue;
                }

                $hija = $this->generarHija($madre, $cuota);
                $resultado['generadas']++;

                if ($this->terminarMadreSiSinPendientes($madre)) {
                    $resultado['madres_terminadas']++;
                }

                Log::info('solicitudpago.generar-cuotas', [
                    'madre_codigo' => $madre->codigo,
                    'cuota' => $cuota->nro_cuota,
                    'hija_codigo' => $hija->codigo,
                ]);
            } catch (\Throwable $e) {
                $msg = "Madre {$madre->codigo} cuota {$cuota->nro_cuota}: ".$e->getMessage();
                $resultado['errores'][] = $msg;
                Log::error('solicitudpago.generar-cuotas', ['error' => $msg]);
            }
        }

        return $resultado;
    }

    private function generarHija(Solicitudpago $madre, Solicitudpago_Cuota $cuota): Solicitudpago
    {
        return DB::transaction(function () use ($madre, $cuota) {
            $madre->loadMissing('cuentas');
            $montoCuota = (float) $cuota->monto;
            $montoMadre = (float) $madre->monto;
            $coef = $montoMadre > 0.0 ? ($montoMadre / max($montoCuota, 0.00001)) : 1.0;

            $detalle = trim((string) ($madre->detalle ?? ''));
            $sufijo = ' SP '.$madre->codigo.' Cuota Nro. '.$cuota->nro_cuota;
            $detalle = mb_substr($detalle.$sufijo, 0, 180);

            $vto = $cuota->fecha_vencimiento
                ? Carbon::parse($cuota->fecha_vencimiento)->toDateString()
                : Carbon::today()->toDateString();

            $empresaIds = [];
            $cuentaIds = [];
            $ccIds = [];
            $dhs = [];
            $montosCta = [];
            foreach ($madre->cuentas as $cta) {
                $empresaIds[] = $cta->empresa_id;
                $cuentaIds[] = $cta->cuentacontable_id;
                $ccIds[] = $cta->centrocosto_id;
                $dhs[] = $cta->debe_haber;
                $montosCta[] = round(((float) $cta->monto) / $coef, 2);
            }

            $hija = $this->repository->guardarCompleto([
                'empresa_id' => $madre->empresa_id,
                'fecha' => Carbon::today()->toDateString(),
                'tratamiento' => $madre->tratamiento,
                'proveedor_id' => $madre->proveedor_id,
                'concepto_solicitudpago_id' => $madre->concepto_solicitudpago_id,
                'formapagosol_id' => $madre->formapagosol_id,
                'moneda_id' => $madre->moneda_id,
                'beneficiario' => $madre->beneficiario,
                'endoso' => $madre->endoso,
                'fecha_entrega' => $vto,
                'fecha_vencimiento' => $vto,
                'monto' => $montoCuota,
                'observacion' => $madre->observacion,
                'estado' => SolicitudpagoEstados::AUTORIZADA,
                'sector_solicitudpago_id' => $madre->sector_solicitudpago_id,
                'centrocosto_id' => $madre->centrocosto_id,
                'detalle' => $detalle,
                'solicitudpago_madre_id' => $madre->id,
                'empresa_ids' => $empresaIds,
                'cuentacontable_ids' => $cuentaIds,
                'centrocosto_ids' => $ccIds,
                'debe_haberes' => $dhs,
                'montos_cuenta' => $montosCta,
            ], null);

            $cuota->update(['solicitudpago_hija_id' => $hija->id]);

            if (config('solicitudpago.arbol_al_generar_cuota', false)) {
                try {
                    app(SolicitudpagoArbolIntegracionService::class)->dispararAlGuardar((int) $hija->id);
                } catch (\Throwable $e) {
                    Log::warning('solicitudpago.generar-cuotas.arbol', ['error' => $e->getMessage()]);
                }
            }

            return $hija;
        });
    }

    private function terminarMadreSiSinPendientes(Solicitudpago $madre): bool
    {
        $pendientes = Solicitudpago_Cuota::query()
            ->where('solicitudpago_id', $madre->id)
            ->whereNull('solicitudpago_hija_id')
            ->exists();

        if ($pendientes) {
            return false;
        }

        if ($madre->estado === SolicitudpagoEstados::TERMINADA) {
            return false;
        }

        $this->repository->cambiarEstado((int) $madre->id, SolicitudpagoEstados::TERMINADA, 'Terminada');

        return true;
    }
}
