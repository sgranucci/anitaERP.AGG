<?php

namespace App\Console\Commands;

use App\Models\Uif\Cliente_Premio_Uif;
use App\Services\Uif\ClientePremioUifFotoTesoreria;
use Illuminate\Console\Command;

/**
 * Replica la importación de fotos realizada al sincronizar desde Anita:
 * archivo en {@see \App\Services\Uif\ClienteUifFotoDocumento::basePath()} como pago_{anita_inropremioid}.*
 */
class ImportarFotosPremiosUifDesdeTesoreria extends Command
{
    protected $signature = 'uif:fotos-premios-tesoreria {--force : Importar también si ya hay foto}';

    protected $description = 'Copia fotos pago_* desde tesorería (see config/uif.php FOTOS_CLIENTES_PATH) a storage público premios UIF';

    public function handle(): int
    {
        $q = Cliente_Premio_Uif::query()->whereNotNull('anita_inropremioid')->where('anita_inropremioid', '>', 0);

        if (! $this->option('force')) {
            $q->where(function ($w) {
                $w->whereNull('foto')->orWhere('foto', '');
            });
        }

        $total = $q->count();
        if ($total === 0) {
            $this->info('Sin registros pendientes (use --force para reimportar todas las que tengan anita_inropremioid).');

            return self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;

        $q->orderBy('id')->chunkById(100, function ($rows) use (&$ok, &$fail) {
            foreach ($rows as $premio) {
                $pid = (int) $premio->anita_inropremioid;
                $nuevo = ClientePremioUifFotoTesoreria::importToPublicStorage($pid, null);
                if ($nuevo === null) {
                    $this->line("Sin archivo o error: premio local #{$premio->id} anita_inropremioid={$pid}");
                    $fail++;

                    continue;
                }
                $anterior = $premio->foto ?? '';
                if ($anterior !== '' && $anterior !== $nuevo) {
                    ClientePremioUifFotoTesoreria::deletePublicFotoIfUnused($anterior);
                }
                $premio->update(['foto' => $nuevo]);
                $ok++;
            }
        });

        $this->info("Listo. Importadas: {$ok}. Sin archivo o error: {$fail}.");

        return self::SUCCESS;
    }
}
