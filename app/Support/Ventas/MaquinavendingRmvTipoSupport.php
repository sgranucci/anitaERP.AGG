<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Tipotransaccion;
use InvalidArgumentException;

/**
 * Tipo RMV (Anita t_comp: Rendicion maquinas vending).
 * p-vtagastro.c: sucursal ≥ 1000 = expendedora; emite RMV letra Z.
 * p-rg3685.c: RMV entra al Libro IVA como Factura B (tipo 6, doc 89, nro 1).
 */
final class MaquinavendingRmvTipoSupport
{
    public const ABREVIATURA = 'RMV';

    public const LETRA = 'Z';

    public const NOMBRE_CLIENTE = 'Venta expendedoras';

    public static function tipoId(): int
    {
        $id = (int) (Tipotransaccion::query()
            ->where('abreviatura', self::ABREVIATURA)
            ->whereNull('deleted_at')
            ->value('id') ?? 0);

        if ($id <= 0) {
            throw new InvalidArgumentException(
                'Falta tipo de transacción RMV (Rendicion maquinas vending). Ejecute migraciones.',
            );
        }

        return $id;
    }

    public static function tipo(): Tipotransaccion
    {
        return Tipotransaccion::query()->findOrFail(self::tipoId());
    }

    public static function esRmv(?Tipotransaccion $tipo): bool
    {
        if ($tipo === null) {
            return false;
        }

        return strtoupper(trim((string) $tipo->abreviatura)) === self::ABREVIATURA;
    }
}
