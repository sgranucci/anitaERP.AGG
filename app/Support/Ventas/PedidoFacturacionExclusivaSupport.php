<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Pedido;
use App\Models\Ventas\Pedido_Articulo;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Evita dos facturas del mismo pedido (doble clic / pestañas / carrera con ARCA).
 * La factura partida Bierzo+Villafranca sigue en el mismo request, con un solo candado.
 */
final class PedidoFacturacionExclusivaSupport
{
    public const MSG_EN_CURSO = 'Ya hay una facturación en curso de este pedido. Espere a que termine.';

    public const MSG_YA_FACTURADO = 'El pedido ya está facturado.';

    public const MSG_YA_TIENE_FACTURA = 'El pedido ya tiene una factura emitida.';

    public static function clave(int $pedidoId): string
    {
        return 'ventas:pedido:facturar:'.$pedidoId;
    }

    public static function segundosBloqueo(): int
    {
        return max(60, (int) config('facturacion.pedido_facturacion_lock_segundos', 180));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|array{error: string}
     */
    public static function ejecutar(int $pedidoId, callable $callback)
    {
        if ($pedidoId <= 0) {
            return $callback();
        }

        $lock = Cache::lock(self::clave($pedidoId), self::segundosBloqueo());
        if (! $lock->get()) {
            return ['error' => self::MSG_EN_CURSO];
        }

        try {
            $motivo = self::motivoBloqueo($pedidoId);
            if ($motivo !== null) {
                return ['error' => $motivo];
            }

            $resultado = $callback();

            if (self::emisionCompletaSinError($resultado)) {
                self::marcarItemsFacturados($pedidoId);
            }

            return $resultado;
        } finally {
            $lock->release();
        }
    }

    public static function motivoBloqueo(int $pedidoId): ?string
    {
        if ($pedidoId <= 0) {
            return null;
        }

        return DB::transaction(function () use ($pedidoId) {
            $pedido = Pedido::query()->whereKey($pedidoId)->lockForUpdate()->first();
            if ($pedido === null) {
                return 'Pedido inexistente';
            }

            $estado = PedidoEstadoErpSupport::normalizarEstadoCabecera(
                $pedido->estado ?? null,
                $pedido->estadopedido ?? null
            );
            if ($estado === PedidoEstadoErpSupport::FACTURADO) {
                return self::MSG_YA_FACTURADO;
            }

            if (self::tieneFacturaEmitida($pedidoId)) {
                return self::MSG_YA_TIENE_FACTURA;
            }

            return null;
        });
    }

    public static function tieneFacturaEmitida(int $pedidoId): bool
    {
        if ($pedidoId <= 0) {
            return false;
        }

        return Venta::query()
            ->where('pedido_id', $pedidoId)
            ->where('codigo', 'like', 'FAC%')
            ->exists();
    }

    public static function marcarItemsFacturados(int $pedidoId): void
    {
        if ($pedidoId <= 0) {
            return;
        }

        $items = Pedido_Articulo::query()
            ->where('pedido_id', $pedidoId)
            ->get();

        foreach ($items as $item) {
            if (! PedidoEstadoErpSupport::esItemPendienteFacturable($item->estado ?? null)) {
                continue;
            }
            if ((float) $item->pesada <= 0) {
                continue;
            }

            $item->update(['estado' => PedidoEstadoErpSupport::FACTURADO]);
        }
    }

    public static function emisionCompletaSinError(mixed $resultado): bool
    {
        if (! is_array($resultado) || $resultado === []) {
            return false;
        }

        if (! empty($resultado['error'])) {
            return false;
        }

        foreach ($resultado as $clave => $item) {
            if (! is_int($clave)) {
                continue;
            }
            if (is_array($item) && ! empty($item['error'])) {
                return false;
            }
        }

        return true;
    }
}
