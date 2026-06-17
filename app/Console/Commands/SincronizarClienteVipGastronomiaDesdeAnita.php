<?php

namespace App\Console\Commands;

use App\Services\Ventas\ClienteVipGastronomiaAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarClienteVipGastronomiaDesdeAnita extends Command
{
    protected $signature = 'cliente-vip-gastronomia:sincronizar-anita
                            {--empresa= : Importar solo una empresa (1=Biyemas, 2=Kandiko, 3=Rebisco)}
                            {--numeroid= : Importar solo un registro por inumeroid Anita}';

    protected $description = 'Importa clientes VIP gastronomía desde Anita (base_admin.clivipg) por empresa.';

    public function handle(ClienteVipGastronomiaAnitaSyncService $sync): int
    {
        $empresaOpt = $this->option('empresa');
        $numeroid = $this->option('numeroid');

        try {
            if ($empresaOpt !== null && $empresaOpt !== '' && $numeroid !== null && $numeroid !== '') {
                $this->info("Importando cliente VIP Anita empresa {$empresaOpt} numeroid {$numeroid}…");
                $estado = $sync->traerRegistroDeAnita((int) $empresaOpt, (int) $numeroid);
                $this->info("Resultado: {$estado}");

                return self::SUCCESS;
            }

            if ($empresaOpt !== null && $empresaOpt !== '') {
                $empresaId = (int) $empresaOpt;
                $this->info("Sincronizando clientes VIP desde Anita (base_admin.clivipg) para empresa_id={$empresaId}…");
                $ret = $sync->sincronizarEmpresaDesdeAnita($empresaId);
            } else {
                $this->info('Sincronizando clientes VIP desde Anita (Biyemas → Kandiko → Rebisco)…');
                $ret = $sync->sincronizarConAnita();
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; importados: {$ret['importados']}; actualizados: {$ret['actualizados']}; omitidos: {$ret['omitidos']}."
        );
        foreach ($ret['errores'] as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }
}
