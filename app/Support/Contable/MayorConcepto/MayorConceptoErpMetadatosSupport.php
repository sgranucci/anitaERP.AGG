<?php

declare(strict_types=1);

namespace App\Support\Contable\MayorConcepto;

/**
 * Metadatos de visualización del mayor por concepto cuando la fuente es ERP.
 *
 * Solo enriquece descripción / emisor / CUIT / cotización mostrada. No altera
 * importes, cuentas, conceptos ni el ruteo por subd_ref_tipo (saldos intactos).
 */
final class MayorConceptoErpMetadatosSupport
{
    /**
     * Número de asiento operativo Anita (`subd_nro_operacion` / `ctav_nro_asiento`).
     *
     * En ERP: preferir `anita_nro_asiento` si existe; si no, `numeroasiento`.
     * Nunca la PK `asiento.id` — eso desdobla la conciliación id ↔ número.
     */
    public static function numeroAsientoOperativo(int|string|null $anitaNroAsiento, int|string|null $numeroAsiento): int
    {
        $anita = (int) ($anitaNroAsiento ?? 0);
        if ($anita > 0) {
            return $anita;
        }

        return (int) ($numeroAsiento ?? 0);
    }

    /**
     * Descripción genérica del circuito SP → ingreso/egreso (`Pago SP 11317`).
     */
    public static function esDescripcionGenericaPagoSp(string $descripcion): bool
    {
        return preg_match('/^Pago SP\s+\d+\s*$/i', trim($descripcion)) === 1;
    }

    /**
     * Arma una descripción útil a partir de SP / proveedor / cheque, sin inventar montos.
     */
    public static function enriquecerDescripcion(
        string $descripcionActual,
        string $detalleSp = '',
        string $nombreProveedor = '',
        string $nroCheque = '',
        string $codigoSp = '',
    ): string {
        $actual = trim($descripcionActual);
        $detalleSp = trim($detalleSp);
        $nombreProveedor = trim($nombreProveedor);
        $nroCheque = trim($nroCheque);
        $codigoSp = trim($codigoSp);

        $base = $actual;
        if (self::esDescripcionGenericaPagoSp($actual) || $actual === '') {
            if ($detalleSp !== '') {
                $base = $detalleSp;
            } elseif ($nombreProveedor !== '') {
                $base = $nombreProveedor;
                if ($codigoSp !== '') {
                    $base .= ' SP '.$codigoSp;
                }
            }
        }

        if ($nroCheque !== '' && $nroCheque !== '0'
            && ! preg_match('/\bCh:\s*\d+/i', $base)) {
            $base = trim($base.' Ch: '.$nroCheque);
        }

        return $base !== '' ? $base : $actual;
    }

    /**
     * Cotización de referencia para la columna del reporte (no se usa en conversión).
     */
    public static function cotizacionVista(float $cotizacionMovimiento, float $cotizacionCaja): float
    {
        if ($cotizacionCaja >= 0.01) {
            return $cotizacionCaja;
        }

        return $cotizacionMovimiento > 0 ? $cotizacionMovimiento : 1.0;
    }

    public static function pareceCodigoProveedor(string $texto): bool
    {
        $texto = trim($texto);
        if ($texto === '') {
            return false;
        }

        return preg_match('/^\d{1,8}$/', $texto) === 1;
    }

    /**
     * @return array{emisor: string, cuit: string}
     */
    public static function resolverEmisorCuitVista(
        string $emisorMeta,
        string $cuitMeta,
        string $codigoEmisorLinea,
        ?object $promae,
        int $maxNombre = 15,
    ): array {
        $emisor = trim($emisorMeta);
        $cuit = trim($cuitMeta);
        $codigo = trim($codigoEmisorLinea);

        $usarPromae = $promae !== null && (
            $cuit === ''
            || $emisor === ''
            || self::pareceCodigoProveedor($emisor)
        );

        if ($usarPromae) {
            $nombre = trim((string) ($promae->prom_nombre ?? ''));
            if ($nombre !== '' && ($emisor === '' || self::pareceCodigoProveedor($emisor))) {
                $emisor = mb_strlen($nombre) > $maxNombre
                    ? mb_substr($nombre, 0, $maxNombre)
                    : $nombre;
            }
            if ($cuit === '') {
                $cuit = trim((string) ($promae->prom_cuit ?? ''));
            }
        }

        if ($emisor === '' && $codigo !== '' && ! self::pareceCodigoProveedor($codigo)) {
            $emisor = $codigo;
        }

        return [
            'emisor' => $emisor,
            'cuit' => $cuit,
        ];
    }
}
