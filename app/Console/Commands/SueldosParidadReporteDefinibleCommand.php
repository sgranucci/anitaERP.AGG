<?php

namespace App\Console\Commands;

use App\Models\Sueldos\ReporteSueldosDefinibleParidad;
use App\Repositories\Sueldos\ReporteSueldosDefinibleRepository;
use App\Services\Sueldos\ReporteSueldosDefinibleEjecucionService;
use App\Support\Sueldos\AnitaAuxLiquidacionSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleParidadAnitaSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleParidadPublicacionSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleProcesador;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Smoke / paridad liviana: ejecuta listados y opcionalmente compara totales de concepto vs Anita auxhist.
 */
class SueldosParidadReporteDefinibleCommand extends Command
{
    protected $signature = 'sueldos:paridad-reporte-definible
                            {--reporte= : ID ERP del listado}
                            {--liquidacion= : ID liquidacion_sueldos}
                            {--anita-liq= : Nro liquidación Anita (ej. 20260700)}
                            {--empresa=1 : Empresa Anita}
                            {--nomina=normal : normal|confidencial|ambos}
                            {--tolerancia=0.01 : Diferencia absoluta admitida por columna}
                            {--ejecutar : Persiste la corrida y su matriz de paridad; sin este flag solo simula}
                            {--certificar : Crea acta de certificación si --ejecutar y no hay fallas}';

    protected $description = 'Compara automáticamente cada columna definible contra Anita auxhist/auxconfh';

