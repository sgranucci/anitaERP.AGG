<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Configuracion\PadronIibbBandejaIngresoService;
use App\Support\Configuracion\PadronIibbBandejaSupport;
use Illuminate\Console\Command;

class VigilarPadronIibbBandeja extends Command
{
    protected $signature = 'padron-iibb:vigilar-bandeja
                            {--dry-run : Muestra qué encolaría, sin mover archivos ni encolar}
                            {--preparar : Solo crea las carpetas de las jurisdicciones tildadas y el LEEME}';

    protected $description = 'Encola los padrones IIBB pegados en la bandeja externa (CABA y provincias; ARBA no)';

    public function handle(PadronIibbBandejaIngresoService $ingreso): int
    {
        if ((bool) $this->option('preparar')) {
            PadronIibbBandejaSupport::asegurarDirectorios();
            $claves = array_keys(PadronIibbBandejaSupport::carpetas());
            $this->info($claves === []
                ? 'Sin provincias con padrón y tildadas como agente o consulta: no hay carpetas.'
                : 'Carpetas: '.implode(', ', $claves));

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $resultados = $ingreso->vigilar($dryRun);

        foreach ($resultados as $fila) {
            $texto = trim(($fila['clave'] !== '' ? $fila['clave'] . ': ' : '') . ($fila['mensaje'] ?? $fila['archivo'] ?? ''));
            $linea = ($dryRun ? '[dry-run] ' : '') . $fila['estado'] . ' ' . $texto;
            if ($fila['estado'] === 'error' || $fila['estado'] === 'sin_directorio') {
                $this->error($linea);
                continue;
            }
            if (in_array($fila['estado'], ['ocupada', 'liberada', 'liberaria'], true)) {
                $this->warn($linea);
                continue;
            }
            $this->info($linea);
        }

        if ($dryRun && $resultados === []) {
            $this->info('Nada para encolar.');
        }

        return self::SUCCESS;
    }
}
