<?php

namespace App\Support\Caja;

/**
 * Leyenda del asiento de un ingreso/egreso (la que ve el mayor).
 *
 * 1. Detalle del encabezado.
 * 2. Si no hay, los comentarios de las cuentas de caja.
 * 3. Si tampoco, tipo de operación y banco o cuenta.
 */
final class IngresoEgresoAsientoDescripcionSupport
{
    public static function esGenerica(?string $texto): bool
    {
        $txt = self::normalizar($texto);

        return $txt === '' || $txt === 'movimiento de caja' || $txt === 'alta de movimiento de caja';
    }

    /**
     * @param  list<string>  $observacionesCaja
     * @param  list<string>  $bancos
     * @param  list<string>  $cuentas
     */
    public static function resolver(
        ?string $detalleEncabezado,
        array $observacionesCaja,
        string $tipoNombre,
        array $bancos,
        array $cuentas,
    ): string {
        $detalle = trim((string) $detalleEncabezado);
        if (! self::esGenerica($detalle)) {
            return self::recortar($detalle);
        }

        $deCuentas = self::unir($observacionesCaja);
        if ($deCuentas !== '') {
            return $deCuentas;
        }

        return self::componer($tipoNombre, $bancos, $cuentas);
    }

    /**
     * @param  list<string>  $observaciones
     */
    public static function unir(array $observaciones): string
    {
        $vistas = [];
        $partes = [];
        foreach ($observaciones as $observacion) {
            $texto = trim((string) $observacion);
            if (self::esGenerica($texto)) {
                continue;
            }
            $clave = self::normalizar($texto);
            if (isset($vistas[$clave])) {
                continue;
            }
            $vistas[$clave] = true;
            $partes[] = $texto;
        }

        return self::recortar(implode(' · ', $partes));
    }

    /**
     * @param  list<string>  $bancos
     * @param  list<string>  $cuentas
     */
    public static function componer(string $tipoNombre, array $bancos, array $cuentas): string
    {
        $tipo = trim($tipoNombre);
        $banco = self::primeroUtil($bancos);
        if ($banco !== '') {
            return self::recortar(trim($tipo.' '.$banco));
        }

        $cuenta = self::primeroUtil($cuentas);
        if ($cuenta !== '') {
            return self::recortar(trim($tipo.' '.$cuenta));
        }

        return $tipo !== '' ? self::recortar($tipo) : 'Movimiento de caja';
    }

    /** @param  list<string>  $textos */
    private static function primeroUtil(array $textos): string
    {
        foreach ($textos as $texto) {
            $texto = trim((string) $texto);
            if ($texto !== '') {
                return $texto;
            }
        }

        return '';
    }

    private static function normalizar(?string $texto): string
    {
        $txt = trim((string) $texto);
        $txt = preg_replace('/\s+/u', ' ', $txt) ?? $txt;

        return mb_strtolower($txt);
    }

    private static function recortar(string $texto): string
    {
        return mb_substr(trim($texto), 0, 255);
    }
}
