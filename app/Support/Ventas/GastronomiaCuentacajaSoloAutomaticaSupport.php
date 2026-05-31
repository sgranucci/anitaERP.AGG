<?php

namespace App\Support\Ventas;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Cuentas de caja del POS gastronomía que no deben listarse ni elegirse manualmente.
 *
 * CTG → solo vía canje ticket tarjeta. TOTEM → solo vía orden Waitry cobrada en tótem.
 * Mercado Pago y Totalcoin sí pueden elegirse manualmente en el POS.
 */
final class GastronomiaCuentacajaSoloAutomaticaSupport
{
    /**
     * @return list<string>
     */
    public static function codigos(): array
    {
        return array_values(array_unique(array_filter([
            GastronomiaCuentacajaCanjeTarjeta::codigo(),
            GastronomiaCuentacajaTotem::codigo(),
        ])));
    }

    /**
     * @return list<int>
     */
    public static function idsParaEmpresa(int $empresaId): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        $ids = [];

        $totem = GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId);
        if ($totem !== null) {
            $ids[] = (int) $totem['id'];
        }

        $ctg = GastronomiaCuentacajaCanjeTarjeta::cuentaParaEmpresa($empresaId);
        if ($ctg !== null) {
            $ids[] = (int) $ctg['id'];
        }

        return array_values(array_unique(array_filter($ids)));
    }

    public static function esSoloAutomatica(int $cuentacajaId, ?string $codigo, int $empresaId): bool
    {
        if ($cuentacajaId > 0 && in_array($cuentacajaId, self::idsParaEmpresa($empresaId), true)) {
            return true;
        }

        $codigoNorm = strtoupper(trim((string) $codigo));

        return $codigoNorm !== '' && in_array($codigoNorm, self::codigos(), true);
    }

    public static function aplicarExclusionEnQuery(Builder $query, int $empresaId): Builder
    {
        $ids = self::idsParaEmpresa($empresaId);
        if ($ids !== []) {
            $query->whereNotIn('cuentacaja.id', $ids);
        }

        $codigos = self::codigos();
        if ($codigos !== []) {
            $query->whereNotIn(DB::raw('UPPER(TRIM(cuentacaja.codigo))'), $codigos);
        }

        return $query;
    }

    public static function mensajeRechazoManual(?string $codigo = null): string
    {
        $codigoNorm = strtoupper(trim((string) $codigo));

        if ($codigoNorm === GastronomiaCuentacajaCanjeTarjeta::codigo()) {
            return 'La cuenta CTG solo puede usarse mediante canje de ticket tarjeta gastronomía (no manualmente).';
        }

        if ($codigoNorm === GastronomiaCuentacajaTotem::codigo()) {
            return 'La cuenta TOTEM se asigna automáticamente al importar una orden Waitry ya cobrada en el tótem.';
        }

        return 'La cuenta TOTEM se asigna automáticamente al importar una orden Waitry ya cobrada en el tótem.';
    }
}
