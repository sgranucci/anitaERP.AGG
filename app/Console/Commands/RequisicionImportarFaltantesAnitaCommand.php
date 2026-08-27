<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Compras\RequisicionImportarFaltantesDesdeAnitaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class RequisicionImportarFaltantesAnitaCommand extends Command
{
    protected $signature = 'requisicion:importar-faltantes-anita
                            {--anio=2026 : Año de reqm_fecha a importar}
                            {--usuario= : ID usuario ERP para creousuario_id}
                            {--dry-run : Solo informa, no escribe}
                            {--anita-todas : Reescribe penmp/penvp_requisicion en Anita para todas las OC vinculadas}
                            {--oc=* : Números de OC a reescribir en Anita (default 222441 222442 222443)}';

    protected $description = 'Importa requisiciones Anita faltantes en lote (2–3 lecturas) y vincula OC sin requisición';

    public function handle(RequisicionImportarFaltantesDesdeAnitaService $service): int
    {
        $anio = (int) $this->option('anio');
        $dryRun = (bool) $this->option('dry-run');
        $usuarioOpt = $this->option('usuario');
        $usuarioId = ($usuarioOpt !== null && $usuarioOpt !== '')
            ? (int) $usuarioOpt
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error("No se pudo autenticar usuario id {$usuarioId}.");

            return self::FAILURE;
        }

        $numerosOcAnita = null;
        if (! $this->option('anita-todas')) {
            $ocOpt = $this->option('oc');
            $numerosOcAnita = is_array($ocOpt) && $ocOpt !== []
                ? array_values(array_filter(array_map('intval', $ocOpt)))
                : [222441, 222442, 222443];
        }

        $this->info(sprintf(
            'Año %d | usuario %d | %s | Anita OC: %s',
            $anio,
            $usuarioId,
            $dryRun ? 'SIMULACIÓN' : 'escritura',
            $numerosOcAnita === null ? 'todas las vinculadas' : implode(', ', $numerosOcAnita)
        ));

        try {
            $stats = $service->ejecutar($anio, $usuarioId, $dryRun, $numerosOcAnita);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['Lecturas bridge', (string) $stats['lecturas_bridge']],
            ['Cabeceras Anita', (string) $stats['anita_cabeceras']],
            ['Líneas Anita', (string) $stats['anita_lineas']],
            ['Faltantes en ERP', (string) $stats['faltantes']],
            ['Importadas', (string) $stats['importadas']],
            ['Req. con líneas completadas', (string) ($stats['lineas_completadas'] ?? 0)],
            ['OC vinculadas ERP', (string) $stats['oc_vinculadas']],
            ['OC escritas en Anita', (string) $stats['oc_anita']],
            ['Escrituras bridge', (string) $stats['escrituras_bridge']],
            ['Errores import', (string) count($stats['errores_import'])],
            ['Errores OC', (string) count($stats['errores_oc'])],
        ]);

        foreach (array_slice($stats['errores_import'], 0, 20) as $err) {
            $this->warn($err);
        }
        foreach (array_slice($stats['errores_oc'], 0, 20) as $err) {
            $this->warn($err);
        }

        return ($stats['errores_import'] !== [] || $stats['errores_oc'] !== [])
            ? self::FAILURE
            : self::SUCCESS;
    }
}
