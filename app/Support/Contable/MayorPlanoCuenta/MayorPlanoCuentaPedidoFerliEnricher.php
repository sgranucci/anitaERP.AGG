<?php

declare(strict_types=1);

namespace App\Support\Contable\MayorPlanoCuenta;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa Pedido y el nombre del cliente de la factura en el mayor de Ferli.
 */
class MayorPlanoCuentaPedidoFerliEnricher
{
    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public function enriquecer(array $filas): array
    {
        if (! MayorPlanoCuentaPedidoFerliSupport::mostrarColumna() || $filas === []) {
            return $filas;
        }

        $ventaIds = [];
        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }
            $ventaId = (int) ($fila['venta_id'] ?? 0);
            if ($ventaId > 0) {
                $ventaIds[$ventaId] = $ventaId;
            }
        }
        if ($ventaIds === [] || ! Schema::hasTable('venta')) {
            return $filas;
        }

        $ventas = $this->leerVentas(array_values($ventaIds));
        $pedidosTn = $this->leerPedidosTiendanube(array_values($ventaIds));
        $pedidosTn += $this->leerPedidosCambioMarketplace(array_values($ventaIds), $pedidosTn);

        foreach ($filas as $idx => $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }
            $ventaId = (int) ($fila['venta_id'] ?? 0);
            $venta = $ventas[$ventaId] ?? null;
            if ($venta === null) {
                $filas[$idx]['pedido_nro'] = '';

                continue;
            }

            $resuelto = MayorPlanoCuentaPedidoFerliSupport::resolver(
                (string) ($venta->nombre ?? ''),
                (string) ($venta->leyenda ?? ''),
                (string) ($pedidosTn[$ventaId] ?? ''),
            );
            $filas[$idx]['pedido_nro'] = $resuelto['pedido'];

            $nombreActual = trim((string) ($filas[$idx]['emisor_nombre'] ?? ''));
            if ($resuelto['nombre'] !== ''
                && ($nombreActual === '' || MayorPlanoCuentaPedidoFerliSupport::esConsumidorFinal($nombreActual))
            ) {
                $filas[$idx]['emisor_nombre'] = $resuelto['nombre'];
            }
        }

        return $filas;
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array<int, object>
     */
    private function leerVentas(array $ventaIds): array
    {
        $out = [];
        foreach (array_chunk($ventaIds, 500) as $lote) {
            $rows = DB::table('venta')
                ->whereIn('id', $lote)
                ->get(['id', 'nombre', 'leyenda']);
            foreach ($rows as $row) {
                $out[(int) $row->id] = $row;
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array<int, string>
     */
    private function leerPedidosTiendanube(array $ventaIds): array
    {
        $out = [];
        if (Schema::hasTable('tiendanube_pedido') && Schema::hasColumn('tiendanube_pedido', 'venta_id')) {
            foreach (array_chunk($ventaIds, 500) as $lote) {
                $rows = DB::table('tiendanube_pedido')
                    ->whereIn('venta_id', $lote)
                    ->whereNotNull('order_number')
                    ->where('order_number', '<>', '')
                    ->get(['venta_id', 'order_number']);
                foreach ($rows as $row) {
                    $out[(int) $row->venta_id] = trim((string) $row->order_number);
                }
            }
        }

        if (! Schema::hasTable('tiendanube_pedido_venta') || ! Schema::hasTable('tiendanube_pedido')) {
            return $out;
        }

        $faltantes = array_values(array_filter(
            $ventaIds,
            static fn (int $id): bool => ! isset($out[$id])
        ));
        foreach (array_chunk($faltantes, 500) as $lote) {
            if ($lote === []) {
                continue;
            }
            $rows = DB::table('tiendanube_pedido_venta as tv')
                ->join('tiendanube_pedido as tn', 'tn.id', '=', 'tv.tiendanube_pedido_id')
                ->whereIn('tv.venta_id', $lote)
                ->whereNotNull('tn.order_number')
                ->where('tn.order_number', '<>', '')
                ->get(['tv.venta_id', 'tn.order_number']);
            foreach ($rows as $row) {
                $ventaId = (int) $row->venta_id;
                if (! isset($out[$ventaId])) {
                    $out[$ventaId] = trim((string) $row->order_number);
                }
            }
        }

        return $out;
    }

    /**
     * Factura de reemplazo o NC de un cambio de Tiendanube: el pedido está
     * en el legajo, no en la venta.
     *
     * @param  list<int>  $ventaIds
     * @param  array<int, string>  $yaResueltos
     * @return array<int, string>
     */
    private function leerPedidosCambioMarketplace(array $ventaIds, array $yaResueltos): array
    {
        if (! Schema::hasTable('cambio_devolucion_marketplace') || ! Schema::hasTable('tiendanube_pedido')) {
            return [];
        }

        $faltantes = array_values(array_filter(
            $ventaIds,
            static fn (int $id): bool => ! isset($yaResueltos[$id]) || $yaResueltos[$id] === ''
        ));
        if ($faltantes === []) {
            return [];
        }

        $out = [];
        foreach (['venta_reemplazo_id', 'venta_nc_id', 'venta_original_id'] as $columna) {
            $pendientes = array_values(array_filter(
                $faltantes,
                static fn (int $id): bool => ! isset($out[$id])
            ));
            foreach (array_chunk($pendientes, 500) as $lote) {
                if ($lote === []) {
                    continue;
                }
                $rows = DB::table('cambio_devolucion_marketplace as c')
                    ->join('tiendanube_pedido as tn', 'tn.id', '=', 'c.tiendanube_pedido_id')
                    ->whereIn('c.'.$columna, $lote)
                    ->whereNotNull('tn.order_number')
                    ->where('tn.order_number', '<>', '')
                    ->get(['c.'.$columna.' as venta_id', 'tn.order_number']);
                foreach ($rows as $row) {
                    $ventaId = (int) $row->venta_id;
                    if ($ventaId > 0 && ! isset($out[$ventaId])) {
                        $out[$ventaId] = trim((string) $row->order_number);
                    }
                }
            }
        }

        return $out;
    }
}
