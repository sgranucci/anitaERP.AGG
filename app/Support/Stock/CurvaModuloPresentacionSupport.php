<?php

namespace App\Support\Stock;

use Illuminate\Support\Facades\DB;

/**
 * Presentación de una curva de talles como «un módulo + cuántos módulos».
 *
 * Si las cantidades son un múltiplo entero de un módulo del catálogo (modulo_talle),
 * se muestra la curva de ese módulo y la cantidad de módulos.
 * Si la curva no está cargada pero se repite y el módulo resultante tiene la cantidad
 * de pares de algún módulo del catálogo (8, 12, etc.), también se muestra así.
 * Una curva que no arma ese tamaño (por ejemplo 6-4-2) queda con los talles reales y 1 módulo.
 * Entre varios módulos que dividen, gana el de mayor cantidad (el módulo chico).
 */
final class CurvaModuloPresentacionSupport
{
    /** @var array<string, true>|null */
    private static ?array $firmasCatalogo = null;

    /**
     * @param  array<int|string, float|int>  $cantidadesPorTalle
     * @param  array<int, array<int|string, int>>|null  $catalogo  null = leer modulo_talle
     * @return array{
     *     medidas: array<int|string, int|float>,
     *     pares_modulo: int|float,
     *     modulos: int,
     *     total: int|float,
     *     coincide: bool
     * }
     */
    public static function presentar(array $cantidadesPorTalle, ?array $catalogo = null): array
    {
        $reales = self::sumarPositivos($cantidadesPorTalle);
        $total = 0.0;
        foreach ($reales as $cantidad) {
            $total += (float) $cantidad;
        }

        $sinModulo = [
            'medidas' => $reales,
            'pares_modulo' => $total,
            'modulos' => $total > 0 ? 1 : 0,
            'total' => $total,
            'coincide' => false,
        ];

        $enteros = self::enterosEstrictos($reales);
        if ($enteros === null || $enteros === []) {
            return $sinModulo;
        }

        $firmas = $catalogo === null
            ? self::firmasCatalogo()
            : self::firmasDesdeCatalogo($catalogo);

        $mejorK = 0;
        $mejorCurva = null;
        foreach ($firmas as $firma => $_) {
            $curva = self::curvaDesdeFirma($firma);
            $k = self::multiploExacto($enteros, $curva);
            if ($k !== null && $k > $mejorK) {
                $mejorK = $k;
                $mejorCurva = $curva;
            }
        }

        if ($mejorCurva === null || $mejorK < 1) {
            $porRepeticion = self::presentarPorRepeticion($enteros, self::totalesPares($firmas));
            if ($porRepeticion !== null) {
                return $porRepeticion;
            }

            return $sinModulo;
        }

        $pares = 0;
        foreach ($mejorCurva as $cantidad) {
            $pares += $cantidad;
        }

        return [
            'medidas' => $mejorCurva,
            'pares_modulo' => $pares,
            'modulos' => $mejorK,
            'total' => array_sum($enteros),
            'coincide' => true,
        ];
    }

