<?php

namespace App\Console\Commands;

use App\Repositories\Caja\PerdidaPersonalRepositoryInterface;
use Illuminate\Console\Command;

class SincronizarPerdidaPersonalDesdeAnita extends Command
{
    protected $signature = 'caja:sincronizar-perdida-personal-anita
                            {--numero= : Importar/actualizar solo un perdm_nro Anita}
                            {--solo-faltantes : Solo insertar faltantes (sin actualizar existentes)}';

    protected $description = 'Sincroniza pérdidas de personal desde Anita (perdmae). Por defecto UPSERT histórico.';

    public function handle(PerdidaPersonalRepositoryInterface $repository): int
    {
        $numeroOpt = $this->option('numero');
        $numero = ($numeroOpt !== null && $numeroOpt !== '') ? (int) $numeroOpt : null;
        $actualizarExistentes = ! $this->option('solo-faltantes');

        try {
            if ($numero !== null && $numero > 0) {
                $this->info("Sincronizando pérdida Anita perdm_nro={$numero}…");
            } elseif ($actualizarExistentes) {
                $this->info('Sincronizando pérdidas de personal desde Anita (perdmae) — UPSERT histórico…');
            } else {
                $this->info('Importando pérdidas de personal faltantes desde Anita (perdmae)…');
            }

            $ret = $repository->sincronizarConAnita($numero, $actualizarExistentes);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; importados: {$ret['importados']}; "
            ."actualizados: {$ret['actualizados']}; omitidos: {$ret['omitidos']}."
        );

        if ($ret['en_anita'] === 0) {
            $this->warn('Anita no devolvió registros en perdmae. Revise la conexión ANITA_* y variables del bridge.');
        }

        foreach ($ret['errores'] as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
