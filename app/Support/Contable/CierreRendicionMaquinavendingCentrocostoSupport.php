<?php

namespace App\Support\Contable;

use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use InvalidArgumentException;

/**
 * Centro de costo para líneas de asientos del cierre contable vending
 * cuando la cuenta contable maneja CC (p. ej. ventas).
 */
final class CierreRendicionMaquinavendingCentrocostoSupport
{
    public const CODIGO_CENTROCOSTO_DEFAULT = '85';

    /** @var array<int, bool> */
    private static array $cacheManejaCosto = [];

    /** @var array<string, ?int> */
    private static array $cacheCentrocostoId = [];

    public static function codigoCentrocostoConfigurado(): string
    {
        $codigo = trim((string) config(
            'gastronomia.cierre_rendicion_vending_contable.centrocosto_codigo',
            self::CODIGO_CENTROCOSTO_DEFAULT,
        ));

        return $codigo !== '' ? $codigo : self::CODIGO_CENTROCOSTO_DEFAULT;
    }

    public static function idCentrocostoVending(): ?int
    {
        $codigo = self::codigoCentrocostoConfigurado();
        if (array_key_exists($codigo, self::$cacheCentrocostoId)) {
            return self::$cacheCentrocostoId[$codigo];
        }

        $id = Centrocosto::query()->where('codigo', $codigo)->value('id');
        self::$cacheCentrocostoId[$codigo] = $id !== null ? (int) $id : null;

        return self::$cacheCentrocostoId[$codigo];
    }

    public static function idCentrocostoVendingOError(): int
    {
        $id = self::idCentrocostoVending();
        if ($id === null || $id <= 0) {
            throw new InvalidArgumentException(
                'No existe centro de costo «'.self::codigoCentrocostoConfigurado()
                .'» para asientos de cierre vending. '
                .'Configure VENDING_CIERRE_RENDICION_CENTROCOSTO_CODIGO.',
            );
        }

        return $id;
    }

    public static function cuentacontableManejaCentroCosto(int $cuentacontableId): bool
    {
        if ($cuentacontableId <= 0) {
            return false;
        }

        if (array_key_exists($cuentacontableId, self::$cacheManejaCosto)) {
            return self::$cacheManejaCosto[$cuentacontableId];
        }

        $flag = Cuentacontable::query()->whereKey($cuentacontableId)->value('manejaccosto');
        $maneja = in_array((string) $flag, ['S', '1'], true);
        self::$cacheManejaCosto[$cuentacontableId] = $maneja;

        return $maneja;
    }

    public static function resolverCentrocostoIdParaCuentacontable(int $cuentacontableId): ?int
    {
        if (! self::cuentacontableManejaCentroCosto($cuentacontableId)) {
            return null;
        }

        return self::idCentrocostoVending();
    }

    /**
     * Al grabar cierre: exige CC configurado si la cuenta maneja centro de costo.
     */
    public static function resolverCentrocostoIdParaCuentacontableOError(int $cuentacontableId): ?int
    {
        if (! self::cuentacontableManejaCentroCosto($cuentacontableId)) {
            return null;
        }

        return self::idCentrocostoVendingOError();
    }
}
