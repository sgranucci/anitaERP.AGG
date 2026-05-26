<?php

namespace App\Console\Commands;

use App\Repositories\Caja\CuentacajaRepositoryInterface;
use Illuminate\Console\Command;

class SincronizarCbuCuentacajaDesdeAnita extends Command
{
    protected $signature = 'cuentacaja:sincronizar-cbu-anita
                            {--codigo= : Sincronizar solo una cuenta por código Anita (tesmc_cuenta)}';

    protected $description = 'Actualiza cuentacaja.cbu desde Anita (tabla tesmcbu). Solo entorno AGG.';

    public function handle(CuentacajaRepositoryInterface $repository): int
    {
        if (config('app.empresa') !== 'AGG') {
            $this->warn('Este comando solo aplica al entorno AGG (CBU en tabla tesmcbu).');

            return self::FAILURE;
        }

        $codigo = $this->option('codigo');

        try {
            if ($codigo !== null && $codigo !== '') {
                $this->info("Sincronizando CBU de cuenta Anita {$codigo}…");
            } else {
                $this->info('Sincronizando CBU de cuentas de caja desde Anita (tesmcbu)…');
            }

            $ret = $repository->sincronizarCbuConAnita($codigo ?: null);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; actualizados: {$ret['actualizados']}; sin cambios: {$ret['sin_cambios']}; sin cuenta local: {$ret['sin_cuenta_local']}."
        );

        if ($ret['en_anita'] === 0) {
            $this->warn('Anita no devolvió registros en tesmcbu. Revise la conexión ANITA_* y que la tabla exista en Informix.');
        }

        return self::SUCCESS;
    }
}
