<?php

namespace App\Console\Commands;

use App\Services\Ventas\Gastronomia\GastronomiaBackfillClienteInternoFidelidadPlatinoService;
use Illuminate\Console\Command;

class GastronomiaBackfillClienteInternoFidelidadPlatino extends Command
{
    protected $signature = 'gastronomia:backfill-cliente-interno-fidelidad-platino
                            {--fecha-desde=2026-05-20 : Fecha jornada desde (Y-m-d)}
                            {--fecha-hasta= : Fecha jornada hasta (Y-m-d); default hoy}
                            {--empresas=1,2,3 : empresa_id separados por coma}
                            {--dry-run : Simular sin grabar}';

    protected $description = 'Corrige cliente_interno 500→1500 en canjes fidelidad Platino (solo ERP, sin Anita)';

    public function handle(GastronomiaBackfillClienteInternoFidelidadPlatinoService $service): int
    {
        $fechaDesde = trim((string) $this->option('fecha-desde'));
        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?: date('Y-m-d')));
        if ($fechaDesde === '' || $fechaHasta === '') {
            $this->error('Indique --fecha-desde y --fecha-hasta.');

            return self::FAILURE;
        }

        $empresaIds = $this->parseEmpresas((string) $this->option('empresas'));
        $dryRun = (bool) $this->option('dry-run');

        $this->line("Fidelidad Platino ERP {$fechaDesde} — {$fechaHasta}, empresas: ".implode(',', $empresaIds)
            .($dryRun ? ' [dry-run]' : ' [GRABAR]'));

        try {
            $resultado = $service->ejecutar($fechaDesde, $fechaHasta, $empresaIds, $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Concepto', 'Valor'], [
            ['Fidelidad Platino: 500 → 1500', (string) ($resultado['fidelidad_platino_corregidas'] ?? 0)],
            ['Omitidas (sin cambio)', (string) ($resultado['omitidas'] ?? 0)],
            ['Errores', (string) count($resultado['errores'] ?? [])],
        ]);

        foreach ($resultado['por_empresa'] ?? [] as $empresaId => $stats) {
            $this->line("Empresa {$empresaId}: corregidas ".($stats['fidelidad_platino_corregidas'] ?? 0)
                .', omitidas '.($stats['omitidas'] ?? 0));
        }

        foreach ($resultado['errores'] ?? [] as $err) {
            $this->warn($err);
        }

        if ($dryRun && ($resultado['fidelidad_platino_corregidas'] ?? 0) > 0) {
            $this->newLine();
            $this->info('Dry-run: ningún registro modificado. Ejecute sin --dry-run para aplicar.');
        }

        return ($resultado['errores'] ?? []) === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<int>
     */
    private function parseEmpresas(string $raw): array
    {
        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $id = (int) trim($part);
            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids !== [] ? $ids : [1, 2, 3];
    }
}
