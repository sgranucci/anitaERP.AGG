<?php

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use Illuminate\Support\Facades\DB;

/**
 * Tope de devolución por línea = cantidad del COM origen − DEPs confirmadas previas.
 *
 * Evita devolver dos veces la misma recepción (caso COM + DEP + DEP + nueva COM).
 */
final class RecepcionProveedorDevolucionDisponibleSupport
{
    public static function claveLinea(int $articuloId, ?int $ordencompraArticuloId): string
    {
        return $articuloId.'-'.(int) ($ordencompraArticuloId ?? 0);
    }

    /**
     * @param  iterable<int, object|array<string, mixed>>  $lineas
     * @return array<string, float>
     */
    public static function sumarPorClaveLinea(iterable $lineas): array
    {
        $out = [];
        foreach ($lineas as $linea) {
            $articuloId = (int) (is_array($linea) ? ($linea['articulo_id'] ?? 0) : ($linea->articulo_id ?? 0));
            $ocaId = is_array($linea)
                ? ($linea['ordencompra_articulo_id'] ?? null)
                : ($linea->ordencompra_articulo_id ?? null);
            $cantidad = (float) (is_array($linea) ? ($linea['cantidad'] ?? 0) : ($linea->cantidad ?? 0));
            if ($articuloId <= 0 || abs($cantidad) < 0.000001) {
                continue;
            }
            $key = self::claveLinea($articuloId, $ocaId !== null ? (int) $ocaId : null);
            $out[$key] = ($out[$key] ?? 0.0) + $cantidad;
        }

        return $out;
    }

    /**
     * @param  array<string, float>  $recibido
     * @param  array<string, float>  $yaDevuelto
     * @return array<string, float>
     */
    public static function disponiblesPorClave(array $recibido, array $yaDevuelto): array
    {
        $out = [];
        foreach ($recibido as $key => $cant) {
            $out[$key] = max(0.0, (float) $cant - (float) ($yaDevuelto[$key] ?? 0.0));
        }

        return $out;
    }

    /**
     * @return array<string, float>
     */
    public static function cantidadesYaDevueltasConfirmadas(int $origenId, ?int $excluirRecepcionId = null): array
    {
        if ($origenId <= 0) {
            return [];
        }

        $query = DB::table('recepcion_proveedor_articulo as rpa')
            ->join('recepcion_proveedor as rp', 'rp.id', '=', 'rpa.recepcion_proveedor_id')
            ->where('rp.recepcion_referencia_id', $origenId)
            ->where('rp.tipo', Recepcion_Proveedor::TIPO_DEVOLUCION)
            ->where('rp.estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->select([
                'rpa.articulo_id',
                'rpa.ordencompra_articulo_id',
                'rpa.cantidad',
            ]);

        if ($excluirRecepcionId !== null && $excluirRecepcionId > 0) {
            $query->where('rp.id', '!=', $excluirRecepcionId);
        }

