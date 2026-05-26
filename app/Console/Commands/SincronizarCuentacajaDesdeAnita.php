<?php

namespace App\Console\Commands;

use App\Repositories\Caja\CuentacajaRepositoryInterface;
use Illuminate\Console\Command;

class SincronizarCuentacajaDesdeAnita extends Command
{
    protected $signature = 'cuentacaja:sincronizar-anita
                            {--codigo= : Importar solo una cuenta por tesm_cuenta Anita}
                            {--con-cbu : En AGG, sincronizar también CBU desde tesmcbu al finalizar}';

    protected $description = 'Importa cuentas de caja desde Anita (tesmae) que no existen en cuentacaja.';

    public function handle(CuentacajaRepositoryInterface $repository): int
    {
        $codigo = $this->option('codigo');
        $sincronizarCbu = (bool) $this->option('con-cbu');

        try {
            if ($codigo !== null && $codigo !== '') {
                $this->info("Importando cuenta Anita tesm_cuenta={$codigo}…");
            } else {
                $this->info('Importando cuentas de caja faltantes desde Anita (tesmae)…');
            }

            $ret = $repository->sincronizarConAnita($codigo ?: null, $sincronizarCbu);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; importados: {$ret['importados']}; omitidos (ya en ERP): {$ret['omitidos']}."
        );

        if ($ret['en_anita'] === 0) {
            $this->warn('Anita no devolvió cuentas en tesmae. Revise la conexión ANITA_* y variables del bridge.');
        }

        foreach ($ret['errores'] as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
