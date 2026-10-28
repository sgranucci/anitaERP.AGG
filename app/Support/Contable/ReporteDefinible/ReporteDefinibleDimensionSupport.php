<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Contable\Centrocosto;

/**
 * Helpers de layouts / fuentes de Plan y columnas de c.costo.
 */
class ReporteDefinibleDimensionSupport
{
    /** Asientos de la empresa presupuesto (código operativo + 10), como Anita «Compara con presup.». */
    public const FUENTE_PLAN_EMPRESA_PRESUPUESTO = 'empresa_presupuesto';

    /** Cuentas origen P de la definición (mismo libro Real). */
    public const FUENTE_PLAN_ASIGNACION_P = 'asignacion_p';

    /** Partidas de gasto ERP (cuenta + c.costo + período). */
    public const FUENTE_PLAN_PARTIDAGASTO = 'partidagasto';

    public const MAX_COLUMNAS_CCOSTO = 30;

    /**
     * @return array<string, string>
     */
    public static function fuentesPlan(): array
    {
        return [
            self::FUENTE_PLAN_EMPRESA_PRESUPUESTO => 'Empresa presupuesto (código + 10)',
            self::FUENTE_PLAN_PARTIDAGASTO => 'Partidas de gasto (cuenta + c.costo)',
            self::FUENTE_PLAN_ASIGNACION_P => 'Cuentas origen P de la definición',
        ];
    }

    /**
     * @param  list<int>  $codigosEnMovimientos
     * @param  array{desde: int, hasta: int}|null  $filtroRuntime
     * @return array{columnas: list<array<string, mixed>>, truncado: bool, total_ccostos: int}
     */
    public static function armarColumnasCcosto(
        array $codigosEnMovimientos,
        ?array $filtroRuntime,
        bool $incluirSinCcosto = true,
        bool $incluirTotal = true,
        int $max = self::MAX_COLUMNAS_CCOSTO,
    ): array {
        $codigos = array_values(array_unique(array_filter(
            array_map('intval', $codigosEnMovimientos),
            fn (int $c) => $c > 0
        )));
        sort($codigos);

        $filtraGrupo = isset($filtroRuntime['codigo_set']) && is_array($filtroRuntime['codigo_set']);
        if ($filtroRuntime !== null) {
            $d = (int) ($filtroRuntime['desde'] ?? 0);
            $h = (int) ($filtroRuntime['hasta'] ?? 0);
            if ($d > 0 || $h > 0) {
                if ($h <= 0) {
                    $h = $d;
                }
                if ($d <= 0) {
                    $d = $h;
                }
                $codigos = array_values(array_filter(
                    $codigos,
                    fn (int $c) => $c >= $d && $c <= $h
                ));
            }
            if ($filtraGrupo) {
                $codigos = array_values(array_filter(
                    $codigos,
                    fn (int $c) => isset($filtroRuntime['codigo_set'][$c])
                ));
            }
        }

        $nombres = Centrocosto::query()
            ->whereIn('codigo', array_map('strval', $codigos))
            ->pluck('nombre', 'codigo')
            ->all();

        $columnas = [];
        $incluyeSin = $incluirSinCcosto
            && ! $filtraGrupo
            && ($filtroRuntime === null || (int) ($filtroRuntime['desde'] ?? 0) <= 0);
        if ($incluyeSin) {
            $columnas[] = [
                'codigo' => 0,
                'key' => 'cc:0',
                'label' => 'Sin c.costo',
                'tipo' => 'actual',
            ];
        }

        $truncado = false;
        foreach ($codigos as $codigo) {
            if ($codigo <= 0) {
                continue;
            }
            if (count($columnas) >= $max) {
                $truncado = true;
                break;
            }
            $nom = trim((string) ($nombres[(string) $codigo] ?? ''));
            $columnas[] = [
                'codigo' => $codigo,
                'key' => 'cc:'.$codigo,
                'label' => $nom !== '' ? $codigo.' '.$nom : (string) $codigo,
                'tipo' => 'actual',
            ];
        }

        if ($incluirTotal) {
            $columnas[] = [
                'codigo' => -1,
                'key' => 'cc:total',
                'label' => 'Total',
                'tipo' => 'actual',
            ];
        }

        return [
            'columnas' => $columnas,
            'truncado' => $truncado,
            'total_ccostos' => count($codigos),
        ];
    }
}
