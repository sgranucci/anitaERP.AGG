<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Compras\OrdencompraAnitaSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class SincronizarOrdencompraDesdeAnita extends Command
{
    protected $signature = 'ordencompra:sincronizar-anita
                            {--usuario= : ID usuario para creousuario_id y estados}
                            {--nro= : Importar solo una OC por penmp_nro (numeroordencompra)}';

    protected $description = 'Importa órdenes de compra desde Anita (pendmaep y tablas relacionadas) con mapeo campo a campo.';

    public function handle(OrdencompraAnitaSyncService $sync): int
    {
        $usuarioId = $this->option('usuario');
        $usuarioId = ($usuarioId !== null && $usuarioId !== '')
            ? (int) $usuarioId
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        if ($usuarioId <= 0) {
            $this->error('usuario inválido.');

            return self::FAILURE;
        }

        if (! Auth::loginUsingId($usuarioId)) {
            $this->error("No existe usuario id {$usuarioId}.");

            return self::FAILURE;
        }

        $nro = $this->option('nro');
        try {
            if ($nro !== null && $nro !== '') {
                $this->info("Importando OC {$nro}…");
                $estado = $sync->traerRegistroDeAnita((int) $nro);
                $this->info("Resultado: {$estado}");
                if ($estado === 'lineas_completadas') {
                    $this->info('Se completaron ítems faltantes desde Anita (pendmovp).');
                }

                return self::SUCCESS;
            }

            $this->info('Sincronizando órdenes de compra desde Anita (puede tardar)…');
            $ret = $sync->sincronizarConAnita($usuarioId);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("En Anita: {$ret['en_anita']}; importadas: {$ret['importados']}; omitidas (ya en ERP): {$ret['omitidos']}.");
        foreach ($ret['errores'] as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }
}
