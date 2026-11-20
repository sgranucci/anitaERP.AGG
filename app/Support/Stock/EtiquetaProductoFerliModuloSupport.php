<?php

namespace App\Support\Stock;

/**
 * Líneas EPL de módulos de línea en la etiqueta de producto Ferli.
 *
 * El nombre del módulo llega como «12 A (35-40) 1-2-3-3-2-1».
 * En etiqueta se parte: cabecera «12 A 35-40» y numeración abajo.
 * Entre módulos: línea fina LO (casi sin ocupar espacio).
 */
final class EtiquetaProductoFerliModuloSupport
{
    /** Máximo de módulos: 3 × 2 líneas = 6, caben sin achicar la fuente. */
    public const MAX_MODULOS = 3;

    /** Paso entre líneas de texto del mismo módulo (dots). */
    public const PASO_LINEA = 22;

    /** Grosor de la línea entre módulos (dots). */
    public const GROSOR_SEPARADOR = 1;

    /** Hueco extra antes/después del separador (dots). */
    public const HUECO_SEPARADOR = 3;

    /** Largo del separador a lo ancho del texto rotado (dots). */
    public const LARGO_SEPARADOR = 170;

    /**
     * @param  iterable<int, object>  $modulos  con codigo/nombre
     * @return list<string> líneas ya escapadas para EPL (sin comillas externas)
     */
    public static function lineasDesdeModulos(iterable $modulos, ?int $maxModulos = null): array
    {
        $lineas = [];
        foreach (self::bloquesDesdeModulos($modulos, $maxModulos) as $bloque) {
            foreach ($bloque as $linea) {
                $lineas[] = $linea;
            }
        }

        return $lineas;
    }

    /**
     * Un bloque = líneas de un módulo (cabecera + numeración).
     *
     * @param  iterable<int, object>  $modulos  con codigo/nombre
     * @return list<list<string>>
     */
    public static function bloquesDesdeModulos(iterable $modulos, ?int $maxModulos = null): array
    {
        $maxModulos ??= self::MAX_MODULOS;
        $bloques = [];
        $vistos = 0;
        foreach ($modulos as $modulo) {
            if ($vistos >= $maxModulos) {
                break;
            }
            $texto = trim((string) (($modulo->nombre ?? '') ?: ($modulo->codigo ?? '')));
            if ($texto === '') {
                continue;
            }
            $lineas = [];
            foreach (self::partirNombre($texto) as $linea) {
                $lineas[] = self::escaparEpl($linea);
            }
            if ($lineas === []) {
                continue;
            }
            $bloques[] = $lineas;
            $vistos++;
        }

        return $bloques;
    }

    /**
     * Comandos EPL (A + LO) para los módulos, con separador fino entre cada uno.
     * Coordenadas alineadas a la etiqueta producto: A{x},{y},1,… (texto rotado 90°).
     *
     * @param  iterable<int, object>|list<list<string>>  $modulosOBloques
     * @return list<string> comandos sin salto de línea final
     */
    public static function comandosEpl(
        iterable $modulosOBloques,
        int $xInicio,
        int $y = 5,
        ?int $maxModulos = null
    ): array {
        $bloques = self::normalizarBloques($modulosOBloques, $maxModulos);
        if ($bloques === []) {
            return [];
        }

        $comandos = [];
        $x = $xInicio;
        $ultimo = count($bloques) - 1;

        foreach ($bloques as $i => $lineas) {
            foreach ($lineas as $texto) {
                $comandos[] = 'A'.$x.','.$y.',1,2,1,1,N,"'.$texto.'"';
                $x -= self::PASO_LINEA;
            }

            if ($i >= $ultimo) {
                continue;
            }

            // Hueco mínimo + línea de 1 dot entre módulos (como el trazo a lapicera).
            $x -= self::HUECO_SEPARADOR;
            $comandos[] = 'LO'.$x.','.$y.','.self::GROSOR_SEPARADOR.','.self::LARGO_SEPARADOR;
            $x -= self::GROSOR_SEPARADOR + self::HUECO_SEPARADOR;
        }

        return $comandos;
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

    /**
     * @param  iterable<int, object>|list<list<string>>  $modulosOBloques
     * @return list<list<string>>
     */
    private static function normalizarBloques(iterable $modulosOBloques, ?int $maxModulos): array
    {
        $items = is_array($modulosOBloques) ? $modulosOBloques : iterator_to_array($modulosOBloques, false);
        if ($items === []) {
            return [];
        }

        $primero = reset($items);
        if (is_array($primero)) {
            return array_values($items);
        }

        return self::bloquesDesdeModulos($items, $maxModulos);
    }

    private static function escaparEpl(string $texto): string
    {
        return str_replace('"', "'", $texto);
    }
}
