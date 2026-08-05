<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Configuracion\PadronIibbSantaFeCargaService;
use App\Support\Configuracion\PadronIibbCargaNotificacionSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportarPadronIibbSantaFe extends Command
{
    protected $signature = 'padron-iibb-santafe:import
                            {archivo : Ruta absoluta al CSV o ZIP PARP_YYYYMM}
                            {--provincia-id= : ID provincia Santa Fe (default: por jurisdiccion 921)}
                            {--batch=3000 : Filas por lote}
                            {--pause-ms=10 : Pausa entre lotes (ms)}
                            {--keep-period : No borrar el período antes de insertar}';

    protected $description = 'Importa padrón IIBB Santa Fe (API PARP) a padron_iibb / padron_iibb_tasa en lotes';

    public function handle(PadronIibbSantaFeCargaService $service): int
    {
        $archivo = (string) $this->argument('archivo');
        if ($archivo !== '' && $archivo[0] !== '/') {
            $archivo = base_path($archivo);
            if (! is_file($archivo)) {
                $alt = '/home/sergio/padronsantafe/' . ltrim((string) $this->argument('archivo'), '/');
                if (is_file($alt)) {
                    $archivo = $alt;
                }
            }
        }

        $provinciaId = (int) ($this->option('provincia-id') ?: 0);
        if ($provinciaId <= 0) {
            $provinciaId = (int) (DB::table('provincia')
                ->where('jurisdiccion', (string) PadronIibbSantaFeCargaService::JURISDICCION)
                ->value('id') ?: 0);
        }
        if ($provinciaId <= 0) {
            $this->error('No se encontró provincia Santa Fe (jurisdicción 921).');

            return self::FAILURE;
        }

        $this->info("Importando Santa Fe: {$archivo} (provincia_id={$provinciaId})");

        try {
            $stats = $service->cargar(
                $archivo,
                $provinciaId,
                (int) $this->option('batch'),
                (int) $this->option('pause-ms'),
                (bool) $this->option('keep-period')
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            PadronIibbCargaNotificacionSupport::notificar(
                false,
                'IIBB Santa Fe (API)',
                'Falló la importación Santa Fe (CLI).',
                $archivo,
                [],
                $e->getMessage()
            );

            return self::FAILURE;
        }

        $this->info(sprintf(
            'OK período %s→%s — leídas=%d tasas=%d cuits_nuevos=%d borrados=%d omitidas=%d errores=%d lotes=%d',
            $stats['desdefecha'] ?? '?',
            $stats['hastafecha'] ?? '?',
            $stats['leidas'],
            $stats['insertadas_tasa'],
            $stats['insertadas_cuit'],
            $stats['borrados'],
            $stats['omitidas'],
            $stats['errores'],
            $stats['lotes']
        ));

        PadronIibbCargaNotificacionSupport::notificar(
            true,
            'IIBB Santa Fe (API)',
            sprintf(
                'Importación Santa Fe OK (CLI). Período %s → %s.',
                $stats['desdefecha'] ?? '?',
                $stats['hastafecha'] ?? '?'
            ),
            $archivo,
            [
                'leidas' => $stats['leidas'],
                'insertadas_cuit' => $stats['insertadas_cuit'],
                'insertadas_tasa' => $stats['insertadas_tasa'],
                'borrados' => $stats['borrados'],
                'omitidas' => $stats['omitidas'],
                'errores' => $stats['errores'],
                'lotes' => $stats['lotes'],
            ]
        );

        return self::SUCCESS;
    }
}
