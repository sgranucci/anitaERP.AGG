<?php

namespace App\Support\Compras;

/**
 * Anita `tcomp_genera_asi` / `in_genera_asiento` (factprov.c / a-compprov.c):
 *
 * | Valor | Asiento | Bien de uso |
 * |---|---|---|
 * | N | No | No |
 * | S | Sí | No |
 * | U | Sí | Sí (ofrece cargar a-biemae) |
 *
 * En ERP se separa en `asientocontable` (S/N) y `genera_bien_de_uso` (S/N).
 */
final class TipotransaccionCompraGeneraAsiAnitaSupport
{
    public const ANITA_NO = 'N';

    public const ANITA_SI = 'S';

    public const ANITA_BIEN_USO = 'U';

    public const ERP_SI = 'S';

    public const ERP_NO = 'N';

    /**
     * @return array{asientocontable: string, genera_bien_de_uso: string}
     */
    public static function desdeAnita(mixed $tcompGeneraAsi): array
    {
        $valor = strtoupper(trim((string) ($tcompGeneraAsi ?? self::ANITA_NO)));

        if ($valor === self::ANITA_BIEN_USO) {
            return [
                'asientocontable' => self::ERP_SI,
                'genera_bien_de_uso' => self::ERP_SI,
            ];
        }

        if ($valor === self::ANITA_SI) {
            return [
                'asientocontable' => self::ERP_SI,
                'genera_bien_de_uso' => self::ERP_NO,
            ];
        }

        return [
            'asientocontable' => self::ERP_NO,
            'genera_bien_de_uso' => self::ERP_NO,
        ];
    }

    /**
     * Codifica hacia Anita. Si pide bien de uso, manda U (implica asiento).
     */
    public static function haciaAnita(mixed $asientocontable, mixed $generaBienDeUso): string
    {
        $bienUso = strtoupper(trim((string) ($generaBienDeUso ?? self::ERP_NO))) === self::ERP_SI;
        if ($bienUso) {
            return self::ANITA_BIEN_USO;
        }

        $asiento = strtoupper(trim((string) ($asientocontable ?? self::ERP_NO)));

        return $asiento === self::ERP_SI ? self::ANITA_SI : self::ANITA_NO;
    }

    public static function generaBienDeUsoValor(mixed $valor): bool
    {
        return strtoupper(trim((string) ($valor ?? ''))) === self::ERP_SI;
    }

    /**
     * Heurística de remapeo: abreviaturas que terminan en U = familia bienes de uso.
     */
    public static function abreviaturaEsBienDeUso(mixed $abreviatura): bool
    {
        $abrev = strtoupper(trim((string) ($abreviatura ?? '')));

        return $abrev !== '' && str_ends_with($abrev, 'U');
    }
}
