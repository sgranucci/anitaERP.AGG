<?php

declare(strict_types=1);

namespace App\Support\Ventas\Tiendanube;

use App\Models\Ventas\TiendanubePedido;
use App\Models\Ventas\TiendanubePedidoLinea;
use App\Models\Ventas\TiendanubePedidoVenta;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * NC total sobre una FAC emitida desde Tiendanube: libera cantidades del staging
 * para poder refacturar el pedido. No toca cobranza ni metafields de TN.
 */
final class NotaCreditoReabreTiendanubePedidoSupport
{
    /**
     * @param  array<string, mixed>|null  $opcionesEmision
     * @return array{
     *   aplicado: bool,
     *   pedidos: int,
     *   lineas_liberadas: int,
     *   pivots_borrados: int,
     *   motivo?: string
     * }
     */
    public static function alGrabarNc(
        int $ventaOrigenId,
        float $totalNcAbsoluto,
        $tipotransaccion,
        ?array $opcionesEmision = null,
        int $ventaNcId = 0
    ): array {
        $vacio = [
            'aplicado' => false,
            'pedidos' => 0,
            'lineas_liberadas' => 0,
            'pivots_borrados' => 0,
        ];

        if ($ventaOrigenId <= 0) {
            return $vacio + ['motivo' => 'sin_venta_origen'];
        }

        if (! is_object($tipotransaccion) || ! method_exists($tipotransaccion, 'esNotaCredito') || ! $tipotransaccion->esNotaCredito()) {
            return $vacio + ['motivo' => 'no_nc'];
        }

        // Staging TN solo existe en Ferli (migración gated). En AGG/estacionamiento/etc.
        // las tablas no están: no tumbar la NC por un SELECT a tabla inexistente.
        if (! Schema::hasTable('tiendanube_pedido')) {
            return $vacio + ['motivo' => 'sin_tablas_tiendanube'];
        }

        $pivots = Schema::hasTable('tiendanube_pedido_venta')
            ? TiendanubePedidoVenta::query()->where('venta_id', $ventaOrigenId)->get()
            : collect();
        if ($pivots->isEmpty()) {
            // FAC sin staging TN (factura común) o solo cabecera.venta_id legacy.
            $pedidoLegacy = TiendanubePedido::query()->where('venta_id', $ventaOrigenId)->first();
            if (! $pedidoLegacy) {
                return $vacio + ['motivo' => 'sin_vinculo_tiendanube'];
            }
        }

        $ventaOrigen = Venta::query()->find($ventaOrigenId);
        if (! $ventaOrigen) {
            return $vacio + ['motivo' => 'venta_origen_inexistente'];
        }

        if (! self::esNcTotal($ventaOrigen, $totalNcAbsoluto, $opcionesEmision)) {
            return $vacio + ['motivo' => 'nc_parcial'];
        }

        $emisiones = self::cantidadesPorClaveDesdeEmisiones($ventaOrigenId);
        if ($emisiones === [] && $ventaNcId > 0) {
            $emisiones = self::cantidadesPorClaveDesdeEmisiones($ventaNcId);
        }
        if ($emisiones === []) {
            return $vacio + ['motivo' => 'sin_emisiones'];
        }

        $pedidoIds = $pivots->pluck('tiendanube_pedido_id')->map(static fn ($id) => (int) $id)->all();
        if ($pedidoIds === []) {
            $pedidoIds = [(int) TiendanubePedido::query()->where('venta_id', $ventaOrigenId)->value('id')];
            $pedidoIds = array_values(array_filter($pedidoIds));
        }

        $lineasLiberadas = 0;
        $pivotsBorrados = 0;
        $pedidosTocados = 0;

        foreach (array_unique($pedidoIds) as $pedidoId) {
            $pedido = TiendanubePedido::query()->with('lineas')->find($pedidoId);
            if (! $pedido) {
                continue;
            }

            $restantes = $emisiones;
            foreach ($pedido->lineas as $linea) {
                $clave = self::claveLinea(
                    (int) ($linea->articulo_id ?? 0),
                    (int) ($linea->combinacion_id ?? 0),
                    (int) ($linea->talle_id ?? 0)
                );
                if ($clave === '0|0|0' || ! isset($restantes[$clave]) || $restantes[$clave] <= 0.0001) {
                    continue;
                }
                $facturada = (float) $linea->cantidad_facturada;
                if ($facturada <= 0.0001) {
                    continue;
                }
                $liberar = min($facturada, $restantes[$clave]);
                $linea->cantidad_facturada = round(max(0., $facturada - $liberar), 4);
                $linea->save();
                $restantes[$clave] = round($restantes[$clave] - $liberar, 4);
                $lineasLiberadas++;
            }

            // Fallback: mismo artículo sin importar variante (TN a veces no graba talle en emisión).
            foreach ($pedido->lineas as $linea) {
                $articuloId = (int) ($linea->articulo_id ?? 0);
                if ($articuloId <= 0) {
                    continue;
                }
                $facturada = (float) $linea->cantidad_facturada;
                if ($facturada <= 0.0001) {
                    continue;
                }
                foreach ($restantes as $clave => $qty) {
                    if ($qty <= 0.0001) {
                        continue;
                    }
                    $partes = explode('|', (string) $clave);
                    if ((int) ($partes[0] ?? 0) !== $articuloId) {
                        continue;
                    }
                    $liberar = min($facturada, $qty);
                    $linea->cantidad_facturada = round(max(0., $facturada - $liberar), 4);
                    $linea->save();
                    $restantes[$clave] = round($qty - $liberar, 4);
                    $lineasLiberadas++;
                    break;
                }
            }

            $borrados = 0;
            if (Schema::hasTable('tiendanube_pedido_venta')) {
                $borrados = TiendanubePedidoVenta::query()
                    ->where('tiendanube_pedido_id', $pedido->id)
                    ->where('venta_id', $ventaOrigenId)
                    ->delete();
            }
            $pivotsBorrados += (int) $borrados;

            self::refrescarCabecera($pedido);
            $pedidosTocados++;
        }

        try {
            Log::info('tiendanube.nc.reabre_pedido', [
                'venta_origen_id' => $ventaOrigenId,
                'venta_nc_id' => $ventaNcId,
                'pedidos' => $pedidosTocados,
                'lineas_liberadas' => $lineasLiberadas,
                'pivots_borrados' => $pivotsBorrados,
            ]);
        } catch (\Throwable) {
            // No tumbar la NC por fallo de log.
        }

        return [
            'aplicado' => $pedidosTocados > 0 && ($lineasLiberadas > 0 || $pivotsBorrados > 0),
            'pedidos' => $pedidosTocados,
            'lineas_liberadas' => $lineasLiberadas,
            'pivots_borrados' => $pivotsBorrados,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $opcionesEmision
     */
    private static function esNcTotal(Venta $ventaOrigen, float $totalNcAbsoluto, ?array $opcionesEmision): bool
    {
        $anul = strtoupper(trim((string) (
            $opcionesEmision['fce_anulacion']
            ?? $opcionesEmision['fceAnulacion']
            ?? ''
        )));
        if ($anul === 'S') {
            return true;
        }

        $totalFac = abs((float) $ventaOrigen->total);
        if ($totalFac <= 0.0) {
            return $totalNcAbsoluto > 0.0;
        }

        return ($totalNcAbsoluto / $totalFac) >= 0.999;
    }

    /**
     * @return array<string, float> clave articulo|combinacion|talle => cantidad
     */
    private static function cantidadesPorClaveDesdeEmisiones(int $ventaId): array
    {
        $out = [];
        $rows = Venta_Emision::query()
            ->where('venta_id', $ventaId)
            ->whereNotNull('articulo_id')
            ->get(['articulo_id', 'combinacion_id', 'talle_id', 'cantidad']);

        foreach ($rows as $row) {
            $articuloId = (int) ($row->articulo_id ?? 0);
            if ($articuloId <= 0) {
                continue;
            }
            $clave = self::claveLinea(
                $articuloId,
                (int) ($row->combinacion_id ?? 0),
                (int) ($row->talle_id ?? 0)
            );
            $out[$clave] = ($out[$clave] ?? 0.) + abs((float) $row->cantidad);
        }

        return $out;
    }

    private static function claveLinea(int $articuloId, int $combinacionId, int $talleId): string
    {
        return $articuloId.'|'.$combinacionId.'|'.$talleId;
    }

    private static function refrescarCabecera(TiendanubePedido $pedido): void
    {
        $pedido->refresh();
        $pedido->load('lineas');

        $otraFacId = 0;
        if (Schema::hasTable('tiendanube_pedido_venta')) {
            $otraFacId = (int) (TiendanubePedidoVenta::query()
                ->where('tiendanube_pedido_id', $pedido->id)
                ->orderByDesc('id')
                ->value('venta_id') ?? 0);
        }

        $tieneCantidadFacturada = $pedido->lineas->contains(
            static fn (TiendanubePedidoLinea $linea): bool => (float) $linea->cantidad_facturada > 0.0001
        );

        if ($otraFacId > 0) {
            $pedido->venta_id = $otraFacId;
        } else {
            $pedido->venta_id = null;
            $pedido->facturado_at = null;
            $pedido->facturado_por_usuario_id = null;
        }

        $pedido->error_mensaje = null;

        if ($pedido->cubiertoPorCompleto()) {
            $pedido->estado_erp = TiendanubePedidoEstadoSupport::FACTURADO;
        } elseif ($tieneCantidadFacturada) {
            $pedido->estado_erp = TiendanubePedidoEstadoSupport::PARCIAL;
        } else {
            // Sacá "facturado" antes de evaluar: si no, ListoSupport corta con "Ya facturado".
            $pedido->estado_erp = TiendanubePedidoEstadoSupport::LISTO;
            $evaluacion = TiendanubePedidoListoSupport::evaluar($pedido);
            $pedido->estado_erp = ! empty($evaluacion['listo'])
                ? TiendanubePedidoEstadoSupport::LISTO
                : TiendanubePedidoEstadoSupport::BLOQUEADO_FISCAL;
        }

        $pedido->save();
    }
}
