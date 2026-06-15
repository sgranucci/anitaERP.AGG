<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Services\Ventas\Gastronomia\GastronomiaBackfillVentasAnitaErpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class GastronomiaBackfillVentasAnitaErp extends Command
{
    protected $signature = 'gastronomia:backfill-ventas-anita-erp
                            {--fecha-jornada=2026-06-14 : Fecha jornada (Y-m-d)}
                            {--empresas=1,2,3 : empresa_id separados por coma}
                            {--usuario= : usuario_id para altas}
                            {--dry-run : Solo simular importación}
                            {--solo-plan : Solo listar rangos a importar, sin ejecutar}';

    protected $description = 'Backfill Anita→ERP: importa ventas faltantes (huecos + solo Anita) detectadas por correlatividad';

    public function handle(GastronomiaBackfillVentasAnitaErpService $service): int
    {
        $fechaJornada = trim((string) $this->option('fecha-jornada'));
        if ($fechaJornada === '') {
            $this->error('Indique --fecha-jornada.');

            return self::FAILURE;
        }

        $usuarioId = (int) ($this->option('usuario') ?: Usuario::query()->orderBy('id')->value('id') ?? 1);
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido para importación.');

            return self::FAILURE;
        }

        $empresaIds = $this->parseEmpresas((string) $this->option('empresas'));
        $dryRun = (bool) $this->option('dry-run');
        $soloPlan = (bool) $this->option('solo-plan');

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line("Jornada {$fechaJornada}".($dryRun ? ' [dry-run]' : ''));

        if ($soloPlan) {
            try {
                $plan = $service->planificar($fechaJornada, $empresaIds);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            $this->mostrarPlan($plan['rangos'] ?? [], $plan['correlatividad_previa'] ?? []);

            return self::SUCCESS;
        }

        try {
            $resultado = $service->ejecutar($fechaJornada, $empresaIds, $usuarioId, $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $prev = $resultado['correlatividad_previa'] ?? [];
        $this->table(['Concepto', 'Valor'], [
            ['Solo Anita (previo)', (string) ($prev['solo_anita_en_rango'] ?? 0)],
            ['Huecos correlativos (previo)', (string) ($prev['huecos_corr_erp'] ?? 0)],
            ['Rangos a importar', (string) count($resultado['rangos'] ?? [])],
            ['Importados', (string) ($resultado['importados'] ?? 0)],
            ['Omitidos (ya en ERP)', (string) ($resultado['omitidos'] ?? 0)],
            ['Errores', (string) count($resultado['errores'] ?? [])],
        ]);

        $this->mostrarPlan($resultado['rangos'] ?? [], $prev);

        foreach ($resultado['errores'] as $err) {
            $this->warn($err);
        }

        return ($resultado['errores'] ?? []) === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<int> */
    private function parseEmpresas(string $raw): array
    {
        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $id = (int) trim($part);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** @param  list<array<string, mixed>>  $rangos */
    /** @param  array<string, mixed>  $prev */
    private function mostrarPlan(array $rangos, array $prev): void
    {
        if ($rangos === []) {
            $this->info('Sin rangos para importar.');

            return;
        }

        $this->newLine();
        $this->comment('Rangos Anita → ERP');
        $this->table(['PV', 'Emp', 'Suc', 'Anita', 'Rango nro', 'Cant', 'PC'], array_map(static fn (array $r) => [
            $r['pv_codigo'] ?? '',
            $r['empresa_id'] ?? '',
            $r['sucursal'] ?? '',
            $r['tipo_anita'] ?? 'FAC',
            ($r['desde'] ?? '').'–'.($r['hasta'] ?? ''),
            $r['cantidad'] ?? '',
            $r['identificador_pc'] ?? '',
        ], $rangos));

        $descartados = [];
        $vistos = [];
        foreach ($rangos as $r) {
            foreach ($r['descartados'] ?? [] as $nro) {
                $clave = ($r['pv_codigo'] ?? '').'|'.($r['empresa_id'] ?? '').'|'.$nro;
                if (isset($vistos[$clave])) {
                    continue;
                }
                $vistos[$clave] = true;
                $descartados[] = ($r['pv_codigo'] ?? '').' emp='.($r['empresa_id'] ?? '').' nro='.$nro.' (sin FAK/FAC de esa empresa en Anita)';
            }
        }
        if ($descartados !== []) {
            $this->newLine();
            $this->comment('Descartados (hueco ERP sin cabecera Anita de esa empresa)');
            foreach ($descartados as $linea) {
                $this->line($linea);
            }
        }
    }
}
