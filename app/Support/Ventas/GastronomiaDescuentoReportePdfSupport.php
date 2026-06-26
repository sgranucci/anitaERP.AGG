<?php

namespace App\Support\Ventas;

final class GastronomiaDescuentoReportePdfSupport
{
    /** Grupos de descuento/cliente por tabla PDF (cada grupo = 3 subcolumnas). */
    public const MAX_GRUPOS_COLUMNAS_POR_TABLA = 4;

    /**
     * Parte la vista consolidada en tablas que entren en legal apaisado.
     *
     * @param  array<string, mixed>|null  $vistaColumnas
     * @return list<array{
     *   columnas:list<array<string,mixed>>,
     *   filas:list<array<string,mixed>>,
     *   totales_por_columna:list<array<string,mixed>>,
     *   indice:int,
     *   total_partes:int
     * }>
     */
    public static function particionesVistaColumnas(?array $vistaColumnas): array
    {
        if (! is_array($vistaColumnas) || ($vistaColumnas['columnas'] ?? []) === []) {
            return [];
        }

        $columnas = $vistaColumnas['columnas'];
        $filas = $vistaColumnas['filas'] ?? [];
        $totalesPorColumna = $vistaColumnas['totales_por_columna'] ?? [];
        $totalesPorClave = [];

        foreach ($totalesPorColumna as $totCol) {
            $clave = (string) ($totCol['clave'] ?? '');
            if ($clave !== '') {
                $totalesPorClave[$clave] = $totCol;
            }
        }

        $partesRaw = array_chunk($columnas, self::MAX_GRUPOS_COLUMNAS_POR_TABLA);
        $totalPartes = count($partesRaw);
        $particiones = [];

        foreach ($partesRaw as $idx => $colsChunk) {
            $claves = array_map(fn (array $col) => (string) ($col['clave'] ?? ''), $colsChunk);
            $totalesChunk = [];
            foreach ($claves as $clave) {
                if ($clave !== '' && isset($totalesPorClave[$clave])) {
                    $totalesChunk[] = $totalesPorClave[$clave];
                }
            }

            $particiones[] = [
                'columnas' => $colsChunk,
                'filas' => $filas,
                'totales_por_columna' => $totalesChunk,
                'indice' => $idx + 1,
                'total_partes' => $totalPartes,
            ];
        }

        return $particiones;
    }
}
