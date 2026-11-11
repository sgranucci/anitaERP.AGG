<?php

declare(strict_types=1);

namespace App\Services\Contable\Sicore;

use App\Models\Contable\Sicore_Config;
use App\Support\Contable\MayorFuenteConsultaSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use App\Support\Contable\Sicore\SicoreConciliacionAuditoriaSupport;
use App\Support\Contable\Sicore\SicoreConfigCuentaSupport;
use App\Support\Contable\Sicore\SicoreFormatoV8Support;
use App\Support\Contable\Sicore\SicoreMayorComparableSupport;
use App\Support\Contable\Sicore\SicoreSaldoEjercicioSupport;
use Illuminate\Support\Collection;

/**
 * Conciliación SICORE vs mayor del ERP:
 * 1) saldo de ejercicio (col. P) al cierre del rango;
 * 2) opcional: solo movimientos del período vs retenciones emitidas.
 */
final class SicoreConciliacionContableService
{
    public function __construct(
        private readonly SicoreSaldoEjercicioSupport $saldoEjercicioSupport,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<array<string, mixed>>  $registros
     * @param  Collection<int, Sicore_Config>  $configs
     * @return array<string, mixed>
     */
    public function conciliar(array $filtros, array $registros, Collection $configs): array
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
        $tolerancia = SicoreFormatoV8Support::toleranciaConciliacion();
        $mostrarControlPeriodo = ! empty($filtros['control_periodo']);

        // Precarga saldos de ejercicio (col. P) de todas las cuentas del proceso en un solo mayor plano.
        $codigosSaldo = [];
        foreach ($configs as $configPrecarga) {
            foreach ($this->cuentasDetalle($configPrecarga, $empresaId) as $c) {
                $codigo = (int) preg_replace('/\D/', '', (string) ($c['codigo'] ?? ''));
                if ($codigo > 0) {
                    $codigosSaldo[$codigo] = $codigo;
                }
            }
        }
        $this->saldoEjercicioSupport->precargar(
            $empresaId,
            $hasta,
            array_values($codigosSaldo),
            MayorFuenteConsultaSupport::MODO_ERP,
            $desde,
        );

        $items = [];
        foreach ($configs as $config) {
            $configId = (int) $config->id;

            $registrosConfig = array_values(array_filter(
                $registros,
                static fn (array $r) => (int) ($r['sicore_config_id'] ?? 0) === $configId,
            ));

            $totalSicore = round(array_sum(array_map(
                static fn (array $r) => (float) ($r['importe'] ?? 0),
                $registrosConfig,
            )), 2);

            $cuentasDetalle = $this->cuentasDetalle($config, $empresaId);

            $cuentaInversa = SicoreConciliacionAuditoriaSupport::cuentasSonInversas($cuentasDetalle);

            // Col. P del mayor del ERP, en convención SICORE (haber +).
            $totalMayor = $this->saldoEjercicioSupport->saldoComparable(
                $empresaId,
                $hasta,
                $cuentasDetalle,
                $cuentaInversa,
                MayorFuenteConsultaSupport::MODO_ERP,
                $desde,
            );

            $codigosItem = [];
            foreach ($cuentasDetalle as $cuentaItem) {
                $codigoItem = (int) preg_replace('/\D/', '', (string) ($cuentaItem['codigo'] ?? ''));
                if ($codigoItem > 0) {
                    $codigosItem[] = $codigoItem;
                }
            }
            $particion = SicoreMayorComparableSupport::particionar(
                $this->saldoEjercicioSupport->lineasPeriodo(
                    $empresaId,
                    $desde,
                    $hasta,
                    $codigosItem,
                    MayorFuenteConsultaSupport::MODO_ERP,
                ),
            );
            $auditoria = $cuentasDetalle === []
                ? null
                : SicoreConciliacionAuditoriaSupport::auditarOperaciones(
                    $registrosConfig,
                    $particion['comparables'],
                    $cuentaInversa,
                    $tolerancia,
                );

            // Solo movimientos del rango consultado (retenciones emitidas / anuladas),
            // sin arrastrar el saldo desde principio de ejercicio.
            $totalMayorPeriodo = round(array_sum(array_map(
                static fn (array $m) => SicoreConciliacionAuditoriaSupport::importeLineaMayorFirmado($m),
                $particion['comparables'],
            )), 2);

            $dif = round($totalSicore - $totalMayor, 2);
            $difPeriodo = round($totalSicore - $totalMayorPeriodo, 2);

            $items[] = [
                'config_id' => $configId,
                'codigo_impuesto' => (int) $config->codigo_impuesto,
                'codigo_regimen' => (int) ($config->codigo_regimen ?? 0),
                'nombre' => $config->nombre,
                'criterio' => $config->criterio,
                'concilia_con' => $config->concilia_con,
                'cuentas' => $cuentasDetalle,
                'cuenta_inversa' => $cuentaInversa,
                'total_sicore' => $totalSicore,
                'total_mayor' => $totalMayor,
                'diferencia' => $dif,
                'cuadra' => SicoreFormatoV8Support::cuadraConciliacion($totalSicore, $totalMayor),
                'total_mayor_periodo' => $totalMayorPeriodo,
                'diferencia_periodo' => $difPeriodo,
                'cuadra_periodo' => SicoreFormatoV8Support::cuadraConciliacion($totalSicore, $totalMayorPeriodo),
                'registros' => count($registrosConfig),
                'tolerancia' => $tolerancia,
                'auditoria' => $auditoria,
                'mayor_excluidos' => count($particion['excluidos']),
            ];
        }

        return [
            'habilitada' => true,
            'items' => $items,
            'tolerancia' => $tolerancia,
            'control_periodo' => $mostrarControlPeriodo,
            'periodo_desde' => $desde,
            'periodo_hasta' => $hasta,
            'saldo_ejercicio_desde' => self::ymdAIso(MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD),
            'saldo_ejercicio_hasta' => $hasta,
        ];
    }

    /**
     * Cuenta cargada en la configuración SICORE.
     * Ganancias sin cuentas en la configuración usa la automática RGP.
     *
     * @return list<array{id: int, codigo: string, nombre: string, tipocuenta: ?string}>
     */
    private function cuentasDetalle(Sicore_Config $config, int $empresaId): array
    {
        return SicoreConfigCuentaSupport::detalle($config, $empresaId);
    }

    private static function ymdAIso(int $ymd): string
    {
        $s = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }
}
