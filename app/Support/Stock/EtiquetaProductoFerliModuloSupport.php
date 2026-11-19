<?php

namespace App\Support\Stock;

/**
 * Líneas EPL de módulos de línea en la etiqueta de producto Ferli.
 *
 * El nombre del módulo llega como «12 A (35-40) 1-2-3-3-2-1».
 * En etiqueta se parte: cabecera «12 A 35-40» y numeración abajo.
 */
final class EtiquetaProductoFerliModuloSupport
{
    /** Máximo de módulos: 3 × 2 líneas = 6, caben sin achicar la fuente. */
    public const MAX_MODULOS = 3;

    /**
     * @param  iterable<int, object>  $modulos  con codigo/nombre
     * @return list<string> líneas ya escapadas para EPL (sin comillas externas)
     */
    public static function lineasDesdeModulos(iterable $modulos, ?int $maxModulos = null): array
    {
        $maxModulos ??= self::MAX_MODULOS;
        $lineas = [];
        $vistos = 0;
        foreach ($modulos as $modulo) {
            if ($vistos >= $maxModulos) {
                break;
            }
            $texto = trim((string) (($modulo->nombre ?? '') ?: ($modulo->codigo ?? '')));
            if ($texto === '') {
                continue;
            }
            foreach (self::partirNombre($texto) as $linea) {
                $lineas[] = self::escaparEpl($linea);
            }
            $vistos++;
        }

        return $lineas;
    }

    /**
     * @return list<string> una o dos líneas (cabecera + numeración)
     */
    public static function partirNombre(string $nombre): array
    {
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre) ?? $nombre);
        if ($nombre === '') {
            return [];
        }

        if (preg_match('/^(.*?)\s*\((\d+)\s*-\s*(\d+)\)\s*(.*)$/u', $nombre, $m)) {
            $prefijo = trim($m[1]);
            $rango = $m[2].'-'.$m[3];
            $cabecera = trim($prefijo.' '.$rango);
            $numeracion = trim($m[4]);

            $out = [];
            if ($cabecera !== '') {
                $out[] = $cabecera;
            }
            if ($numeracion !== '') {
                $out[] = $numeracion;
            }

            return $out !== [] ? $out : [$nombre];
        }

        return [$nombre];
    }

    private static function escaparEpl(string $texto): string
    {
        return str_replace('"', "'", $texto);
    }
}
