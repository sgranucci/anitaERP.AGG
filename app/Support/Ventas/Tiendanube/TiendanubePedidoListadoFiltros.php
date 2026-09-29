<?php

namespace App\Support\Ventas\Tiendanube;

use App\Support\Listado\ListadoOrdenamientoSupport;
use Illuminate\Support\Facades\Cache;

/**
 * Filtros del listado de pedidos Tiendanube.
 */
final class TiendanubePedidoListadoFiltros
{
    private const CACHE_ORDEN = 'tiendanube-pedidos-orden';

    /**
     * Columnas ordenables de la grilla (whitelist SQL).
     *
     * @return array<string, array{column: string, label: string, type: string}>
     */
    public static function camposOrdenables(): array
    {
        return [
            'order_number' => ['column' => 'tiendanube_pedido.order_number', 'label' => 'Nº TN', 'type' => 'texto'],
            'store_id' => ['column' => 'tiendanube_pedido.store_id', 'label' => 'Tienda', 'type' => 'texto'],
            'tiendanube_order_id' => ['column' => 'tiendanube_pedido.tiendanube_order_id', 'label' => 'ID interno', 'type' => 'entero'],
            'paid_at' => ['column' => 'tiendanube_pedido.paid_at', 'label' => 'Pagado', 'type' => 'fecha'],
            'customer_name' => ['column' => 'tiendanube_pedido.customer_name', 'label' => 'Cliente', 'type' => 'texto'],
            'customer_doc' => ['column' => 'tiendanube_pedido.customer_doc', 'label' => 'Doc', 'type' => 'texto'],
            'gateway_name' => ['column' => 'tiendanube_pedido.gateway_name', 'label' => 'Gateway', 'type' => 'texto'],
            'total' => ['column' => 'tiendanube_pedido.total', 'label' => 'Total', 'type' => 'decimal'],
            'status' => ['column' => 'tiendanube_pedido.status', 'label' => 'Estado TN', 'type' => 'texto'],
            'estado_erp' => ['column' => 'tiendanube_pedido.estado_erp', 'label' => 'Estado ERP', 'type' => 'texto'],
            'venta_id' => ['column' => 'tiendanube_pedido.venta_id', 'label' => 'Venta', 'type' => 'entero'],
        ];
    }

    /**
     * @return array{
     *   desde:?string,
     *   hasta:?string,
     *   estado_erp:?string,
     *   status_tn:?string,
     *   payment_status:?string,
     *   buscar:?string,
     *   store_id:?string,
     *   consultar:bool,
     *   orden:list<array{campo:string, dir:string}>
     * }
     */
    public static function resolverDesdeRequest(\Illuminate\Http\Request $request): array
    {
        $desde = trim((string) $request->input('desde', ''));
        $hasta = trim((string) $request->input('hasta', ''));
        $estado = trim((string) $request->input('estado_erp', ''));
        $statusTn = strtolower(trim((string) $request->input('status_tn', '')));
        $payment = trim((string) $request->input('payment_status', 'paid'));
        $buscar = trim((string) $request->input('buscar', ''));
        $storeId = trim((string) $request->input('store_id', ''));
        $consultar = (string) $request->input('consultar', '') === '1'
            || $request->has('desde')
            || $request->has('estado_erp')
            || $request->has('status_tn')
            || $request->has('store_id');

        $statusPermitidos = array_keys(TiendanubePedidoStatusExternoSupport::etiquetas());
        if ($statusTn !== '' && ! in_array($statusTn, $statusPermitidos, true)) {
            $statusTn = '';
        }
        $storesPermitidos = array_column(TiendanubeTiendasSupport::paraVista(), 'store_id');
        if ($storeId !== '' && ! in_array($storeId, $storesPermitidos, true)) {
            $storeId = '';
        }

        $camposOrden = self::camposOrdenables();
        if ($request->exists('sort') || $request->exists('sort_definido')) {
            $orden = ListadoOrdenamientoSupport::normalizar($request->input('sort'), $camposOrden);
            self::recordarOrden($orden);
        } else {
            $orden = self::leerOrdenRecordado();
        }

        return [
            'desde' => $desde !== '' ? $desde : null,
            'hasta' => $hasta !== '' ? $hasta : null,
            'estado_erp' => $estado !== '' ? $estado : null,
            'status_tn' => $statusTn !== '' ? $statusTn : null,
            'payment_status' => $payment !== '' ? $payment : null,
            'buscar' => $buscar !== '' ? $buscar : null,
            'store_id' => $storeId !== '' ? $storeId : null,
            'consultar' => $consultar,
            'orden' => $orden,
        ];
    }

    /**
     * @param  list<array{campo: string, dir: string}>  $orden
     */
    public static function recordarOrden(array $orden): void
    {
        $usuarioId = auth()->id();
        if ($usuarioId === null || (int) $usuarioId <= 0) {
            return;
        }

        Cache::forever(generaKey(self::CACHE_ORDEN), array_values($orden));
    }

    /**
     * @return list<array{campo: string, dir: string}>
     */
    public static function leerOrdenRecordado(): array
    {
        $usuarioId = auth()->id();
        if ($usuarioId === null || (int) $usuarioId <= 0) {
            return [];
        }

        return ListadoOrdenamientoSupport::normalizar(
            Cache::get(generaKey(self::CACHE_ORDEN)),
            self::camposOrdenables()
        );
    }

    /**
     * @param  list<array{campo: string, dir: string}>  $orden
     */
    public static function textoOrden(array $orden): string
    {
        $campos = self::camposOrdenables();
        $partes = [];
        foreach ($orden as $criterio) {
            $campo = (string) ($criterio['campo'] ?? '');
            if ($campo === '' || ! isset($campos[$campo])) {
                continue;
            }
            $dir = ($criterio['dir'] ?? 'asc') === 'desc' ? 'descendente' : 'ascendente';
            $partes[] = $campos[$campo]['label'].' '.$dir;
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  array<string,mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        foreach (['desde', 'hasta', 'estado_erp', 'status_tn', 'payment_status', 'buscar', 'store_id'] as $k) {
            if (! empty($filtros[$k])) {
                $out[$k] = (string) $filtros[$k];
            }
        }
        if (! empty($filtros['consultar'])) {
            $out['consultar'] = '1';
        }

        $orden = is_array($filtros['orden'] ?? null) ? $filtros['orden'] : [];

        return array_merge($out, ListadoOrdenamientoSupport::paraQueryString($orden));
    }

    /**
     * Sin criterios: fecha de pago descendente y luego id.
     * Con criterios: el orden pedido y id como desempate.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Ventas\TiendanubePedido>  $query
     * @param  list<array{campo: string, dir: string}>  $orden
     */
    public static function aplicarOrden($query, array $orden): void
    {
        if ($orden === []) {
            $query->orderByDesc('tiendanube_pedido.paid_at')->orderByDesc('tiendanube_pedido.id');

            return;
        }

        ListadoOrdenamientoSupport::aplicar($query, $orden, self::camposOrdenables());
        $query->orderByDesc('tiendanube_pedido.id');
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
        if (! empty($filtros['status_tn'])) {
            $query->where('status', $filtros['status_tn']);
        }
        if (! empty($filtros['payment_status'])) {
            $query->where('payment_status', $filtros['payment_status']);
        }
        if (! empty($filtros['store_id'])) {
            $query->where('store_id', $filtros['store_id']);
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
