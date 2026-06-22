<?php

namespace App\Console\Commands;

use App\Services\Contable\EfeMensualReporteService;
use App\Support\Contable\Efe\EfeComparacionExcelSupport;
use App\Support\Contable\EfeMensualListadoFiltros;
use App\Support\Contable\MayorConcepto\MayorConceptoRuntimeSupport;
use Illuminate\Console\Command;

class CompararEfeExcelCommand extends Command
{
    protected $signature = 'efe:comparar-excel
                            {excel : Ruta al Excel de referencia}
                            {--empresa_id=1 : Empresa Anita}
                            {--mes=5 : Mes}
                            {--anio=2026 : Año}
                            {--moneda_id=1 : Moneda reporte}
                            {--top=15 : Cantidad de desvíos a listar}';

    protected $description = 'Compara Resumen de pagos (col B) y Sumarias (E68) del EFE ERP vs un Excel de referencia';

    public function handle(
        EfeMensualReporteService $reporteService,
        EfeComparacionExcelSupport $comparacion,
    ): int {
        MayorConceptoRuntimeSupport::elevarLimites();

        $rutaExcel = (string) $this->argument('excel');
        if (! is_file($rutaExcel)) {
            $this->error('No existe el archivo: '.$rutaExcel);

            return self::FAILURE;
        }

        $filtros = [
            'empresa_id' => (int) $this->option('empresa_id'),
            'mes' => (int) $this->option('mes'),
            'anio' => (int) $this->option('anio'),
            'moneda_id' => (int) $this->option('moneda_id'),
            'solo_moneda_origen' => false,
        ];

        $this->info('Generando EFE ERP…');
        ini_set('memory_limit', '-1');
        $resultado = $reporteService->generarDesdeFiltros($filtros);

        $cmp = $comparacion->compararResumenPagos($resultado['resumen_pagos'] ?? [], $rutaExcel);
        $sum = $comparacion->compararSumariasTotal($resultado['sumarias'] ?? [], $rutaExcel);
        $posfin = $comparacion->compararPosFinSaldoFinal(
            $resultado['totales']['posfin_saldo_final'] ?? null,
            $rutaExcel,
        );
        $c53 = $comparacion->resumenConcepto53($resultado['resumen_pagos'] ?? []);
        $c2 = $this->netoConcepto($resultado['resumen_pagos'] ?? [], 2);
        $posfinDet = $this->compararPosFinDetalle($resultado['posicion_financiera'] ?? [], $rutaExcel);

        $this->newLine();
        $this->line('Datos: '.count($resultado['filas_datos'] ?? []).' líneas');
        $this->line('Sumarias E68 ERP (miles): '.$sum['total_e68'].' · Excel: '.$sum['excel_e68'].' · Δ '.$sum['diff']);
        $this->line('Concepto 2 BIENES DE USO: ERP '.$c2['neto'].' · Excel ref '
            .number_format((float) ($comparacion->leerReferenciaResumenPagos($rutaExcel)[2] ?? 0), 2, '.', ','));
        $this->line('Concepto 53: '.$c53['lineas'].' líneas · neto '.$c53['neto']
            .' (Pagos '.$c53['pagos'].' · Cobros '.$c53['cobros'].')');
        if ($posfin['erp'] !== null || $posfin['excel'] !== null) {
            $this->line('Pos fin saldo final ERP: '.($posfin['erp'] ?? '—')
                .' · Excel: '.($posfin['excel'] ?? '—')
                .' · Δ '.($posfin['diff'] ?? '—'));
        }
        if ($posfinDet !== []) {
            $this->newLine();
            $this->info('Pos fin Biy — etiquetas clave');
            $this->table(
                ['Etiqueta', 'Excel', 'ERP', 'Δ'],
                $posfinDet,
            );
        }
        $this->newLine();

        $this->info('Resumen de pagos (col B) — coincidencias: '
            .$cmp['totales']['coincidencias'].' · desvíos: '.$cmp['totales']['desvios']);

        $top = max(1, (int) $this->option('top'));
        $filas = array_slice(array_filter($cmp['filas'], fn ($f) => ! $f['ok']), 0, $top);

        if ($filas === []) {
            $this->info('Sin desvíos por encima del umbral.');

            return self::SUCCESS;
        }

        $this->table(
            ['Concepto', 'Excel', 'ERP', 'Δ'],
            array_map(fn ($f) => [
                $f['concepto_id'],
                number_format($f['excel'], 2, '.', ','),
                number_format($f['erp'], 2, '.', ','),
                number_format($f['diff'], 2, '.', ','),
            ], $filas),
        );

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $resumenPagos
     * @return array{concepto_id: int, neto: float, lineas: int}
     */
    private function netoConcepto(array $resumenPagos, int $conceptoId): array
    {
        foreach ($resumenPagos as $fila) {
            if ((int) ($fila['concepto_id'] ?? 0) === $conceptoId) {
                return [
                    'concepto_id' => $conceptoId,
                    'neto' => (float) ($fila['neto'] ?? 0),
                    'lineas' => (int) ($fila['cantidad_lineas'] ?? 0),
                ];
            }
        }

        return ['concepto_id' => $conceptoId, 'neto' => 0.0, 'lineas' => 0];
    }

    /**
     * @param  array<string, mixed>  $posicionFinanciera
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function compararPosFinDetalle(array $posicionFinanciera, string $rutaReferencia): array
    {
        $erp = $posicionFinanciera['totales_por_etiqueta'] ?? [];
        $excel = [];

        if (str_ends_with(strtolower($rutaReferencia), '.json') && is_file($rutaReferencia)) {
            $payload = json_decode((string) file_get_contents($rutaReferencia), true);
            foreach ($payload['posfin_etiquetas'] ?? [] as $etiqueta => $valor) {
                $excel[(string) $etiqueta] = (float) $valor;
            }
            $mapaLegacy = [
                'VENTA BINGO' => 'posfin_venta_bingo',
                'SOBRANTES' => 'posfin_sobrantes',
                'VALES' => 'posfin_vales',
                'MAQUINAS VENTAS' => 'posfin_maquinas_ventas',
                'MAQUINAS CAJA' => 'posfin_maquinas_caja',
            ];
            foreach ($mapaLegacy as $etiqueta => $claveJson) {
                if (! isset($excel[$etiqueta]) && isset($payload[$claveJson])) {
                    $excel[$etiqueta] = (float) $payload[$claveJson];
                }
            }
        }

        $etiquetas = array_unique(array_merge(array_keys($excel), array_keys($erp)));
        sort($etiquetas);

        $filas = [];
        foreach ($etiquetas as $etiqueta) {
            if (stripos($etiqueta, 'Saldo') === 0) {
                continue;
            }
            $vExcel = (float) ($excel[$etiqueta] ?? 0);
            $vErp = (float) ($erp[$etiqueta] ?? 0);
            if (abs($vExcel) < 0.005 && abs($vErp) < 0.005) {
                continue;
            }
            $diff = round($vErp - $vExcel, 2);
            $filas[] = [
                $etiqueta,
                number_format($vExcel, 2, '.', ','),
                number_format($vErp, 2, '.', ','),
                number_format($diff, 2, '.', ','),
            ];
        }

        usort($filas, fn ($a, $b) => abs((float) str_replace(',', '', $b[3])) <=> abs((float) str_replace(',', '', $a[3])));

        return array_slice($filas, 0, 20);
    }
}
