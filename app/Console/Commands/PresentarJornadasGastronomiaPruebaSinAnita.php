<?php

namespace App\Console\Commands;

use App\Models\Configuracion\Empresa;
use App\Services\Caja\RendicionGastronomiaPresentacionPruebaService;
use Illuminate\Console\Command;

class PresentarJornadasGastronomiaPruebaSinAnita extends Command
{
    protected $signature = 'gastronomia:presentar-jornadas-prueba-sin-anita
                            {--rebisco-hasta=2026-06-09 : Fecha jornada inclusive Rebisco (Y-m-d)}
                            {--biyemas-hasta=2026-05-31 : Fecha jornada inclusive Biyemas (Y-m-d)}
                            {--caja=1 : caja_id para las rendiciones}
                            {--usuario=1 : creousuario_id}
                            {--dry-run : Simula sin grabar}';

    protected $description = 'Presenta jornadas cerradas de prueba en Caja (turnos + jornada) sin bridge Anita';

    public function handle(RendicionGastronomiaPresentacionPruebaService $service): int
    {
        $cajaId = (int) $this->option('caja');
        $usuarioId = (int) $this->option('usuario');
        $dryRun = (bool) $this->option('dry-run');

        $lotes = [
            ['empresa_id' => 3, 'nombre' => 'REBISCO', 'hasta' => (string) $this->option('rebisco-hasta')],
            ['empresa_id' => 1, 'nombre' => 'BIYEMAS', 'hasta' => (string) $this->option('biyemas-hasta')],
        ];

        if ($dryRun) {
            $this->warn('Modo simulación — no se grabará nada.');
        }

        $exitCode = self::SUCCESS;

        foreach ($lotes as $lote) {
            $empresa = Empresa::query()->find($lote['empresa_id']);
            $this->newLine();
            $this->info(sprintf(
                '%s (empresa #%d) — jornadas cerradas hasta %s',
                $empresa?->nombre ?? $lote['nombre'],
                $lote['empresa_id'],
                $lote['hasta'],
            ));

            try {
                $resultado = $service->presentarJornadasCerradasHasta(
                    $lote['empresa_id'],
                    $lote['hasta'],
                    $cajaId,
                    $usuarioId,
                    $dryRun,
                );
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                $exitCode = self::FAILURE;

                continue;
            }

            if ($resultado['jornadas_omitidas'] !== []) {
                $this->line('Ya presentadas: '.implode(', ', $resultado['jornadas_omitidas']));
            }
            if ($resultado['turnos_rendidos'] !== []) {
                $this->line(($dryRun ? 'Turnos a rendir: ' : 'Turnos rendidos: ').implode(', ', $resultado['turnos_rendidos']));
            }
            if ($resultado['jornadas_presentadas'] !== []) {
                $this->line(($dryRun ? 'Jornadas a presentar: ' : 'Jornadas presentadas: ').implode(', ', $resultado['jornadas_presentadas']));
            }
            if ($resultado['jornadas_presentadas'] === [] && $resultado['jornadas_omitidas'] === [] && $resultado['turnos_rendidos'] === []) {
                $this->comment('Sin jornadas pendientes en este rango.');
            }
            foreach ($resultado['errores'] as $error) {
                $this->error($error);
                $exitCode = self::FAILURE;
            }
        }

        return $exitCode;
    }
}
