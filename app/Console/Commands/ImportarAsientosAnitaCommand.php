<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Contable\AnitaAsientoImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportarAsientosAnitaCommand extends Command
{
    protected $signature = 'contable:importar-asientos-anita
                            {--desde=2025-01-01 : Fecha inicial Y-m-d}
                            {--hasta=2025-01-31 : Fecha final Y-m-d}
                            {--empresas=1,2,3 : Códigos Anita de empresa}
                            {--meses-bloque=1 : Meses por lectura bridge}
                            {--usuario-id=1 : usuario_id de los asientos importados}
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Persiste en ERP (no escribe Anita)}
                            {--reemplazar-diferentes : En --ejecutar, reemplaza duplicados con diferencias}';

    protected $description = 'Importa asientos Anita (ctamov + subdiario/subhist) a ERP. Excluye ctamov resumen V/C/T (no P/PER).';

    public function handle(AnitaAsientoImportService $service): int
    {
        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        $mesesBloque = max(1, (int) $this->option('meses-bloque'));
        $usuarioId = max(1, (int) $this->option('usuario-id'));
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');
        $reemplazar = (bool) $this->option('reemplazar-diferentes');

        if ($ejecutar && (bool) $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        $empresas = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $this->option('empresas')),
        )));

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Rango %s → %s | empresas %s | bloque %d mes(es) | %s%s',
            $desde,
            $hasta,
            implode(',', $empresas),
            $mesesBloque,
            $dryRun ? 'DRY-RUN' : 'EJECUTAR',
            $reemplazar ? ' +reemplazar-diferentes' : '',
        ));
        $this->line('Exclusión ctamov: sistemas '.implode(',', AnitaAsientoImportService::SISTEMAS_CIERRE_SUBDIARIO)
            .' con asi_mon_ref≠'.AnitaAsientoImportService::ASI_MON_REF_ORIGEN_ERP
            .' (resumen subdiario; asi_mon_ref=-1 = origen ERP, se importa)');
        $this->line('Detalle: subdiario (abierto) + subhist (cerrado) → numeroasiento = nro_operacion');
        $this->line('Anita fuente de verdad hasta '.AnitaAsientoImportService::ANITA_FUENTE_VERDAD_HASTA
            .' (reemplaza diferencias en ERP; conserva FKs de proceso)');

        try {
            $r = $service->importarRango(
                $desde,
                $hasta,
                $empresas,
                $mesesBloque,
                $dryRun,
                $reemplazar,
                $usuarioId,
                fn (string $m) => $this->line($m),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry-run finalizado' : 'Importación finalizada');
        $this->table(
            ['Concepto', 'Valor'],
            [
                ['ctamov filas', (string) $r['ctamov_filas_leidas']],
                ['subdiario filas', (string) $r['subdiario_filas_leidas']],
                ['subhist filas', (string) $r['subhist_filas_leidas']],
                ['ctamov excluidos (V/C/T) asientos', (string) $r['ctamov_excluidos_cierre']],
                ['ctamov excluidos líneas', (string) $r['ctamov_excluidos_lineas']],
                ['A crear (asientos)', (string) $r['a_crear']],
                ['A crear (líneas mov.)', (string) $r['lineas_a_crear']],
                ['Creados', (string) $r['creados']],
                ['Duplicados total', (string) $r['duplicados']],
                ['Duplicados → dejar', (string) $r['duplicados_dejar']],
                ['Duplicados → reemplazar', (string) $r['duplicados_reemplazar']],
                ['Reemplazados', (string) $r['reemplazados']],
                ['Omitidos sin movimientos', (string) $r['omitidos_sin_movimientos']],
                ['Cuentas faltantes (distintas)', (string) count($r['cuentas_faltantes'] ?? [])],
                ['Errores bridge', (string) count($r['errores'] ?? [])],
            ],
        );

        if (($r['a_crear_por_origen'] ?? []) !== []) {
            $filas = [];
            foreach ($r['a_crear_por_origen'] as $origen => $cant) {
                $filas[] = [$origen, (string) $cant];
            }
            $this->table(['Origen a crear', 'Asientos'], $filas);
        }

        if (($r['cuentas_faltantes'] ?? []) !== []) {
            arsort($r['cuentas_faltantes']);
            $filas = [];
            foreach (array_slice($r['cuentas_faltantes'], 0, 25, true) as $codigo => $cant) {
                $filas[] = [(string) $codigo, (string) $cant];
            }
            $this->warn('Top cuentas Anita sin match ERP (código → apariciones):');
            $this->table(['Cuenta Anita', 'Apariciones'], $filas);
        }

        if (($r['tipos_faltantes'] ?? []) !== []) {
            $this->warn('Tipos asiento faltantes: '.json_encode($r['tipos_faltantes']));
        }

        $dupsReemplazar = array_values(array_filter(
            $r['duplicados_detalle'] ?? [],
            static fn (array $d) => ($d['decision'] ?? '') === 'reemplazar',
        ));
        if ($dupsReemplazar !== []) {
            $this->warn('Duplicados candidatos a reemplazar (muestra):');
            $filas = [];
            foreach (array_slice($dupsReemplazar, 0, 30) as $d) {
                $filas[] = [
                    (string) ($d['numeroasiento'] ?? ''),
                    (string) ($d['empresa_id'] ?? ''),
                    (string) ($d['origen_anita'] ?? ''),
                    (string) ($d['motivo'] ?? ''),
                    (string) ($d['erp_lineas'] ?? '').'→'.(string) ($d['anita_lineas'] ?? ''),
                ];
            }
            $this->table(['Nro', 'Emp', 'Origen', 'Motivo', 'Líneas ERP→Anita'], $filas);
        }

        foreach (array_slice($r['errores'] ?? [], 0, 20) as $err) {
            $this->error((string) $err);
        }

        $reportePath = storage_path(sprintf(
            'logs/anita_asiento_import_%s_%s_%s.json',
            $dryRun ? 'dryrun' : 'exec',
            str_replace('-', '', $desde),
            str_replace('-', '', $hasta),
        ));
        File::put($reportePath, json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line('Reporte JSON: '.$reportePath);

        if ($dryRun) {
            $this->comment('Para persistir: agregar --ejecutar (y opcional --reemplazar-diferentes).');
        }

        return self::SUCCESS;
    }
}
