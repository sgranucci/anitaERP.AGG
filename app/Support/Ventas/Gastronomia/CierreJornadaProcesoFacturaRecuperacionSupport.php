<?php

namespace App\Support\Ventas\Gastronomia;

use InvalidArgumentException;

/**
 * Re-emisión de facturas del cierre Waitry desde snapshot (mismas comandas y numeración).
 */
final class CierreJornadaProcesoFacturaRecuperacionSupport
{
    /**
     * @param  list<array<string, mixed>>  $facturasRecuperacion
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array{
     *   numero:int,
     *   comandas:list<array<string,mixed>>,
     *   total:float,
     *   cantidad_comandas:int,
     *   waitry_order_ids:list<int>,
     *   numerocomprobante_forzado:int
     * }>
     */
    public static function armarLotesDesdeRecuperacion(array $facturasRecuperacion, array $movimientos): array
    {
        $movPorId = [];
        foreach (CierreJornadaProcesoFacturaComandasSupport::movimientosFacturacion($movimientos) as $mov) {
            $wid = (int) ($mov['waitry_order_id'] ?? 0);
            if ($wid > 0) {
                $movPorId[$wid] = $mov;
            }
        }

        $lotes = [];
        foreach ($facturasRecuperacion as $fac) {
            if (! is_array($fac)) {
                continue;
            }

            $waitryIds = array_values(array_filter(array_map(
                static fn ($id) => (int) $id,
                $fac['waitry_order_ids'] ?? [],
            ), static fn (int $id) => $id > 0));

            $comandas = [];
            foreach ($waitryIds as $wid) {
                if (! isset($movPorId[$wid])) {
                    throw new InvalidArgumentException(
                        'La comanda Waitry #'.$wid.' no está en la clasificación actual (lote '.($fac['lote'] ?? '?').').',
                    );
                }
                $comandas[] = $movPorId[$wid];
            }

            if ($comandas === []) {
                throw new InvalidArgumentException(
                    'El lote '.($fac['lote'] ?? '?').' no tiene comandas Waitry para rearmar.',
                );
            }

            $numeroForzado = self::numerocomprobanteDesdeReferencia((string) ($fac['factura'] ?? ''));
            if ($numeroForzado <= 0) {
                throw new InvalidArgumentException(
                    'No se pudo leer el número de comprobante de «'.($fac['factura'] ?? '').'».',
                );
            }

            $lotes[] = [
                'numero' => (int) ($fac['lote'] ?? 0),
                'comandas' => $comandas,
                'total' => (float) ($fac['total'] ?? CierreJornadaProcesoFacturaComandasSupport::totalComandas($comandas)),
                'cantidad_comandas' => count($comandas),
                'waitry_order_ids' => $waitryIds,
                'numerocomprobante_forzado' => $numeroForzado,
            ];
        }

        if ($lotes === []) {
            throw new InvalidArgumentException('No hay lotes para recuperar en el snapshot.');
        }

        usort($lotes, static fn (array $a, array $b) => ($a['numero'] ?? 0) <=> ($b['numero'] ?? 0));

        return $lotes;
    }

    public static function numerocomprobanteDesdeReferencia(string $factura): int
    {
        $normalizada = trim(str_replace(' ', '', $factura));
        if (preg_match('/-(\d+)$/', $normalizada, $coincidencias)) {
            return (int) $coincidencias[1];
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public static function datosRecuperacionDesdePayload(array $payload): ?array
    {
        $recuperacion = $payload['factura_proceso_emision_recuperacion'] ?? null;
        if (is_array($recuperacion) && ! empty($recuperacion['facturas'])) {
            return $recuperacion;
        }

        $activa = $payload['factura_proceso_emision'] ?? null;
        if (is_array($activa) && ! empty($activa['facturas'])) {
            return $activa;
        }

        return null;
    }

    /**
     * @return list<int>
     */
    public static function ventaIdsDesdeRecuperacion(array $recuperacion): array
    {
        $ids = [];
        foreach ($recuperacion['facturas'] ?? [] as $fac) {
            if (! is_array($fac)) {
                continue;
            }
            $ventaId = (int) ($fac['venta_id'] ?? 0);
            if ($ventaId > 0) {
                $ids[] = $ventaId;
            }
        }

        return array_values(array_unique($ids));
    }
}
