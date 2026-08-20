<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Motivocierrepedido;
use App\Models\Ventas\Pedido_Articulo;
use App\Models\Ventas\Pedido_Articulo_Estado;

/**
 * Cierra ítems pendientes sin pesada al facturar el pedido.
 */
final class PedidoItemCierreFaltaStockSupport
{
    public const NOMBRE_MOTIVO = 'Falta Stock';

    public const OBSERVACION = 'Cierre automático: sin pesada al facturar';

    public static function resolverMotivoId(): int
    {
        $motivo = Motivocierrepedido::query()
            ->where('nombre', self::NOMBRE_MOTIVO)
            ->first();

        if ($motivo === null) {
            $motivo = Motivocierrepedido::query()->create([
                'nombre' => self::NOMBRE_MOTIVO,
            ]);
        }

        return (int) $motivo->id;
    }

    /**
     * Anula ítems pendientes del pedido con pesada en cero.
     *
     * @return int cantidad de ítems cerrados
     */
    public static function cerrarItemsSinPesadaDelPedido(int $pedidoId): int
    {
        if ($pedidoId <= 0) {
            return 0;
        }

        $motivoId = self::resolverMotivoId();
        $cerrados = 0;

        $items = Pedido_Articulo::query()
            ->where('pedido_id', $pedidoId)
            ->get();

        foreach ($items as $item) {
            if (! PedidoEstadoErpSupport::esItemPendienteFacturable($item->estado ?? null)) {
                continue;
            }
            if ((float) $item->pesada > 0) {
                continue;
            }

            $item->update(['estado' => PedidoEstadoErpSupport::ANULADO]);

            Pedido_Articulo_Estado::query()->create([
                'pedido_articulo_id' => $item->id,
                'motivocierrepedido_id' => $motivoId,
                'cliente_id' => null,
                'estado' => PedidoEstadoErpSupport::ANULADO,
                'observacion' => self::OBSERVACION,
            ]);

            $cerrados++;
        }

        return $cerrados;
    }
}
