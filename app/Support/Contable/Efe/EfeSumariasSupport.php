<?php

namespace App\Support\Contable\Efe;

use App\Services\Contable\MayorPlanoCuentaReporteService;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Saldos de disponibilidad (solapa Sumarias) vía mayor plano por cuenta.
 */
class EfeSumariasSupport
{
    private const PLANTILLA_RELATIVA = 'templates/contable/efe_plantilla.xlsx';

    public function __construct(
        private readonly MayorPlanoCuentaReporteService $mayorPlanoService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros  EfeMensualListadoFiltros
     * @return list<array{
     *   cuenta_codigo: string,
     *   cuenta_nombre: string,
     *   saldo_ejer: float,
     *   ajuste: float,
     *   saldo_ajustado: float,
     *   saldo_mes_anterior: float
     * }>
     */
    public function generar(array $filtros): array
    {
        $cuentasPlantilla = $this->cuentasDesdePlantilla();
        if ($cuentasPlantilla === []) {
            return [];
        }

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $mes = (int) ($filtros['mes'] ?? 0);
        $anio = (int) ($filtros['anio'] ?? 0);
        if ($empresaId <= 0 || $mes <= 0 || $anio <= 0) {
            return [];
        }

        $filtrosMayorMes = $this->filtrosMayorPlanoMes($filtros, $mes, $anio);
        $resultadoMes = $this->mayorPlanoService->generarDesdeFiltros($filtrosMayorMes);
        $saldosMes = $this->saldosFinalesPorCuenta($resultadoMes);

        $fechaMesAnterior = Carbon::createFromDate($anio, $mes, 1)->subMonth();
        $filtrosMayorAnt = $this->filtrosMayorPlanoMes(
            $filtros,
            (int) $fechaMesAnterior->month,
            (int) $fechaMesAnterior->year,
        );
        $resultadoAnt = $this->mayorPlanoService->generarDesdeFiltros($filtrosMayorAnt);
        $saldosAnt = $this->saldosFinalesPorCuenta($resultadoAnt);

        $filas = [];
        foreach ($cuentasPlantilla as $item) {
            $codigo = (int) $item['codigo'];
            $saldoEjer = (float) ($saldosMes[$codigo] ?? 0.0);
            $ajuste = 0.0;
            $saldoAnt = (float) ($saldosAnt[$codigo] ?? 0.0);

            $filas[] = [
                'cuenta_codigo' => $item['cuenta_codigo'],
                'cuenta_nombre' => $item['cuenta_nombre'],
                'saldo_ejer' => round($saldoEjer, 2),
                'ajuste' => $ajuste,
                'saldo_ajustado' => round($saldoEjer + $ajuste, 2),
                'saldo_mes_anterior' => round($saldoAnt, 2),
            ];
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array<int, float>
     */
    private function saldosFinalesPorCuenta(array $resultado): array
    {
        $out = [];
        foreach ($resultado['secciones'] ?? [] as $seccion) {
            $cuenta = (int) ($seccion['cuenta'] ?? 0);
            if ($cuenta <= 0) {
                continue;
            }

            $lineas = $seccion['lineas'] ?? [];
            if ($lineas !== []) {
                $ultima = $lineas[count($lineas) - 1];
                $out[$cuenta] = (float) ($ultima['saldo_ejercicio'] ?? 0);
                continue;
            }

            $out[$cuenta] = (float) ($seccion['saldo_inicial'] ?? 0);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function filtrosMayorPlanoMes(array $filtros, int $mes, int $anio): array
    {
        return [
            'empresa_ids' => [(int) ($filtros['empresa_id'] ?? 0)],
            'consolidar_empresas' => true,
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
            'modo_periodo' => 'mes',
            'mes' => $mes,
            'anio' => $anio,
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'solo_moneda_origen' => (bool) ($filtros['solo_moneda_origen'] ?? false),
            'incluye_subdiario' => true,
            'modo_inclusion_asientos' => 'sin_cierre_ni_inflacion',
            'cuenta_desde' => 111010001,
            'cuenta_hasta' => 112010008,
            'filtro_texto' => '',
        ];
    }

    /**
     * @return list<array{codigo: int, cuenta_codigo: string, cuenta_nombre: string}>
     */
    private function cuentasDesdePlantilla(): array
    {
        $jsonPath = storage_path('templates/contable/efe_sumarias_cuentas.json');
        if (is_file($jsonPath)) {
            $decoded = json_decode((string) file_get_contents($jsonPath), true);
            if (is_array($decoded)) {
                $cuentas = [];
                foreach ($decoded as $item) {
                    $codigoTxt = trim((string) ($item['cuenta_codigo'] ?? ''));
                    if ($codigoTxt === '') {
                        continue;
                    }
                    $codigo = MayorPlanoCuentaSupport::parsearCodigoCuenta(str_replace('-', '', $codigoTxt));
                    if ($codigo <= 0) {
                        continue;
                    }
                    $cuentas[] = [
                        'codigo' => $codigo,
                        'cuenta_codigo' => $codigoTxt,
                        'cuenta_nombre' => (string) ($item['cuenta_nombre'] ?? ''),
                    ];
                }

                return $cuentas;
            }
        }

        $path = storage_path(self::PLANTILLA_RELATIVA);
        if (! is_file($path)) {
            return [];
        }

        $reader = IOFactory::createReader('Xlsx');
        $reader->setLoadSheetsOnly(['Sumarias']);
        $sheet = $reader->load($path)->getSheetByName('Sumarias');
        if ($sheet === null) {
            return [];
        }

        $cuentas = [];
        for ($fila = 2; $fila <= 67; $fila++) {
            $codigoTxt = trim((string) $sheet->getCell('A'.$fila)->getValue());
            if ($codigoTxt === '') {
                continue;
            }

            $codigo = MayorPlanoCuentaSupport::parsearCodigoCuenta(str_replace('-', '', $codigoTxt));
            if ($codigo <= 0) {
                continue;
            }

            $cuentas[] = [
                'codigo' => $codigo,
                'cuenta_codigo' => $codigoTxt,
                'cuenta_nombre' => (string) $sheet->getCell('B'.$fila)->getValue(),
            ];
        }

        return $cuentas;
    }
}
