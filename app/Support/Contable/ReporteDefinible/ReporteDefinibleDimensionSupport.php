<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Contable\Centrocosto;

/**
 * Helpers de layouts / fuentes de Plan y columnas de c.costo.
 */
class ReporteDefinibleDimensionSupport
{
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

        if ($filtroRuntime !== null) {
            $d = (int) $filtroRuntime['desde'];
            $h = (int) $filtroRuntime['hasta'];
            $codigos = array_values(array_filter(
                $codigos,
                fn (int $c) => $c >= $d && $c <= $h
            ));
        }

        $nombres = Centrocosto::query()
            ->whereIn('codigo', array_map('strval', $codigos))
            ->pluck('nombre', 'codigo')
            ->all();

        $columnas = [];
        if ($incluirSinCcosto && ($filtroRuntime === null || (int) $filtroRuntime['desde'] <= 0)) {
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
