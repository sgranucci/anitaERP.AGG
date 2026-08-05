<?php

namespace App\Console\Commands;

use App\Services\Uif\UifPremioRuletaReclasificacionService;
use App\Support\Uif\UifMaquinaRuletaBienUsoSupport;
use Illuminate\Console\Command;

/**
 * Backfill opcional de juego RULETA según bien_uso.
 * Por defecto solo dry-run; requiere --apply para grabar.
 */
class UifReclasificarPremiosRuletaCommand extends Command
{
    protected $signature = 'uif:reclasificar-premios-ruleta
                            {--apply : Persiste los cambios (sin esto solo informa)}
                            {--ejemplos=20 : Cantidad de ejemplos a listar}';

    protected $description = 'Reclasifica premios UIF a RULETA si la posición coincide con ruletas en bien_uso (padrón ERP)';

    public function handle(UifPremioRuletaReclasificacionService $service): int
    {
        $aplicar = (bool) $this->option('apply');
        $ejemplos = max(0, (int) $this->option('ejemplos'));

        $this->info('Padrón bien_uso (tema Roulette/Ruleta):');
        foreach (UifMaquinaRuletaBienUsoSupport::resumenPadron() as $fila) {
            $this->line(sprintf('  empresa_id=%d → %d máquinas', $fila['empresa_id'], $fila['cantidad']));
        }

        if ($aplicar) {
            if (! $this->confirm('¿Aplicar reclasificación histórica a RULETA?', false)) {
                $this->warn('Cancelado.');

                return self::SUCCESS;
            }
        } else {
            $this->comment('Modo dry-run (no graba). Use --apply para persistir.');
        }

        $resultado = $service->ejecutar($aplicar, $ejemplos);

        $this->newLine();
        $this->info('Candidatos a RULETA: '.$resultado['candidatos']);
        if ($aplicar) {
            $this->info('Actualizados: '.$resultado['actualizados']);
        }

        if ($resultado['por_juego_origen'] !== []) {
            $this->line('Por juego origen:');
            foreach ($resultado['por_juego_origen'] as $juego => $cant) {
                $this->line("  {$juego}: {$cant}");
            }
        }

        if ($resultado['por_sala'] !== []) {
            $this->line('Por sala_id:');
            foreach ($resultado['por_sala'] as $sala => $cant) {
                $this->line("  sala {$sala}: {$cant}");
            }
        }

        if ($resultado['ejemplos'] !== []) {
            $this->newLine();
            $this->line('Ejemplos:');
            foreach ($resultado['ejemplos'] as $ej) {
                $this->line(sprintf(
                    '  id=%d sala=%d juego=%s pos=%s monto=%.2f fecha=%s',
                    $ej['id'],
                    $ej['sala_id'],
                    $ej['juego_actual'],
                    json_encode($ej['posicion']),
                    $ej['monto'],
                    $ej['fechaentrega'],
                ));
            }
        }

        return self::SUCCESS;
    }
}