        return self::sumarPorClaveLinea($query->get());
    }

    /**
     * @return array<string, float>
     */
    public static function disponiblesDesdeOrigen(
        Recepcion_Proveedor $origen,
        ?int $excluirRecepcionId = null
    ): array {
        $origen->loadMissing('recepcion_proveedor_articulos');

        return self::disponiblesPorClave(
            self::sumarPorClaveLinea($origen->recepcion_proveedor_articulos),
            self::cantidadesYaDevueltasConfirmadas((int) $origen->id, $excluirRecepcionId)
        );
    }

    /**
     * @param  array<string, float>  $disponiblePorClave
     * @param  list<array<string, mixed>>  $items
     */
    public static function assertCantidadesDentroDisponible(array $disponiblePorClave, array $items): void
    {
        foreach ($items as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }
            $cant = (float) ($item['cantidad'] ?? 0);
            if ($cant <= 0.000001) {
                continue;
            }
            $key = self::claveLinea(
                (int) ($item['articulo_id'] ?? 0),
                isset($item['ordencompra_articulo_id']) ? (int) $item['ordencompra_articulo_id'] : null
            );
            $max = (float) ($disponiblePorClave[$key] ?? 0.0);
            if ($cant > $max + 0.000001) {
                throw new \RuntimeException(
                    'Línea '.($idx + 1).": cantidad a devolver ({$cant}) supera lo disponible "
                    ."({$max}) tras devoluciones confirmadas previas sobre esta recepción."
                );
            }
        }
    }

    public static function assertOrigenTieneDisponible(Recepcion_Proveedor $origen): void
    {
        $disponibles = self::disponiblesDesdeOrigen($origen);
        $total = array_sum($disponibles);
        if ($total <= 0.000001) {
            throw new \RuntimeException(
                'Esta recepción ya fue devuelta por completo; no hay cantidad disponible para una nueva DEP.'
            );
        }
    }

    /**
     * Ajusta ítems del formulario de devolución al saldo neto aún devolvible.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, float>  $disponiblePorClave
     * @return list<array<string, mixed>>
     */
    public static function aplicarDisponibleAItems(array $items, array $disponiblePorClave): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $key = self::claveLinea(
                (int) ($item['articulo_id'] ?? 0),
                isset($item['ordencompra_articulo_id']) ? (int) $item['ordencompra_articulo_id'] : null
            );
            $disponible = (float) ($disponiblePorClave[$key] ?? 0.0);
            $item['cantidad_recepcionada_origen'] = $disponible;
            $item['cantidad'] = $disponible;
            $item['cantidad_rechazada'] = 0;
            $item['accion_linea_oc'] = $disponible > 0.000001
                ? RecepcionProveedorAccionLineaOc::RECIBIR
                : RecepcionProveedorAccionLineaOc::PENDIENTE;
            $out[] = $item;
        }

        return array_values($out);
    }

    /**
     * Adjunta deps confirmadas a filas del listado con 1 sola query (IN de ids de la página).
     * No usa subselect correlacionada en el SELECT del index.
     *
     * @param  iterable<int, object>  $rows
     */
    public static function adjuntarDepsConfirmadasAlListado(iterable $rows): void
    {
        $idsCom = [];
        foreach ($rows as $row) {
            if ((string) ($row->tipo ?? '') === Recepcion_Proveedor::TIPO_RECEPCION) {
                $idsCom[] = (int) $row->id;
            }
            $row->deps_confirmadas = [];
        }

        $idsCom = array_values(array_unique(array_filter($idsCom)));
        if ($idsCom === []) {
            return;
        }

        $deps = DB::table('recepcion_proveedor')
            ->whereIn('recepcion_referencia_id', $idsCom)
            ->where('tipo', Recepcion_Proveedor::TIPO_DEVOLUCION)
            ->where('estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->orderBy('id')
            ->get(['id', 'recepcion_referencia_id', 'numerorecepcion']);

        $porOrigen = [];
        foreach ($deps as $dep) {
            $porOrigen[(int) $dep->recepcion_referencia_id][] = [
                'id' => (int) $dep->id,
                'numerorecepcion' => (int) $dep->numerorecepcion,
            ];
        }

        foreach ($rows as $row) {
            if ((string) ($row->tipo ?? '') !== Recepcion_Proveedor::TIPO_RECEPCION) {
                continue;
            }
            $row->deps_confirmadas = $porOrigen[(int) $row->id] ?? [];
        }
    }

    /**
     * @return list<array{id:int, numerorecepcion:int}>
     */
    public static function depsConfirmadasDeOrigen(int $origenId): array
    {
        if ($origenId <= 0) {
            return [];
        }

        return DB::table('recepcion_proveedor')
            ->where('recepcion_referencia_id', $origenId)
            ->where('tipo', Recepcion_Proveedor::TIPO_DEVOLUCION)
            ->where('estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->orderBy('id')
            ->get(['id', 'numerorecepcion'])
            ->map(static fn ($dep): array => [
                'id' => (int) $dep->id,
                'numerorecepcion' => (int) $dep->numerorecepcion,
            ])
            ->all();
    }
}
