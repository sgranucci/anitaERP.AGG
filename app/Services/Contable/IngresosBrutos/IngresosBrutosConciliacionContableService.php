<?php

declare(strict_types=1);

namespace App\Services\Contable\IngresosBrutos;

use App\Models\Contable\Iibb_Presentacion_Config;
use App\Support\Contable\MayorFuenteConsultaSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use App\Support\Contable\Sicore\SicoreConciliacionAuditoriaSupport;
use App\Support\Contable\Sicore\SicoreMayorComparableSupport;
use App\Support\Contable\Sicore\SicoreSaldoEjercicioSupport;

/**
 * Conciliación IIBB vs mayor Anita:
 * 1) saldo de ejercicio (col. P) al cierre del rango;
 * 2) control del período: solo movimientos comparables del rango
 *    (retenciones emitidas / anuladas / devoluciones), sin pagos ARBA/DDJJ.
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
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        if ($desde === '') {
            $desde = $hasta;
        }
        $mostrarControlPeriodo = ! empty($filtros['control_periodo']);

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
        $codigos = $this->saldoEjercicioSupport->codigosNumericos($cuentasDetalle);

        // Precarga col. P + líneas del período en un solo mayor Anita.
        $this->saldoEjercicioSupport->precargar(
            $empresaId,
            $hasta,
            $codigos,
            MayorFuenteConsultaSupport::MODO_ANITA,
            $desde,
        );

        $totalMayor = $this->saldoEjercicioSupport->saldoComparable(
            $empresaId,
            $hasta,
            $cuentasDetalle,
            $cuentaInversa,
            MayorFuenteConsultaSupport::MODO_ANITA,
            $desde,
        );

        $lineasPeriodo = $this->saldoEjercicioSupport->lineasPeriodo(
            $empresaId,
            $desde,
            $hasta,
            $codigos,
            MayorFuenteConsultaSupport::MODO_ANITA,
        );
        $particion = SicoreMayorComparableSupport::particionar($lineasPeriodo);

        $totalMayorPeriodo = round(array_sum(array_map(
            static fn (array $m) => SicoreConciliacionAuditoriaSupport::importeLineaMayorFirmado($m),
            $particion['comparables'],
        )), 2);

        $tolerancia = (float) $config->tolerancia_conciliacion;
        $dif = round($totalIibb - $totalMayor, 2);
        $difPeriodo = round($totalIibb - $totalMayorPeriodo, 2);

        $auditoria = $cuentasDetalle === []
            ? null
            : SicoreConciliacionAuditoriaSupport::auditarOperaciones(
                $registros,
                $particion['comparables'],
                $cuentaInversa,
                $tolerancia,
            );

        $excluidosDetalle = array_map(static function (array $m): array {
            return [
                'fecha' => (string) ($m['fecha'] ?? ''),
                'asiento' => $m['asiento_id'] ?? $m['numeroasiento'] ?? null,
                'detalle' => (string) ($m['detalle'] ?? ''),
                'importe' => SicoreConciliacionAuditoriaSupport::importeLineaMayorFirmado($m),
                'motivo' => (string) ($m['motivo_exclusion'] ?? ''),
            ];
        }, $particion['excluidos']);
        $excluidosNeto = round(array_sum(array_map(
            static fn (array $e) => (float) ($e['importe'] ?? 0),
            $excluidosDetalle,
        )), 2);

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
                'cuadra' => abs(round($totalIibb - $totalMayor, 2)) <= $tolerancia,
                'total_mayor_periodo' => $totalMayorPeriodo,
                'diferencia_periodo' => $difPeriodo,
                'cuadra_periodo' => abs(round($totalIibb - $totalMayorPeriodo, 2)) <= $tolerancia,
                'registros' => count($registros),
                'tolerancia' => $tolerancia,
                'auditoria' => $auditoria,
                'mayor_excluidos' => count($particion['excluidos']),
                'mayor_excluidos_neto' => $excluidosNeto,
                'mayor_excluidos_detalle' => $excluidosDetalle,
            ]],
            'tolerancia' => $tolerancia,
            'control_periodo' => $mostrarControlPeriodo,
            'periodo_desde' => $desde,
            'periodo_hasta' => $hasta,
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
