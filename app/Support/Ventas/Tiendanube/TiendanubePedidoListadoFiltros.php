<?php

namespace App\Support\Ventas\Tiendanube;

/**
 * Filtros del listado de pedidos Tiendanube.
 */
final class TiendanubePedidoListadoFiltros
{
    /**
     * @return array{
     *   desde:?string,
     *   hasta:?string,
     *   estado_erp:?string,
     *   payment_status:?string,
     *   buscar:?string,
     *   consultar:bool
     * }
     */
    public static function resolverDesdeRequest(\Illuminate\Http\Request $request): array
    {
        $desde = trim((string) $request->input('desde', ''));
        $hasta = trim((string) $request->input('hasta', ''));
        $estado = trim((string) $request->input('estado_erp', ''));
        $payment = trim((string) $request->input('payment_status', 'paid'));
        $buscar = trim((string) $request->input('buscar', ''));
        $consultar = (string) $request->input('consultar', '') === '1'
            || $request->has('desde')
            || $request->has('estado_erp');

        return [
            'desde' => $desde !== '' ? $desde : null,
            'hasta' => $hasta !== '' ? $hasta : null,
            'estado_erp' => $estado !== '' ? $estado : null,
            'payment_status' => $payment !== '' ? $payment : null,
            'buscar' => $buscar !== '' ? $buscar : null,
            'consultar' => $consultar,
        ];
    }

    /**
     * @param  array<string,mixed>  $filtros
     * @return array<string,string>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        foreach (['desde', 'hasta', 'estado_erp', 'payment_status', 'buscar'] as $k) {
            if (! empty($filtros[$k])) {
                $out[$k] = (string) $filtros[$k];
            }
        }
        if (! empty($filtros['consultar'])) {
            $out['consultar'] = '1';
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Ventas\TiendanubePedido>  $query
     * @param  array<string,mixed>  $filtros
     */
    public static function aplicar($query, array $filtros): void
    {
        if (! empty($filtros['desde'])) {
            $query->whereDate('paid_at', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $query->whereDate('paid_at', '<=', $filtros['hasta']);
        }
        if (! empty($filtros['estado_erp'])) {
            $query->where('estado_erp', $filtros['estado_erp']);
        }
        if (! empty($filtros['payment_status'])) {
            $query->where('payment_status', $filtros['payment_status']);
        }
        if (! empty($filtros['buscar'])) {
            $b = $filtros['buscar'];
            $query->where(function ($q) use ($b) {
                $q->where('order_number', 'like', '%'.$b.'%')
                    ->orWhere('tiendanube_order_id', 'like', '%'.$b.'%')
                    ->orWhere('customer_name', 'like', '%'.$b.'%')
                    ->orWhere('customer_doc', 'like', '%'.$b.'%')
                    ->orWhere('customer_email', 'like', '%'.$b.'%');
            });
        }
    }
}
