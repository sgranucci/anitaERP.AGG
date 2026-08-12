<?php

namespace App\Console\Commands\Contable;

use App\Models\Contable\ReporteContable;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleProcesador;
use App\Support\Contable\SumasSaldos\SumasSaldosRuntimeSupport;
use Illuminate\Console\Command;

/**
 * Smoke read-only: ejecuta un informe definible un mes y resume filas/avisos.
 */
class SmokeReporteDefinibleCommand extends Command
{
    protected $signature = 'contable:smoke-reporte-definible {id : ID de reporte_contable} {--periodo= : AAAAMM (default mes actual)}';

    protected $description = 'Smoke de ReporteDefinibleProcesador: cuenta filas y muestra primeras advertencias';

    public function handle(ReporteDefinibleProcesador $procesador): int
    {
        SumasSaldosRuntimeSupport::elevarLimites();

        $id = (int) $this->argument('id');
        $reporte = ReporteContable::query()
            ->with([
                'rubros' => fn ($q) => $q->orderBy('orden')->orderBy('id'),
                'rubros.cuentas' => fn ($q) => $q->orderBy('orden')->orderBy('id'),
                'rubros.cuentas.ccostos',
            ])
            ->find($id);
        if (! $reporte) {
            $this->error("Informe {$id} no encontrado.");

            return self::FAILURE;
        }

        $periodoOpt = trim((string) $this->option('periodo'));
        if ($periodoOpt !== '' && preg_match('/^\d{6}$/', $periodoOpt)) {
            $anio = (int) substr($periodoOpt, 0, 4);
            $mes = (int) substr($periodoOpt, 4, 2);
        } else {
            $anio = (int) date('Y');
            $mes = (int) date('n');
        }
        if ($mes < 1 || $mes > 12) {
            $this->error('Período inválido (use AAAAMM).');

            return self::FAILURE;
        }

        $empresaIds = \App\Models\Configuracion\Empresa::query()
            ->orderBy('id')
            ->limit(2)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $periodo = $anio * 100 + $mes;
        $filtros = [
            'modo_periodo' => 'periodos',
            'mes_desde' => $mes,
            'anio_desde' => $anio,
            'mes_hasta' => $mes,
            'anio_hasta' => $anio,
            'periodo_desde' => $periodo,
            'periodo_hasta' => $periodo,
            'empresa_ids' => $empresaIds,
            'consolidar_empresas' => true,
            'ocultar_ceros' => true,
            'moneda_id' => 1,
        ];
        if ($reporte->layout_default_id) {
            $filtros['layout_id'] = (int) $reporte->layout_default_id;
        }

        $this->info(sprintf(
            'Smoke informe #%d «%s» período %02d/%04d…',
            $reporte->id,
            $reporte->nombre,
            $mes,
            $anio
        ));

        $t0 = microtime(true);
        $resultado = $procesador->ejecutar($reporte, $filtros);
        $secs = round(microtime(true) - $t0, 2);

        $filas = $resultado['filas'] ?? [];
        $cols = $resultado['columnas'] ?? [];
        $adv = $resultado['advertencias'] ?? [];
        $nRubro = count(array_filter($filas, fn ($f) => ($f['kind'] ?? '') === 'rubro'));
        $nCuenta = count(array_filter($filas, fn ($f) => ($f['kind'] ?? '') === 'cuenta'));

        $this->line("Columnas: ".count($cols));
        $this->line("Filas total: ".count($filas)." (rubros={$nRubro}, cuentas={$nCuenta})");
        $this->line("Fuente: ".($resultado['fuente'] ?? 'n/d')." — {$secs}s");

        if ($adv === []) {
            $this->info('Sin advertencias.');
        } else {
            $this->warn('Advertencias (máx. 10):');
            foreach (array_slice($adv, 0, 10) as $i => $msg) {
                $this->line('  '.($i + 1).'. '.$msg);
            }
            if (count($adv) > 10) {
                $this->line('  … +'.(count($adv) - 10).' más');
            }
        }

        return self::SUCCESS;
    }
}
