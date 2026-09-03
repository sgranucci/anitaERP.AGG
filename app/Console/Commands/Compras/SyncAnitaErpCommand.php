<?php

namespace App\Console\Commands\Compras;

use App\Models\Seguridad\Usuario;
use App\Services\Compras\OrdencompraAnitaSyncService;
use App\Services\Compras\RequisicionImportarFaltantesDesdeAnitaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class SyncAnitaErpCommand extends Command
{
    protected $signature = 'compras:sync-anita-erp
                            {--anio=2026 : Año (reqm_fecha / penmp_fecha)}
                            {--usuario= : ID usuario ERP fallback}
                            {--solo-req : Solo requisiciones}
                            {--solo-oc : Solo órdenes de compra}
                            {--dry-run : Solo informa requisiciones (OC no tiene simulación completa)}';

    protected $description = 'Trae requisiciones  (con aprobcomp) y luego OC Anita → ERP del año, sin pisar documentos nativos ERP';

    public function handle(
        RequisicionImportarFaltantesDesdeAnitaService $reqService,
        OrdencompraAnitaSyncService $ocService,
    ): int {
        $anio = (int) $this->option('anio');
        $dryRun = (bool) $this->option('dry-run');
        $soloReq = (bool) $this->option('solo-req');
        $soloOc = (bool) $this->option('solo-oc');
        $usuarioOpt = $this->option('usuario');
        $usuarioId = ($usuarioOpt !== null && $usuarioOpt !== '')
            ? (int) $usuarioOpt
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error("No se pudo autenticar usuario id {$usuarioId}.");

            return self::FAILURE;
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $this->info(sprintf(
            'Sync Anita → ERP año %d | usuario %d | %s',
            $anio,
            $usuarioId,
            $dryRun ? 'SIMULACIÓN' : 'escritura'
        ));

        $errores = 0;

        if (! $soloOc) {
            $this->info('1) Requisiciones + autorizaciones (aprobcomp)…');
            try {
                $statsReq = $reqService->ejecutar($anio, $usuarioId, $dryRun, []);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $this->table(['Métrica REQ', 'Cantidad'], [
                ['Cabeceras Anita', (string) $statsReq['anita_cabeceras']],
                ['Líneas Anita', (string) $statsReq['anita_lineas']],
                ['Faltantes', (string) $statsReq['faltantes']],
                ['Importadas', (string) $statsReq['importadas']],
                ['Alineadas (nacidas Anita)', (string) ($statsReq['alineadas'] ?? 0)],
                ['ERP nativas omitidas', (string) ($statsReq['omitidas_erp_nativas'] ?? 0)],
                ['Líneas completadas', (string) ($statsReq['lineas_completadas'] ?? 0)],
                ['OC vinculadas', (string) $statsReq['oc_vinculadas']],
                ['Errores', (string) count($statsReq['errores_import'])],
            ]);
            foreach (array_slice($statsReq['errores_import'], 0, 30) as $err) {
                $this->warn($err);
            }
            $errores += count($statsReq['errores_import']);
        }

        if (! $soloReq && ! $dryRun) {
            $this->info('2) Órdenes de compra…');
            $desde = $anio * 10000 + 101;
            $hasta = $anio * 10000 + 1231;
            try {
                $statsOc = $ocService->sincronizarConAnita(
                    $usuarioId,
                    $desde,
                    $hasta,
                    function (int $i, int $total, int $nro) {
                        if ($i === 1 || $i % 50 === 0 || $i === $total) {
                            $this->line("   OC {$i}/{$total} (nro {$nro})");
                        }
                    }
                );
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $this->table(['Métrica OC', 'Cantidad'], [
                ['En Anita (año)', (string) $statsOc['en_anita']],
                ['Importadas', (string) $statsOc['importados']],
                ['Alineadas', (string) ($statsOc['alineadas'] ?? 0)],
                ['Líneas completadas', (string) ($statsOc['lineas_completadas'] ?? 0)],
                ['Omitidas', (string) $statsOc['omitidos']],
                ['ERP nativas omitidas', (string) ($statsOc['omitidos_erp_nativas'] ?? 0)],
                ['Errores', (string) count($statsOc['errores'])],
            ]);
            foreach (array_slice($statsOc['errores'], 0, 40) as $err) {
                $this->warn($err);
            }
            $errores += count($statsOc['errores']);
        } elseif ($dryRun && ! $soloReq) {
            $this->warn('Dry-run: no se importan OC (el bridge Anita no tiene modo simulación por documento).');
        }

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
