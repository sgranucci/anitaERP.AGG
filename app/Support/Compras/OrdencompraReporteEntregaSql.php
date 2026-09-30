<?php

namespace App\Support\Compras;

use App\Models\Stock\Recepcion_Proveedor;

/**
 * Cantidad entregada del informe de OC (l-pedprov).
 *
 * Muchas COM importadas de Anita tienen la cabecera ligada a la OC y la línea
 * sin ordencompra_articulo_id. Esas cantidades se reparten, en orden de línea,
 * entre las líneas del mismo artículo que todavía tienen saldo.
 */
final class OrdencompraReporteEntregaSql
{
    /**
     * Misma regla que el subquery SQL: llena líneas en orden (penvp_orden, id)
     * hasta agotar el pool o la capacidad de cada línea.
     *
     * @param  list<array{id: int, capacidad: float, penvp_orden: int|null}>  $lineas
     * @return array<int, float> id => cantidad asignada
     */
    public static function repartirPool(array $lineas, float $pool): array
    {
        usort($lineas, static function (array $a, array $b): int {
            $aNull = $a['penvp_orden'] === null;
            $bNull = $b['penvp_orden'] === null;
            if ($aNull !== $bNull) {
                return $aNull <=> $bNull;
            }
            $orden = (int) ($a['penvp_orden'] ?? 0) <=> (int) ($b['penvp_orden'] ?? 0);
            if ($orden !== 0) {
                return $orden;
            }

            return $a['id'] <=> $b['id'];
        });

        $resto = $pool;
        $asignado = [];
        foreach ($lineas as $linea) {
            $capacidad = max(0.0, (float) $linea['capacidad']);
            $toma = $resto > 0 ? min($capacidad, $resto) : 0.0;
            $asignado[(int) $linea['id']] = $toma;
            $resto -= $toma;
        }

        return $asignado;
    }

    public static function subqueryCantidadEntregada(): string
    {
        $neta = self::exprCantidadNeta('rp', 'rpa');
        $estado = self::q(Recepcion_Proveedor::ESTADO_CONFIRMADA);
        $tipos = self::q(Recepcion_Proveedor::TIPO_RECEPCION).','.self::q(Recepcion_Proveedor::TIPO_DEVOLUCION);

        return '(
            WITH pool AS (
                SELECT
                    rp.ordencompra_id,
                    rpa.articulo_id,
                    SUM('.$neta.') AS qty
                FROM recepcion_proveedor_articulo rpa
                INNER JOIN recepcion_proveedor rp ON rp.id = rpa.recepcion_proveedor_id
                WHERE rp.estado = '.$estado.'
                  AND rp.tipo IN ('.$tipos.')
                  AND rpa.ordencompra_articulo_id IS NULL
                  AND rp.ordencompra_id IS NOT NULL
                GROUP BY rp.ordencompra_id, rpa.articulo_id
            ),
            linked AS (
                SELECT
                    rpa.ordencompra_articulo_id AS linea_id,
                    SUM('.$neta.') AS qty
                FROM recepcion_proveedor_articulo rpa
                INNER JOIN recepcion_proveedor rp ON rp.id = rpa.recepcion_proveedor_id
                WHERE rp.estado = '.$estado.'
                  AND rp.tipo IN ('.$tipos.')
                  AND rpa.ordencompra_articulo_id IS NOT NULL
                GROUP BY rpa.ordencompra_articulo_id
            ),
            caps AS (
                SELECT
                    oa_ent.id,
                    oa_ent.ordencompra_id,
                    oa_ent.articulo_id,
                    oa_ent.penvp_orden,
                    GREATEST(oa_ent.cantidad - COALESCE(linked.qty, 0), 0) AS capacidad,
                    pool.qty AS pool_qty
                FROM ordencompra_articulo oa_ent
                INNER JOIN pool
                    ON pool.ordencompra_id = oa_ent.ordencompra_id
                   AND pool.articulo_id = oa_ent.articulo_id
                LEFT JOIN linked ON linked.linea_id = oa_ent.id
            ),
            running AS (
                SELECT
                    caps.id AS linea_id,
                    GREATEST(0, LEAST(
                        caps.capacidad,
                        caps.pool_qty - (
                            SUM(caps.capacidad) OVER (
                                PARTITION BY caps.ordencompra_id, caps.articulo_id
                                ORDER BY (caps.penvp_orden IS NULL), caps.penvp_orden, caps.id
                            ) - caps.capacidad
                        )
                    )) AS qty
                FROM caps
            )
            SELECT linea_id AS ordencompra_articulo_id, SUM(qty) AS cantidad_entregada
            FROM (
                SELECT linea_id, qty FROM linked
                UNION ALL
                SELECT linea_id, qty FROM running
                WHERE qty > 0.0000001
            ) entregado
            GROUP BY linea_id
        )';
    }

    public static function subqueryPrimeraRecepcion(): string
    {
        $estado = self::q(Recepcion_Proveedor::ESTADO_CONFIRMADA);
        $tipo = self::q(Recepcion_Proveedor::TIPO_RECEPCION);

        return '(
            SELECT
                linea_id AS ordencompra_articulo_id,
                MIN(recepcion_id) AS recepcion_id,
                MIN(numero_recepcion) AS numero_recepcion,
                MIN(fecha_recepcion) AS fecha_recepcion
            FROM (
                SELECT
                    rpa.ordencompra_articulo_id AS linea_id,
                    rp.id AS recepcion_id,
                    rp.numerorecepcion AS numero_recepcion,
                    rp.fecha AS fecha_recepcion
                FROM recepcion_proveedor_articulo rpa
                INNER JOIN recepcion_proveedor rp ON rp.id = rpa.recepcion_proveedor_id
                WHERE rp.estado = '.$estado.'
                  AND rp.tipo = '.$tipo.'
                  AND rpa.ordencompra_articulo_id IS NOT NULL
                UNION ALL
                SELECT
                    oa_rec.id AS linea_id,
                    rp.id AS recepcion_id,
                    rp.numerorecepcion AS numero_recepcion,
                    rp.fecha AS fecha_recepcion
                FROM recepcion_proveedor_articulo rpa
                INNER JOIN recepcion_proveedor rp ON rp.id = rpa.recepcion_proveedor_id
                INNER JOIN ordencompra_articulo oa_rec
                    ON oa_rec.ordencompra_id = rp.ordencompra_id
                   AND oa_rec.articulo_id = rpa.articulo_id
                WHERE rp.estado = '.$estado.'
                  AND rp.tipo = '.$tipo.'
                  AND rpa.ordencompra_articulo_id IS NULL
                  AND rp.ordencompra_id IS NOT NULL
            ) recs
            GROUP BY linea_id
        )';
    }

    private static function exprCantidadNeta(string $aliasRp, string $aliasRpa): string
    {
        $recepcion = self::q(Recepcion_Proveedor::TIPO_RECEPCION);
        $devolucion = self::q(Recepcion_Proveedor::TIPO_DEVOLUCION);

        return 'CASE '.$aliasRp.'.tipo
            WHEN '.$recepcion.' THEN '.$aliasRpa.'.cantidad + COALESCE('.$aliasRpa.'.cantidad_rechazada, 0)
            WHEN '.$devolucion.' THEN -('.$aliasRpa.'.cantidad + COALESCE('.$aliasRpa.'.cantidad_rechazada, 0))
            ELSE 0 END';
    }

    private static function q(string $valor): string
    {
        return "'".str_replace("'", "''", $valor)."'";
    }
}
