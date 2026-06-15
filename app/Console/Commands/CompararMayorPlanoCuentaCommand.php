<?php

namespace App\Console\Commands;

use App\Services\Contable\MayorPlanoCuentaReporteService;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaComparacionSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CompararMayorPlanoCuentaCommand extends Command
{
    protected $signature = 'contable:comparar-mayor-plano-cuenta
                            {--csv= : Ruta al export CSV Anita l-mayor}
                            {--empresa=1 : ID empresa AnitaERP}
                            {--mes=4 : Mes 1-12}
                            {--anio=2026 : Año}
                            {--moneda=1 : Moneda de reporte}
                            {--tolerancia=0.05 : Tolerancia en pesos}
                            {--salida= : Directorio salida JSON}
                            {--detalle=25 : Máx. filas diff en consola}
                            {--sin-subdiario : No incluir subdiario (solo ctamov)}';

    protected $description = 'Compara mayor analítico por cuenta AnitaERP vs export CSV Anita l-mayor';

    public function handle(
        MayorPlanoCuentaReporteService $reporteService,
        MayorPlanoCuentaComparacionSupport $comparacion,
    ): int {
        $rutaCsv = trim((string) $this->option('csv'));
        if ($rutaCsv === '') {
            $this->error('Indique --csv=/ruta/al/l_mayor.csv');

            return self::FAILURE;
        }

        if (! is_readable($rutaCsv)) {
            $this->error('CSV no legible: '.$rutaCsv);

            return self::FAILURE;
        }

        $empresaId = (int) $this->option('empresa');
        $mes = (int) $this->option('mes');
        $anio = (int) $this->option('anio');
        $monedaId = max(1, (int) $this->option('moneda'));
        $tolerancia = (float) $this->option('tolerancia');
        $maxDetalle = max(0, (int) $this->option('detalle'));

        $filtros = [
            'empresa_ids' => [$empresaId],
            'moneda_id' => $monedaId,
            'modo_periodo' => 'mes',
            'mes' => $mes,
            'anio' => $anio,
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'solo_moneda_origen' => false,
            'incluye_subdiario' => ! $this->option('sin-subdiario'),
            'modo_inclusion_asientos' => 'sin_cierre_ni_inflacion',
            'cuenta_desde' => 0,
            'cuenta_hasta' => 0,
            'filtro_texto' => '',
        ];

        $this->info(sprintf(
            'Comparación mayor plano — empresa %d, %02d/%d, moneda %d, subdiario %s',
            $empresaId,
            $mes,
            $anio,
            $monedaId,
            $filtros['incluye_subdiario'] ? 'sí' : 'no',
        ));
        $this->line('CSV Anita: '.$rutaCsv);

        try {
            $this->comment('Generando mayor AnitaERP vía bridge…');
            $resultado = $reporteService->generarDesdeFiltros($filtros);
            $filas = $reporteService->aplanarFilas($resultado, [], false);

            $this->comment('Leyendo CSV Anita…');
            $csvAnita = $comparacion->leerCsvAnita($rutaCsv);

            $informe = $comparacion->comparar($filas, $resultado, $csvAnita, $tolerancia);

            $directorio = is_string($this->option('salida')) && trim((string) $this->option('salida')) !== ''
                ? trim((string) $this->option('salida'))
                : Storage::path('reportes');

            $jsonPath = $comparacion->guardarInforme($informe, $directorio);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            Log::error('mayor_plano.comparacion.fallo', ['exception' => $e]);

            return self::FAILURE;
        }

        $resumen = $informe['resumen'] ?? [];
        $stats = $resultado['stats'] ?? [];

        $this->newLine();
        $this->table(['Métrica', 'Valor'], [
            ['Ctamov bridge', (string) ($stats['ctamov_filas'] ?? 0)],
            ['Subdiario bridge', (string) ($stats['subdiario_filas'] ?? 0)],
            ['Cuentas ERP', (string) ($resumen['cuentas_erp'] ?? 0)],
            ['Líneas ERP', (string) ($resumen['lineas_erp'] ?? 0)],
            ['Líneas Anita CSV', (string) ($resumen['lineas_anita'] ?? 0)],
            ['Coincidencias', (string) ($resumen['coincidencias_lineas'] ?? 0)],
            ['Solo ERP', (string) ($resumen['solo_erp'] ?? 0)],
            ['Solo Anita', (string) ($resumen['solo_anita'] ?? 0)],
            ['Debe ERP / Anita', ($resumen['total_debe_erp'] ?? 0).' / '.($resumen['total_debe_anita'] ?? 0)],
            ['Haber ERP / Anita', ($resumen['total_haber_erp'] ?? 0).' / '.($resumen['total_haber_anita'] ?? 0)],
            ['Δ Debe', (string) ($resumen['delta_debe'] ?? 0)],
            ['Δ Haber', (string) ($resumen['delta_haber'] ?? 0)],
            ['Cuadra totales', $this->fmtBool($resumen['cuadra_totales'] ?? null)],
            ['Cuadra líneas', $this->fmtBool($resumen['cuadra_lineas'] ?? null)],
        ]);

        $this->line('Informe JSON: '.$jsonPath);

        $this->mostrarDiffs('Solo en AnitaERP', $informe['lineas']['solo_erp'] ?? [], $maxDetalle);
        $this->mostrarDiffs('Solo en Anita CSV', $informe['lineas']['solo_anita'] ?? [], $maxDetalle);

        $diffsCuenta = $informe['totales_cuenta'] ?? [];
        if ($diffsCuenta !== []) {
            $this->warn('Cuentas con Δ totales ('.count($diffsCuenta).'):');
            $this->table(
                ['Cuenta', 'Debe ERP', 'Debe Anita', 'Δ D', 'Haber ERP', 'Haber Anita', 'Δ H'],
                array_slice(array_map(fn ($f) => [
                    $f['cuenta_codigo'] ?? $f['cuenta'],
                    $f['debe_erp'], $f['debe_anita'], $f['delta_debe'],
                    $f['haber_erp'], $f['haber_anita'], $f['delta_haber'],
                ], $diffsCuenta), 0, $maxDetalle),
            );
        }

        $diffsSaldo = $informe['saldos_iniciales'] ?? [];
        if ($diffsSaldo !== []) {
            $this->warn('Saldos iniciales distintos ('.count($diffsSaldo).'):');
            $this->table(
                ['Cuenta', 'ERP', 'Anita', 'Δ'],
                array_slice(array_map(fn ($f) => [
                    $f['cuenta'], $f['saldo_erp'], $f['saldo_anita'], $f['delta'],
                ], $diffsSaldo), 0, $maxDetalle),
            );
        }

        $cuadra = ($resumen['cuadra_totales'] ?? false) && ($resumen['cuadra_lineas'] ?? false);

        return $cuadra ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function mostrarDiffs(string $titulo, array $filas, int $max): void
    {
        if ($filas === []) {
            return;
        }

        $this->warn($titulo.' ('.count($filas).'):');
        $this->table(
            ['Cuenta', 'Fecha', 'N.Asi.', 'Descripción', 'Debe', 'Haber'],
            array_slice(array_map(fn ($f) => [
                $f['cuenta_codigo'] ?? '',
                $f['fecha_fmt'] ?? '',
                $f['nro_asiento'] ?? '',
                $f['descripcion'] ?? '',
                $f['debe'] ?? '',
                $f['haber'] ?? '',
            ], $filas), 0, $max),
        );
    }

    private function fmtBool(?bool $v): string
    {
        if ($v === null) {
            return '—';
        }

        return $v ? '✓ Sí' : '✗ No';
    }
}
