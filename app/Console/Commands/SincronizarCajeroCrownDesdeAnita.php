<?php

namespace App\Console\Commands;

use App\Services\Caja\CajeroCrownAnitaSyncService;
use App\Support\Caja\CajeroCrownSistemaExternoSupport;
use App\Support\Ventas\GastronomiaSalaSupport;
use Illuminate\Console\Command;

class SincronizarCajeroCrownDesdeAnita extends Command
{
    protected $signature = 'cajero-crown:sincronizar-anita
                            {--dry-run : Solo listar qué se importaría / enlazaría, sin grabar}';

    protected $description = 'Importa cajeros Crown desde Anita (cajero + cajeroexterno) y enlaza mozos de la sala (CIP).';

    public function handle(CajeroCrownAnitaSyncService $sync): int
    {
        if ($this->option('dry-run')) {
            return $this->dryRun($sync);
        }

        try {
            $this->info('Sincronizando cajeros Crown desde Anita…');
            $ret = $sync->sincronizarDesdeAnita();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; importados: {$ret['importados']}; "
            ."actualizados: {$ret['actualizados']}; omitidos: {$ret['omitidos']}; "
            ."enlazados mozo: {$ret['enlazados_mozo']}; filas externo: {$ret['externos']}."
        );
        foreach ($ret['errores'] as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }

    private function dryRun(CajeroCrownAnitaSyncService $sync): int
    {
        $this->warn('DRY-RUN: no se graba nada.');
        $sala = trim((string) config('cajero_crown_anita.sala_codigo', ''));
        $salaId = GastronomiaSalaSupport::idPorCodigo($sala);
        $this->info('Bridge: sistema='.config('cajero_crown_anita.sistema')
            .' ifx='.config('cajero_crown_anita.ifx_server')
            .' path='.config('cajero_crown_anita.path_sistema')
            .' sala='.($sala !== '' ? "{$sala} (id=".($salaId ?? '—').')' : '(ninguna)')
            .' empresa='.config('cajero_crown_anita.empresa_default_id'));

        try {
            $lista = $sync->listarCajerosDesdeAnita();
            $externos = $sync->listarExternosDesdeAnita();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $empresaId = (int) config('cajero_crown_anita.empresa_default_id', 1);
        $mozos = \App\Models\Ventas\MozoGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->when($salaId !== null, fn ($q) => $q->where('sala_id', $salaId))
            ->get(['id', 'codigo', 'nombre'])
            ->keyBy(fn ($m) => (string) (int) $m->codigo);

        $this->info('En Anita cajeros: '.count($lista).' | cajeroexterno: '.count($externos)
            .' | mozos sala: '.$mozos->count());

        $rows = [];
        $enlazados = 0;
        foreach ($lista as $row) {
            $codigo = (int) ($row->caj_cajero ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $mozoAnita = (int) ($row->caj_mozo ?? 0);
            $mozo = $mozoAnita > 0
                ? $mozos->get((string) $mozoAnita)
                : $mozos->get((string) $codigo);
            if ($mozo !== null) {
                $enlazados++;
            }
            $rows[] = [
                (string) $codigo,
                trim((string) ($row->caj_nombre ?? '')),
                $mozoAnita > 0 ? (string) $mozoAnita : '—',
                $mozo ? "{$mozo->codigo} {$mozo->nombre}" : '—',
            ];
        }

        $this->info("Enlazables con mozo {$sala}: {$enlazados}");
        $this->table(['codigo', 'nombre', 'caj_mozo', 'mozo ERP'], array_slice($rows, 0, 30));
        if (count($rows) > 30) {
            $this->line('… y '.(count($rows) - 30).' más.');
        }

        $pxsol = 0;
        foreach ($externos as $e) {
            $s = CajeroCrownSistemaExternoSupport::normalizar((string) ($e->caje_sistema ?? ''));
            if ($s === CajeroCrownSistemaExternoSupport::PXSOL) {
                $pxsol++;
            }
        }
        $this->info("Externos Anita: PXSOL={$pxsol} (total ".count($externos).')');

        return self::SUCCESS;
    }
}
