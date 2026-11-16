<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

use App\Models\Contable\Asiento;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Contable\AsientoAnitaMetadatosSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Alinea asiento.fecha = venta.fechajornada en transferencias casino/gastronomía Crown.
 * No toca Importación ARCA ni administración.
 */
final class CorregirFechaAsientoTransferenciaCasinoSupport
{
    /**
     * @return array{
     *   candidatos: int,
     *   total_venta: float,
     *   corregidos: int,
     *   muestra: list<array{asiento_id:int,numeroasiento:string,fecha_antes:string,fecha_despues:string,codigo:string,total:float}>
     * }
     */
    public function ejecutar(bool $dryRun = true, int $limiteMuestra = 15): array
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return [
                'candidatos' => 0,
                'total_venta' => 0.0,
                'corregidos' => 0,
                'muestra' => [],
            ];
        }

        $filas = DB::table('asiento as a')
            ->join('venta as v', 'v.id', '=', 'a.venta_id')
            ->where('a.anita_origen', AsientoAnitaMetadatosSupport::ORIGEN_TRANSFERENCIA)
            ->whereNotNull('v.fechajornada')
            ->whereRaw('DATE(a.fecha) <> DATE(v.fechajornada)')
            ->where(function ($q): void {
                $q->where('a.observacion', 'like', 'Transferencia gastronomía%')
                    ->orWhere('a.observacion', 'like', 'Transferencia gastronomia%');
            })
            ->where('a.observacion', 'not like', 'Importación ARCA%')
            ->where('a.observacion', 'not like', 'Importacion ARCA%')
            ->orderBy('a.id')
            ->get([
                'a.id as asiento_id',
                'a.numeroasiento',
                'a.fecha as fecha_asiento',
                'v.fechajornada',
                'v.codigo',
                'v.total',
            ]);

        $muestra = [];
        $corregidos = 0;
        $totalVenta = 0.0;

        foreach ($filas as $fila) {
            $antes = (string) $fila->fecha_asiento;
            $despues = (string) $fila->fechajornada;
            $totalVenta += abs((float) $fila->total);
            if (count($muestra) < $limiteMuestra) {
                $muestra[] = [
                    'asiento_id' => (int) $fila->asiento_id,
                    'numeroasiento' => (string) $fila->numeroasiento,
                    'fecha_antes' => $antes,
                    'fecha_despues' => $despues,
                    'codigo' => (string) $fila->codigo,
                    'total' => round(abs((float) $fila->total), 2),
                ];
            }
        }

        if (! $dryRun && $filas->isNotEmpty()) {
            DB::transaction(function () use ($filas, &$corregidos): void {
                foreach ($filas as $fila) {
                    $asiento = Asiento::query()->whereKey((int) $fila->asiento_id)->lockForUpdate()->first();
                    if ($asiento === null) {
                        continue;
                    }
                    $jornada = (string) $fila->fechajornada;
                    if ((string) $asiento->fecha === $jornada) {
                        continue;
                    }
                    $asiento->fecha = $jornada;
                    $asiento->save();
                    $corregidos++;
                }
            });

            Log::info('crown.asiento.fecha_jornada.corregidos', [
                'corregidos' => $corregidos,
                'candidatos' => $filas->count(),
            ]);
        }

        return [
            'candidatos' => $filas->count(),
            'total_venta' => round($totalVenta, 2),
            'corregidos' => $dryRun ? 0 : $corregidos,
            'muestra' => $muestra,
        ];
    }
}
