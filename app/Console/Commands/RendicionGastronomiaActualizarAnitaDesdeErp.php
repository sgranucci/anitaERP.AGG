<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Caja\RendicionGastronomiaActualizarTodasDesdeErpAnitaService;
use Illuminate\Console\Command;

class RendicionGastronomiaActualizarAnitaDesdeErp extends Command
{
    protected $signature = 'rendicion-gastronomia:actualizar-anita-desde-erp
                            {--empresas=1,2,3 : IDs empresa separados por coma}
                            {--dry-run : Simula sin escribir en Anita}
                            {--sin-post-cierre : No marcar rendg_estado=F en CIERRE-WAITRY}';

    protected $description = 'Re-sincroniza rendgastro/rendvalor desde rendicion_gastronomia_caja (rendg_estado=F) y post-cierre Waitry';

    public function handle(RendicionGastronomiaActualizarTodasDesdeErpAnitaService $service): int
    {
        $empresas = array_values(array_filter(array_map('intval', explode(',', (string) $this->option('empresas')))));
        $dryRun = (bool) $this->option('dry-run');

        if ($empresas === []) {
            $this->error('Indique al menos una empresa en --empresas');

            return self::FAILURE;
        }

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Empresas %s%s%s',
            implode(', ', $empresas),
            $dryRun ? ' | MODO SIMULACIÓN' : '',
            $this->option('sin-post-cierre') ? ' | sin post-cierre' : '',
        ));

        try {
            $informe = $service->ejecutar(
                $empresas,
                $dryRun,
                ! (bool) $this->option('sin-post-cierre'),
                fn (string $msg) => $this->line('  '.$msg),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rt = $informe['rendiciones_turno'] ?? [];
        $this->table(
            ['Concepto', 'Cantidad'],
            [
                ['Rendiciones turno ERP (nro_oper>0)', (string) ($rt['total'] ?? 0)],
                [$dryRun ? 'Simuladas' : 'Actualizadas en Anita', (string) ($rt['actualizadas'] ?? 0)],
                ['Insertadas (faltaban cabecera)', (string) ($rt['insertadas'] ?? 0)],
                ['Omitidas tipo jornada', (string) ($rt['omitidas_jornada'] ?? 0)],
                ['Errores turno', (string) count($rt['errores'] ?? [])],
            ],
        );

        $pc = $informe['post_cierre'] ?? [];
        $this->table(
            ['Post-cierre CIERRE-WAITRY', 'Cantidad'],
            [
                ['Cabeceras', (string) ($pc['total'] ?? 0)],
                [$dryRun ? 'Simuladas estado F' : 'rendg_estado=F', (string) ($pc['estado_f'] ?? 0)],
                ['Errores', (string) count($pc['errores'] ?? [])],
            ],
        );

        foreach (array_merge($rt['errores'] ?? [], $pc['errores'] ?? []) as $err) {
            $this->error(json_encode($err, JSON_UNESCAPED_UNICODE));
        }

        if (($rt['errores'] ?? []) !== [] || ($pc['errores'] ?? []) !== []) {
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->comment('Simulación lista. Ejecute sin --dry-run para aplicar.');
        } else {
            $this->info('Actualización rendgastro desde ERP completada.');
        }

        return self::SUCCESS;
    }
}
