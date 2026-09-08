<?php

namespace App\Support\Contable;

/**
 * Origen de lectura del mayor (plano y por concepto) por consulta.
 *
 * Solo modos explícitos: ERP (asientos nativos) o Anita (bridge).
 * El híbrido automático se eliminó: mezclaba fuentes en un mismo informe y descuadraba
 * contra el mayor clásico de Anita / el ERP puro.
 *
 * {@see self::MODO_AUTO} queda como alias de ERP por compatibilidad de requests viejos.
 */
final class MayorFuenteConsultaSupport
{
    /** @deprecated Alias de {@see self::MODO_ERP}; no ofrecer en UI. */
    public const MODO_AUTO = 'auto';

    public const MODO_ERP = 'erp';

    public const MODO_ANITA = 'anita';

    /** Referencia operativa (documentación / defaults legacy); ya no parte tramos. */
    public const CORTE_DEFAULT_YMD = 20260831;

    /**
     * @return self::MODO_ERP|self::MODO_ANITA
     */
    public static function normalizarModo(mixed $valor): string
    {
        $modo = strtolower(trim((string) $valor));

        if ($modo === self::MODO_ANITA) {
            return self::MODO_ANITA;
        }

        // auto / vacío / erp / basura → ERP (origen explícito por defecto).
        return self::MODO_ERP;
    }

    /**
     * Corte Ymd desde config. Vacío = 0.
     *
     * Se conserva por pantallas/documentación (hasta cuándo hay import ERP), pero
     * ya no define un tramo híbrido en la consulta.
     *
     * Si se pasa `$configKey` (p. ej. mayor por concepto), se usa solo esa clave:
     * vacío significa “sin tope documentado” y no hereda el corte del mayor plano.
     */
    public static function corteYmd(?string $configKey = null): int
    {
        if ($configKey !== null && $configKey !== '') {
            return self::parseYmd(trim((string) config($configKey, '')));
        }

        foreach ([
            'contable.mayor_plano_cuenta.fuente_erp_hasta',
            'contable.mayor_concepto.fuente_erp_hasta',
        ] as $key) {
            $ymd = self::parseYmd(trim((string) config($key, '')));
            if ($ymd > 0) {
                return $ymd;
            }
        }

        return 0;
    }

    /**
     * Corte documentado según modo (informativo). En Anita es 0; en ERP usa config o default.
     */
    public static function corteEfectivo(string $modo, ?string $configKey = null): int
    {
        $modo = self::normalizarModo($modo);

        if ($modo === self::MODO_ANITA) {
            return 0;
        }

        $corte = self::corteYmd($configKey);

        return $corte > 0 ? $corte : self::CORTE_DEFAULT_YMD;
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
        string $modo = self::MODO_ERP,
        ?string $configKey = null,
    ): array {
        $modo = self::normalizarModo($modo);
        if ($fechaDesde > $fechaHasta && $fechaDesde > 0 && $fechaHasta > 0) {
            [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
        }

        $corte = self::corteEfectivo($modo, $configKey);

        if ($modo === self::MODO_ANITA) {
            return self::soloAnita($modo, $fechaDesde, $fechaHasta, 0);
        }

        return self::soloErp($modo, $fechaDesde, $fechaHasta, $corte);
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
            'etiqueta' => 'Anita (bridge)',
        ];
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
    private static function soloErp(string $modo, int $fechaDesde, int $fechaHasta, int $corte): array
    {
        $usa = $fechaDesde > 0 && $fechaHasta >= $fechaDesde;

        return [
            'modo' => $modo,
            'corte' => $corte,
            'usa_erp' => $usa,
            'usa_anita' => false,
            'tramo_erp_desde' => $usa ? $fechaDesde : 0,
            'tramo_erp_hasta' => $usa ? $fechaHasta : 0,
            'tramo_anita_desde' => 0,
            'tramo_anita_hasta' => 0,
            'etiqueta' => 'ERP nativo (asientos)',
        ];
    }
}
