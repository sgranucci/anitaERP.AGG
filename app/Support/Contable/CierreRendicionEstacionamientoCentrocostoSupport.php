<?php

namespace App\Support\Contable;

use App\Models\Contable\Centrocosto;
use InvalidArgumentException;

/**
 * Centro de costo en asientos del cierre contable de rendiciones estacionamiento.
 */
final class CierreRendicionEstacionamientoCentrocostoSupport
{
    public const CODIGO_CENTROCOSTO_DEFAULT = '80';

    /** @var array<string, ?int> */
    private static array $cacheCentrocostoId = [];

    public static function codigoCentrocostoConfigurado(): string
    {
        $codigo = trim((string) config(
            'estacionamiento.cierre_rendicion_centrocosto_codigo',
            self::CODIGO_CENTROCOSTO_DEFAULT,
        ));

        return $codigo !== '' ? $codigo : self::CODIGO_CENTROCOSTO_DEFAULT;
    }

    public static function idCentrocostoEstacionamiento(): ?int
    {
        $codigo = self::codigoCentrocostoConfigurado();
        if (array_key_exists($codigo, self::$cacheCentrocostoId)) {
            return self::$cacheCentrocostoId[$codigo];
        }

        $id = Centrocosto::query()->where('codigo', $codigo)->value('id');
        self::$cacheCentrocostoId[$codigo] = $id !== null ? (int) $id : null;

        return self::$cacheCentrocostoId[$codigo];
    }

    public static function idCentrocostoEstacionamientoOError(): int
    {
        $id = self::idCentrocostoEstacionamiento();
        if ($id === null || $id <= 0) {
            throw new InvalidArgumentException(
                'No existe centro de costo «'.self::codigoCentrocostoConfigurado()
                .'» para asientos de cierre estacionamiento. '
                .'Configure ESTACIONAMIENTO_CIERRE_RENDICION_CENTROCOSTO_CODIGO.',
            );
        }

        return $id;
    }

    /**
     * CC en ventas y diferencia de caja del cierre estacionamiento (cuentas de config).
     *
     * @param  array<string, mixed>  $config
     */
    public static function resolverCentrocostoIdParaCuentacontable(int $cuentacontableId, array $config): ?int
    {
        if ($cuentacontableId <= 0) {
            return null;
        }

        $cuentasConCc = array_values(array_filter([
            (int) ($config['cuenta_ventas_id'] ?? 0),
            (int) ($config['cuenta_diferencia_caja_id'] ?? 0),
        ], static fn (int $id) => $id > 0));

        if (! in_array($cuentacontableId, $cuentasConCc, true)) {
            return null;
        }

        return self::idCentrocostoEstacionamiento();
    }
}
