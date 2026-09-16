<?php

namespace App\Console\Commands;

use App\Repositories\Caja\ChequeraRepositoryInterface;
use Illuminate\Console\Command;

class SincronizarChequeraDesdeAnita extends Command
{
    protected $signature = 'chequera:sincronizar-anita';

    protected $description = 'Importa y actualiza chequeras desde Anita (cprocheq): rangos, estado, tipo y cuenta.';

    public function handle(ChequeraRepositoryInterface $repository): int
    {
        $this->info('Sincronizando chequeras desde Anita (cprocheq)…');

        try {
            $ret = $repository->sincronizarConAnita();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; creadas: {$ret['creadas']}; actualizadas: {$ret['actualizadas']}; omitidas: {$ret['omitidas']}."
        );

        if ($ret['en_anita'] === 0) {
            $this->warn('Anita no devolvió chequeras en cprocheq. Revise la conexión ANITA_* y el bridge.');
        }

        foreach ($ret['errores'] as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
