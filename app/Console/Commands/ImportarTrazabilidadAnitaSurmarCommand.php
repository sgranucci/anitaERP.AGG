<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Stock\Surmar\TrazabilidadSurmarAnitaImportService;
use App\Support\Stock\Surmar\TrazabilidadSurmarAnitaBridgeSupport as Bridge;
use Illuminate\Console\Command;

class ImportarTrazabilidadAnitaSurmarCommand extends Command
{
    protected $signature = 'stock:importar-trazabilidad-anita-surmar
                            {--usuario= : ID usuario}
                            {--paso=* : tipos|etiquetas|movimientos|vinculos|consumos|saldos (default: todos)}
                            {--dry-run : Contadores sin grabar}';

    protected $description = 'Importa trazabilidad Surmar desde Anita (t_comp, recepaper, stkmov, stkvaper, apcom). Una lectura por tabla.';

    public function handle(TrazabilidadSurmarAnitaImportService $service): int
    {
        $usuarioId = $this->option('usuario');
        $usuarioId = ($usuarioId !== null && $usuarioId !== '')
            ? (int) $usuarioId
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        $pasos = $this->option('paso');
        $pasos = is_array($pasos) ? array_values(array_filter($pasos)) : [];
        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Trazabilidad Surmar path=%s fecha_desde=%s fecha_hasta=%s pasos=%s%s',
            Bridge::pathSistema(),
            Bridge::fechaDesde(),
            Bridge::fechaHasta() ?? '∞',
            $pasos === [] ? 'todos' : implode(',', $pasos),
            $dryRun ? ' [DRY-RUN]' : ''
        ));

        try {
            $ret = $service->ejecutar($usuarioId, $dryRun, $pasos === [] ? null : $pasos);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach (['tipos', 'etiquetas', 'movimientos', 'vinculos', 'consumos', 'saldos'] as $paso) {
            if (! is_array($ret[$paso] ?? null)) {
                continue;
            }
            $this->newLine();
            $this->info('=== '.$paso.' ===');
            $rows = [];
            foreach ($ret[$paso] as $k => $v) {
                $rows[] = [$k, is_scalar($v) ? (string) $v : json_encode($v)];
            }
            $this->table(['Métrica', 'Valor'], $rows);
        }

        if ($dryRun) {
            $this->comment('Dry-run: no se grabó nada.');
        }

        return self::SUCCESS;
    }
}
