<?php

namespace App\Support\Sueldos\Lsd;

use App\Support\Sueldos\Formula\ParametroSueldosResolver;

/**
 * Detracción Ley 27.430 art. 4 (contribuciones patronales / base 10 del LSD).
 *
 * Reemplaza al haber 1002 y sus fórmulas Anita (DTBR + modalidad 8/14).
 * El monto mensual vive en parametro_sueldos (DETRACCION_LEY_27430), no hardcodeado.
 *
 * Reglas:
 *  - Jubilado (condicion SIJP 2): no detrae.
 *  - Prorratea por min(días, 30) / 30.
 *  - No supera la base SIPA previa a la detracción.
 *  - Si hay MINIMO_SIPA, base_10 no queda por debajo de ese piso.
 *  - Tope mensual del período: una sola detracción legal (no se duplica entre liqs).
 *  - 67 % tiempo parcial: solo si DETRACCION_MODALIDADES_PARCIAL lista el código AFIP.
 *    En este ERP la modalidad 8/14 de Anita no es “tiempo parcial AFIP”; no se asume.
 */
class LsdDetraccionSupport
{
    public const CODIGO_MONTO = 'DETRACCION_LEY_27430';

    public const CODIGO_FACTOR_PARCIAL = 'DETRACCION_TIEMPO_PARCIAL';

    public const CODIGO_MODALIDADES_PARCIAL = 'DETRACCION_MODALIDADES_PARCIAL';

    /** Fallback si el parámetro aún no está sembrado (valor nominal congelado Ley 27.541). */
    public const MONTO_GENERAL_DEFAULT = 7003.68;

    public const FACTOR_PARCIAL_DEFAULT = 0.67;

    /**
     * @return array{importe_detraer: float, base_10: float}
     */
    public static function resolver(
        float $baseSipaBruta,
        float $cupo,
        float $minimoSipa = 0.0,
    ): array {
        $base = max(0.0, round($baseSipaBruta, 2));
        $cupo = max(0.0, round($cupo, 2));
        $det = min($cupo, $base);
        $base10 = max(0.0, round($base - $det, 2));
        if ($minimoSipa > 0 && $base >= $minimoSipa && $base10 < $minimoSipa) {
            $base10 = round($minimoSipa, 2);
            $det = max(0.0, round($base - $base10, 2));
        }

        return [
            'importe_detraer' => round($det, 2),
            'base_10' => $base10,
        ];
    }

    public static function esJubilado(?string $condicionSijp): bool
    {
        return in_array(ltrim((string) $condicionSijp, '0'), ['2'], true);
    }

    /**
     * @param  list<int>  $modalidadesParcial
     */
    public static function esTiempoParcial(int $modalidad, array $modalidadesParcial): bool
    {
        if ($modalidadesParcial === [] || $modalidad <= 0) {
            return false;
        }

        return in_array($modalidad, $modalidadesParcial, true);
    }

    public static function prorratear(float $montoMensual, int $dias): float
    {
        $dias = max(0, min(30, $dias > 0 ? $dias : 30));

        return round($montoMensual * ($dias / 30.0), 2);
    }

    /**
     * @return list<int>
     */
    public static function modalidadesParcial(ParametroSueldosResolver $parametros): array
    {
        if (! $parametros->tiene(self::CODIGO_MODALIDADES_PARCIAL)) {
            return [];
        }
        $raw = $parametros->valor(self::CODIGO_MODALIDADES_PARCIAL);
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }
        $out = [];
        foreach (preg_split('/[,\s;]+/', $raw) ?: [] as $p) {
            $n = (int) ltrim(trim($p), '0');
            if ($n > 0) {
                $out[] = $n;
            }
        }

