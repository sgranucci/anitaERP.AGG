<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

/**
 * Detección de saltos en secuencias numéricas de comprobantes (ERP / Anita).
 */
final class GastronomiaNumeracionHuecosSupport
{
    /**
     * Detecta saltos respetando el orden de emisión (p. ej. ventas ordenadas por codigo).
     *
     * @param  list<int>  $numerosEnOrdenEmision
     * @return list<array{desde:int,hasta:int,faltantes:string,cantidad:int}>
     */
    public static function detectarHuecosSecuencia(array $numerosEnOrdenEmision): array
    {
        $huecos = [];
        $prev = null;

        foreach ($numerosEnOrdenEmision as $n) {
            $n = (int) $n;
            if ($n <= 0) {
                continue;
            }
            if ($prev !== null && $n > $prev + 1) {
                $faltantes = range($prev + 1, $n - 1);
                $huecos[] = [
                    'desde' => $prev,
                    'hasta' => $n,
                    'faltantes' => implode(',', array_map('strval', $faltantes)),
                    'cantidad' => count($faltantes),
                ];
            }
            $prev = $n;
        }

        return $huecos;
    }

    /**
     * Detecta huecos reales dentro del tramo ocupado por el circuito auditado, completando la
     * secuencia con comprobantes de otros circuitos que comparten PV y numeración ARCA.
     *
     * @param  iterable<int|string, int>  $numerosCircuito
     * @param  iterable<int|string, int>  $numerosCompartidos
     * @return list<array{desde:int,hasta:int,faltantes:string,cantidad:int}>
     */
    public static function detectarHuecosSecuenciaCompartida(
        iterable $numerosCircuito,
        iterable $numerosCompartidos,
    ): array {
        $objetivo = self::normalizarNumeros($numerosCircuito);
        if (count($objetivo) < 2) {
            return [];
        }

        $desde = min($objetivo);
        $hasta = max($objetivo);
        $secuencia = array_values(array_filter(
            self::normalizarNumeros($numerosCompartidos),
            static fn (int $numero): bool => $numero >= $desde && $numero <= $hasta,
        ));

        return self::detectarHuecos($secuencia);
    }

    /**
     * Detecta saltos en una secuencia numérica ya ordenada ascendentemente (rango continuo).
     *
     * @param  list<int>  $numerosOrdenados  Números únicos, orden ascendente
     * @return list<array{desde:int,hasta:int,faltantes:string,cantidad:int}>
     */
    public static function detectarHuecos(array $numerosOrdenados): array
    {
        $huecos = [];
        $prev = null;

        foreach ($numerosOrdenados as $n) {
            $n = (int) $n;
            if ($n <= 0) {
                continue;
            }
            if ($prev !== null && $n > $prev + 1) {
                $faltantes = range($prev + 1, $n - 1);
                $huecos[] = [
                    'desde' => $prev,
                    'hasta' => $n,
                    'faltantes' => implode(',', array_map('strval', $faltantes)),
                    'cantidad' => count($faltantes),
                ];
            }
            $prev = $n;
        }

        return $huecos;
    }

    /**
     * @param  iterable<int|string, int>  $numeros
     * @return list<int>
     */
    public static function normalizarNumeros(iterable $numeros): array
    {
        $lista = [];
        foreach ($numeros as $n) {
            $n = (int) $n;
            if ($n > 0) {
                $lista[$n] = $n;
            }
        }
        $lista = array_values($lista);
        sort($lista, SORT_NUMERIC);

        return $lista;
    }
}
