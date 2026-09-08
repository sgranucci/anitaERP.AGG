<?php

declare(strict_types=1);

namespace App\Services\Contable\IngresosBrutos;

use App\Models\Contable\Iibb_Presentacion_Config;
use App\Support\Contable\IngresosBrutos\IngresosBrutosFormatoArbaSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use App\Support\Contable\Sicore\SicoreConciliacionAuditoriaSupport;
use App\Support\Contable\Sicore\SicoreSaldoEjercicioSupport;

/**
 * Conciliación IIBB vs mayor: suma del período vs col. P (saldo ejerc.)
 * del último movimiento de la quincena/mes elegida.
 */
final class IngresosBrutosConciliacionContableService
{
    public function __construct(
        private readonly SicoreSaldoEjercicioSupport $saldoEjercicioSupport,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<array<string, mixed>>  $registros
     * @return array<string, mixed>
     */
    public function conciliar(array $filtros, array $registros, Iibb_Presentacion_Config $config): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId <= 0 || empty($filtros['conciliar_contable'])) {
            return ['habilitada' => false, 'items' => []];
        }

        $hasta = (string) ($filtros['fecha_hasta'] ?? '');

        $cuentasDetalle = $config->cuentas
            ->where('empresa_id', $empresaId)
            ->map(static fn ($c) => [
                'id' => (int) $c->cuentacontable_id,
                'codigo' => $c->cuentacontable?->codigo ?? '',
                'nombre' => $c->cuentacontable?->nombre ?? '',
                'tipocuenta' => $c->cuentacontable?->tipocuenta ?? null,
            ])
            ->values()
            ->all();

        $totalIibb = round(array_sum(array_map(
            static fn (array $r) => (float) ($r['importe'] ?? 0),
            $registros,
        )), 2);

        $cuentaInversa = SicoreConciliacionAuditoriaSupport::cuentasSonInversas($cuentasDetalle);

        // Col. P del mayor plano: saldo de ejercicio al último movimiento ≤ fecha_hasta.
        $totalMayor = $this->saldoEjercicioSupport->saldoComparable(
            $empresaId,
            $hasta,
            $cuentasDetalle,
            $cuentaInversa,
        );

        $tolerancia = IngresosBrutosFormatoArbaSupport::tolerancia();
        $dif = round($totalIibb - $totalMayor, 2);

        return [
            'habilitada' => true,
            'items' => [[
                'config_id' => (int) $config->id,
                'codigo_impuesto' => (int) ($config->codigo_actividad_arba ?? 0),
                'nombre' => $config->nombre,
                'criterio' => $config->tipo,
                'concilia_con' => 'ingresos_brutos',
                'cuentas' => $cuentasDetalle,
                'cuenta_inversa' => $cuentaInversa,
                'total_iibb' => $totalIibb,
                'total_mayor' => $totalMayor,
                'diferencia' => $dif,
                'cuadra' => IngresosBrutosFormatoArbaSupport::cuadra($totalIibb, $totalMayor),
                'registros' => count($registros),
                'tolerancia' => $tolerancia,
            ]],
            'tolerancia' => $tolerancia,
            'saldo_ejercicio_desde' => self::ymdAIso(MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD),
            'saldo_ejercicio_hasta' => $hasta,
        ];
    }

    private static function ymdAIso(int $ymd): string
    {
        $s = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }
}
