<?php

namespace App\Support\Compras;

use App\Support\Database\SqlDialectSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deuda abierta por proveedor para sembrar el programa de pagos.
 * Mismo alcance que Deuda / ficha de proveedores: facturas y notas con saldo,
 * más anticipos OPA. Las órdenes de pago no suman deuda.
 */
final class ProgramaPagoDeudaSupport
{
    /**
     * @return Collection<int, object{proveedor_id:int, codigo:string, nombre:string, saldo:float}>
     */
    public static function saldosPorProveedor(int $empresaId, string $fechaBase): Collection
    {
        $aplicaciones = DB::table('proveedor_cuentacorriente_aplicacion')
            ->selectRaw('proveedor_cuentacorriente_id, SUM(total) AS aplicado')
            ->where('fecha', '<=', $fechaBase)
            ->groupBy('proveedor_cuentacorriente_id');

        $aplicado = SqlDialectSupport::coalesce('apl.aplicado', '0');
        // Mismo saldo pendiente que Deuda / ficha de proveedores: no pasa de cero
        // y no arrastra un comprobante ya cubierto de más.
        $saldoExpr = 'CASE WHEN cc.total >= 0 THEN GREATEST(0, cc.total + '.$aplicado.')'
            .' ELSE LEAST(0, cc.total + '.$aplicado.') END';

        return DB::table('proveedor_cuentacorriente as cc')
            ->join('proveedor as p', 'p.id', '=', 'cc.proveedor_id')
            ->whereNull('p.deleted_at')
            ->leftJoinSub($aplicaciones, 'apl', 'apl.proveedor_cuentacorriente_id', '=', 'cc.id')
            ->where('cc.empresa_id', $empresaId)
            ->where('cc.fecha', '<=', $fechaBase)
            ->whereRaw(SqlDialectSupport::sqlAlcanceDeudaAbiertaProveedorCc('cc'))
            ->whereRaw('ABS('.$aplicado.') < ABS(cc.total)')
            ->groupBy('cc.proveedor_id', 'p.codigo', 'p.nombre')
            ->havingRaw('ABS(SUM('.$saldoExpr.')) > 0.009')
            ->orderBy('p.nombre')
            ->selectRaw('cc.proveedor_id as proveedor_id, p.codigo as codigo, p.nombre as nombre, SUM('.$saldoExpr.') as saldo')
            ->get()
            ->map(function ($row) {
                $row->proveedor_id = (int) $row->proveedor_id;
                $row->saldo = round((float) $row->saldo, 2);

                return $row;
            });
    }

    public static function saldoProveedor(int $empresaId, int $proveedorId, string $fechaBase): float
    {
        $fila = self::saldosPorProveedor($empresaId, $fechaBase)
            ->firstWhere('proveedor_id', $proveedorId);

        return $fila ? (float) $fila->saldo : 0.0;
    }
}
