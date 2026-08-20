<?php

namespace App\Console\Commands;

use App\Services\Uif\UifPremioPosicionElectronicaASlotsService;
use Illuminate\Console\Command;

/**
 * Backfill POSICION ELECTRONICA → SLOTS (RULETA si bien_uso).
 * Por defecto dry-run; --apply graba.
 */
class UifReclasificarPremiosPeASlotsCommand extends Command
{
    protected $signature = 'uif:reclasificar-premios-pe-a-slots
                            {--apply : Persiste los cambios (sin esto solo informa)}
                            {--ejemplos=20 : Cantidad de ejemplos a listar}';

    protected $description = 'Reclasifica premios UIF de POSICION ELECTRONICA a SLOTS (o RULETA si la posición es ruleta en bien_uso)';

    public function handle(UifPremioPosicionElectronicaASlotsService $service): int
    {
        $aplicar = (bool) $this->option('apply');
        $ejemplos = max(0, (int) $this->option('ejemplos'));

        if ($aplicar) {
            $this->warn('Aplicando reclasificación histórica POSICION ELECTRONICA → SLOTS/RULETA.');
        } else {
            $this->comment('Modo dry-run (no graba). Use --apply para persistir.');
        }

        $resultado = $service->ejecutar($aplicar, $ejemplos);

        $this->newLine();
        $this->info('Candidatos a SLOTS: '.$resultado['candidatos_slots']);
        $this->info('Candidatos a RULETA: '.$resultado['candidatos_ruleta']);
        if ($aplicar) {
            $this->info('Actualizados SLOTS: '.$resultado['actualizados_slots']);
            $this->info('Actualizados RULETA: '.$resultado['actualizados_ruleta']);
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
                    '  id=%d sala=%d → %s pos=%s tito=%s fecha=%s',
                    $ej['id'],
                    $ej['sala_id'],
                    $ej['destino'],
                    json_encode($ej['posicion']),
                    json_encode($ej['numerotito']),
                    $ej['fechaentrega'],
                ));
            }
        }

        return self::SUCCESS;
    }
}
