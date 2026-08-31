<?php

namespace App\Support\Contable;

use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;

/**
 * Centros de costo del cierre máquinas (p-vtamaquina.c arma_asiento + ctam_fl_ccosto).
 *
 * - Si la cuenta no maneja CC → null.
 * - Si Pccosto explícito (89 vales, 96 ticket prom debe, APGAS) → ese código.
 * - Si Pccosto=0 y la cuenta maneja CC → dft_ccosto Anita (sucursal) ≈ código 89 Máquinas.
 */
final class CierreRendicionMaquinaCentrocostoSupport
{
    /** dft_ccosto típico sala máquinas (Anita sucursal FSL / ccosto 89). */
    public const CODIGO_DEFAULT = '89';

    /** Obsequios / ticket promocional debe (p-vtamaquina.c 96L). */
    public const CODIGO_TICKET_PROM = '96';

    /** Vales / reintegros (p-vtamaquina.c 89L). */
    public const CODIGO_VALES = '89';

    /** @var array<int, bool> */
    private static array $cacheManeja = [];

    /** @var array<string, int> */
    private static array $cacheIdPorCodigo = [];

    public static function codigoDefault(): string
    {
        $codigo = trim((string) config(
            'rendicion_maquina_anita.cierre_rendicion_contable.centrocosto_default',
            self::CODIGO_DEFAULT,
        ));

        return $codigo !== '' ? $codigo : self::CODIGO_DEFAULT;
    }

    public static function codigoTicketProm(): string
    {
        $codigo = trim((string) config(
            'rendicion_maquina_anita.cierre_rendicion_contable.centrocosto_ticket_prom',
            self::CODIGO_TICKET_PROM,
        ));

        return $codigo !== '' ? $codigo : self::CODIGO_TICKET_PROM;
    }

    public static function idPorCodigo(string $codigo): int
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return 0;
        }
        if (array_key_exists($codigo, self::$cacheIdPorCodigo)) {
            return self::$cacheIdPorCodigo[$codigo];
        }

        $id = (int) (Centrocosto::query()->where('codigo', $codigo)->value('id') ?? 0);
        self::$cacheIdPorCodigo[$codigo] = $id;

        return $id;
    }

    public static function idDefault(): int
    {
        return self::idPorCodigo(self::codigoDefault());
    }

    public static function idTicketProm(): int
    {
        return self::idPorCodigo(self::codigoTicketProm());
    }

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
     * Resuelve CC para una línea: explícito gana; si no, default 89 si la cuenta maneja CC.
     */
    public static function resolverParaCuenta(int $cuentacontableId, int $centrocostoIdExplicito = 0): ?int
    {
        if ($centrocostoIdExplicito > 0) {
            return $centrocostoIdExplicito;
        }
        if (! self::cuentacontableManejaCentroCosto($cuentacontableId)) {
            return null;
        }
        $id = self::idDefault();

        return $id > 0 ? $id : null;
    }
}
