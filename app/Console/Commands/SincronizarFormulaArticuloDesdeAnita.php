<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Formula_Articulo;
use App\Services\Stock\FormulaArticuloAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarFormulaArticuloDesdeAnita extends Command
{
    protected $signature = 'formula-articulo:sincronizar-anita
                            {--fuente=api : api (ApiAnita, como Articulo) o archivo (UNLOAD |)}
                            {--modo=bulk : bulk (un solo list masivo) o lote (re-sync una por una de las fórmulas ya cargadas en ERP)}
                            {--una= : Forzar sincronización de una sola fórmula Anita (stkcm_formula). Ignora --modo y --fuente=archivo.}
                            {--purgar : Borra todas las fórmulas (formula_articulo y dependientes) y articulo.formula antes de sincronizar.}
                            {--stkcmae=/home/sergio/tmp/stkcmae.dat : Ruta datos stkcmae (pipe, sin cabecera; sólo --fuente=archivo)}
                            {--stkcmov=/home/sergio/tmp/stkcmov.dat : Ruta datos stkcmov (pipe; FRASLE: 9 cols con stkcv_ranura; sólo --fuente=archivo)}
                            {--usuario= : ID usuario para estado/historia (default: primer usuario)}';

    protected $description = 'Importa fórmulas desde Anita (stkcmae/stkcmov) vía ApiAnita o archivos pipe; estado ACTIVA con observación "Alta desde anita". Soporta modo bulk, lote individual y fórmula puntual. Con --purgar borra todo lo cargado antes de re-sincronizar.';

    public function handle(FormulaArticuloAnitaSyncService $sync): int
    {
        $usuarioId = $this->option('usuario');
        $usuarioId = ($usuarioId !== null && $usuarioId !== '')
            ? (int) $usuarioId
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        if ($usuarioId <= 0) {
            $this->error('usuario inválido.');

            return self::FAILURE;
        }

        $fuente = strtolower(trim((string) $this->option('fuente')));
        $modo = strtolower(trim((string) ($this->option('modo') ?? 'bulk'))) ?: 'bulk';
        $unaOpt = $this->option('una');
        $una = ($unaOpt !== null && $unaOpt !== '') ? (int) $unaOpt : 0;
        $purgar = (bool) $this->option('purgar');

        if ($purgar) {
            $this->warn('Purgando fórmulas existentes (formula_articulo y dependientes) y articulo.formula…');
            $resumen = $sync->purgarFormulas();
            $this->info(sprintf(
                'Purga: %d fórmula(s), %d hijo(s), %d estado(s), %d archivo(s); %d artículo(s) con articulo.formula reseteados.',
                $resumen['formulas'],
                $resumen['hijos'],
                $resumen['estados'],
                $resumen['archivos'],
                $resumen['articulos_formula_reseteados']
            ));
        }

        try {
            if ($una > 0) {
                $this->info("Sincronizando sólo fórmula Anita {$una} vía ApiAnita…");
                $ret = $sync->sincronizarUnaDesdeApi($una, $usuarioId);
            } elseif ($modo === 'lote') {
                $total = (int) Formula_Articulo::query()
                    ->whereNotNull('anita_stkcm_formula')
                    ->where('anita_stkcm_formula', '>', 0)
                    ->count();

                if ($total === 0) {
                    $this->warn('No hay fórmulas con anita_stkcm_formula > 0 cargadas en el ERP. Use --modo=bulk o cargue fórmulas primero.');

                    return self::SUCCESS;
                }

                $this->info("Re-sincronización individual de {$total} fórmula(s) ya cargada(s) en ERP…");
                $bar = $this->output->createProgressBar($total);
                $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%  Anita=%message%');
                $bar->setMessage('-');
                $bar->start();

                $ret = $sync->sincronizarTodasUnaPorUnaDesdeApi(
                    $usuarioId,
                    function (int $anitaF, int $erpId, ?array $r, ?string $err) use ($bar) {
                        $bar->setMessage((string) $anitaF);
                        $bar->advance();
                    }
                );

                $bar->finish();
                $this->newLine();
                if (isset($ret['procesadas'], $ret['fallidas'])) {
                    $this->info("Procesadas OK: {$ret['procesadas']} — fallidas: {$ret['fallidas']}.");
                }
            } elseif ($fuente === 'api') {
                $this->info('Leyendo stkcmae/stkcmov vía ApiAnita (bulk)…');
                $ret = $sync->sincronizarDesdeApi($usuarioId);
            } else {
                $mae = (string) $this->option('stkcmae');
                $mov = (string) $this->option('stkcmov');
                $this->info("Leyendo archivos:\n  {$mae}\n  {$mov}");
                $ret = $sync->sincronizarDesdeArchivos($mae, $mov, $usuarioId);
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Fórmulas procesadas: {$ret['formulas']}; líneas hijo: {$ret['lineas']}.");
        foreach ($ret['advertencias'] as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }
}
