<?php

namespace App\Support\Ventas\Tiendanube;

use App\Models\Ventas\TiendanubePedido;

/**
 * Decide si un pedido Tiendanube puede facturarse en lote (sin intervención).
 */
final class TiendanubePedidoListoSupport
{
    /**
     * @return array{
     *   listo:bool,
     *   motivos:list<string>,
     *   input?:array{
     *     puntoventa_id:int,
     *     deposito_id:int,
     *     listaprecio_id:int,
     *     cliente_id:?int,
     *     receptor:array<string,mixed>,
     *     medios_pago:list<array{cuentacaja_id:int,moneda_id:int,monto:float}>,
     *     forzar_cf:bool,
     *     descuentoimportepie:float
     *   }
     * }
     */
    public static function evaluar(TiendanubePedido $pedido): array
    {
        $pedido->loadMissing('lineas');
        $motivos = [];

        if ($pedido->estaFacturado()) {
            return ['listo' => false, 'motivos' => ['Ya facturado']];
        }
        if (! $pedido->estaPagado()) {
            return ['listo' => false, 'motivos' => ['No está pagado']];
        }

        $productos = $pedido->lineas->where('tipo', 'producto');
        if ($productos->isEmpty()) {
            $motivos[] = 'Sin líneas de producto';
        }
        foreach ($productos as $linea) {
            if (! $linea->articulo_id) {
                $motivos[] = 'SKU sin artículo: '.($linea->sku ?: $linea->nombre);
            } elseif (! $linea->combinacion_id || ! $linea->talle_id) {
                $motivos[] = 'SKU sin combinación/talle: '.($linea->sku ?: '');
            }
        }

        foreach ($pedido->lineas->where('tipo', 'envio') as $envio) {
            if (! $envio->articulo_id && (float) $envio->price > 0.0001) {
                $motivos[] = 'Falta artículo de envío (TIENDANUBE_ARTICULO_ENVIO_SKU)';
            }
        }

        $pvId = (int) ($pedido->puntoventa_id_sugerido
            ?: TiendanubePedidoMaestrosSupport::puntoventaDefault()?->id
            ?: 0);
        if ($pvId <= 0) {
            $motivos[] = 'Sin punto de venta default';
        }

        $depId = (int) ($pedido->deposito_id_sugerido
            ?: TiendanubePedidoMaestrosSupport::depositoDefault()?->id
            ?: 0);
        if ($depId <= 0) {
            $motivos[] = 'Sin depósito default';
        }

        $cuentacajaId = TiendanubePedidoMaestrosSupport::sugerirCuentacajaId($pedido->gateway);
        if (! $cuentacajaId) {
            $motivos[] = 'Sin cuentas de caja con uso «'
                .TiendanubeUsoCuentacajaSupport::nombre()
                .'». Asigná medios en el ABM de cuentas de caja.';
        }

        $doc = preg_replace('/\D+/', '', (string) ($pedido->customer_doc ?? '')) ?: '';
        $limite = (float) config('facturacion_local.limite_resto', 400000);
        $forzarCf = false;
        if ($doc === '') {
            if ((float) $pedido->total > $limite) {
                $motivos[] = 'Faltan datos fiscales (CUIT/DNI) y el total supera el límite CF';
            } else {
                $forzarCf = true;
            }
        }

        if ($motivos !== []) {
            return ['listo' => false, 'motivos' => array_values(array_unique($motivos))];
        }

        $listaId = TiendanubePedidoMaestrosSupport::listaprecioIdDefault();

        return [
            'listo' => true,
            'motivos' => [],
            'input' => [
                'puntoventa_id' => $pvId,
                'deposito_id' => $depId,
                'listaprecio_id' => $listaId,
                'cliente_id' => $pedido->cliente_id ? (int) $pedido->cliente_id : null,
                'receptor' => [
                    'nombre' => $pedido->customer_name,
                    'nrodoc' => $doc !== '' ? $doc : null,
                    'email' => $pedido->customer_email,
                ],
                'medios_pago' => [[
                    'cuentacaja_id' => (int) $cuentacajaId,
                    'moneda_id' => 1,
                    'monto' => round((float) $pedido->total, 2),
                ]],
                'forzar_cf' => $forzarCf,
                'descuentoimportepie' => 0.,
            ],
        ];
    }

    public static function estaListo(TiendanubePedido $pedido): bool
    {
        return self::evaluar($pedido)['listo'];
    }
}
