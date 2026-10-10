<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Models\Compras\Pagoproveedor;
use Illuminate\Support\Facades\DB;

/**
 * OPP y OPA de caja que son la misma orden que pagos a proveedores.
 */
final class IngresoEgresoPagoProveedorSupport
{
    /** @var list<string> */
    public const ABREVIATURAS = ['OPP', 'OPA'];

    public static function esPagoProveedor(?string $abreviatura, int $pagoproveedorId): bool
    {
        return $pagoproveedorId > 0
            && in_array(strtoupper(trim((string) $abreviatura)), self::ABREVIATURAS, true);
    }

    public static function ordenTieneMovimiento(int $pagoproveedorId): bool
    {
        if ($pagoproveedorId < 1) {
            return false;
        }

        return DB::table('caja_movimiento as cm')
            ->join('tipotransaccion_caja as ttc', 'ttc.id', '=', 'cm.tipotransaccion_caja_id')
            ->where('cm.pagoproveedor_id', $pagoproveedorId)
            ->whereRaw('UPPER(TRIM(ttc.abreviatura)) IN (?, ?)', self::ABREVIATURAS)
            ->exists();
    }

    public static function marcarMailEnPagina(iterable $filas): void
    {
        $ids = [];
        foreach ($filas as $fila) {
            $es = self::esPagoProveedor(
                (string) ($fila->abreviaturatipotransaccion_caja ?? ''),
                (int) ($fila->pagoproveedor_id ?? 0)
            );
            $fila->setAttribute('_es_pago_proveedor', $es);
            $fila->setAttribute('_mail_enviado', false);
            if ($es) {
                $ids[(int) $fila->pagoproveedor_id] = true;
            }
        }
        if ($ids === []) {
            return;
        }

        $enviados = [];
        $prefijo = Pagoproveedor::PREFIJO_OBSERVACION_ENVIO_CORREO.'%';
        foreach (array_chunk(array_keys($ids), 1000) as $lote) {
            $encontrados = DB::table('pagoproveedor_estado')
                ->whereIn('pagoproveedor_id', $lote)
                ->where('observacion', 'like', $prefijo)
                ->distinct()
                ->pluck('pagoproveedor_id');
            foreach ($encontrados as $id) {
                $enviados[(int) $id] = true;
            }
        }

        foreach ($filas as $fila) {
            $pagoId = (int) ($fila->pagoproveedor_id ?? 0);
            if (! empty($fila->_es_pago_proveedor) && isset($enviados[$pagoId])) {
                $fila->setAttribute('_mail_enviado', true);
            }
        }
    }
}
