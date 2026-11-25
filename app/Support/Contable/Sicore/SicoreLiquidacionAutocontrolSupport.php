<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

/**
 * Autocontrol de liquidación SICORE vs mayor.
 *
 * En DJ el mayor ya refleja saldo a favor e ingresos a cuenta cargados a mano:
 * hay que restarlos del total SICORE antes de comparar. En 787 no se restan
 * los ingresos a cuenta (se pagan después del "Hasta" de sueldos). En 1Q no se ajusta.
 */
final class SicoreLiquidacionAutocontrolSupport
{
    /** Códigos a los que se les restan ingresos a cuenta en el autocontrol DJ. */
    private const CODIGOS_RESTA_INGRESOS = ['217', '767'];

    /**
     * Total SICORE comparable al mayor (col. P) según tipo de liquidación.
     *
     * @param  array<string, float>  $saf
     * @param  array<string, array{fecha?: string, importe?: float}|float>  $ingresos
     */
    public static function sicoreComparableMayor(
        string $tipo,
        string $codigo,
        float $totalSicore,
        array $saf = [],
        array $ingresos = [],
    ): float {
        $total = round($totalSicore, 2);
        if (! SicoreLiquidacionTipoSupport::esDj($tipo)) {
            return $total;
        }

        $codigo = (string) $codigo;
        $safCod = round((float) ($saf[$codigo] ?? 0), 2);
        $ingCod = 0.0;
        if (in_array($codigo, self::CODIGOS_RESTA_INGRESOS, true)) {
            $ing = $ingresos[$codigo] ?? 0;
            $ingCod = is_array($ing)
                ? round((float) ($ing['importe'] ?? 0), 2)
                : round((float) $ing, 2);
        }

        return round($total - $safCod - $ingCod, 2);
    }

    /**
     * Ajusta la conciliación del listado/PDF para DJ: Total SICORE comparable al mayor.
     * 1Q se devuelve sin cambios. El control de período sigue contra el bruto emitido.
     *
     * @param  array<string, mixed>  $conciliacion
     * @param  array<string, float>  $saf
     * @param  array<string, array{fecha?: string, importe?: float}|float>  $ingresos
     * @return array<string, mixed>
     */
    public static function ajustarConciliacion(
        array $conciliacion,
        string $tipo,
        array $saf = [],
        array $ingresos = [],
    ): array {
        if ($conciliacion === [] || empty($conciliacion['habilitada']) || ! SicoreLiquidacionTipoSupport::esDj($tipo)) {
            return $conciliacion;
        }

        $items = [];
        foreach ($conciliacion['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $bruto = round((float) ($item['total_sicore'] ?? 0), 2);
            $codigo = (string) ($item['codigo_impuesto'] ?? '');
            $comparable = self::sicoreComparableMayor($tipo, $codigo, $bruto, $saf, $ingresos);
            $totalMayor = round((float) ($item['total_mayor'] ?? 0), 2);
            $totalMayorPeriodo = round((float) ($item['total_mayor_periodo'] ?? 0), 2);

            $item['total_sicore_bruto'] = $bruto;
            $item['total_sicore'] = $comparable;
            $item['diferencia'] = round($comparable - $totalMayor, 2);
            $item['cuadra'] = SicoreFormatoV8Support::cuadraConciliacion($comparable, $totalMayor);
            $item['diferencia_periodo'] = round($bruto - $totalMayorPeriodo, 2);
            $item['cuadra_periodo'] = SicoreFormatoV8Support::cuadraConciliacion($bruto, $totalMayorPeriodo);
            $items[] = $item;
        }
        $conciliacion['items'] = $items;
        $conciliacion['ajustada_dj'] = true;

        return $conciliacion;
    }

    /**
     * @param  array<string, array{part: array<string, mixed>, conciliacion: array<string, mixed>}>  $porCodigo
     * @param  array<string, float>  $saf
     * @param  array<string, array{fecha?: string, importe?: float}|float>  $ingresos
     * @return array{ok: bool, items: list<array<string, mixed>>}
     */
    public static function evaluar(
        array $porCodigo,
        string $tipo = SicoreLiquidacionTipoSupport::TIPO_1Q,
        array $saf = [],
        array $ingresos = [],
    ): array {
        $items = [];
        $okGlobal = true;

        foreach ($porCodigo as $cod => $data) {
            $part = $data['part'] ?? [];
            $totalDetalle = round((float) ($part['total'] ?? 0), 2);
            $itemConc = self::itemConciliacionPorCodigo($data['conciliacion'] ?? [], (int) $cod);
            $totalSicore = round((float) ($itemConc['total_sicore'] ?? $totalDetalle), 2);
            $totalMayor = round((float) ($itemConc['total_mayor'] ?? 0), 2);
            $sicoreComparable = self::sicoreComparableMayor($tipo, (string) $cod, $totalSicore, $saf, $ingresos);

            $okDet = SicoreFormatoV8Support::cuadra($totalDetalle, $totalSicore);
            $okMay = $itemConc === []
                || SicoreFormatoV8Support::cuadraConciliacion($sicoreComparable, $totalMayor);
            $ok = $okDet && $okMay;
            $okGlobal = $okGlobal && $ok;

            $items[] = [
                'codigo' => (string) $cod,
                'total_detalle' => $totalDetalle,
                'total_sicore' => $totalSicore,
                'total_sicore_comparable' => $sicoreComparable,
                'total_mayor' => $totalMayor,
                'estado' => ! empty($itemConc['cuadra']) || $okMay ? 'OK' : 'Diferencia',
                'ok' => $ok,
            ];
        }

        return ['ok' => $okGlobal, 'items' => $items];
    }

    /**
     * @param  array<string, mixed>  $conciliacion
     * @return array<string, mixed>
     */
    private static function itemConciliacionPorCodigo(array $conciliacion, int $codigo): array
    {
        foreach ($conciliacion['items'] ?? [] as $item) {
            if ((int) ($item['codigo_impuesto'] ?? 0) === $codigo) {
                return $item;
            }
        }

        return [];
    }
}
