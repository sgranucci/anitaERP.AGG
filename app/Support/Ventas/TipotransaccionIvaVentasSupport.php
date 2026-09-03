<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Tipotransaccion;

/**
 * Tipo de transacción → IVA ventas (tilde en el ABM).
 */
final class TipotransaccionIvaVentasSupport
{
    private static ?bool $fslVaAlIvaVentas = null;

    public static function resetCache(): void
    {
        self::$fslVaAlIvaVentas = null;
    }

    public static function vaAlIvaVentas(?Tipotransaccion $tipo): bool
    {
        if ($tipo === null) {
            return false;
        }

        return self::marcaActiva($tipo->getAttribute('iva_ventas'));
    }

    public static function marcaActiva(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        $raw = strtolower(trim((string) $valor));

        return in_array($raw, ['1', 'true', 't', 's', 'y'], true);
    }

    public static function fslVaAlIvaVentas(): bool
    {
        if (self::$fslVaAlIvaVentas !== null) {
            return self::$fslVaAlIvaVentas;
        }

        $valor = Tipotransaccion::query()
            ->where('abreviatura', MaquinaFslTipoSupport::ABREVIATURA)
            ->whereNull('deleted_at')
            ->value('iva_ventas');

        return self::$fslVaAlIvaVentas = self::marcaActiva($valor);
    }
}
