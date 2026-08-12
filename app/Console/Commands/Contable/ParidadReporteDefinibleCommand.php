<?php

namespace App\Console\Commands\Contable;

use App\Models\Configuracion\Empresa;
use App\Services\Contable\ReporteDefinibleParidadService;
use Illuminate\Console\Command;

/**
 * Paridad read-only del informe definible: anitaERP (asientos) vs Anita (ctamov + subdiario).
 */
class ParidadReporteDefinibleCommand extends Command
{
    protected $signature = 'contable:paridad-reporte-definible
        {id : ID de reporte_contable}
        {--periodo= : AAAAMM (default mes anterior)}
        {--periodo-hasta= : AAAAMM final (default = --periodo)}
        {--empresa=* : IDs de empresa ERP (default primera empresa)}
        {--base=periodo : periodo|ejercicio}
        {--tolerancia=0.05 : diferencia admitida por rubro}
        {--solo-diferencias : mostrar solo rubros que no cuadran}
        {--limite=40 : máximo de filas a listar}';

    protected $description = 'Compara un informe definible calculado sobre asientos ERP contra ctamov + subdiario de Anita';

    public function handle(ReporteDefinibleParidadService $service): int
    {
        $periodo = $this->periodoOpcion('periodo') ?: (int) date('Ym', strtotime('first day of last month'));
        $periodoHasta = $this->periodoOpcion('periodo-hasta') ?: $periodo;

        $empresaIds = array_values(array_filter(array_map('intval', (array) $this->option('empresa')), fn (int $i) => $i > 0));
        if ($empresaIds === []) {
            $empresaIds = Empresa::query()->orderBy('id')->limit(1)->pluck('id')->map(fn ($v) => (int) $v)->all();
        }

        $base = $this->option('base') === 'ejercicio' ? 'ejercicio' : 'periodo';
        $tolerancia = (float) $this->option('tolerancia');

        $resultado = $service->comparar((int) $this->argument('id'), [
            'empresa_ids' => $empresaIds,
            'modo_periodo' => 'periodos',
            'periodo_desde' => $periodo,
            'periodo_hasta' => $periodoHasta,
            'base_saldo' => $base,
            'modo_inclusion_asientos' => 'sin_cierre_ni_inflacion',
        ], $tolerancia);

        $reporte = $resultado['reporte'];
        if ($reporte === null) {
            foreach ($resultado['advertencias'] as $aviso) {
                $this->error($aviso);
            }

            return self::FAILURE;
        }

        $p = $resultado['parametros'];
        $this->info(sprintf(
            'Informe %s — %s | empresas %s | %s → %s | base %s | %d cuentas',
            (string) $reporte->codigo,
            (string) $reporte->nombre,
            implode(',', $p['empresa_ids']),
            $p['fecha_desde'],
            $p['fecha_hasta'],
            $p['base_saldo'],
            (int) $p['cuentas']
        ));

        $s = $resultado['stats'];
        $this->line(sprintf(
            'Movimientos: ERP %d | Anita %d (ctamov %d, subdiario %d)',
            $s['movimientos_erp'],
            $s['movimientos_anita'],
            $s['ctamov_filas'],
            $s['subdiario_filas']
        ));

        foreach ($resultado['advertencias'] as $aviso) {
            $this->warn('· '.$aviso);
        }

        $filas = $resultado['filas'];
        if ($this->option('solo-diferencias')) {
            $filas = array_values(array_filter($filas, fn ($f) => ! $f['cuadra'] || ! $f['cuadra_motor']));
        }
        $limite = max(1, (int) $this->option('limite'));

        $this->table(
            ['Línea', 'Rubro', 'Informe', 'Asientos ERP', 'Anita', 'Dif. motor', 'Dif. Anita'],
            array_map(fn ($f) => [
                $f['codigo'],
                str_repeat('  ', max(0, (int) $f['nivel'] - 1)).mb_substr($f['nombre'], 0, 38),
                $f['impreso'] !== null ? number_format($f['impreso'], 2, ',', '.') : '',
                number_format($f['erp'], 2, ',', '.'),
                number_format($f['anita'], 2, ',', '.'),
                $f['cuadra_motor'] ? '' : number_format((float) $f['diferencia_motor'], 2, ',', '.'),
                $f['cuadra'] ? '' : number_format($f['diferencia'], 2, ',', '.'),
            ], array_slice($filas, 0, $limite))
        );

        if (($resultado['cuentas_fuera_plan'] ?? []) !== []) {
            $this->warn('Cuentas movidas en Anita que no existen en el plan ERP:');
            foreach (array_slice($resultado['cuentas_fuera_plan'], 0, 10) as $cuenta) {
                $this->line(sprintf('    %s  Anita %s', $cuenta['codigo_fmt'], number_format($cuenta['anita'], 2, ',', '.')));
            }
        }

        $r = $resultado['resumen'];
        if (! $r['cuadra_motor']) {
            $this->error(sprintf(
                'MOTOR: el informe impreso (fuente %s) difiere del recálculo por asientos en %d rubro(s) | peor %s %s (%s)',
                (string) ($r['fuente_impreso'] ?? ''),
                (int) $r['con_diferencia_motor'],
                (string) ($r['peor_motor']['codigo'] ?? ''),
                (string) ($r['peor_motor']['nombre'] ?? ''),
                number_format((float) ($r['peor_motor']['diferencia'] ?? 0), 2, ',', '.')
            ));
        }

        if ($r['cuadra'] && $r['cuadra_motor']) {
            $this->info(sprintf('PARIDAD OK — %d rubros sin diferencias (tolerancia %.2f).', $r['rubros'], $p['tolerancia']));

            return self::SUCCESS;
        }

        if ($r['cuadra']) {
            return self::FAILURE;
        }

        $this->error(sprintf(
            'DIFERENCIAS: %d de %d rubros | suma |dif| %s | peor %s %s (%s)',
            $r['con_diferencia'],
            $r['rubros'],
            number_format($r['suma_abs_diferencia'], 2, ',', '.'),
            (string) ($r['peor']['codigo'] ?? ''),
            (string) ($r['peor']['nombre'] ?? ''),
            number_format((float) ($r['peor']['diferencia'] ?? 0), 2, ',', '.')
        ));

        foreach (array_slice(array_values(array_filter($resultado['filas'], fn ($f) => ! $f['cuadra'])), 0, 5) as $fila) {
            if ($fila['cuentas'] === []) {
                continue;
            }
            $this->line(sprintf('  %s %s:', $fila['codigo'], $fila['nombre']));
            foreach (array_slice($fila['cuentas'], 0, 5) as $cuenta) {
                $this->line(sprintf(
                    '    %s  ERP %s | Anita %s | dif %s',
                    $cuenta['codigo_fmt'],
                    number_format($cuenta['erp'], 2, ',', '.'),
                    number_format($cuenta['anita'], 2, ',', '.'),
                    number_format($cuenta['diferencia'], 2, ',', '.')
                ));
            }
        }

        return self::FAILURE;
    }

    private function periodoOpcion(string $nombre): int
    {
        $valor = trim((string) $this->option($nombre));

        return preg_match('/^\d{6}$/', $valor) ? (int) $valor : 0;
    }
}
