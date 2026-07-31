<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Configuracion\PadronIibbCabaCargaService;
use App\Support\Configuracion\PadronIibbCargaNotificacionSupport;
use Illuminate\Console\Command;
use Throwable;

class ImportarPadronIibbCaba extends Command
{
    protected $signature = 'padron-iibb-caba:import
                            {archivo : Ruta absoluta o relativa al TXT AGIP (ARDJU….TXT)}
                            {--batch=2000 : Filas por INSERT}
                            {--pause-ms=20 : Pausa entre lotes (ms)}
                            {--keep-period : No borrar el período antes de insertar}';

    protected $description = 'Importa padrón IIBB CABA (AGIP) a padron_iibb_caba en lotes (background-friendly)';

    public function handle(PadronIibbCabaCargaService $service): int
    {
        $archivo = (string) $this->argument('archivo');
        if ($archivo !== '' && $archivo[0] !== '/') {
            $archivo = base_path($archivo);
            if (! is_file($archivo)) {
                $alt = '/home/sergio/padroncaba/' . ltrim((string) $this->argument('archivo'), '/');
                if (is_file($alt)) {
                    $archivo = $alt;
                }
            }
        }

        $this->info("Importando CABA: {$archivo}");

        try {
            $stats = $service->cargar(
                $archivo,
                (int) $this->option('batch'),
                (int) $this->option('pause-ms'),
                (bool) $this->option('keep-period')
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            PadronIibbCargaNotificacionSupport::notificar(
                false,
                'IIBB CABA (AGIP)',
                'Falló la importación CABA (CLI).',
                $archivo,
                [],
                $e->getMessage()
            );

            return self::FAILURE;
        }

        $this->info(sprintf(
            'OK período %s→%s — leídas=%d insertadas=%d borrados=%d omitidas=%d errores=%d lotes=%d',
            $stats['desdefecha'] ?? '?',
            $stats['hastafecha'] ?? '?',
            $stats['leidas'],
            $stats['insertadas'],
            $stats['borrados'],
            $stats['omitidas'],
            $stats['errores'],
            $stats['lotes']
        ));

        PadronIibbCargaNotificacionSupport::notificar(
            true,
            'IIBB CABA (AGIP)',
            sprintf(
                'Importación CABA OK (CLI). Período %s → %s.',
                $stats['desdefecha'] ?? '?',
                $stats['hastafecha'] ?? '?'
            ),
            $archivo,
            [
                'leidas' => $stats['leidas'],
                'insertadas' => $stats['insertadas'],
                'borrados' => $stats['borrados'],
                'omitidas' => $stats['omitidas'],
                'errores' => $stats['errores'],
                'lotes' => $stats['lotes'],
            ]
        );

        return self::SUCCESS;
    }
}
