<?php

declare(strict_types=1);

namespace App\Support\Contable;

use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Contable\Cuentacontable_Centrocosto;
use App\Support\Database\SqlDialectSupport;
use InvalidArgumentException;

/**
 * Centro de costo del cierre bingo (paridad arma_asiento / CCOSV_asigna_ccosto en p-vtabingo.c).
 *
 * Si la cuenta maneja CC (manejaccosto=S): primer centro vinculado en
 * cuentacontable_centrocosto (análogo ccosvalid), ordenado por código.
 * Si no hay vínculo: fallback opcional config (análogo dft_ccosto de suc_leyenda2).
 */
final class CierreRendicionBingoCentrocostoSupport
{
    /** @var array<int, bool> */
    private static array $cacheManeja = [];

    /** @var array<int, ?int> */
    private static array $cachePorCuenta = [];

    /** @var int|null|false */
    private static $cacheFallbackId = false;

    public static function cuentacontableManejaCentroCosto(int $cuentacontableId): bool
    {
        if ($cuentacontableId <= 0) {
            return false;
        }

        if (array_key_exists($cuentacontableId, self::$cacheManeja)) {
            return self::$cacheManeja[$cuentacontableId];
        }

        $flag = Cuentacontable::query()->whereKey($cuentacontableId)->value('manejaccosto');
        $maneja = in_array((string) $flag, ['S', '1'], true);
        self::$cacheManeja[$cuentacontableId] = $maneja;

        return $maneja;
    }

    /**
     * @return ?int id de centrocosto, o null si la cuenta no maneja CC
     */
    public static function resolverCentrocostoIdParaCuentacontable(int $cuentacontableId): ?int
    {
        if (! self::cuentacontableManejaCentroCosto($cuentacontableId)) {
            return null;
        }

        if (array_key_exists($cuentacontableId, self::$cachePorCuenta)) {
            return self::$cachePorCuenta[$cuentacontableId];
        }

        $id = self::primerCentrocostoVinculado($cuentacontableId);
        if ($id === null || $id <= 0) {
            $id = self::idCentrocostoFallback();
        }

        self::$cachePorCuenta[$cuentacontableId] = $id;

        return $id;
    }

    /**
     * Al grabar cierre: exige CC si la cuenta lo maneja.
     */
    public static function resolverCentrocostoIdParaCuentacontableOError(int $cuentacontableId): ?int
    {
        if (! self::cuentacontableManejaCentroCosto($cuentacontableId)) {
            return null;
        }

        $id = self::resolverCentrocostoIdParaCuentacontable($cuentacontableId);
        if ($id === null || $id <= 0) {
            $codigo = (string) (Cuentacontable::query()->whereKey($cuentacontableId)->value('codigo') ?? $cuentacontableId);
            throw new InvalidArgumentException(
                'La cuenta '.$codigo.' maneja centro de costo pero no tiene vínculo en '
                .'cuentacontable_centrocosto (ccosvalid) ni fallback BINGO_CIERRE_CENTROCOSTO_DEFAULT.',
            );
        }

        return $id;
    }

    private static function primerCentrocostoVinculado(int $cuentacontableId): ?int
    {
        $id = Cuentacontable_Centrocosto::query()
            ->from('cuentacontable_centrocosto as cc')
            ->join('centrocosto as c', 'c.id', '=', 'cc.centrocosto_id')
            ->where('cc.cuentacontable_id', $cuentacontableId)
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('c.codigo'))
            ->value('c.id');

        return $id !== null ? (int) $id : null;
    }

    private static function idCentrocostoFallback(): ?int
    {
        if (self::$cacheFallbackId !== false) {
            return self::$cacheFallbackId;
        }

        $codigo = trim((string) config('bingo.cierre_rendicion_contable.centrocosto_default', ''));
        if ($codigo === '') {
            self::$cacheFallbackId = null;

            return null;
        }

        $id = Centrocosto::query()->where('codigo', $codigo)->value('id');
        self::$cacheFallbackId = $id !== null ? (int) $id : null;

        return self::$cacheFallbackId;
    }
}
