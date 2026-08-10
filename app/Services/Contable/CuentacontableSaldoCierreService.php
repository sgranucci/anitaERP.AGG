<?php

namespace App\Services\Contable;

use App\Support\Database\SqlDialectSupport;
use App\Models\Contable\Cuentacontable_Saldo_Cierre;
use App\Models\Contable\Cuentacontable_Saldo_Mes;
use App\Models\Contable\PeriodoCierreContable;
use App\Support\Contable\CuentacontableSaldoMesSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Congela saldos acumulados al registrar un cierre de período contable (fase 2).
 */
class CuentacontableSaldoCierreService
{
    public function congelarParaCierre(PeriodoCierreContable $cierre): int
    {
        $empresaId = (int) $cierre->empresa_id;
        $fechaHasta = Carbon::parse($cierre->fecha_hasta);
        $anioMes = (int) $fechaHasta->format('Ym');
        $registros = 0;

        DB::transaction(function () use ($cierre, $empresaId, $fechaHasta, $anioMes, &$registros) {
            Cuentacontable_Saldo_Cierre::query()
                ->where('periodo_cierre_id', $cierre->id)
                ->delete();

            $filas = Cuentacontable_Saldo_Mes::query()
                ->selectRaw('
                    cuentacontable_id,
                    centrocosto_id,
                    moneda_id,
                    SUM(monto) AS monto_acumulado,
                    SUM(monto_local) AS monto_local_acumulado
                ')
                ->where('empresa_id', $empresaId)
                ->where('anio_mes', '<=', $anioMes)
                ->groupBy('cuentacontable_id', 'centrocosto_id', 'moneda_id')
                ->get();

            $now = now();
            $batch = [];

            foreach ($filas as $fila) {
                $monto = (float) $fila->monto_acumulado;
                $montoLocal = (float) $fila->monto_local_acumulado;
                if (abs($monto) < 1e-9 && abs($montoLocal) < 1e-9) {
                    continue;
                }

                $batch[] = [
                    'periodo_cierre_id' => $cierre->id,
                    'empresa_id' => $empresaId,
                    'fecha_hasta' => $fechaHasta->format('Y-m-d'),
                    'anio_mes' => $anioMes,
                    'cuentacontable_id' => (int) $fila->cuentacontable_id,
                    'centrocosto_id' => CuentacontableSaldoMesSupport::normalizarCentrocostoId($fila->centrocosto_id),
                    'moneda_id' => (int) $fila->moneda_id,
                    'monto_acumulado' => $monto,
                    'monto_local_acumulado' => $montoLocal,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $registros++;

                if (count($batch) >= 500) {
                    DB::table('cuentacontable_saldo_cierre')->insert($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                DB::table('cuentacontable_saldo_cierre')->insert($batch);
            }
        });

        Log::info('cuentacontable_saldo_cierre: snapshot generado', [
            'periodo_cierre_id' => $cierre->id,
            'empresa_id' => $empresaId,
            'fecha_hasta' => $fechaHasta->format('Y-m-d'),
            'filas' => $registros,
        ]);

        return $registros;
    }

    /**
     * Saldo local acumulado congelado si existe cierre contable exactamente en ese YYYYMM.
     */
    public function saldoLocalDesdeUltimoCierre(
        int $empresaId,
        int $cuentacontableId,
        int $anioMesHasta,
        ?int $centrocostoId = null,
    ): ?float {
        $cierre = PeriodoCierreContable::query()
            ->where('empresa_id', $empresaId)
            ->where('alcance', PeriodoContableCierreSupport::ALCANCE_GENERAL)
            ->whereRaw(SqlDialectSupport::anioMes('fecha_hasta').' = ?', [$anioMesHasta])
            ->orderByDesc('id')
            ->first();

        if ($cierre === null) {
            return null;
        }

        $query = Cuentacontable_Saldo_Cierre::query()
            ->where('periodo_cierre_id', $cierre->id)
            ->where('cuentacontable_id', $cuentacontableId);

        $normalizado = CuentacontableSaldoMesSupport::normalizarCentrocostoId($centrocostoId);
        if ($normalizado === null) {
            $query->whereNull('centrocosto_id');
        } else {
            $query->where('centrocosto_id', $normalizado);
        }

        return (float) ($query->sum('monto_local_acumulado') ?? 0);
    }
}
