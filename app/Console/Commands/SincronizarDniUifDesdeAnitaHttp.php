<?php

namespace App\Console\Commands;

use App\Services\Uif\ClienteUifDniAnitaHttpSyncService;
use App\Support\Uif\ClienteUifArchivoStorage;
use Illuminate\Console\Command;
use Throwable;

class SincronizarDniUifDesdeAnitaHttp extends Command
{
    protected $signature = 'uif:sincronizar-dni-anita-http
                            {--origen= : biyemas|kandiko|rebisco|crown (default: orígenes con empresa_id>0)}
                            {--dry-run : Solo cuenta, no baja ni asocia}';

    protected $description = 'Copia DNI {nro}.pdf desde el Anita viejo (/dni_uif) a /scan/tesoreria/dni_uif y los asocia a las fichas.';

    public function handle(ClienteUifDniAnitaHttpSyncService $service): int
    {
        $origenOpt = strtolower(trim((string) $this->option('origen')));
        if ($origenOpt !== '') {
            $origenes = [$origenOpt];
        } else {
            $origenes = [];
            foreach (config('uif.anita_origenes', []) as $origen => $cfg) {
                if ((int) ($cfg['empresa_id'] ?? 0) > 0) {
                    $origenes[] = (string) $origen;
                }
            }
            if ($origenes === []) {
                $origenes = ['biyemas', 'kandiko', 'rebisco'];
            }
        }
        $dry = (bool) $this->option('dry-run');

        foreach ($origenes as $origen) {
            try {
                ClienteUifArchivoStorage::configOrigen($origen);
            } catch (Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        foreach ($origenes as $origen) {
            $this->info(($dry ? '[dry-run] ' : '').'Origen '.$origen);
            try {
                $stats = $service->sincronizar($origen, $dry, function (string $msg) {
                    $this->line($msg);
                });
            } catch (Throwable $e) {
                $this->error($origen.': '.$e->getMessage());

                return self::FAILURE;
            }
            $this->info(sprintf(
                '%s listo: remotos=%d copiados=%d ya_estaban=%d fallidos=%d asociados=%d',
                $origen,
                $stats['remotos'],
                $stats['copiados'],
                $stats['ya_estaban'],
                $stats['fallidos'],
                $stats['asociados']
            ));
        }

        return self::SUCCESS;
    }
}
