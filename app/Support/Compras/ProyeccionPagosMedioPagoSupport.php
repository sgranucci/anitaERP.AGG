<?php

namespace App\Support\Compras;

/**
 * Medio de pago del detalle de proyección (Anita l-proy.c, imprime_un_mov).
 *
 * Anita lee occ_medio_pago de la orden de compra vinculada a la factura.
 * Si no hay vínculo, el medio queda vacío. La importación desde Anita dejó
 * las notas de crédito con forma de pago Efectivo por defecto, y el informe
 * las mostraba como efectivo aunque la OC (o Anita) tuviera otro medio.
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
        if ($vinculada !== '') {
            return PropuestaPagoLineaPresentacionSupport::abreviaturaAnita($vinculada);
        }

        $deCuota = self::texto($abreviaturaCuota, $nombreCuota);
        $deDocumento = self::texto($abreviaturaOcDocumento, $nombreOcDocumento);
        $esCredito = $signoTipo < 0;
        $importada = strtoupper(trim((string) $origenEntrada)) === self::ORIGEN_IMPORTADO;

        if ($esCredito && $importada) {
            if ($deDocumento !== '') {
                return PropuestaPagoLineaPresentacionSupport::abreviaturaAnita($deDocumento);
            }

            if (self::esEfectivo($deCuota)) {
                return '';
            }
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
