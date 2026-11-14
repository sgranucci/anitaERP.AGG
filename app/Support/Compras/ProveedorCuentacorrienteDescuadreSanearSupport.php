<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use Illuminate\Support\Facades\DB;

/**
 * Sanea causas de descuadre deuda vs ficha (no pisa con «Ajuste saldo CC»).
 *
 * 1) Apps sintéticas (sin pagoproveedor_id) que duplican un par ERP.
 * 2) Facturas CC con total=0: restaura el total desde comprobante_proveedor.
 * 3) Huérfanos: intenta vínculo a OP (delega en VinculoBackfill); lista el resto.
 */
final class ProveedorCuentacorrienteDescuadreSanearSupport
{
    /**
     * @return array{
     *   empresa_id: int|null,
     *   apps_duplicadas: list<array{app_ids: list<int>, cc_a: int, cc_b: int, monto: float}>,
     *   facturas_cero: list<array{cc_id: int, proveedor_id: int, total_nuevo: float, cp_id: int}>,
     *   huerfanos: array{total: int, suma: float, vinculables: int, sin_op: int},
     *   vinculo: array<string, int|list>,
     *   muestra_apps: list<array<string, mixed>>,
     *   muestra_ceros: list<array<string, mixed>>,
     *   muestra_huerfanos: list<array<string, mixed>>
     * }
     */
    public static function planear(?int $empresaId = null): array
    {
        $appsDup = self::planAppsDuplicadas($empresaId);
        $ceros = self::planFacturasTotalCero($empresaId);
        $vinculoDry = PagoproveedorCuentacorrienteVinculoBackfillSupport::ejecutar(true);
        $huerfanos = self::resumenHuerfanos($empresaId);

        return [
            'empresa_id' => $empresaId,
            'apps_duplicadas' => $appsDup['pares'],
            'facturas_cero' => $ceros['filas'],
            'huerfanos' => $huerfanos,
            'vinculo' => $vinculoDry,
            'muestra_apps' => $appsDup['muestra'],
            'muestra_ceros' => $ceros['muestra'],
            'muestra_huerfanos' => $huerfanos['muestra'],
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{
     *   apps_borradas: int,
     *   facturas_restauradas: int,
     *   vinculo: array<string, int|list>
     * }
     */
    public static function ejecutar(array $plan): array
    {
        $appsBorradas = 0;
        $facturasRestauradas = 0;

        DB::transaction(function () use ($plan, &$appsBorradas, &$facturasRestauradas) {
            $appIds = [];
            foreach ($plan['apps_duplicadas'] ?? [] as $par) {
                foreach ($par['app_ids'] as $id) {
                    $appIds[] = (int) $id;
                }
            }
            $appIds = array_values(array_unique(array_filter($appIds)));
            if ($appIds !== []) {
                $appsBorradas = ProveedorCuentacorrienteAplicacionBorradoSupport::borrar(
                    Proveedor_Cuentacorriente_Aplicacion::query()
                        ->whereIn('id', $appIds)
                        ->whereNull('pagoproveedor_id')
                );
            }

            foreach ($plan['facturas_cero'] ?? [] as $fila) {
                $n = Proveedor_Cuentacorriente::query()
                    ->whereKey((int) $fila['cc_id'])
                    ->where('total', 0)
                    ->whereNotNull('comprobante_proveedor_id')
                    ->update([
                        'total' => round((float) $fila['total_nuevo'], 4),
                        'updated_at' => now(),
                    ]);
                $facturasRestauradas += $n;
            }
        });

        $vinculo = PagoproveedorCuentacorrienteVinculoBackfillSupport::ejecutar(false);

        return [
            'apps_borradas' => $appsBorradas,
            'facturas_restauradas' => $facturasRestauradas,
            'vinculo' => $vinculo,
        ];
    }

    /**
     * Apps sintéticas (sin OP) que repiten un |monto| ya aplicado por una OP ERP
     * sobre la misma CC, aunque el par de contraparte sea distinto (caso Albano).
     *
     * @return array{pares: list<array{app_ids: list<int>, cc_a: int, cc_b: int, monto: float}>, muestra: list<array<string, mixed>>}
     */
    private static function planAppsDuplicadas(?int $empresaId): array
    {
        $q = DB::table('proveedor_cuentacorriente_aplicacion as sint')
            ->whereNull('sint.pagoproveedor_id')
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('proveedor_cuentacorriente_aplicacion as erp')
                    ->whereColumn('erp.proveedor_cuentacorriente_id', 'sint.proveedor_cuentacorriente_id')
                    ->where('erp.pagoproveedor_id', '>', 0)
                    ->whereRaw('ABS(ABS(erp.total) - ABS(sint.total)) < 0.02');
            })
            ->select([
                'sint.id as sint_id',
                'sint.proveedor_cuentacorriente_id as cc_a',
                'sint.proveedor_cuentacorriente_aplicado_id as cc_b',
                'sint.total as monto',
                'sint.empresa_id',
            ]);
        if ($empresaId !== null && $empresaId > 0) {
            $q->where('sint.empresa_id', $empresaId);
        }

        $filas = $q->orderBy('sint.id')->get();
        /** @var array<int, true> $appIds */
        $appIds = [];
        /** @var array<string, array{app_ids: array<int, true>, cc_a: int, cc_b: int, monto: float}> $pares */
        $pares = [];
        foreach ($filas as $fila) {
            $sintId = (int) $fila->sint_id;
            $a = (int) $fila->cc_a;
            $b = (int) $fila->cc_b;
            $monto = abs((float) $fila->monto);
            $appIds[$sintId] = true;

            // Par contrario sintético (misma pareja CC, sin OP).
            $contrarias = DB::table('proveedor_cuentacorriente_aplicacion')
                ->whereNull('pagoproveedor_id')
                ->where('proveedor_cuentacorriente_id', $b)
                ->where('proveedor_cuentacorriente_aplicado_id', $a)
                ->whereRaw('ABS(ABS(total) - ?) < 0.02', [$monto])
                ->pluck('id');
            foreach ($contrarias as $cid) {
                $appIds[(int) $cid] = true;
            }

            $key = $a.'|'.$b.'|'.number_format($monto, 2, '.', '');
            $pares[$key]['app_ids'][$sintId] = true;
            foreach ($contrarias as $cid) {
                $pares[$key]['app_ids'][(int) $cid] = true;
            }
            $pares[$key]['cc_a'] = $a;
            $pares[$key]['cc_b'] = $b;
            $pares[$key]['monto'] = $monto;
        }

        $lista = [];
        foreach ($pares as $par) {
            $lista[] = [
                'app_ids' => array_map('intval', array_keys($par['app_ids'])),
                'cc_a' => $par['cc_a'],
                'cc_b' => $par['cc_b'],
                'monto' => $par['monto'],
            ];
        }

        // Si quedó alguna sintética suelta sin armar par, igual va al borrado.
        $cubiertos = [];
        foreach ($lista as $par) {
            foreach ($par['app_ids'] as $id) {
                $cubiertos[$id] = true;
            }
        }
        foreach (array_keys($appIds) as $id) {
            if (isset($cubiertos[$id])) {
                continue;
            }
            $lista[] = [
                'app_ids' => [(int) $id],
                'cc_a' => 0,
                'cc_b' => 0,
                'monto' => 0.0,
            ];
        }

        $muestra = [];
        foreach (array_slice($lista, 0, 20) as $par) {
            $muestra[] = [
                'cc_a' => $par['cc_a'],
                'cc_b' => $par['cc_b'],
                'monto' => number_format($par['monto'], 2, ',', '.'),
                'apps' => count($par['app_ids']),
            ];
        }

        return ['pares' => $lista, 'muestra' => $muestra];
    }