    /**
     * Curva repetida que no está en el catálogo, pero el módulo unitario tiene
     * la cantidad de pares de algún módulo cargado.
     *
     * @param  array<int|string, int>  $enteros
     * @param  array<int, true>  $totalesPares
     * @return array{
     *     medidas: array<int|string, int>,
     *     pares_modulo: int,
     *     modulos: int,
     *     total: int,
     *     coincide: bool
     * }|null
     */
    private static function presentarPorRepeticion(array $enteros, array $totalesPares): ?array
    {
        $mcd = 0;
        foreach ($enteros as $cantidad) {
            $mcd = $mcd === 0 ? $cantidad : self::mcd($mcd, $cantidad);
        }
        if ($mcd < 2) {
            return null;
        }

        for ($k = $mcd; $k >= 2; $k--) {
            if ($mcd % $k !== 0) {
                continue;
            }
            $curva = [];
            foreach ($enteros as $talle => $cantidad) {
                $curva[$talle] = intdiv($cantidad, $k);
            }
            $pares = array_sum($curva);
            if (! isset($totalesPares[$pares])) {
                continue;
            }

            return [
                'medidas' => $curva,
                'pares_modulo' => $pares,
                'modulos' => $k,
                'total' => array_sum($enteros),
                'coincide' => true,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, true>  $firmas
     * @return array<int, true>
     */
    private static function totalesPares(array $firmas): array
    {
        $totales = [];
        foreach ($firmas as $firma => $_) {
            $pares = 0;
            foreach (self::curvaDesdeFirma($firma) as $cantidad) {
                $pares += $cantidad;
            }
            if ($pares > 0) {
                $totales[$pares] = true;
            }
        }

        return $totales;
    }

    private static function mcd(int $a, int $b): int
    {
        $a = abs($a);
        $b = abs($b);
        while ($b !== 0) {
            $resto = $a % $b;
            $a = $b;
            $b = $resto;
        }

        return $a;
    }

    /**
     * @param  list<array{medida?: mixed, cantidad?: mixed}>  $lista
     * @param  array<int, array<int|string, int>>|null  $catalogo
     * @return array{
     *     medidas: array<int|string, int|float>,
     *     pares_modulo: int|float,
     *     modulos: int,
     *     total: int|float,
     *     coincide: bool
     * }
     */
    public static function presentarDesdeLista(array $lista, ?array $catalogo = null): array
    {
        $map = [];
        foreach ($lista as $item) {
            $medida = self::claveTalle((string) ($item['medida'] ?? ''));
            if ($medida === '' || $medida === 0) {
                continue;
            }
            $map[$medida] = (float) ($map[$medida] ?? 0) + (float) ($item['cantidad'] ?? 0);
        }

        return self::presentar($map, $catalogo);
    }

    public static function olvidarCatalogo(): void
    {
        self::$firmasCatalogo = null;
    }

    /**
     * @param  array<int|string, float|int>  $cantidades
     * @return array<int|string, float>
     */
    private static function sumarPositivos(array $cantidades): array
    {
        $out = [];
        foreach ($cantidades as $talle => $cantidad) {
            $clave = self::claveTalle((string) $talle);
            if ($clave === '' || $clave === 0) {
                continue;
            }
            $n = (float) $cantidad;
            if ($n <= 0.0001) {
                continue;
            }
            $out[$clave] = (float) (($out[$clave] ?? 0) + $n);
        }

        return $out;
    }

    /**
     * Enteros positivos. Si hay un decimal o un negativo ya filtrado, no se arma módulo.
     *
     * @param  array<int|string, float>  $reales
     * @return array<int|string, int>|null
     */
    private static function enterosEstrictos(array $reales): ?array
    {
        $out = [];
        foreach ($reales as $talle => $cantidad) {
            $redondeado = (int) round($cantidad);
            if ($redondeado <= 0 || abs($cantidad - $redondeado) > 0.001) {
                return null;
            }
            $out[$talle] = $redondeado;
        }

        return $out;
    }

    /**
     * @param  array<int|string, int>  $pedido
     * @param  array<int|string, int>  $modulo
     */
    private static function multiploExacto(array $pedido, array $modulo): ?int
    {
        if ($modulo === [] || count($pedido) !== count($modulo)) {
            return null;
        }

        $k = null;
        foreach ($modulo as $talle => $cantModulo) {
            if ($cantModulo <= 0 || ! isset($pedido[$talle])) {
                return null;
            }
            if ($pedido[$talle] % $cantModulo !== 0) {
                return null;
            }
            $ki = intdiv($pedido[$talle], $cantModulo);
            if ($ki < 1) {
                return null;
            }
            if ($k === null) {
                $k = $ki;
            } elseif ($k !== $ki) {
                return null;
            }
        }

        return $k;
    }

    /**
     * @return array<string, true>
     */
    private static function firmasCatalogo(): array
    {
        if (self::$firmasCatalogo !== null) {
            return self::$firmasCatalogo;
        }

        $filas = DB::table('modulo_talle as mt')
            ->join('talle as t', 't.id', '=', 'mt.talle_id')
            ->where('mt.cantidad', '>', 0)
            ->get(['mt.modulo_id', 't.nombre', 'mt.cantidad']);

        $porModulo = [];
        foreach ($filas as $fila) {
            $clave = self::claveTalle((string) $fila->nombre);
            if ($clave === '' || $clave === 0) {
                continue;
            }
            $porModulo[(int) $fila->modulo_id][$clave] = (int) $fila->cantidad;
        }

        self::$firmasCatalogo = self::firmasDesdeCatalogo($porModulo);

        return self::$firmasCatalogo;
    }

    /**
     * @param  array<int, array<int|string, int>>  $catalogo
     * @return array<string, true>
     */
    private static function firmasDesdeCatalogo(array $catalogo): array
    {
        $set = [];
        foreach ($catalogo as $curva) {
            $firma = self::firma(is_array($curva) ? $curva : []);
            if ($firma !== '') {
                $set[$firma] = true;
            }
        }

        return $set;
    }

    /**
     * @param  array<int|string, int|float>  $curva
     */
    private static function firma(array $curva): string
    {
        $norm = [];
        foreach ($curva as $talle => $cantidad) {
            $clave = self::claveTalle((string) $talle);
            $n = (int) round((float) $cantidad);
            if ($clave === '' || $clave === 0 || $n <= 0) {
                continue;
            }
            $norm[$clave] = $n;
        }
        if ($norm === []) {
            return '';
        }

        uksort($norm, static function ($a, $b): int {
            return strnatcmp((string) $a, (string) $b);
        });

        $partes = [];
        foreach ($norm as $talle => $cantidad) {
            $partes[] = $talle.':'.$cantidad;
        }

        return implode('|', $partes);
    }

    /**
     * @return array<int|string, int>
     */
    private static function curvaDesdeFirma(string $firma): array
    {
        $curva = [];
        foreach (explode('|', $firma) as $parte) {
            [$talle, $cantidad] = array_pad(explode(':', $parte, 2), 2, '0');
            $clave = self::claveTalle($talle);
            $curva[$clave] = (int) $cantidad;
        }

        return $curva;
    }

    private static function claveTalle(string $nombre): int|string
    {
        $nombre = trim($nombre);
        if ($nombre !== '' && ctype_digit($nombre)) {
            return (int) $nombre;
        }

        return $nombre;
    }
}
