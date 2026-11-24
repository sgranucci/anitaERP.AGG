<?php

namespace App\Support\Ventas\Mercadolibre;

use App\Models\Ventas\MercadolibrePedido;
use Illuminate\Support\Collection;

/**
 * Decide si un pedido Mercado Libre puede facturarse en lote (sin intervención).
 * Regla Cati: una factura por pack — se evalúa el grupo completo.
 */
final class MercadolibrePedidoListoSupport
{
    /**
     * @return array{
     *   listo:bool,
     *   motivos:list<string>,
     *   input?:array{
     *     puntoventa_id:int,
     *     deposito_id:int,
     *     cliente_id:?int,
     *     letra:string,
     *     medios_pago:list<array{cuentacaja_id:int,moneda_id:int,monto:float}>
     *   }
     * }
     */
    public static function evaluar(MercadolibrePedido $pedido): array
    {
        $pedido->loadMissing('lineas');
        $motivos = [];

        if ($pedido->estaFacturado()) {
            return ['listo' => false, 'motivos' => ['Ya facturado']];
        }
        if ($pedido->estado_erp === MercadolibrePedidoEstadoSupport::CANCELADO) {
            return ['listo' => false, 'motivos' => ['Orden cancelada']];
        }
        if (! $pedido->estaPagado()) {
            return ['listo' => false, 'motivos' => ['No está pagado']];
        }

        $grupo = self::grupoFacturable($pedido);
        foreach ($grupo as $hermano) {
            if ($hermano->id === $pedido->id) {
                continue;
            }
            if ($hermano->estaFacturado()) {
                $motivos[] = 'El pack ya tiene la orden #'.$hermano->order_id.' facturada';
            }
            if (! $hermano->estaPagado()) {
                $motivos[] = 'La orden #'.$hermano->order_id.' del pack no está pagada';
            }
        }

        $ancla = $grupo->first() ?? $pedido;
        $defaults = MercadolibreDefaultsSupport::resolver($ancla->shipping_json);
        $pvId = (int) ($ancla->puntoventa_id_sugerido ?: ($defaults['puntoventa_id'] ?? 0));
        $depId = (int) ($ancla->deposito_id_sugerido ?: ($defaults['deposito_id'] ?? 0));
        $cuentaId = (int) ($defaults['cuentacaja_id'] ?? 0);

        if ($pvId <= 0) {
            $motivos[] = 'Sin punto de venta (MeLi 21 / Full 27)';
        }
        if ($depId <= 0) {
            $motivos[] = 'Sin depósito default';
        }
        if ($cuentaId <= 0) {
            $motivos[] = 'Sin cuenta de caja 608';
        }

        $hayLineas = false;
        foreach ($grupo as $p) {
            foreach ($p->lineas as $linea) {
                $hayLineas = true;
                if (! $linea->articulo_id) {
                    $motivos[] = 'Orden #'.$p->order_id.' SKU no encontrado: '.($linea->seller_sku ?: $linea->nombre);
                }
            }
        }
        if (! $hayLineas) {
            $motivos[] = 'El pack/orden no tiene líneas';
        }

        $letra = strtoupper(trim((string) ($ancla->letra_sugerida ?: 'B'))) ?: 'B';
        $doc = preg_replace('/\D+/', '', (string) ($ancla->buyer_doc ?? '')) ?: '';
        if ($letra === 'A' && strlen($doc) < 11) {
            $motivos[] = 'Factura A requiere CUIT (11 dígitos) del comprador';
        }

        if ($motivos !== []) {
            return ['listo' => false, 'motivos' => array_values(array_unique($motivos))];
        }

        $totalGrupo = round((float) $grupo->sum(static fn (MercadolibrePedido $p) => (float) $p->total), 2);

        return [
            'listo' => true,
            'motivos' => [],
            'input' => [
                'puntoventa_id' => $pvId,
                'deposito_id' => $depId,
                'cliente_id' => null,
                'letra' => $letra,
                'medios_pago' => [[
                    'cuentacaja_id' => $cuentaId,
                    'moneda_id' => 1,
                    'monto' => $totalGrupo,
                ]],
            ],
        ];
    }

    public static function estaListo(MercadolibrePedido $pedido): bool
    {
        return self::evaluar($pedido)['listo'];
    }

    /**
     * Clave de factura: un pack = una factura; sin pack = la orden.
     */
    public static function claveGrupo(MercadolibrePedido $pedido): string
    {
        if ($pedido->pack_id) {
            return 'pack:'.(int) $pedido->seller_id.':'.(int) $pedido->pack_id;
        }

        return 'ord:'.(int) $pedido->id;
    }

    /**
     * @return Collection<int, MercadolibrePedido>
     */
    public static function grupoFacturable(MercadolibrePedido $pedido): Collection
    {
        $pedido->loadMissing('lineas');
        if (! $pedido->pack_id) {
            return collect([$pedido]);
        }

        return MercadolibrePedido::query()
            ->where('seller_id', $pedido->seller_id)
            ->where('pack_id', $pedido->pack_id)
            ->with('lineas')
            ->orderBy('id')
            ->get();
    }
}
