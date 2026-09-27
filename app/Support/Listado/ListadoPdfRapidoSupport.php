<?php

namespace App\Support\Listado;

/**
 * Parte un listado PDF en varias tablas chicas.
 *
 * DomPDF calcula el ancho de columna recorriendo todas las celdas de la tabla.
 * Una sola tabla de miles de filas es mucho más lenta que varias de ~200.
 */
final class ListadoPdfRapidoSupport
{
    public const FILAS_POR_TABLA = 200;

    /**
     * @param  list<array<string, mixed>>  $filas  filas de ListadoAgrupacionSupport::segmentar
     * @return list<list<array<string, mixed>>>
     */
    public static function lotes(array $filas, int $tamano = self::FILAS_POR_TABLA): array
    {
        if ($filas === []) {
            return [[]];
        }

        $tamano = max(40, $tamano);
        $lotes = [];
        $actual = [];
        $datos = 0;
        $cantidad = count($filas);

        for ($i = 0; $i < $cantidad; $i++) {
            $fila = $filas[$i];
            $esEncabezado = ($fila['type'] ?? '') === 'header';

            if ($datos >= $tamano && $actual !== []) {
                $ultimo = $actual[array_key_last($actual)];
                if (($ultimo['type'] ?? '') === 'header') {
                    array_pop($actual);
                    $lotes[] = $actual;
                    $actual = [$ultimo];
                    $datos = 0;
                } else {
                    $lotes[] = $actual;
                    $actual = [];
                    $datos = 0;
                }
            }

            $actual[] = $fila;
            if (! $esEncabezado) {
                $datos++;
            }
        }

        if ($actual !== []) {
            $lotes[] = $actual;
        }

        return $lotes === [] ? [[]] : $lotes;
    }
}
