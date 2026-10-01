<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Configuracion\PadronIibbBandejaIngresoService;
use Illuminate\Console\Command;

class VigilarPadronIibbBandeja extends Command
{
    protected $signature = 'padron-iibb:vigilar-bandeja
                            {--dry-run : Muestra qué encolaría, sin mover archivos ni encolar}';

    protected $description = 'Encola los padrones IIBB pegados en la bandeja externa (CABA y provincias; ARBA no)';

    public function handle(PadronIibbBandejaIngresoService $ingreso): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $resultados = $ingreso->vigilar($dryRun);

        foreach ($resultados as $fila) {
            $texto = trim(($fila['clave'] !== '' ? $fila['clave'] . ': ' : '') . ($fila['mensaje'] ?? $fila['archivo'] ?? ''));
            if ($fila['estado'] === 'error' || $fila['estado'] === 'sin_directorio') {
                $this->error($fila['estado'] . ' ' . $texto);
                continue;
            }
            $this->info(($dryRun ? '[dry-run] ' : '') . $fila['estado'] . ' ' . $texto);
        }

        if ($dryRun && $resultados === []) {
            $this->info('Nada para encolar.');
        }

        return self::SUCCESS;
    }
}
