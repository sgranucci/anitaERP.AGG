<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Ventas\LocalVenta;
use App\Services\Stock\PrecioServiceFerli;

/**
 * Precio de lista del local para POS y para el reemplazo del legajo de cambio/devolución.
 */
final class FacturacionLocalPrecioArticuloSupport
{
    /**
     * @return array{precio:float,precio_lista:float,incluyeimpuesto_lista:string,listaprecio_id:int|null}
     */
    public static function paraLocal(int $localId, int $articuloId, int $combinacionId = 0, int $talleId = 0): array
    {
        $local = $localId > 0 ? LocalVenta::query()->find($localId) : null;
        $listaId = (int) ($local?->listaprecio_id ?? 0);
        $precio = 0.;
        $listaUsada = $listaId;

        try {
            $svc = app(PrecioServiceFerli::class);
            $fecha = now()->format('Y-m-d');
            $comb = $combinacionId > 0 ? $combinacionId : null;

            // Lista del local (Lugano/Web/etc.) manda.
            if ($articuloId > 0 && $listaId > 0) {
                $precio = $svc->precioVigente($articuloId, $listaId, $comb, $fecha);
            }

            // Fallback: lista por rango de talle (ABM Ferli clásico).
            if ($precio <= 0 && $articuloId > 0 && $talleId > 0) {
                $filas = $svc->asignaPrecio($articuloId, $comb, $talleId, $fecha);
                $precio = PrecioServiceFerli::primerPrecioNumerico($filas);
                if (is_array($filas[0] ?? null) && (int) ($filas[0]['listaprecio_id'] ?? 0) > 0) {
                    $listaUsada = (int) $filas[0]['listaprecio_id'];
                }
            }
        } catch (\Throwable $e) {
            $precio = 0.;
        }

        $listaFinal = $listaUsada > 0 ? $listaUsada : $listaId;
        $flagLista = FacturacionLocalPrecioIvaSupport::flagLista($listaFinal > 0 ? $listaFinal : null);
        $precioMostrar = FacturacionLocalPrecioIvaSupport::precioParaPos($precio, $listaFinal > 0 ? $listaFinal : null);

        return [
            'precio' => $precioMostrar,
            'precio_lista' => $precio,
            'incluyeimpuesto_lista' => $flagLista,
            'listaprecio_id' => $listaFinal > 0 ? $listaFinal : ($local?->listaprecio_id ? (int) $local->listaprecio_id : null),
        ];
    }
}
