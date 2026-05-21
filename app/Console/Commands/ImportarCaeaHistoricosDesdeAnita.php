<?php

namespace App\Console\Commands;

use App\Services\Arca\ArcaCaeaImportacionAnitaService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportarCaeaHistoricosDesdeAnita extends Command
{
    protected $signature = 'arca:importar-caea-anita
                            {--desde=20260101 : Fecha desde (YYYYMMDD), default enero 2026}
                            {--hasta= : Fecha hasta (YYYYMMDD), default hoy}
                            {--empresa_id= : Importar solo esta empresa (id en anitaERP)}
                            {--todas-empresas : Incluir empresas sin asignación en usuario_empresa}
                            {--force : Sobrescribir registros ya autorizados en arca_caea}
                            {--dry-run : Simular sin grabar en MySQL}';

    protected $description = 'Importación única/manual: CAEA históricos desde Anita → arca_caea (no usar en el flujo quincenal automático)';

    public function handle(ArcaCaeaImportacionAnitaService $importacion): int
    {
        $desde = (int) $this->option('desde');
        $hastaOpt = $this->option('hasta');
        $hasta = ($hastaOpt !== null && $hastaOpt !== '')
            ? (int) $hastaOpt
            : (int) now()->format('Ymd');

        $empresaId = $this->option('empresa_id');
        $empresaIdFiltro = ($empresaId !== null && $empresaId !== '') ? (int) $empresaId : null;
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $soloAsignadas = ! (bool) $this->option('todas-empresas');

        $this->info('Importación CAEA Anita → anitaERP (arca_caea)');
        $this->line('Desde: '.$this->formatearYmd($desde).' — Hasta: '.$this->formatearYmd($hasta));
        if ($dryRun) {
            $this->warn('Modo dry-run: no se persisten cambios.');
        }
        if ($force) {
            $this->warn('Modo force: se sobrescriben CAEA ya autorizados.');
        }

        try {
            $stats = $importacion->importarHistoricos(
                $desde,
                $hasta,
                $empresaIdFiltro,
                $dryRun,
                $force,
                $soloAsignadas,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Filas leídas en Anita', $stats['leidos']],
                ['Nuevos importados', $stats['importados']],
                ['Actualizados', $stats['actualizados']],
                ['Omitidos (ya existían)', $stats['omitidos']],
                ['Sin empresa en ERP', $stats['sin_empresa']],
                ['Errores de fila', $stats['errores']],
            ]
        );

        if ($stats['detalle_sin_empresa'] !== []) {
            $this->warn('CUITs en Anita sin empresa en anitaERP (muestra):');
            foreach ($stats['detalle_sin_empresa'] as $linea) {
                $this->line('  - '.$linea);
            }
        }

        if ($stats['detalle_errores'] !== []) {
            $this->error('Errores al procesar filas:');
            foreach ($stats['detalle_errores'] as $linea) {
                $this->line('  - '.$linea);
            }
        }

        if ($stats['leidos'] === 0) {
            $this->comment('No se obtuvieron filas. Verifique ANITA_IP, bridge HTTP y tabla caea en Informix.');
        }

        return ($stats['errores'] > 0) ? self::FAILURE : self::SUCCESS;
    }

    private function formatearYmd(int $ymd): string
    {
        try {
            return Carbon::createFromFormat('Ymd', (string) $ymd)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $ymd;
        }
    }
}
