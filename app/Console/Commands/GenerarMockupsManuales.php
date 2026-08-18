<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Manuales\ManualMockupCatalogo;
use App\Support\Manuales\ManualMockupGeneradorService;
use Illuminate\Console\Command;

class GenerarMockupsManuales extends Command
{
    protected $signature = 'manual:generar-mockups
                            {manual=all : Slug del manual o all}
                            {--qa : Solo auditar capturas existentes}
                            {--check-escenas : Verificar cobertura de claves de config (sin generar)}';

    protected $description = 'Genera mockups PNG uniformes (1280×760) para los manuales activos';

    public function handle(ManualMockupGeneradorService $service): int
    {
        $manual = (string) $this->argument('manual');
        $slugs = $manual === 'all'
            ? array_keys(ManualMockupCatalogo::manuales())
            : [$manual];

        if (! isset(ManualMockupCatalogo::manuales()[$manual]) && $manual !== 'all') {
            $this->error('Manual desconocido. Válidos: '.implode(', ', array_keys(ManualMockupCatalogo::manuales())));

            return self::FAILURE;
        }

        $coberturaOk = true;
        foreach ($slugs as $slug) {
            try {
                $service->asegurarEscenasCubrenConfig($slug);
                $this->info("Cobertura OK: {$slug}");
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                $coberturaOk = false;
            }
        }

        if ($this->option('check-escenas')) {
            return $coberturaOk ? self::SUCCESS : self::FAILURE;
        }

        if (! $coberturaOk) {
            return self::FAILURE;
        }

        if ($this->option('qa')) {
            return $this->mostrarAuditoria($service, $manual);
        }

        $this->info('Generando mockups…');
        $result = $service->generar($manual);
        $this->info("Generadas: {$result['generadas']} | Omitidas (diagramas en catálogo): {$result['omitidas']}");
        foreach ($result['errores'] as $err) {
            $this->error($err);
        }

        $qaExit = $this->mostrarAuditoria($service, $manual);

        return ($result['errores'] === [] && $qaExit === self::SUCCESS)
            ? self::SUCCESS
            : self::FAILURE;
    }

    private function mostrarAuditoria(ManualMockupGeneradorService $service, string $manual): int
    {
        $audit = $service->auditar($manual);
        $this->line('QA capturas:');
        $this->table(array_keys($audit['resumen']), [array_values($audit['resumen'])]);
        foreach ($audit['problemas'] as $p) {
            $this->warn($p);
        }

        return $audit['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
