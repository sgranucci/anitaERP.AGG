<?php

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use Illuminate\Support\Facades\DB;

/**
 * Depósito de la última recepción COM confirmada del artículo en el ERP.
 */
final class TransferenciaMercaderiaDepositoRecepcionSupport
{
    /**
     * @return array{deposito_id: ?int, origen: ?string}
     */
    public static function resolver(int $articuloId, int $empresaId): array
    {
        if ($articuloId <= 0 || $empresaId <= 0) {
            return ['deposito_id' => null, 'origen' => null];
        }

        $desdeErp = self::desdeRecepcionErp($articuloId, $empresaId);

        return [
            'deposito_id' => $desdeErp,
            'origen' => $desdeErp !== null ? 'erp' : null,
        ];
    }

    private static function desdeRecepcionErp(int $articuloId, int $empresaId): ?int
    {
        $depositoId = DB::table('recepcion_proveedor_articulo as rpa')
            ->join('recepcion_proveedor as rp', 'rp.id', '=', 'rpa.recepcion_proveedor_id')
            ->where('rpa.articulo_id', $articuloId)
            ->where('rp.estado', RecepcionProveedorEstados::CONFIRMADA)
            ->where('rp.tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->where('rp.empresa_id', $empresaId)
            ->orderByDesc('rp.fecha')
            ->orderByDesc('rp.id')
            ->orderByDesc('rpa.id')
            ->value(DB::raw('COALESCE(rpa.deposito_id, rp.deposito_id)'));

        $depositoId = (int) ($depositoId ?? 0);

        return $depositoId > 0 ? $depositoId : null;
    }
}