    /**
     * @return array{filas: list<array{cc_id: int, proveedor_id: int, total_nuevo: float, cp_id: int}>, muestra: list<array<string, mixed>>}
     */
    private static function planFacturasTotalCero(?int $empresaId): array
    {
        $q = DB::table('proveedor_cuentacorriente as cc')
            ->join('comprobante_proveedor as cp', 'cp.id', '=', 'cc.comprobante_proveedor_id')
            ->where('cc.total', 0)
            ->whereNotNull('cc.comprobante_proveedor_id')
            ->where('cp.total', '>', 0)
            ->select([
                'cc.id as cc_id',
                'cc.proveedor_id',
                'cc.comprobante_proveedor_id as cp_id',
                'cp.total as cp_total',
                'cc.empresa_id',
            ]);
        if ($empresaId !== null && $empresaId > 0) {
            $q->where('cc.empresa_id', $empresaId);
        }

        $filas = [];
        $muestra = [];
        foreach ($q->orderBy('cc.id')->get() as $fila) {
            $totalNuevo = round((float) $fila->cp_total, 4);
            $item = [
                'cc_id' => (int) $fila->cc_id,
                'proveedor_id' => (int) $fila->proveedor_id,
                'total_nuevo' => $totalNuevo,
                'cp_id' => (int) $fila->cp_id,
            ];
            $filas[] = $item;
            if (count($muestra) < 20) {
                $muestra[] = [
                    'cc_id' => $item['cc_id'],
                    'cp_id' => $item['cp_id'],
                    'total_nuevo' => number_format($totalNuevo, 2, ',', '.'),
                ];
            }
        }

        return ['filas' => $filas, 'muestra' => $muestra];
    }

