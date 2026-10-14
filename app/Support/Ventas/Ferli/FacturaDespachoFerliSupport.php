<?php

namespace App\Support\Ventas\Ferli;

use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Lote;
use App\Models\Ventas\Pedido_Combinacion;
use App\Support\Ventas\PedidoPickingFerliSupport;

/**
 * Ferli: el número de despacho de aduana vive en lote.numerodespacho.
 * El comprobante de mostrador (OT y picking de distintos pedidos) conoce
 * el lote de stock (ordentrabajo_stock_id / picking_lote_codigo), no el id del lote.
 */
final class FacturaDespachoFerliSupport
{
    /**
     * @return array{loteimportacion_id: int, despacho: string}
     */
    public static function desdeLinea(Pedido_Combinacion $linea, int $ordentrabajoId = 0): array
    {
        $codigo = PedidoPickingFerliSupport::loteAsignadoParaStock($linea, $ordentrabajoId);

        return self::desdeCodigoLoteStock($codigo);
    }

    /**
     * @return array{loteimportacion_id: int, despacho: string}
     */
    public static function desdeCodigoLoteStock(string $codigoLote): array
    {
        $codigo = trim($codigoLote);
        if ($codigo === '' || $codigo === '0' || ! ctype_digit($codigo)) {
            return ['loteimportacion_id' => 0, 'despacho' => ''];
        }

        $loteId = (int) Articulo_Movimiento::query()
            ->where('lote', (int) $codigo)
            ->where('loteimportacion_id', '>', 0)
            ->orderBy('loteimportacion_id')
            ->value('loteimportacion_id');
        if ($loteId <= 0) {
            return ['loteimportacion_id' => 0, 'despacho' => ''];
        }

        $nro = trim((string) (Lote::query()->whereKey($loteId)->value('numerodespacho') ?? ''));

        return [
            'loteimportacion_id' => $loteId,
            'despacho' => $nro,
        ];
    }

    /**
     * Reimpresión: emisiones ya grabadas sin loteimportacion_id.
     *
     * @param  array<int, array{pc: int, ot: int}>  $sinLote  id de venta_emision => línea
     * @return array<int, string> id de venta_emision => numerodespacho
     */
    public static function porEmisiones(array $sinLote): array
    {
        if ($sinLote === []) {
            return [];
        }

        $pcIds = [];
        foreach ($sinLote as $row) {
            $pcId = (int) ($row['pc'] ?? 0);
            if ($pcId > 0) {
                $pcIds[$pcId] = $pcId;
            }
        }
        if ($pcIds === []) {
            return [];
        }

        $lineas = Pedido_Combinacion::query()
            ->whereIn('id', array_values($pcIds))
            ->get()
            ->keyBy('id');

        $despachoPorCodigo = [];
        $out = [];
        foreach ($sinLote as $emisionId => $row) {
            $linea = $lineas->get((int) ($row['pc'] ?? 0));
            if (! $linea) {
                continue;
            }
            $codigo = PedidoPickingFerliSupport::loteAsignadoParaStock($linea, (int) ($row['ot'] ?? 0));
            if (! array_key_exists($codigo, $despachoPorCodigo)) {
                $despachoPorCodigo[$codigo] = self::desdeCodigoLoteStock($codigo)['despacho'];
            }
            $nro = $despachoPorCodigo[$codigo];
            if ($nro !== '') {
                $out[(int) $emisionId] = $nro;
            }
        }

        return $out;
    }
}
