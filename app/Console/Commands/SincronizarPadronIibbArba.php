<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Configuracion\ImportarPadronIibbArbaJob;
use App\Services\Configuracion\PadronIibbArbaCargaService;
use App\Services\Configuracion\PadronIibbArbaDescargaService;
use App\Support\Configuracion\PadronIibbCargaNotificacionSupport;
use Illuminate\Console\Command;
use Throwable;

class SincronizarPadronIibbArba extends Command
{
    protected $signature = 'padron-iibb-arba:sincronizar
                            {--periodo=siguiente : actual (mes en curso) o siguiente (próximo mes)}
                            {--solo-descargar : Solo baja el ZIP, no importa}
                            {--sync : Importa en este proceso (sin cola); por defecto encola en padrones}
                            {--directorio= : Carpeta destino del ZIP}';

    protected $description = 'Descarga padrón RG ARBA (DFE) e importa a padron_iibb_arba';

    public function handle(
        PadronIibbArbaDescargaService $descarga,
        PadronIibbArbaCargaService $carga
    ): int {
        $periodo = strtolower((string) $this->option('periodo'));
        if (! in_array($periodo, ['actual', 'siguiente'], true)) {
            $this->error('--periodo debe ser actual o siguiente');

            return self::FAILURE;
        }

        $dirOpt = $this->option('directorio');
        $directorio = is_string($dirOpt) && $dirOpt !== '' ? $dirOpt : null;

        try {
            $info = $descarga->descargar($periodo, $directorio);
        } catch (Throwable $e) {
            $this->error('Descarga falló: ' . $e->getMessage());
            PadronIibbCargaNotificacionSupport::notificar(
                false,
                'IIBB ARBA (descarga)',
                'Falló la descarga DFE ARBA.',
                '',
                ['periodo' => $periodo],
                $e->getMessage()
            );

            return self::FAILURE;
        }

        $this->info(sprintf(
            'ZIP OK: %s (%s bytes) período %s–%s (mes %s/%s)',
            $info['zip'],
            number_format($info['bytes'], 0, ',', '.'),
            $info['fecha_desde'],
            $info['fecha_hasta'],
            $info['mes'],
            $info['anio']
        ));

        if ($this->option('solo-descargar')) {
            return self::SUCCESS;
        }

        if ($this->option('sync')) {
            $this->info('Importando en proceso (sync)…');
            try {
                $resultado = $carga->cargar(
                    $info['zip'],
                    (int) config('padrones_iibb.batch_arba', 5000),
                    (int) config('padrones_iibb.pause_ms', 20)
                );
            } catch (Throwable $e) {
                $this->error('Import falló: ' . $e->getMessage());
                PadronIibbCargaNotificacionSupport::notificar(
                    false,
                    'IIBB ARBA',
                    'Falló la importación ARBA (sync).',
                    $info['zip'],
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
            PadronIibbCargaNotificacionSupport::notificar(
                true,
                'IIBB ARBA',
                'Importación ARBA finalizada correctamente (sync).',
                $info['zip'],
                $resumen
            );

            return self::SUCCESS;
        }

        ImportarPadronIibbArbaJob::dispatch(
            $info['zip'],
            (int) config('padrones_iibb.batch_arba', 5000),
            (int) config('padrones_iibb.pause_ms', 20),
            false
        );
        $this->info('Importación encolada en cola "' . config('padrones_iibb.cola', 'padrones') . '".');

        return self::SUCCESS;
    }
}
