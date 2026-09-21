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

        $productos = $pedido->lineas->where('tipo', 'producto')->filter(
            static fn ($linea): bool => ! $linea->estaCubierta()
        );
        if ($productos->isEmpty() && $pedido->totalPendiente() <= 0.0001) {
            $motivos[] = 'Sin cantidades pendientes';
        }
        foreach ($productos as $linea) {
            if (! $linea->articulo_id) {
                $motivos[] = 'SKU sin artículo: '.($linea->sku ?: $linea->nombre);
            } elseif (! $linea->combinacion_id || ! $linea->talle_id) {
                $motivos[] = 'SKU sin combinación/talle: '.($linea->sku ?: '');
            }
        }

        foreach ($pedido->lineas->where('tipo', 'envio') as $envio) {
            if ($envio->estaCubierta()) {
                continue;
            }
            if (! $envio->articulo_id && (float) $envio->price > 0.0001) {
                $motivos[] = 'Falta artículo de envío (TIENDANUBE_ARTICULO_ENVIO_SKU)';
            }
        }

        $resuelto = TiendanubePedidoMaestrosSupport::resolverPuntoventaYDeposito(
            $pedido->store_id,
            $pedido->puntoventa_id_sugerido,
            $pedido->deposito_id_sugerido,
        );
        $pvId = (int) $resuelto['puntoventa_id'];
        if ($pvId <= 0) {
            $motivos[] = 'Sin punto de venta default';
        }

        $depId = (int) $resuelto['deposito_id'];
        if ($depId <= 0) {
            $motivos[] = 'Sin depósito default';
        }

        $cuentacajaId = TiendanubePedidoMaestrosSupport::sugerirCuentacajaId(
            $pedido->gateway,
            $pedido->gateway_name,
            is_array($pedido->payment_json) ? $pedido->payment_json : null,
            $pedido->store_id,
        );
        if (! $cuentacajaId) {
            $motivos[] = 'Sin cuentas de caja con uso «'
                .TiendanubeUsoCuentacajaSupport::nombre($pedido->store_id)
                .'». Asigná medios en el ABM de cuentas de caja.';
        }

        $doc = preg_replace('/\D+/', '', (string) ($pedido->customer_doc ?? '')) ?: '';
        $limite = (float) config('facturacion_local.limite_resto', 400000);
        $totalPendiente = $pedido->totalPendiente();
        $forzarCf = false;
        if ($doc === '') {
            if ($totalPendiente > $limite) {
                $motivos[] = 'Faltan datos fiscales (CUIT/DNI) y el total supera el límite CF';
            } else {
                $forzarCf = true;
            }
        }

        if ($motivos !== []) {
            return ['listo' => false, 'motivos' => array_values(array_unique($motivos))];
        }

        $listaId = TiendanubePedidoMaestrosSupport::listaprecioIdDefault($pedido->store_id);

        return [
            'listo' => true,
            'motivos' => [],
            'input' => [
                'puntoventa_id' => $pvId,
                'deposito_id' => $depId,
                'listaprecio_id' => $listaId,
                'cliente_id' => null,
                'letra' => TiendanubePedidoReceptorSupport::LETRA_B,
                'receptor' => [
                    'nombre' => TiendanubePedidoReceptorSupport::nombreDesdePedido($pedido),
                    'numerodocumento' => $doc !== '' ? $doc : null,
                    'email' => $pedido->customer_email,
                    'domicilio' => TiendanubePedidoReceptorSupport::domicilioDesdePedido($pedido),
                ],
                'medios_pago' => [[
                    'cuentacaja_id' => (int) $cuentacajaId,
                    'moneda_id' => 1,
                    'monto' => round($totalPendiente, 2),
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
