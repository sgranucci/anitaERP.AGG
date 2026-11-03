<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoJornadaSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * A las 48 h suelta las líneas Waitry del snapshot de cierre. Se vuelven a leer
 * al pedir el proceso. Factura, asientos y rendición Anita quedan en la fila
 * para no volver a ofrecer el grabado.
 */
class GastronomiaPurgarSnapshotsProcesoCommand extends Command
{
    protected $signature = 'gastronomia:purgar-snapshots-proceso
                            {--horas= : Retención en horas (default config)}
                            {--dry-run : Solo informa, no escribe}
                            {--sin-compactar : No corre OPTIMIZE TABLE al final}';

    protected $description = 'Purga las líneas Waitry de snapshots de cierre más viejos que la retención';

    public function handle(): int
    {
        ini_set('memory_limit', '512M');

        $simulacion = (bool) $this->option('dry-run');
        $horas = max(1, (int) ($this->option('horas') ?: config('gastronomia.snapshot_proceso_purga.horas', 48)));
        $limite = now()->subHours($horas);

        $borrados = 0;
        $achicados = 0;
        $ya = 0;
        $bytesAntes = 0;
        $bytesDespues = 0;

        GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('congelado_en', '<', $limite)
            ->orderBy('id')
            ->chunkById(5, function ($snapshots) use (
                $simulacion,
                &$borrados,
                &$achicados,
                &$ya,
                &$bytesAntes,
                &$bytesDespues
            ) {
                foreach ($snapshots as $snapshot) {
                    $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
                    $antes = strlen((string) json_encode($payload));
                    $bytesAntes += $antes;

                    if (! empty($payload['lineas_purgadas']) && empty($payload['lineas'])) {
                        $ya++;
                        $bytesDespues += $antes;

                        continue;
                    }

                    $persistente = CierreJornadaProcesoJornadaSupport::payloadProcesoPersistente($snapshot);

                    if ($persistente === []) {
                        $borrados++;
                        if (! $simulacion) {
                            $snapshot->delete();
                        }

                        continue;
                    }

                    $nuevo = array_merge($persistente, [
                        'lineas_purgadas' => true,
                        'lineas_purgadas_en' => now()->toIso8601String(),
                        'snapshot_provisional' => (bool) ($payload['snapshot_provisional'] ?? false),
                        'jornada_estado' => (string) ($payload['jornada_estado'] ?? ''),
                        'jornada_cierre_en' => $payload['jornada_cierre_en'] ?? null,
                        'waitry_order_id_hasta' => $payload['waitry_order_id_hasta'] ?? null,
                        'lineas_schema_version' => (int) ($payload['lineas_schema_version'] ?? 1),
                    ]);
                    $achicados++;
                    $bytesDespues += strlen((string) json_encode($nuevo));

                    if (! $simulacion) {
                        $snapshot->payload = $nuevo;
                        $snapshot->save();
                    }
                }
            });

        $liberados = max(0, $bytesAntes - $bytesDespues);
        $this->info(sprintf(
            '%s retención %d h (congelados antes de %s): %s filas sin proceso borradas, %s achicadas, %s ya purgadas. Líneas ~%s MB.',
            $simulacion ? 'Simulado.' : 'Hecho.',
            $horas,
            $limite->toDateTimeString(),
            number_format($borrados),
            number_format($achicados),
            number_format($ya),
            number_format($liberados / 1048576, 1)
        ));

        if (! $simulacion && ! $this->option('sin-compactar') && ($borrados + $achicados) > 0) {
            $this->compactar();
        }

        return self::SUCCESS;
    }

    private function compactar(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->comment('Sin OPTIMIZE: el motor no es MySQL. El espacio queda libre dentro de la tabla.');

            return;
        }

        $this->info('Compactando gastronomia_cierre_jornada_proceso_snapshot…');
        DB::statement('OPTIMIZE TABLE `gastronomia_cierre_jornada_proceso_snapshot`');
        $this->info('Tabla compactada.');
    }
}
