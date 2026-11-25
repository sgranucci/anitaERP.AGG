<?php

namespace App\Support\Compras;

/**
 * Medio de pago del detalle de proyección (Anita l-proy.c, imprime_un_mov).
 *
 * Anita lee occ_medio_pago de la orden de compra vinculada a la factura.
 * Si no hay vínculo, el medio queda vacío. El import Anita resuelve el medio
 * vía OC/proveedor (ComprobanteProveedorAnitaImportFormapagoSupport); el
 * fallback Efectivo del config solo aplica si no hay ninguno.
 *
 * Efectivo en OC/cuota se trata como default no confirmado: si hay otro medio
 * en cuota o en la OC del documento, se prefiere ese (igual que el backfill).
 */
final class ProyeccionPagosMedioPagoSupport
{
    public const ORIGEN_IMPORTADO = 'ANITA_IMPORT';

    public static function resolver(
        ?string $abreviaturaCuota,
        ?string $nombreCuota,
        ?string $abreviaturaOcVinculada,
        ?string $nombreOcVinculada,
        ?string $abreviaturaOcDocumento,
        ?string $nombreOcDocumento,
        int $signoTipo,
        ?string $origenEntrada,
    ): string {
        $vinculada = self::texto($abreviaturaOcVinculada, $nombreOcVinculada);
        $deCuota = self::texto($abreviaturaCuota, $nombreCuota);
        $deDocumento = self::texto($abreviaturaOcDocumento, $nombreOcDocumento);
        $esCredito = $signoTipo < 0;
        $importada = strtoupper(trim((string) $origenEntrada)) === self::ORIGEN_IMPORTADO;

        if ($vinculada !== '' && ! self::esEfectivo($vinculada)) {
            return PropuestaPagoLineaPresentacionSupport::abreviaturaAnita($vinculada);
        }

        if ($esCredito && $importada) {
            if ($deDocumento !== '' && ! self::esEfectivo($deDocumento)) {
                return PropuestaPagoLineaPresentacionSupport::abreviaturaAnita($deDocumento);
            }

            if ($deDocumento !== '') {
                return PropuestaPagoLineaPresentacionSupport::abreviaturaAnita($deDocumento);
            }

            if (self::esEfectivo($deCuota)) {
                return '';
            }
        }

        if ($deCuota !== '' && ! self::esEfectivo($deCuota)) {
            return PropuestaPagoLineaPresentacionSupport::abreviaturaAnita($deCuota);
        }

        if ($deDocumento !== '' && ! self::esEfectivo($deDocumento)) {
            return PropuestaPagoLineaPresentacionSupport::abreviaturaAnita($deDocumento);
        }

        if ($vinculada !== '') {
            return PropuestaPagoLineaPresentacionSupport::abreviaturaAnita($vinculada);
        }

        if ($deCuota !== '') {
            return PropuestaPagoLineaPresentacionSupport::abreviaturaAnita($deCuota);
        }

        return PropuestaPagoLineaPresentacionSupport::abreviaturaAnita($deDocumento);
    }

    private static function texto(?string $abreviatura, ?string $nombre): string
    {
        $abreviatura = trim((string) $abreviatura);
        if ($abreviatura !== '') {
            return $abreviatura;
        }

        return trim((string) $nombre);
    }

    private static function esEfectivo(string $medio): bool
    {
        $u = mb_strtoupper($medio);

        return $u === 'E' || str_contains($u, 'EFECT');
    }
}
