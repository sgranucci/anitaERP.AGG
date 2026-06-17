<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Models\Stock\Tipotransaccion_Stock;

final class TransferenciaMercaderiaAprobacionSupport
{
    public const MODO_INMEDIATA = 'inmediata';

    public const MODO_TIPO_TRANSACCION = 'tipo_transaccion';

    public const MODO_SIEMPRE = 'siempre';

    public static function requiereAprobacion(?Tipotransaccion_Stock $tipo): bool
    {
        $modo = (string) config('stock.transferencia_modo_aprobacion', self::MODO_TIPO_TRANSACCION);

        return match ($modo) {
            self::MODO_INMEDIATA => false,
            self::MODO_SIEMPRE => true,
            default => (bool) ($tipo?->requiere_aprobacion ?? false),
        };
    }

    public static function manejaContabilidad(?Tipotransaccion_Stock $tipo): bool
    {
        return (bool) ($tipo?->maneja_contabilidad ?? false);
    }
}
