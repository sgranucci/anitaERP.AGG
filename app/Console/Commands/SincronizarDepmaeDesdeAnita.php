<?php

namespace App\Console\Commands;

use App\Services\Stock\DepmaeAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarDepmaeDesdeAnita extends Command
{
    protected $signature = 'depmae:sincronizar-anita
                            {--empresa= : Sincronizar solo una empresa (1=Biyemas, 2=Kandiko, 3=Rebisco)}
                            {--codigo= : Importar solo un depósito por codigo Anita}';

    protected $description = 'Importa depmae desde Anita por empresa (bridge propio Kandiko/Rebisco). Omite codigos de maquinas (>100000).';

    public function handle(DepmaeAnitaSyncService $sync): int
    {
        $empresaOpt = $this->option('empresa');
        $codigo = trim((string) ($this->option('codigo') ?? ''));

        try {
            if ($empresaOpt !== null && $empresaOpt !== '' && $codigo !== '') {
                $empresaId = (int) $empresaOpt;
                $this->info("Importando depósito Anita empresa {$empresaId} codigo {$codigo}…");
                $estado = $sync->traerRegistroDeAnita($empresaId, $codigo);
                $this->info("Resultado: {$estado}");

                return self::SUCCESS;
            }

            if ($empresaOpt !== null && $empresaOpt !== '') {
                $empresaId = (int) $empresaOpt;
                $this->info("Sincronizando depmae desde Anita para empresa_id={$empresaId}…");
                $ret = $sync->sincronizarEmpresaDesdeAnita($empresaId);
            } else {
                $this->info('Sincronizando depmae desde Anita (Biyemas → Kandiko → Rebisco)…');
                $ret = $sync->sincronizarConAnita();
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['En Anita', $ret['en_anita']],
            ['Omitidos (máquinas)', $ret['omitidos_maquina']],
            ['Importados', $ret['importados']],
            ['Actualizados', $ret['actualizados']],
            ['Omitidos', $ret['omitidos']],
        ]);
        foreach ($ret['errores'] as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }
}
