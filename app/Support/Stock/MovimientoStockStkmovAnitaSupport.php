<?php

namespace App\Support\Stock;

use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Tipotransaccion_Stock;

/**
 * Clave Anita stkmov para movimientos de stock originados en anitaERP.
 *
 * Namespace aislado: stkv_sucursal fijo (p. ej. 99) + stkv_nro = movimientostock.id,
 * sin usar el numerador Informix de comprobantes nativos.
 */
final class MovimientoStockStkmovAnitaSupport
{
    /** Abreviaturas cuyo stkmov ya lo graba otro bridge (COM recepción, facturación ventas). */
    private const ABREVIATURAS_OMITIR = [
        'RCING',
        'RCDEV',
    ];

    public static function habilitado(): bool
    {
        return (bool) config('stock.anita_stkmov.habilitado', true);
    }

    public static function sistemaVentas(): string
    {
        return (string) config('stock.anita_stkmov.sistema_ventas', 'ventas');
    }

    public static function sucursalErp(): int
    {
        return (int) config('stock.anita_stkmov.sucursal_erp', 99);
    }

    public static function letraErp(): string
    {
        return AnitaStkmovClaveErpSupport::letra();
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}
     */
    public static function claveDesdeMovimiento(
        MovimientoStock $movimiento,
        string $tipoStkmov,
        ?int $empresaCodigoAnita = null,
    ): array {
        $tipo = strtoupper(substr(trim($tipoStkmov), 0, 3));
        if ($tipo === '') {
            throw new \InvalidArgumentException('Tipo stkmov inválido para movimiento '.$movimiento->id);
        }

        return [
            'tipo' => $tipo,
            'letra' => self::letraErp(),
            'sucursal' => AnitaStkmovClaveErpSupport::sucursal($empresaCodigoAnita),
            'nro' => (int) $movimiento->id,
        ];
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    public static function whereCabecera(array $clave): string
    {
        return RecepcionProveedorAnitaWhereSupport::stkmovCabecera($clave);
    }

    public static function debeSincronizar(MovimientoStock $movimiento, ?string $tipoOverride = null): bool
    {
        if (! self::habilitado()) {
            return false;
        }

        if ((bool) ($movimiento->getAttribute('omitir_stkmov_anita') ?? false)) {
            return false;
        }

        $movimiento->loadMissing('tipotransaccion_stock');
        $tipo = $movimiento->tipotransaccion_stock;
        if ($tipo === null) {
            return false;
        }

        $abrev = strtoupper(trim((string) ($tipo->abreviatura ?? '')));
        if (in_array($abrev, self::ABREVIATURAS_OMITIR, true)) {
            return false;
        }

        if ($tipoOverride !== null && trim($tipoOverride) !== '') {
            return true;
        }

        return self::resolverTipoStkmov($movimiento, null) !== null;
    }

    public static function resolverTipoStkmov(MovimientoStock $movimiento, ?string $override = null): ?string
    {
        if ($override !== null && trim($override) !== '') {
            return strtoupper(substr(trim($override), 0, 3));
        }

        $movimiento->loadMissing('tipotransaccion_stock');
        $abrev = strtoupper(trim((string) ($movimiento->tipotransaccion_stock->abreviatura ?? '')));
        if ($abrev === '') {
            return null;
        }

        $mapa = config('stock.anita_stkmov.stkv_tipo_por_abreviatura', []);
        if (is_array($mapa) && array_key_exists($abrev, $mapa)) {
            $mapped = $mapa[$abrev];
            if ($mapped === null || $mapped === '') {
                return null;
            }

            return strtoupper(substr((string) $mapped, 0, 3));
        }

        return strtoupper(substr($abrev, 0, 3));
    }

    public static function esTransferencia(Tipotransaccion_Stock $tipo): bool
    {
        return strtoupper((string) ($tipo->operacion ?? '')) === 'T';
    }
}
