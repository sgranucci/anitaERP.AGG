<?php

namespace App\Support\Contable;

/**
 * Origen de lectura del mayor (plano y por concepto) por consulta.
 *
 * El corte `fuente_erp_hasta` es el techo duro: nunca se lee ERP después de esa fecha.
 * El modo de consulta solo decide si, dentro del tramo ya migrado, se prefiere ERP,
 * se fuerza Anita, o se sigue el híbrido automático.
 */
final class MayorFuenteConsultaSupport
{
    public const MODO_AUTO = 'auto';

    public const MODO_ERP = 'erp';

    public const MODO_ANITA = 'anita';

    /** Tope operativo actual si el modo pide ERP y la config está vacía. */
    public const CORTE_DEFAULT_YMD = 20260831;

    /**
     * @return self::MODO_*
     */
    public static function normalizarModo(mixed $valor): string
    {
        $modo = strtolower(trim((string) $valor));

        return in_array($modo, [self::MODO_AUTO, self::MODO_ERP, self::MODO_ANITA], true)
            ? $modo
            : self::MODO_AUTO;
    }

    /**
     * Corte Ymd desde config. Vacío = 0 (sin tramo ERP en modo auto).
     */
    public static function corteYmd(?string $configKey = null): int
    {
        $keys = array_values(array_filter([
            $configKey,
            'contable.mayor_plano_cuenta.fuente_erp_hasta',
            'contable.mayor_concepto.fuente_erp_hasta',
        ]));

        foreach ($keys as $key) {
            $raw = trim((string) config($key, ''));
            $ymd = self::parseYmd($raw);
            if ($ymd > 0) {
                return $ymd;
            }
        }

        return 0;
    }

    /**
     * Corte efectivo según modo: en `erp` cae al default si la config está vacía.
     */
    public static function corteEfectivo(string $modo, ?string $configKey = null): int
    {
        $modo = self::normalizarModo($modo);
        $corte = self::corteYmd($configKey);

        if ($modo === self::MODO_ANITA) {
            return 0;
        }

        if ($corte > 0) {
            return $corte;
        }

        return $modo === self::MODO_ERP ? self::CORTE_DEFAULT_YMD : 0;
    }

    /**
     * @return array{
     *     modo: string,
     *     corte: int,
     *     usa_erp: bool,
     *     usa_anita: bool,
     *     tramo_erp_desde: int,
     *     tramo_erp_hasta: int,
     *     tramo_anita_desde: int,
     *     tramo_anita_hasta: int,
     *     etiqueta: string
     * }
     */
    public static function resolverTramos(
        int $fechaDesde,
        int $fechaHasta,
        string $modo = self::MODO_AUTO,
        ?string $configKey = null,
    ): array {
        $modo = self::normalizarModo($modo);
        if ($fechaDesde > $fechaHasta && $fechaDesde > 0 && $fechaHasta > 0) {
            [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
        }

        $corte = self::corteEfectivo($modo, $configKey);

        if ($corte <= 0 || $modo === self::MODO_ANITA) {
            return self::soloAnita($modo, $fechaDesde, $fechaHasta, 0);
        }

        $postCorte = self::fechaSiguiente($corte);
        $erpDesde = 0;
        $erpHasta = 0;
        $anitaDesde = 0;
        $anitaHasta = 0;

        $erpPerDesde = $fechaDesde;
        $erpPerHasta = min($fechaHasta, $corte);
        if ($erpPerDesde > 0 && $erpPerHasta >= $erpPerDesde) {
            $erpDesde = $erpPerDesde;
            $erpHasta = $erpPerHasta;
        }

        $anitaPerDesde = max($fechaDesde, $postCorte);
        if ($anitaPerDesde > 0 && $fechaHasta >= $anitaPerDesde) {
            $anitaDesde = $anitaPerDesde;
            $anitaHasta = $fechaHasta;
        }

        $usaErp = $erpDesde > 0 && $erpHasta >= $erpDesde;
        $usaAnita = $anitaDesde > 0 && $anitaHasta >= $anitaDesde;

        return [
            'modo' => $modo,
            'corte' => $corte,
            'usa_erp' => $usaErp,
            'usa_anita' => $usaAnita,
            'tramo_erp_desde' => $usaErp ? $erpDesde : 0,
            'tramo_erp_hasta' => $usaErp ? $erpHasta : 0,
            'tramo_anita_desde' => $usaAnita ? $anitaDesde : 0,
            'tramo_anita_hasta' => $usaAnita ? $anitaHasta : 0,
            'etiqueta' => self::etiqueta($modo, $corte, $usaErp, $usaAnita),
        ];
    }

    public static function formatearYmd(int $ymd): string
    {
        if ($ymd <= 0) {
            return '';
        }
        $s = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($s, 6, 2).'/'.substr($s, 4, 2).'/'.substr($s, 0, 4);
    }

    public static function parseYmd(string $raw): int
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 0;
        }
        if (preg_match('/^\d{8}$/', $raw) === 1) {
            return (int) $raw;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return (int) str_replace('-', '', $raw);
        }

        return 0;
    }

    public static function fechaSiguiente(int $ymd): int
    {
        if ($ymd <= 0) {
            return 0;
        }
        $dt = \DateTimeImmutable::createFromFormat('Ymd', (string) $ymd);
        if (! $dt) {
            return $ymd + 1;
        }

        return (int) $dt->modify('+1 day')->format('Ymd');
    }

    /**
     * @return array{
     *     modo: string,
     *     corte: int,
     *     usa_erp: bool,
     *     usa_anita: bool,
     *     tramo_erp_desde: int,
     *     tramo_erp_hasta: int,
     *     tramo_anita_desde: int,
     *     tramo_anita_hasta: int,
     *     etiqueta: string
     * }
     */
    private static function soloAnita(string $modo, int $fechaDesde, int $fechaHasta, int $corte): array
    {
        $usa = $fechaDesde > 0 && $fechaHasta >= $fechaDesde;

        return [
            'modo' => $modo,
            'corte' => $corte,
            'usa_erp' => false,
            'usa_anita' => $usa,
            'tramo_erp_desde' => 0,
            'tramo_erp_hasta' => 0,
            'tramo_anita_desde' => $usa ? $fechaDesde : 0,
            'tramo_anita_hasta' => $usa ? $fechaHasta : 0,
            'etiqueta' => self::etiqueta($modo, $corte, false, $usa),
        ];
    }

    private static function etiqueta(string $modo, int $corte, bool $usaErp, bool $usaAnita): string
    {
        if ($usaErp && $usaAnita) {
            $post = self::fechaSiguiente($corte);

            return 'Híbrido: ERP hasta '.self::formatearYmd($corte)
                .' · Anita desde '.self::formatearYmd($post);
        }

        if ($usaErp) {
            return 'ERP nativo (hasta '.self::formatearYmd($corte).')';
        }

        if ($modo === self::MODO_ANITA) {
            return 'Anita (forzado)';
        }

        return 'Anita (bridge)';
    }
}
