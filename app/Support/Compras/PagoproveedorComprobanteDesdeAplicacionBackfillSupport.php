<?php

namespace App\Support\Compras;

use App\Models\Compras\Pagoproveedor_Comprobante;
use Illuminate\Support\Facades\DB;

/**
 * Materializa `pagoproveedor_comprobante` desde apps de CC importadas (Anita).
 *
 * El tab Pagos del legajo y tracking/sábana leen esa tabla; el import Anita
 * suele dejar solo `proveedor_cuentacorriente_aplicacion` + CC crédito con
 * `pagoproveedor_id`, o solo la etiqueta `comprobanteaplicado` ("OPP A 2-57827")
 * cuando la cabecera OP se importó aparte sin FK.
 */
final class PagoproveedorComprobanteDesdeAplicacionBackfillSupport
{
    /**
     * @return array{
     *   candidatas: int,
     *   a_crear: int,
     *   creadas: int,
     *   omitidas_ya_existen: int,
     *   resueltas_por_etiqueta: int,
     *   muestra: list<array{pagoproveedor_id: int, proveedor_cuentacorriente_id: int, montoaplicado: float, cp_id: int}>
     * }
     */
    public static function ejecutar(string $desde, string $hasta, bool $dryRun): array
    {
        $stats = [
            'candidatas' => 0,
            'a_crear' => 0,
            'creadas' => 0,
            'omitidas_ya_existen' => 0,
            'resueltas_por_etiqueta' => 0,
            'muestra' => [],
        ];

        $rows = DB::table('proveedor_cuentacorriente_aplicacion as app')
            ->join('proveedor_cuentacorriente as deuda', 'deuda.id', '=', 'app.proveedor_cuentacorriente_id')
            ->leftJoin('proveedor_cuentacorriente as credito', 'credito.id', '=', 'app.proveedor_cuentacorriente_aplicado_id')
            ->where('deuda.total', '>', 0)
            ->whereNotNull('deuda.comprobante_proveedor_id')
            ->where('deuda.comprobante_proveedor_id', '>', 0)
            ->where(function ($q) {
                $q->where('app.pagoproveedor_id', '>', 0)
                    ->orWhere('credito.pagoproveedor_id', '>', 0)
                    ->orWhere('app.comprobanteaplicado', 'like', 'OPP%')
                    ->orWhere('app.comprobanteaplicado', 'like', 'OPA%')
                    ->orWhere('app.comprobanteaplicado', 'like', 'AOP%');
            })
            ->where(function ($q) use ($desde, $hasta) {
                $q->whereBetween('app.fecha', [$desde, $hasta])
                    ->orWhere(function ($q2) use ($desde, $hasta) {
                        $q2->whereNull('app.fecha')
                            ->whereBetween('deuda.fecha', [$desde, $hasta]);
                    });
            })
            ->select([
                'deuda.id as deuda_ct_id',
                'deuda.comprobante_proveedor_id as cp_id',
                'deuda.moneda_id as moneda_id',
                'deuda.cotizacion as cotizacion',
                'app.pagoproveedor_id as app_pagoproveedor_id',
                'credito.pagoproveedor_id as credito_pagoproveedor_id',
                'app.comprobanteaplicado as comprobanteaplicado',
                DB::raw('ABS(app.total) as montoaplicado'),
                DB::raw('COALESCE(app.cotizacion_liquidacion, app.cotizacion, deuda.cotizacion) as cotizacion_aplicada'),
                DB::raw('COALESCE(app.diferencia_cambio, 0) as diferencia_cambio'),
            ])
            ->orderBy('deuda.id')
            ->get();

        $stats['candidatas'] = $rows->count();
        if ($rows->isEmpty()) {
            return $stats;
        }

        $etiquetas = [];
        foreach ($rows as $row) {
            $fk = (int) ($row->app_pagoproveedor_id ?: $row->credito_pagoproveedor_id);
            if ($fk > 0) {
                continue;
            }
            $eti = trim((string) ($row->comprobanteaplicado ?? ''));
            if ($eti !== '' && PagoproveedorEtiquetaAnitaSupport::esEtiquetaOp($eti)) {
                $etiquetas[] = $eti;
            }
        }
        $idsPorEtiqueta = PagoproveedorEtiquetaAnitaSupport::mapaIdsPorEtiquetas($etiquetas);

        /** @var array<string, array{pagoproveedor_id: int, proveedor_cuentacorriente_id: int, montoaplicado: float, moneda_id: ?int, cotizacion: ?float, cotizacion_aplicada: ?float, diferencia_cambio: float, cp_id: int}> $agregado */
        $agregado = [];
        foreach ($rows as $row) {
            $pagoId = (int) ($row->app_pagoproveedor_id ?: $row->credito_pagoproveedor_id);
            if ($pagoId <= 0) {
                $eti = trim((string) ($row->comprobanteaplicado ?? ''));
                $pagoId = (int) ($idsPorEtiqueta[$eti] ?? 0);
                if ($pagoId > 0) {
                    $stats['resueltas_por_etiqueta']++;
                }
            }
            $deudaId = (int) $row->deuda_ct_id;
            $monto = round((float) $row->montoaplicado, 4);
            if ($pagoId <= 0 || $deudaId <= 0 || $monto <= 0) {
                continue;
            }
            $clave = $pagoId.'|'.$deudaId;
            if (! isset($agregado[$clave])) {
                $agregado[$clave] = [
                    'pagoproveedor_id' => $pagoId,
                    'proveedor_cuentacorriente_id' => $deudaId,
                    'montoaplicado' => $monto,
                    'moneda_id' => $row->moneda_id !== null ? (int) $row->moneda_id : null,
                    'cotizacion' => $row->cotizacion !== null ? (float) $row->cotizacion : null,
                    'cotizacion_aplicada' => $row->cotizacion_aplicada !== null ? (float) $row->cotizacion_aplicada : null,
                    'diferencia_cambio' => (float) $row->diferencia_cambio,
                    'cp_id' => (int) $row->cp_id,
                ];
            } else {
                $agregado[$clave]['montoaplicado'] = round($agregado[$clave]['montoaplicado'] + $monto, 4);
            }
        }

        if ($agregado === []) {
            return $stats;
        }

        $pagoIds = array_values(array_unique(array_map(static fn (array $r) => $r['pagoproveedor_id'], $agregado)));
        $deudaIds = array_values(array_unique(array_map(static fn (array $r) => $r['proveedor_cuentacorriente_id'], $agregado)));

        $existentes = DB::table('pagoproveedor_comprobante')
            ->whereIn('pagoproveedor_id', $pagoIds)
            ->whereIn('proveedor_cuentacorriente_id', $deudaIds)
            ->get(['pagoproveedor_id', 'proveedor_cuentacorriente_id']);
        $ya = [];
        foreach ($existentes as $ex) {
            $ya[(int) $ex->pagoproveedor_id.'|'.(int) $ex->proveedor_cuentacorriente_id] = true;
        }

        foreach ($agregado as $clave => $fila) {
            if (isset($ya[$clave])) {
                $stats['omitidas_ya_existen']++;

                continue;
            }
            $stats['a_crear']++;
            if (count($stats['muestra']) < 25) {
                $stats['muestra'][] = [
                    'pagoproveedor_id' => $fila['pagoproveedor_id'],
                    'proveedor_cuentacorriente_id' => $fila['proveedor_cuentacorriente_id'],
                    'montoaplicado' => $fila['montoaplicado'],
                    'cp_id' => $fila['cp_id'],
                ];
            }
            if ($dryRun) {
                continue;
            }
            Pagoproveedor_Comprobante::query()->create([
                'pagoproveedor_id' => $fila['pagoproveedor_id'],
                'proveedor_cuentacorriente_id' => $fila['proveedor_cuentacorriente_id'],
                'montoaplicado' => $fila['montoaplicado'],
                'cotizacion' => $fila['cotizacion'],
                'moneda_id' => $fila['moneda_id'],
                'cotizacion_aplicada' => $fila['cotizacion_aplicada'],
                'diferencia_cambio' => $fila['diferencia_cambio'],
                'proveedor_cuentacorriente_dc_id' => null,
            ]);
            $stats['creadas']++;
        }

        return $stats;
    }
}