    /**
     * @return array{total: int, suma: float, vinculables: int, sin_op: int, muestra: list<array<string, mixed>>}
     */
    private static function resumenHuerfanos(?int $empresaId): array
    {
        $pref = ProveedorCuentacorrienteGrillaSupport::PREFIJO_LEYENDA_AJUSTE;
        $q = DB::table('proveedor_cuentacorriente as cc')
            ->leftJoin('proveedor as p', 'p.id', '=', 'cc.proveedor_id')
            ->whereNull('cc.comprobante_proveedor_id')
            ->whereNull('cc.pagoproveedor_id')
            ->where('cc.total', '<', 0)
            ->where(function ($w) use ($pref) {
                $w->whereNull('cc.leyenda')
                    ->orWhere('cc.leyenda', '')
                    ->orWhere('cc.leyenda', 'not like', $pref.'%');
            });
        if ($empresaId !== null && $empresaId > 0) {
            $q->where('cc.empresa_id', $empresaId);
        }

        $agg = (clone $q)->selectRaw('count(*) n, coalesce(sum(cc.total),0) s')->first();
        $muestra = [];
        foreach ((clone $q)->orderByRaw('abs(cc.total) desc')->limit(15)
            ->get(['cc.id', 'cc.total', 'cc.fecha', 'p.codigo', 'p.nombre']) as $fila) {
            $muestra[] = [
                'cc_id' => (int) $fila->id,
                'codigo' => $fila->codigo,
                'nombre' => mb_substr((string) $fila->nombre, 0, 28),
                'fecha' => substr((string) $fila->fecha, 0, 10),
                'total' => number_format((float) $fila->total, 2, ',', '.'),
            ];
        }

        $vinculo = PagoproveedorCuentacorrienteVinculoBackfillSupport::ejecutar(true);

        return [
            'total' => (int) ($agg->n ?? 0),
            'suma' => round((float) ($agg->s ?? 0), 2),
            'vinculables' => (int) ($vinculo['a_vincular'] ?? 0),
            'sin_op' => (int) ($vinculo['sin_op'] ?? 0),
            'muestra' => $muestra,
        ];
    }
}
