<?php

namespace App\Console\Commands;

use App\Services\Contable\MayorConceptoComparacionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CompararMayorConceptoCommand extends Command
{
    protected $signature = 'contable:comparar-mayor-concepto
                            {--csv= : Ruta al export CSV Anita l_mayorconc (opcional)}
                            {--mayor-plano-csv= : Ruta al export CSV Anita l_mayor (objetivo final disp.)}
                            {--empresa=1 : ID empresa}
                            {--mes= : Mes 1-12 (default: mes actual)}
                            {--anio= : Año (default: año actual)}
                            {--moneda=1 : Moneda de reporte}
                            {--tolerancia=0.05 : Tolerancia en pesos para diferencias}
                            {--salida= : Directorio salida (default: storage/app/reportes)}
                            {--detalle=20 : Cantidad máxima de filas diff a mostrar en consola}
                            {--sin-csv : Solo comparar ERP vs mayor plano, sin CSV Anita}';

    protected $description = 'Compara mayor por concepto ERP vs export Anita y vs mayor plano de disponibilidad';

    public function handle(MayorConceptoComparacionService $service): int
    {
        $empresaId = (int) $this->option('empresa');
        $mes = $this->option('mes') !== null ? (int) $this->option('mes') : (int) date('n');
        $anio = $this->option('anio') !== null ? (int) $this->option('anio') : (int) date('Y');
        $monedaId = max(1, (int) $this->option('moneda'));
        $tolerancia = (float) $this->option('tolerancia');
        $maxDetalle = max(0, (int) $this->option('detalle'));

        $rutaCsv = $this->option('sin-csv') ? null : $this->option('csv');
        if ($rutaCsv !== null && trim((string) $rutaCsv) === '') {
            $rutaCsv = null;
        }

        $rutaMayorPlano = $this->option('mayor-plano-csv');
        if ($rutaMayorPlano !== null && trim((string) $rutaMayorPlano) === '') {
            $rutaMayorPlano = null;
        }

        $salida = $this->option('salida');
        $directorioSalida = is_string($salida) && trim($salida) !== ''
            ? trim($salida)
            : Storage::path('reportes');

        $filtros = [
            'empresa_id' => $empresaId,
            'moneda_id' => $monedaId,
            'modo_periodo' => 'mes',
            'mes' => $mes,
            'anio' => $anio,
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'solo_moneda_origen' => false,
            'agrupacion_resumen' => 'concepto_cuenta',
        ];

        $this->info(sprintf(
            'Comparación mayor por concepto — empresa %d, %02d/%d, moneda %d, tolerancia %.2f',
            $empresaId,
            $mes,
            $anio,
            $monedaId,
            $tolerancia,
        ));

        if ($rutaCsv !== null) {
            $this->line('CSV Anita: '.$rutaCsv);
        } else {
            $this->comment('Sin CSV mayorconc Anita.');
        }
        if ($rutaMayorPlano !== null) {
            $this->line('CSV mayor plano Anita (l_mayor): '.$rutaMayorPlano);
        }

        try {
            $ejecucion = $service->ejecutar($filtros, $rutaCsv, $tolerancia, $directorioSalida, $rutaMayorPlano);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            Log::error('mayor_concepto.comparacion.fallo', [
                'empresa_id' => $empresaId,
                'mes' => $mes,
                'anio' => $anio,
                'csv' => $rutaCsv,
                'exception' => $e,
            ]);

            return self::FAILURE;
        }

        $informe = $ejecucion['informe'];
        $resumen = $informe['resumen'] ?? [];
        $resultado = $ejecucion['resultado_erp'];

        $this->newLine();
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Líneas ERP', (string) ($resumen['lineas_erp'] ?? 0)],
                ['Subdiario leído', (string) ($resultado['stats']['subdiario_filas'] ?? 0)],
                ['Ctamov leído', (string) ($resultado['stats']['ctamov_filas'] ?? 0)],
                ['Ops procesadas', (string) ($resultado['stats']['operaciones_procesadas'] ?? 0)],
                ['Líneas Anita', (string) ($resumen['lineas_anita'] ?? '—')],
                ['Coincidencias', (string) ($resumen['coincidencias_lineas'] ?? '—')],
                ['Solo ERP', (string) ($resumen['solo_erp'] ?? '—')],
                ['Solo Anita', (string) ($resumen['solo_anita'] ?? '—')],
                ['Cuadra Anita', $this->fmtBool($resumen['cuadra_anita'] ?? null)],
                ['Cuadra mayor plano (bridge)', $this->fmtBool($resumen['cuadra_mayor_plano'] ?? null)],
                ['Cuadra vs l_mayor Anita', $this->fmtBool($resumen['cuadra_mayor_plano_anita'] ?? null)],
                ['Cuentas Δ l_mayor', (string) ($resumen['cuentas_descuadradas_mayor_plano_anita'] ?? '—')],
                ['Cuadra contrapartidas (op. disp.)', $this->fmtBool($resumen['cuadra_mayor_plano_analitico'] ?? null)],
                ['Cuentas Δ contrapartidas', (string) ($resumen['cuentas_descuadradas_analitico'] ?? '—')],
                ['Aplicped precargadas', (string) ($resultado['stats']['aplicped_precargadas'] ?? '—')],
                ['COM precargados', (string) ($resultado['stats']['com_subdiario_precargados'] ?? '—')],
                ['Bridge consultas indiv.', (string) ($resultado['stats']['bridge_consultas_individuales'] ?? '—')],
            ],
        );

        $mayorPlanoAnita = $informe['mayor_plano_anita'] ?? null;
        if (is_array($mayorPlanoAnita)) {
            $diffsAnitaPlano = array_values(array_filter(
                $mayorPlanoAnita['filas'] ?? [],
                fn ($f) => empty($f['cuadra']),
            ));
            if ($diffsAnitaPlano !== []) {
                $this->warn('Diferencias imputado ERP vs l_mayor Anita ('.count($diffsAnitaPlano).' cuentas disp.):');
                $this->table(
                    ['Cuenta', 'Anita D', 'Anita H', 'Imput. D', 'Imput. H', 'Δ D', 'Δ H', 'Bridge Δ D'],
                    array_slice(array_map(fn ($f) => [
                        ($f['cuenta_codigo'] ?? '').' '.mb_substr((string) ($f['cuenta_nombre'] ?? ''), 0, 16),
                        $this->fmt($f['anita_debe'] ?? 0),
                        $this->fmt($f['anita_haber'] ?? 0),
                        $this->fmt($f['imputado_debe'] ?? 0),
                        $this->fmt($f['imputado_haber'] ?? 0),
                        $this->fmtDiff($f['diferencia_debe'] ?? 0),
                        $this->fmtDiff($f['diferencia_haber'] ?? 0),
                        $this->fmtDiff($f['diff_anita_bridge_debe'] ?? 0),
                    ], $diffsAnitaPlano), 0, 20),
                );
            } elseif ($resumen['cuadra_mayor_plano_anita'] ?? false) {
                $this->info('Objetivo l_mayor: imputación ERP por cuenta de disponibilidad cuadra con Anita.');
            }
        }

        $analitico = $informe['mayor_plano_analitico'] ?? [];
        $diffsAnalitico = array_values(array_filter(
            $analitico['filas'] ?? [],
            fn ($f) => empty($f['cuadra']) && (int) ($f['lineas_imputadas'] ?? 0) > 0,
        ));

        if ($diffsAnalitico !== []) {
            $this->warn('Diferencias contrapartidas (op. disponibilidad) vs imputado ('.count($diffsAnalitico).' cuentas):');
            $this->table(
                ['Cuenta', 'Plano D', 'Plano H', 'Imput. D', 'Imput. H', 'Δ D', 'Δ H'],
                array_slice(array_map(fn ($f) => [
                    ($f['cuenta_codigo'] ?? '').' '.mb_substr((string) ($f['cuenta_nombre'] ?? ''), 0, 18),
                    $this->fmt($f['plano_debe'] ?? 0),
                    $this->fmt($f['plano_haber'] ?? 0),
                    $this->fmt($f['imputado_debe'] ?? 0),
                    $this->fmt($f['imputado_haber'] ?? 0),
                    $this->fmtDiff($f['diferencia_debe'] ?? 0),
                    $this->fmtDiff($f['diferencia_haber'] ?? 0),
                ], $diffsAnalitico), 0, 15),
            );
        } elseif ($resumen['cuadra_mayor_plano_analitico'] ?? false) {
            $this->info('Contrapartidas desde operaciones de disponibilidad: cuadra con imputación visible por cuenta.');
        }

        $plano = $informe['mayor_plano'] ?? [];
        $diffsPlano = array_values(array_filter(
            $plano['filas'] ?? [],
            fn ($f) => empty($f['cuadra']),
        ));

        if ($diffsPlano !== []) {
            $this->warn('Diferencias mayor plano vs imputado ('.count($diffsPlano).' cuentas):');
            $this->table(
                ['Cuenta', 'Plano D', 'Plano H', 'Imput. D', 'Imput. H', 'Δ D', 'Δ H', 'Líneas'],
                array_map(fn ($f) => [
                    ($f['cuenta_codigo'] ?? '').' '.mb_substr((string) ($f['cuenta_nombre'] ?? ''), 0, 18),
                    $this->fmt($f['plano_debe'] ?? 0),
                    $this->fmt($f['plano_haber'] ?? 0),
                    $this->fmt($f['imputado_debe'] ?? 0),
                    $this->fmt($f['imputado_haber'] ?? 0),
                    $this->fmtDiff($f['diferencia_debe'] ?? 0),
                    $this->fmtDiff($f['diferencia_haber'] ?? 0),
                    (string) ($f['lineas_imputadas'] ?? 0),
                ], array_slice($diffsPlano, 0, $maxDetalle > 0 ? $maxDetalle : 20)),
            );
        } else {
            $this->info('Mayor plano: cuadra con imputación ERP.');
        }

        $erpVsAnita = $informe['erp_vs_anita'] ?? null;
        if ($erpVsAnita !== null) {
            $soloErp = $erpVsAnita['lineas']['solo_erp'] ?? [];
            $soloAnita = $erpVsAnita['lineas']['solo_anita'] ?? [];

            if ($soloErp !== [] && $maxDetalle > 0) {
                $this->warn('Líneas solo en ERP ('.count($soloErp).'):');
                $this->mostrarDiffLineas(array_slice($soloErp, 0, $maxDetalle));
            }

            if ($soloAnita !== [] && $maxDetalle > 0) {
                $this->warn('Líneas solo en Anita ('.count($soloAnita).'):');
                $this->mostrarDiffLineas(array_slice($soloAnita, 0, $maxDetalle));
            }

            $totCuenta = $erpVsAnita['totales_cuenta'] ?? [];
            if ($totCuenta !== [] && $maxDetalle > 0) {
                $this->warn('Totales por cuenta distintos ('.count($totCuenta).'):');
                $this->table(
                    ['Concepto', 'Cuenta', 'Debe ERP', 'Debe Anita', 'Δ Debe', 'Haber ERP', 'Haber Anita', 'Δ Haber'],
                    array_map(fn ($f) => [
                        ($f['concepto_id'] ?? '').' '.mb_substr((string) ($f['concepto_nombre'] ?? ''), 0, 12),
                        $f['cuenta_codigo'] ?? '',
                        $this->fmt($f['debe_erp'] ?? 0),
                        $this->fmt($f['debe_anita'] ?? 0),
                        $this->fmtDiff($f['diff_debe'] ?? 0),
                        $this->fmt($f['haber_erp'] ?? 0),
                        $this->fmt($f['haber_anita'] ?? 0),
                        $this->fmtDiff($f['diff_haber'] ?? 0),
                    ], array_slice($totCuenta, 0, $maxDetalle)),
                );
            }
        }

        $archivos = $ejecucion['archivos'] ?? null;
        if ($archivos !== null) {
            $this->newLine();
            $this->info('Archivos generados:');
            foreach ($archivos as $tipo => $ruta) {
                $this->line('  '.$tipo.': '.$ruta);
            }
        }

        if (! empty($resultado['errores_bridge'])) {
            $this->warn('Errores bridge Anita: '.count($resultado['errores_bridge']));
            foreach (array_slice($resultado['errores_bridge'], 0, 5) as $err) {
                $this->line('  - '.$err);
            }
        }

        Log::info('mayor_concepto.comparacion', [
            'empresa_id' => $empresaId,
            'mes' => $mes,
            'anio' => $anio,
            'resumen' => $resumen,
            'archivos' => $archivos,
        ]);

        return ! empty($resumen['requiere_alerta']) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function mostrarDiffLineas(array $filas): void
    {
        $this->table(
            ['Tipo', 'Concepto', 'Cuenta', 'Asiento', 'Fecha', 'D ERP', 'H ERP', 'D Anita', 'H Anita', 'Descripción'],
            array_map(fn ($f) => [
                $f['tipo'] ?? '',
                (string) ($f['concepto_id'] ?? ''),
                $f['cuenta_codigo'] ?? '',
                (string) ($f['nro_asiento'] ?? ''),
                $f['fecha_fmt'] ?? '',
                $this->fmt($f['debe_erp'] ?? 0),
                $this->fmt($f['haber_erp'] ?? 0),
                $this->fmt($f['debe_anita'] ?? 0),
                $this->fmt($f['haber_anita'] ?? 0),
                mb_substr((string) ($f['descripcion'] ?? ''), 0, 28),
            ], $filas),
        );
    }

    private function fmt(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }

    private function fmtDiff(mixed $valor): string
    {
        $n = (float) $valor;
        $s = number_format($n, 2, '.', '');

        return $n > 0 ? '+'.$s : $s;
    }

    private function fmtBool(mixed $valor): string
    {
        if ($valor === null) {
            return '—';
        }

        return $valor ? 'sí' : 'no';
    }
}
