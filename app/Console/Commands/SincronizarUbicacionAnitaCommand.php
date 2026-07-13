<?php

namespace App\Console\Commands;

use App\Models\Stock\Ubicacion;
use App\Support\Stock\InterformingSifabSupport;
use Illuminate\Console\Command;

class SincronizarUbicacionAnitaCommand extends Command
{
    protected $signature = 'stock:sincronizar-ubicacion-anita';

    protected $description = 'Sincroniza ubicaciones de stock desde Anita (INTERFORMING)';

    public function handle(): int
    {
        if (! InterformingSifabSupport::esInterforming()) {
            $this->error('Solo aplica a EMPRESA=INTERFORMING.');

            return self::FAILURE;
        }

        $ret = (new Ubicacion)->sincronizarConAnita();
        $this->info(sprintf(
            'Anita=%d importados=%d actualizados=%d errores=%d',
            $ret['en_anita'],
            $ret['importados'],
            $ret['actualizados'],
            count($ret['errores'])
        ));
        foreach (array_slice($ret['errores'], 0, 20) as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
