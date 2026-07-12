<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Contable\Cuentacontable;
use App\Models\Contable\Centrocosto;
use InvalidArgumentException;

/**
 * Centro de costo para líneas de asientos del cierre Waitry cuya cuenta contable maneja CC.
 */
final class CierreJornadaProcesoAsientosCentrocostoSupport
{
    public const CODIGO_CENTROCOSTO_DEFAULT = '85';

    /** @var array<int, bool> */
    private static array $cacheManejaCosto = [];

    /** @var array<string, ?int> */
    private static array $cacheCentrocostoId = [];

    public static function codigoCentrocostoConfigurado(): string
    {
        $codigo = trim((string) config(
            'gastronomia.cierre_jornada_centrocosto_codigo',
            self::CODIGO_CENTROCOSTO_DEFAULT,
        ));

        return $codigo !== '' ? $codigo : self::CODIGO_CENTROCOSTO_DEFAULT;
    }

    public static function idCentrocostoGastronomia(): ?int
    {
        $codigo = self::codigoCentrocostoConfigurado();
        if (array_key_exists($codigo, self::$cacheCentrocostoId)) {
            return self::$cacheCentrocostoId[$codigo];
        }

        $id = Centrocosto::query()->where('codigo', $codigo)->value('id');
        self::$cacheCentrocostoId[$codigo] = $id !== null ? (int) $id : null;

        return self::$cacheCentrocostoId[$codigo];
    }

    public static function idCentrocostoGastronomiaOError(): int
    {
        $id = self::idCentrocostoGastronomia();
        if ($id === null || $id <= 0) {
            throw new InvalidArgumentException(
                'No existe centro de costo «'.self::codigoCentrocostoConfigurado()
                .'» para asientos del cierre Waitry. Configure GASTRONOMIA_CIERRE_JORNADA_CENTROCOSTO_CODIGO.',
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

        return self::idCentrocostoGastronomia();
    }
}
