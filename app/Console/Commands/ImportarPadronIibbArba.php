<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Configuracion\PadronIibbArbaCargaService;
use App\Support\Configuracion\PadronIibbCargaNotificacionSupport;
use Illuminate\Console\Command;
use Throwable;

class ImportarPadronIibbArba extends Command
{
    protected $signature = 'padron-iibb-arba:import
                            {archivo : Zip PadronRGS… o TXT Per/Ret (ruta absoluta o relativa)}
                            {--batch=5000 : Filas por commit}
                            {--pause-ms=20 : Pausa entre lotes (ms)}';

    protected $description = 'Importa padrón IIBB ARBA a padron_iibb_arba en lotes (background-friendly)';

    public function handle(PadronIibbArbaCargaService $service): int
    {
        $archivo = (string) $this->argument('archivo');
        if ($archivo !== '' && $archivo[0] !== '/') {
            $archivo = base_path($archivo);
            if (! is_file($archivo)) {
                $alt = '/home/sergio/padronarba/' . ltrim((string) $this->argument('archivo'), '/');
                if (is_file($alt)) {
                    $archivo = $alt;
                }
            }
        }

        $this->info("Importando ARBA: {$archivo}");

        try {
            $resultado = $service->cargar(
                $archivo,
                (int) $this->option('batch'),
                (int) $this->option('pause-ms')
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            PadronIibbCargaNotificacionSupport::notificar(
                false,
                'IIBB ARBA',
                'Falló la importación ARBA (CLI).',
                $archivo,
                [],
                $e->getMessage()
            );

            return self::FAILURE;
        }

        $resumen = [];
        foreach ($resultado['archivos'] as $i => $stats) {
            $line = sprintf(
                '%s %s — leídas=%d insertadas=%d actualizadas=%d errores=%d',
                $stats['tipo'] ?? '?',
                basename((string) ($stats['archivo'] ?? '')),
                $stats['leidas'] ?? 0,
                $stats['insertadas'] ?? 0,
                $stats['actualizadas'] ?? 0,
                $stats['errores'] ?? 0
            );
            $this->info('  ' . $line);
            $resumen['archivo_' . ($i + 1)] = $line;
        }

        $this->info('Fin carga padrón ARBA.');
        PadronIibbCargaNotificacionSupport::notificar(
            true,
            'IIBB ARBA',
            'Importación ARBA finalizada correctamente (CLI).',
            $archivo,
            $resumen
        );

        return self::SUCCESS;
    }
}
