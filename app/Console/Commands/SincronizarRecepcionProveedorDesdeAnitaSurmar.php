<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Stock\Surmar\RecepcionProveedorSurmarAnitaSyncService;
use App\Support\Stock\Surmar\RecepcionProveedorSurmarAnitaBridgeSupport;
use Illuminate\Console\Command;

class SincronizarRecepcionProveedorDesdeAnitaSurmar extends Command
{
    protected $signature = 'recepcion-proveedor:sincronizar-anita-surmar
                            {--usuario= : ID usuario para creousuario_id}
                            {--nro= : Importar solo una COM por recm_nro}
                            {--sucursal=1 : Sucursal Anita (recm_sucursal) para --nro}
                            {--dry-run : Contadores / una COM sin grabar}';

    protected $description = 'Importa recepmae/recepmov COM/D desde Anita Surmar. No usa ni modifica el import AGG.';

    public function handle(RecepcionProveedorSurmarAnitaSyncService $sync): int
    {
        $usuarioId = $this->option('usuario');
        $usuarioId = ($usuarioId !== null && $usuarioId !== '')
            ? (int) $usuarioId
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        if ($usuarioId <= 0) {
            $this->error('usuario inválido.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $nro = $this->option('nro');

        try {
            if ($nro !== null && $nro !== '') {
                $sucursal = (int) $this->option('sucursal');
                if ($sucursal <= 0) {
                    $this->error('--sucursal inválida.');

                    return self::FAILURE;
                }

                $this->info(sprintf(
                    'Importando recepción Surmar COM/%s nro=%s suc=%s (path=%s)…',
                    RecepcionProveedorSurmarAnitaBridgeSupport::letra(),
                    $nro,
                    $sucursal,
                    config('recepcion_anita_surmar.path_sistema')
                ));

                $resultado = $sync->importarUna($sucursal, (int) $nro, $usuarioId, $dryRun);

                $this->table(['Campo', 'Valor'], [
                    ['Estado', $resultado['estado']],
                    ['Recepción ERP id', $resultado['recepcion_id'] ?? '—'],
                    ['Líneas Anita', $resultado['lineas']],
                    ['Mensaje', $resultado['mensaje'] ?? '—'],
                ]);

                return in_array($resultado['estado'], ['importada', 'omitida', 'dry_run'], true)
                    ? self::SUCCESS
                    : self::FAILURE;
            }

            if ($dryRun) {
                $this->info('Dry-run Surmar recepciones: path='.config('recepcion_anita_surmar.path_sistema')
                    .' empresa_id='.config('recepcion_anita_surmar.empresa_id')
                    .' centrocosto_id='.config('recepcion_anita_surmar.centrocosto_id')
                    .' fecha_desde='.config('recepcion_anita_surmar.fecha_desde')
                    .' tipo/letra='.RecepcionProveedorSurmarAnitaBridgeSupport::tipo()
                    .'/'.RecepcionProveedorSurmarAnitaBridgeSupport::letra());
            } else {
                $this->info('Sincronizando recepciones desde Anita Surmar (puede tardar)…');
            }

            $ret = $sync->sincronizar($usuarioId, $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['En Anita (cabeceras)', $ret['en_anita']],
            ['Importadas', $ret['importadas']],
            ['Omitidas / ya existían', $ret['omitidas']],
            ['Sin proveedor ERP', $ret['sin_proveedor']],
            ['Sin líneas', $ret['sin_lineas']],
            ['Líneas grabadas', $ret['lineas']],
            ['Errores', count($ret['errores'])],
        ]);

        foreach ($ret['errores'] as $w) {
            $this->warn($w);
        }

        if ($dryRun) {
            $this->comment('Dry-run: no se grabó nada.');
        }

        return empty($ret['errores']) ? self::SUCCESS : self::FAILURE;
    }
}
