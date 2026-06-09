<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Contable\Cuentacontable;
use Illuminate\Support\Facades\Cache;

/**
 * Resolución y remapeo de cuentas contables en asientos del cierre de jornada Waitry.
 */
final class CierreJornadaProcesoAsientosCuentaSupport
{
    /** Cuenta legacy en medios de cobro canje gastronomía (intereses a devengar). */
    public const CODIGO_CUENTA_MEDIO_LEGACY = '211010017';

    /** Cuenta destino: vales gastronomía. */
    public const CODIGO_CUENTA_VALES_GASTRONOMIA = '211010020';

    /** Cuenta caja «Canje estacionamiento» — no aplica remapeo a vales gastronomía. */
    private const CUENTACAJA_ID_CANJE_ESTACIONAMIENTO = 200;

    /**
     * Reemplaza 211010017 → 211010020 para medios de cobro del proceso (misma empresa).
     */
    public static function aplicarRemapCuentacontableMedioCobro(
        int $cuentacontableId,
        int $empresaId,
        ?int $cuentacajaId = null,
    ): int {
        if ($cuentacontableId <= 0 || $empresaId <= 0) {
            return $cuentacontableId;
        }

        if ($cuentacajaId === self::CUENTACAJA_ID_CANJE_ESTACIONAMIENTO) {
            return $cuentacontableId;
        }

        $codigoActual = self::codigoCuentacontable($cuentacontableId);
        if ($codigoActual !== self::CODIGO_CUENTA_MEDIO_LEGACY) {
            return $cuentacontableId;
        }

        $destinoId = self::idCuentacontablePorCodigoEmpresa(self::CODIGO_CUENTA_VALES_GASTRONOMIA, $empresaId);

        return $destinoId ?? $cuentacontableId;
    }

    /**
     * @return array{codigo:string,nombre:string}|null
     */
    public static function etiquetaCuentacontableMedioCobro(
        int $cuentacontableId,
        int $empresaId,
        ?int $cuentacajaId = null,
    ): ?array {
        $id = self::aplicarRemapCuentacontableMedioCobro($cuentacontableId, $empresaId, $cuentacajaId);
        if ($id <= 0) {
            return null;
        }

        $cuenta = Cuentacontable::query()->find($id, ['id', 'codigo', 'nombre']);
        if ($cuenta === null) {
            return null;
        }

        return [
            'codigo' => trim((string) $cuenta->codigo),
            'nombre' => trim((string) $cuenta->nombre),
        ];
    }

    private static function codigoCuentacontable(int $cuentacontableId): string
    {
        $codigo = Cache::remember(
            'cierre_jornada_cc_codigo_'.$cuentacontableId,
            300,
            static fn () => Cuentacontable::query()->whereKey($cuentacontableId)->value('codigo'),
        );

        return trim((string) $codigo);
    }

    private static function idCuentacontablePorCodigoEmpresa(string $codigo, int $empresaId): ?int
    {
        $cacheKey = 'cierre_jornada_cc_id_'.$empresaId.'_'.md5($codigo);
        $id = Cache::remember(
            $cacheKey,
            300,
            static fn () => Cuentacontable::query()
                ->where('codigo', $codigo)
                ->where('empresa_id', $empresaId)
                ->value('id'),
        );

        return $id !== null ? (int) $id : null;
    }
}
