<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Tipotransaccion;
use InvalidArgumentException;

/**
 * Tipo FBI (p-vtabingo.c: dft_factura / letra B / 100% exento).
 */
final class BingoFbiTipoSupport
{
    public const ABREVIATURA = 'FBI';

    public const LETRA = 'B';

    public static function nombreCliente(): string
    {
        return (string) config('bingo.cierre_rendicion_contable.cliente_nombre', 'Sala de bingo');
    }

    public static function tipoId(): int
    {
        $id = (int) (Tipotransaccion::query()
            ->where('abreviatura', self::ABREVIATURA)
            ->whereNull('deleted_at')
            ->value('id') ?? 0);

        if ($id <= 0) {
            throw new InvalidArgumentException(
                'Falta tipo de transacción FBI (Factura bingo interna). Ejecute migraciones.',
            );
        }

        return $id;
    }

    public static function tipo(): Tipotransaccion
    {
        return Tipotransaccion::query()->findOrFail(self::tipoId());
    }

    public static function esFbi(?Tipotransaccion $tipo): bool
    {
        if ($tipo === null) {
            return false;
        }

        return strtoupper(trim((string) $tipo->abreviatura)) === self::ABREVIATURA;
    }
}
