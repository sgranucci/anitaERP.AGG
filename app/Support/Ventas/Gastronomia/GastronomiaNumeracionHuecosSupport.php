<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\Venta;

/**
 * Detección de saltos en secuencias numéricas de comprobantes (ERP / Anita).
 *
 * La serie fiscal es (punto de venta, código AFIP). La fecha del comprobante
 * ordena esa serie; la jornada no ocupa ni libera un número.
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

    /**
     * Huecos de la serie en una fecha de comprobante.
     *
     * El tramo es el de los números con esa fecha, extendido al último número
     * de la fecha anterior y al primero de la siguiente. Un número existente
     * en el tramo ocupa su lugar cualquiera sea su jornada o su fecha.
     *
     * @return list<array{desde:int,hasta:int,faltantes:string,cantidad:int}>
     */
    public static function huecosSeriePorFechaComprobante(int $puntoventaId, int $codigoAfip, string $fecha): array
    {
        if ($puntoventaId <= 0 || $codigoAfip <= 0 || $fecha === '') {
            return [];
        }

        $enFecha = self::numerosSerie($puntoventaId, $codigoAfip, function ($query) use ($fecha): void {
            $query->whereDate('fecha', $fecha);
        });
        if ($enFecha === []) {
            return [];
        }

        $anterior = self::extremoSerie($puntoventaId, $codigoAfip, $fecha, true);
        $siguiente = self::extremoSerie($puntoventaId, $codigoAfip, $fecha, false);
        $desde = $anterior ?? $enFecha[0];
        $hasta = $siguiente ?? $enFecha[array_key_last($enFecha)];
        if ($desde > $hasta) {
            return [];
        }

        $ocupados = self::numerosSerie($puntoventaId, $codigoAfip, function ($query) use ($desde, $hasta): void {
            $query->whereBetween('numerocomprobante', [$desde, $hasta]);
        });

        return self::detectarHuecos($ocupados);
    }

    public static function numeroExisteEnSerie(int $puntoventaId, int $codigoAfip, int $numero): bool
    {
        if ($puntoventaId <= 0 || $codigoAfip <= 0 || $numero <= 0) {
            return false;
        }

        return Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->where('codigo_afip', $codigoAfip)
            ->where('numerocomprobante', $numero)
            ->exists();
    }

    /**
     * @param  callable(\Illuminate\Database\Eloquent\Builder<Venta>): void  $acotar
     * @return list<int>
     */
    private static function numerosSerie(int $puntoventaId, int $codigoAfip, callable $acotar): array
    {
        $query = Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->where('codigo_afip', $codigoAfip);
        $acotar($query);

        return self::normalizarNumeros($query->pluck('numerocomprobante'));
    }

    private static function extremoSerie(int $puntoventaId, int $codigoAfip, string $fecha, bool $anterior): ?int
    {
        $query = Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->where('codigo_afip', $codigoAfip)
            ->whereDate('fecha', $anterior ? '<' : '>', $fecha);
        $valor = $anterior
            ? $query->max('numerocomprobante')
            : $query->min('numerocomprobante');

        $numero = (int) $valor;

        return $numero > 0 ? $numero : null;
    }
}
