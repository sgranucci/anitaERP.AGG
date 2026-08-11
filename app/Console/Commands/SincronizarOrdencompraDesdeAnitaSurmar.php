<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Compras\Surmar\OrdencompraSurmarAnitaSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class SincronizarOrdencompraDesdeAnitaSurmar extends Command
{
    protected $signature = 'ordencompra:sincronizar-anita-surmar
                            {--usuario= : ID usuario para creousuario_id y estados}
                            {--nro= : Importar solo una OC por penmp_nro (numeroordencompra)}
                            {--dry-run : Lista cantidades sin importar}';

    protected $description = 'Importa OC desde Anita Surmar (/usr2/surmar/compras). No usa ni modifica el sync AGG.';

    public function handle(OrdencompraSurmarAnitaSyncService $sync): int
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

        if ($this->option('dry-run')) {
            $this->info('Dry-run Surmar: path='.config('ordencompra_anita_surmar.path_sistema')
                .' empresa_id='.config('ordencompra_anita_surmar.empresa_id')
                .' centrocosto_id='.config('ordencompra_anita_surmar.centrocosto_id')
                .' fecha_desde='.config('ordencompra_anita_surmar.fecha_desde'));

            $api = new \App\ApiAnita;
            $fechaDesde = (int) config('ordencompra_anita_surmar.fecha_desde', 20260100);
            $payload = \App\Support\Compras\AnitaSync\Surmar\OrdencompraSurmarAnitaBridgeSupport::mergePayload([
                'acc' => 'list',
                'campos' => 'count(*) as c',
                'tabla' => 'pendmaep',
                'whereArmado' => " WHERE penmp_fecha >= {$fechaDesde}",
            ]);
            $raw = $api->apiCall($payload);
            $this->info('pendmaep desde '.$fechaDesde.': '.$raw);

            return self::SUCCESS;
        }

        $nro = $this->option('nro');
        try {
            if ($nro !== null && $nro !== '') {
                $this->info("Importando OC Surmar {$nro}…");
                $estado = $sync->traerRegistroDeAnita((int) $nro);
                $this->info("Resultado: {$estado}");

                return self::SUCCESS;
            }

            $this->info('Sincronizando OC desde Anita Surmar (puede tardar)…');
            $ret = $sync->sincronizarConAnita($usuarioId);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("En Anita: {$ret['en_anita']}; importadas: {$ret['importados']}; omitidas: {$ret['omitidos']}.");
        foreach ($ret['errores'] as $w) {
            $this->warn($w);
        }

        return empty($ret['errores']) ? self::SUCCESS : self::FAILURE;
    }
}
