<?php

namespace App\Console\Commands;

use App\Services\Ventas\VendedorAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarVendedorDesdeAnita extends Command
{
    protected $signature = 'vendedor:sincronizar-anita
                            {--codigo= : Importar o actualizar solo un vendedor por vend_codigo Anita}';

    protected $description = 'Sincroniza vendedores desde Anita (tabla vendedor): crea los faltantes y actualiza los existentes en el ERP.';

    public function handle(VendedorAnitaSyncService $sync): int
    {
        $codigo = $this->option('codigo');

        try {
            if ($codigo !== null && $codigo !== '') {
                $this->info("Sincronizando vendedor Anita vend_codigo={$codigo}…");
                $estado = $sync->traerRegistroDeAnita((string) $codigo);
                $this->info("Resultado: {$estado}");

                return self::SUCCESS;
            }

            $this->info('Sincronizando vendedores desde Anita…');
            $ret = $sync->sincronizarConAnita();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; importados: {$ret['importados']}; actualizados: {$ret['actualizados']}; omitidos: {$ret['omitidos']}."
        );

        if ($ret['en_anita'] === 0) {
            $this->warn('Anita devolvió 0 vendedores. Revise ANITA_* y VENDEDOR_SYNC_ANITA_CAMPOS_LISTADO en .env.');
        }

        foreach ($ret['errores'] as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