        return array_values(array_unique($out));
    }

    public static function montoMensualTabla(ParametroSueldosResolver $parametros): float
    {
        if (! $parametros->tiene(self::CODIGO_MONTO)) {
            return self::MONTO_GENERAL_DEFAULT;
        }

        return max(0.0, (float) $parametros->valor(self::CODIGO_MONTO));
    }

    public static function factorParcial(ParametroSueldosResolver $parametros): float
    {
        if (! $parametros->tiene(self::CODIGO_FACTOR_PARCIAL)) {
            return self::FACTOR_PARCIAL_DEFAULT;
        }
        $f = (float) $parametros->valor(self::CODIGO_FACTOR_PARCIAL);

        return $f > 0 && $f <= 1 ? $f : self::FACTOR_PARCIAL_DEFAULT;
    }

    /**
     * Cupo legal del mes (sin prorrateo por días). Para tope entre liquidaciones.
     *
     * @param  array{condicion_sijp?: string, modalidad_sijp?: int}  $ctx
     */
    public static function cupoMensual(ParametroSueldosResolver $parametros, array $ctx = []): float
    {
        if (self::esJubilado($ctx['condicion_sijp'] ?? '01')) {
            return 0.0;
        }
        $monto = self::montoMensualTabla($parametros);
        $mod = (int) ($ctx['modalidad_sijp'] ?? 0);
        if (self::esTiempoParcial($mod, self::modalidadesParcial($parametros))) {
            $monto = round($monto * self::factorParcial($parametros), 2);
        }

        return $monto;
    }

    /**
     * Cupo de esta liquidación (prorrateado por días).
     *
     * @param  array{condicion_sijp?: string, modalidad_sijp?: int, dias?: int}  $ctx
     */
    public static function cupoLiquidacion(ParametroSueldosResolver $parametros, array $ctx = []): float
    {
        return self::prorratear(
            self::cupoMensual($parametros, $ctx),
            (int) ($ctx['dias'] ?? 30)
        );
    }

    /**
     * Recalcula importe_detraer y base_10. Deshace un 1002 ya mapeado para no duplicar.
     *
     * @param  array<string, float|int>  $bases
     * @param  array{condicion_sijp?: string, modalidad_sijp?: int, dias?: int}  $ctx
     * @return array<string, float|int>
     */
    public static function aplicarSobreBases(
        array $bases,
        ParametroSueldosResolver $parametros,
        array $ctx = [],
    ): array {
        $baseBruta = round(
            (float) ($bases['base_10'] ?? 0) + (float) ($bases['importe_detraer'] ?? 0),
            2
        );
        if ($baseBruta <= 0 && abs((float) ($bases['base_1'] ?? 0)) >= 0.005) {
            $baseBruta = round((float) $bases['base_1'], 2);
        }
        $minimo = (float) $parametros->valor('MINIMO_SIPA');
        $res = self::resolver($baseBruta, self::cupoLiquidacion($parametros, $ctx), $minimo);
        $bases['importe_detraer'] = $res['importe_detraer'];
        $bases['base_10'] = $res['base_10'];

        return $bases;
    }

    /**
     * Tras acumular liquidaciones del mismo período, no superar el cupo mensual.
     *
     * @param  array<string, float|int>  $bases
     * @param  array{condicion_sijp?: string, modalidad_sijp?: int}  $ctx
     * @return array<string, float|int>
     */
    public static function limitarTopeMensual(
        array $bases,
        ParametroSueldosResolver $parametros,
        array $ctx = [],
    ): array {
        $det = (float) ($bases['importe_detraer'] ?? 0);
        $cupo = self::cupoMensual($parametros, $ctx);
        if ($det <= $cupo + 0.004) {
            return $bases;
        }
        $extra = round($det - $cupo, 2);
        $bases['importe_detraer'] = $cupo;
        $bases['base_10'] = round((float) ($bases['base_10'] ?? 0) + $extra, 2);
        $minimo = (float) $parametros->valor('MINIMO_SIPA');
        if ($minimo > 0 && (float) $bases['base_10'] < $minimo) {
            $faltante = round($minimo - (float) $bases['base_10'], 2);
            $bajar = min($faltante, (float) $bases['importe_detraer']);
            $bases['base_10'] = round((float) $bases['base_10'] + $bajar, 2);
            $bases['importe_detraer'] = round((float) $bases['importe_detraer'] - $bajar, 2);
        }

        return $bases;
    }

    /**
     * Importe para la fórmula `detraccion()` del concepto 1002 (opcional en el recibo).
     */
    public static function importeParaFormula(
        ParametroSueldosResolver $parametros,
        int $dias,
        int $modalidadSijp,
        string $condicionSijp,
        float $baseRemunerativa,
    ): float {
        $ctx = [
            'condicion_sijp' => $condicionSijp,
            'modalidad_sijp' => $modalidadSijp,
            'dias' => $dias,
        ];
        $res = self::resolver(
            $baseRemunerativa,
            self::cupoLiquidacion($parametros, $ctx),
            (float) $parametros->valor('MINIMO_SIPA'),
        );

        return $res['importe_detraer'];
    }
}
