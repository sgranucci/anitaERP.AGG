<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo_Movimiento;
use App\Models\Ventas\Ordentrabajo;

/**
 * Lote que graba una TRA Ferli en `articulo_movimiento`.
 *
 * El correlativo de la TRA es el número del documento. Si el operador escribió un
 * lote (o una OT), la salida y la entrada se graban con ese lote: así el saldo pasa
 * de un depósito a otro dentro del mismo bucket. Con el correlativo, el origen quedaba
 * en un bucket nuevo negativo y el lote original seguía con saldo en el origen.
 */
final class TransferenciaMercaderiaLoteBucketFerliSupport
{
    /**
     * @param  array<string, mixed>  $cabecera
     */
    public static function loteParaGrabar(int $correlativo, array $cabecera): int
    {
        if (! MovimientoStockFerliSupport::esCalzadosFerli()) {
            return $correlativo;
        }

        return self::loteDesdeTexto((string) ($cabecera['lote_formulario'] ?? '')) ?? $correlativo;
    }

    /**
     * Lote de la salida ya grabada: la entrada diferida y los reversos lo repiten.
     */
    public static function loteDeMovimientoSalida(int $movimientoStockSalidaId, int $correlativo): int
    {
        if (! MovimientoStockFerliSupport::esCalzadosFerli() || $movimientoStockSalidaId <= 0) {
            return $correlativo;
        }

        $filas = Articulo_Movimiento::query()
            ->where('movimientostock_id', $movimientoStockSalidaId)
            ->where('combinacion_id', '>', 0)
            ->get(['lote', 'ordentrabajo_id']);

        $lotes = [];
        foreach ($filas as $fila) {
            $lote = (int) ($fila->lote ?? 0);
            if ($lote <= 0 && (int) ($fila->ordentrabajo_id ?? 0) > 0) {
                $codigo = trim((string) (Ordentrabajo::query()->whereKey((int) $fila->ordentrabajo_id)->value('codigo') ?? ''));
                $lote = self::loteDesdeTexto($codigo) ?? 0;
            }
            if ($lote > 0) {
                $lotes[$lote] = $lote;
            }
        }

        return count($lotes) === 1 ? (int) reset($lotes) : $correlativo;
    }

    /**
     * null = sin lote propio («LOTE DE ALTA» o vacío).
     */
    public static function loteDesdeTexto(string $texto): ?int
    {
        $texto = trim($texto);
        if ($texto === '' || ! ctype_digit($texto)) {
            return null;
        }
        $lote = (int) $texto;

        return $lote > 0 ? $lote : null;
    }
}
