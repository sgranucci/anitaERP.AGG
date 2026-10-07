<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

/**
 * Evita duplicar en SICORE las retenciones que ya están en Anita (retmov/retimov)
 * cuando también existen en pagoproveedor_retencion (ERP).
 *
 * El ERP solo completa OPs que Anita no trajo (pagos nativos aún no reflejados en retmov).
 */
final class SicoreErpComplementoSupport
{
    /**
     * @param  list<array<string, mixed>>  $erp
     * @param  list<array<string, mixed>>  $yaPresentes
     * @return list<array<string, mixed>>
     */
    public static function soloNuevos(array $erp, array $yaPresentes): array
    {
        $claves = self::claves($yaPresentes);
        $out = [];
        foreach ($erp as $reg) {
            if (self::estaEnClaves($reg, $claves)) {
                continue;
            }
            $out[] = $reg;
        }

        return $out;
    }

    /**
     * La fila de Anita sin base (FC 0) duplica la retención del ERP, que sí trae base.
     * Se queda la del ERP: es la que coincide con el mayor y con el certificado.
     *
     * @param  list<array<string, mixed>>  $anita
     * @param  list<array<string, mixed>>  $erp
     * @return list<array<string, mixed>>
     */
    public static function descartarSinBaseCubiertos(array $anita, array $erp): array
    {
        $cubiertos = [];
        foreach ($erp as $reg) {
            if (abs((float) ($reg['base_calculo'] ?? 0)) < 0.01) {
                continue;
            }
            foreach (self::clavesSinBase($reg) as $clave) {
                $cubiertos[$clave] = true;
            }
        }
        if ($cubiertos === []) {
            return $anita;
        }

        $out = [];
        foreach ($anita as $reg) {
            if (abs((float) ($reg['base_calculo'] ?? 0)) >= 0.01) {
                $out[] = $reg;
                continue;
            }
            $cubre = false;
            foreach (self::clavesSinBase($reg) as $clave) {
                if (isset($cubiertos[$clave])) {
                    $cubre = true;
                    break;
                }
            }
            if (! $cubre) {
                $out[] = $reg;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $registros
     * @return array<string, true>
     */
    public static function claves(array $registros): array
    {
        $out = [];
        foreach ($registros as $reg) {
            foreach (self::clavesDeRegistro($reg) as $clave) {
                $out[$clave] = true;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, true>  $claves
     */
    public static function estaEnClaves(array $reg, array $claves): bool
    {
        foreach (self::clavesDeRegistro($reg) as $clave) {
            if (isset($claves[$clave])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function clavesDeRegistro(array $reg): array
    {
        $nroComp = (int) ($reg['nro_comp'] ?? 0);
        $nroCert = (int) ($reg['nro_cert'] ?? 0);
        $importe = number_format(abs((float) ($reg['importe'] ?? 0)), 2, '.', '');
        $out = [];
        if ($nroComp > 0 && $nroCert > 0) {
            $out[] = 'c:'.$nroComp.'|t:'.$nroCert;
        }
        if ($nroComp > 0 && $importe !== '0.00') {
            $out[] = 'c:'.$nroComp.'|i:'.$importe;
        }

        return $out;
    }

    /**
     * Certificado + importe, o proveedor + fecha + importe.
     * El nro de factura de Anita puede venir en 0 y no sirve para cruzar.
     *
     * @return list<string>
     */
    private static function clavesSinBase(array $reg): array
    {
        $importe = number_format(abs((float) ($reg['importe'] ?? 0)), 2, '.', '');
        if ($importe === '0.00') {
            return [];
        }

        $out = [];
        $nroCert = (int) ($reg['nro_cert'] ?? 0);
        if ($nroCert > 0) {
            $out[] = 't:'.$nroCert.'|i:'.$importe;
        }

        $proveedor = ltrim(trim((string) ($reg['codigo_proveedor'] ?? '')), '0');
        $fecha = (string) ($reg['fecha_retencion'] ?? '');
        if ($proveedor !== '' && $fecha !== '') {
            $out[] = 'p:'.$proveedor.'|f:'.$fecha.'|i:'.$importe;
        }

        return $out;
    }
}