    public function handle(
        ReporteSueldosDefinibleRepository $repo,
        ReporteSueldosDefinibleProcesador $procesador,
        ReporteSueldosDefinibleEjecucionService $ejecuciones,
        ReporteSueldosDefinibleParidadAnitaSupport $paridadAnita
    ): int {
        $reporteId = (int) $this->option('reporte');
        $liqId = (int) $this->option('liquidacion');
        if ($reporteId <= 0 || $liqId <= 0) {
            $this->error('Requiere --reporte=ID y --liquidacion=ID');

            return self::FAILURE;
        }
        $reporte = $repo->findConEstructura($reporteId);
        if (! $reporte) {
            $this->error('Listado no encontrado');

            return self::FAILURE;
        }

        $filtros = [
            'liquidacion_id' => $liqId,
            'origen' => ReporteSueldosDefinibleSupport::ORIGEN_LIQUIDACION,
            'agrupacion' => ReporteSueldosDefinibleSupport::AGRUPACION_EMPLEADO,
            'resumido' => false,
        ];
        $ejecutar = (bool) $this->option('ejecutar');
        if ($ejecutar) {
            $corrida = $ejecuciones->ejecutar($reporte, $filtros, ['origen' => 'paridad']);
            $resultado = $corrida['resultado'];
            $ejecucion = $corrida['ejecucion'];
        } else {
            $resultado = $procesador->ejecutar($reporte, $filtros);
            $ejecucion = null;
        }

        $this->info(sprintf(
            'Listado %d «%s»: %d filas',
            $reporte->codigo,
            $reporte->titulo,
            $resultado['meta']['cantidad_filas'] ?? 0
        ));
        $anitaLiq = (int) $this->option('anita-liq');
        if ($anitaLiq <= 0) {
            $this->error('Requiere --anita-liq=NRO para la paridad automática.');

            return self::FAILURE;
        }
        $empresa = (int) $this->option('empresa');
        $tolerancia = abs((float) $this->option('tolerancia'));
        $nomina = strtolower((string) $this->option('nomina'));
        if (! in_array($nomina, [
            AnitaAuxLiquidacionSupport::NOMINA_NORMAL,
            AnitaAuxLiquidacionSupport::NOMINA_CONFIDENCIAL,
            AnitaAuxLiquidacionSupport::NOMINA_AMBOS,
        ], true)) {
            $this->error('--nomina debe ser normal|confidencial|ambos');

            return self::FAILURE;
        }
        $this->info('Nómina Anita: '.$nomina);
        $anitaPorLegajo = $paridadAnita->valoresPorLegajo($reporte, $empresa, $anitaLiq, $nomina);
        $erpPorLegajo = [];
        foreach ((array) ($resultado['filas'] ?? []) as $fila) {
            $legajo = (int) ($fila['legajo'] ?? 0);
            if ($legajo <= 0) {
                continue;
            }
            foreach ((array) ($resultado['columnas'] ?? []) as $columna) {
                if (! empty($columna['numerica'])) {
                    $nro = (int) $columna['nro'];
                    $erpPorLegajo[$legajo][$nro] = (float) ($fila['c'.$nro] ?? 0);
                }
            }
        }
        $fallas = 0;

        foreach ($resultado['columnas'] as $col) {
            if (! $col['numerica']) {
                continue;
            }
            if ($col['contenido'] === ReporteSueldosDefinibleSupport::CONTENIDO_FORMULA) {
                $this->line(sprintf('  SKIP C%d %s — fórmula ERP', $col['nro'], $col['descripcion']));
                continue;
            }
            $nro = (int) $col['nro'];
            $legajosAnita = array_keys($anitaPorLegajo);
            $legajosErp = array_keys($erpPorLegajo);
            $comunes = array_values(array_intersect($legajosAnita, $legajosErp));
            $soloAnita = array_values(array_diff($legajosAnita, $legajosErp));
            $soloErp = array_values(array_diff($legajosErp, $legajosAnita));

            $totalErp = round(array_sum(array_map(
                fn (array $valores) => (float) ($valores[$nro] ?? 0),
                $erpPorLegajo
            )), 4);
            $totalAnita = round(array_sum(array_map(
                fn (array $valores) => (float) ($valores[$nro] ?? 0),
                $anitaPorLegajo
            )), 4);
            $totalErpComun = 0.0;
            $totalAnitaComun = 0.0;
            $celdasFuera = [];
            foreach ($comunes as $legajo) {
                $valorErp = (float) ($erpPorLegajo[$legajo][$nro] ?? 0);
                $valorAnita = (float) ($anitaPorLegajo[$legajo][$nro] ?? 0);
                $delta = round($valorErp - $valorAnita, 4);
                $totalErpComun += $valorErp;
                $totalAnitaComun += $valorAnita;
                if (abs($delta) > $tolerancia) {
                    $celdasFuera[] = ['legajo' => $legajo, 'erp' => $valorErp, 'anita' => $valorAnita, 'delta' => $delta];
                }
            }
            usort($celdasFuera, fn (array $a, array $b) => abs($b['delta']) <=> abs($a['delta']));

            $diferencia = round($totalErp - $totalAnita, 4);
            $diferenciaComun = round($totalErpComun - $totalAnitaComun, 4);
            $coberturaOk = $soloAnita === [] && $soloErp === [];
            $valoresOk = $celdasFuera === [];
            $coincide = $coberturaOk && $valoresOk;
            $fallas += $coincide ? 0 : 1;
            $this->line(sprintf(
                '  %s C%d %s | comunes %d | Δ común %s | fuera tolerancia %d',
                $valoresOk ? 'VAL OK' : 'VAL FAIL',
                $nro,
                $col['descripcion'],
                count($comunes),
                number_format($diferenciaComun, 2, ',', '.'),
                count($celdasFuera)
            ));
            $this->line(sprintf(
                '       Cobertura: ERP %d | Anita %d | solo ERP %d | solo Anita %d | Δ total %s',
                count($legajosErp),
                count($legajosAnita),
                count($soloErp),
                count($soloAnita),
                number_format($diferencia, 2, ',', '.')
            ));
            if ($soloErp !== []) {
                $this->warn('       Solo ERP: '.implode(', ', array_slice($soloErp, 0, 20)));
            }
            if ($soloAnita !== []) {
                $this->warn('       Solo Anita: '.implode(', ', array_slice($soloAnita, 0, 20)));
            }
            foreach (array_slice($celdasFuera, 0, 10) as $celda) {
                $this->warn(sprintf(
                    '       Legajo %d: ERP %s | Anita %s | Δ %s',
                    $celda['legajo'],
                    number_format($celda['erp'], 2, ',', '.'),
                    number_format($celda['anita'], 2, ',', '.'),
                    number_format($celda['delta'], 2, ',', '.')
                ));
            }

            if ($ejecucion !== null) {
                ReporteSueldosDefinibleParidad::query()->create([
                    'ejecucion_id' => (int) $ejecucion->id,
                    'liquidacion_anita' => $anitaLiq,
                    'empresa_anita' => $empresa,
                    'columna_nro' => (int) $col['nro'],
                    'columna_descripcion' => (string) $col['descripcion'],
                    'total_erp' => $totalErp,
                    'total_anita' => $totalAnita,
                    'diferencia' => $diferencia,
                    'tolerancia' => $tolerancia,
                    'coincide' => $coincide,
                    'detalle' => [
                        'total_erp_comun' => round($totalErpComun, 4),
                        'total_anita_comun' => round($totalAnitaComun, 4),
                        'diferencia_comun' => $diferenciaComun,
                        'legajos_comunes' => count($comunes),
                        'solo_erp' => $soloErp,
                        'solo_anita' => $soloAnita,
                        'celdas_fuera_tolerancia' => array_slice($celdasFuera, 0, 100),
                    ],
                ]);
            }
        }

        $this->line($ejecutar
            ? 'Resultado persistido en ejecución #'.$ejecucion->id.'.'
            : 'DRY-RUN: no se persistió ningún dato.');

        if ($this->option('certificar')) {
            if (! $ejecutar || $ejecucion === null) {
                $this->error('--certificar requiere --ejecutar y una ejecución persistida.');

                return self::FAILURE;
            }
            if ($fallas > 0) {
                $this->error('--certificar omitido: hay columnas fuera de tolerancia.');

                return self::FAILURE;
            }
            $usuarioId = (int) (Auth::id() ?: 1);
            $nomina = strtolower((string) $this->option('nomina'));
            $cert = ReporteSueldosDefinibleParidadPublicacionSupport::certificar(
                $reporte,
                $ejecucion,
                $liqId,
                $nomina,
                $usuarioId,
                'Certificación automática por comando sueldos:paridad-reporte-definible'
            );
            $this->info('Certificación #'.$cert->id.' creada (nómina '.$cert->nomina.').');
        }

        return $fallas > 0 ? self::FAILURE : self::SUCCESS;
    }
}
