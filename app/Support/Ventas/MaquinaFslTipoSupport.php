<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Tipotransaccion;
use InvalidArgumentException;

/**
 * Tipo FSL (p-vtamaquina.c: dft_factura / letra B / 100% exento).
 * No es FMS: legacy usa FSL.
 */
final class MaquinaFslTipoSupport
{
    public const ABREVIATURA = 'FSL';

    public const LETRA = 'B';

    public static function nombreCliente(): string
    {
        return (string) config(
            'rendicion_maquina_anita.cierre_rendicion_contable.cliente_nombre',
            'Sala de máquinas',
        );
    }

    public static function tipoId(): int
    {
        $id = (int) (Tipotransaccion::query()
            ->where('abreviatura', self::ABREVIATURA)
            ->whereNull('deleted_at')
            ->value('id') ?? 0);

        if ($id <= 0) {
            throw new InvalidArgumentException(
                'Falta tipo de transacción FSL (Factura sala maquinas). Ejecute migraciones.',
            );
        }

        return $id;
    }

    public static function tipo(): Tipotransaccion
    {
        return Tipotransaccion::query()->findOrFail(self::tipoId());
    }

    public static function esFsl(?Tipotransaccion $tipo): bool
    {
        if ($tipo === null) {
            return false;
        }

        return strtoupper(trim((string) $tipo->abreviatura)) === self::ABREVIATURA;
    }
}
