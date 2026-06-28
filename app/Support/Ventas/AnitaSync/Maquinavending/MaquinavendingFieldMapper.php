<?php

declare(strict_types=1);

namespace App\Support\Ventas\AnitaSync\Maquinavending;

/**
 * Mapeo campo a campo: maqvmae / ubimvending (Anita) → maquinavending (ERP).
 */
final class MaquinavendingFieldMapper
{
    public static function mapCodigoAnita(object $row): ?int
    {
        $codigo = (int) ($row->maqvm_codigo ?? 0);

        return $codigo > 0 ? $codigo : null;
    }

    public static function mapNombre(object $row): string
    {
        $nombre = trim((string) ($row->maqvm_desc ?? ''));
        if ($nombre !== '') {
            return mb_substr($nombre, 0, 255);
        }

        $codigo = (int) ($row->maqvm_codigo ?? 0);

        return $codigo > 0 ? 'Máquina vending '.$codigo : 'Máquina vending sin nombre';
    }

    public static function mapSucursal(object $row): int
    {
        return (int) ($row->maqvm_sucursal ?? 0);
    }

    public static function mapUbicacionTexto(object $row): string
    {
        return trim((string) ($row->maqvm_ubicacion ?? ''));
    }

    public static function mapDepositoCodigoAnita(object $row): string
    {
        return trim((string) ($row->maqvm_deposito ?? ''));
    }

    public static function mapCodigoArca(object $row): ?string
    {
        $codigo = trim((string) ($row->maqvm_cod_afip ?? ''));
        if ($codigo === '' || $codigo === '0') {
            return null;
        }

        return mb_substr($codigo, 0, 20);
    }

    public static function mapNumeroSerie(object $row): ?string
    {
        $serie = trim((string) ($row->maqvm_nro_serie ?? ''));

        return $serie !== '' ? mb_substr($serie, 0, 50) : null;
    }

    public static function mapCodigoMaquinaAnitaDesdeArticulo(object $row): ?int
    {
        $codigo = (int) ($row->ubimv_codigo ?? 0);

        return $codigo > 0 ? $codigo : null;
    }

    public static function mapNumeroRuloDesdeArticulo(object $row): int
    {
        return (int) ($row->ubimv_ubicacion ?? 0);
    }

    public static function mapSkuArticuloDesdeArticulo(object $row): ?string
    {
        $sku = ltrim(trim((string) ($row->ubimv_articulo ?? '')), '0');

        return $sku !== '' ? $sku : null;
    }
}
