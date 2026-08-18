<?php

namespace App\Console\Commands;

use App\Services\Caja\Flash\FlashCajaCalculoDiarioService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FlashCajaCalcularDiario extends Command
{
    protected $signature = 'flash:calcular-diario
                            {--fecha= : Fecha de jornada Y-m-d (default: ayer, jornada cerrada)}
                            {--empresas= : IDs empresa separados por coma (default: config 1,2,3)}
                            {--dry-run : No persiste; informa qué haría}
                            {--forzar : Recalcula aunque ya exista flash (incluye carga de usuario)}';

    protected $description = 'Calcula el flash de la jornada cerrada (ayer). Omite la empresa si un usuario ya lo cargó en el ABM.';

    public function handle(FlashCajaCalculoDiarioService $service): int
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $fechaOpt = trim((string) ($this->option('fecha') ?? ''));
        $fecha = $fechaOpt !== '' ? $fechaOpt : Carbon::yesterday()->toDateString();
        try {
            $fecha = Carbon::parse($fecha)->toDateString();
        } catch (\Throwable $e) {
            $this->error('Fecha inválida: '.$fechaOpt);

            return self::FAILURE;
        }

        $empresas = $this->resolverEmpresas();
        if ($empresas === []) {
            $this->error('Indique al menos una empresa en --empresas o FLASH_CAJA_CALCULO_DIARIO_EMPRESAS_IDS');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $forzar = (bool) $this->option('forzar');

        $this->line(sprintf(
            'Flash diario %s | empresas %s%s%s',
            $fecha,
            implode(',', $empresas),
            $dryRun ? ' | DRY-RUN' : '',
            $forzar ? ' | FORZAR' : '',
        ));

        $informe = $service->ejecutar($fecha, $empresas, $dryRun, $forzar);

        foreach ($informe['empresas'] as $fila) {
            $estado = strtoupper((string) ($fila['estado'] ?? ''));
            $linea = sprintf(
                '  Empresa %d (%s) — %s — %s',
                (int) ($fila['empresa_id'] ?? 0),
                (string) ($fila['empresa_nombre'] ?? '?'),
                $estado,
                (string) ($fila['mensaje'] ?? ''),
            );
            if (($fila['estado'] ?? '') === FlashCajaCalculoDiarioService::ESTADO_ERROR) {
                $this->error($linea);
            } elseif (str_starts_with((string) ($fila['estado'] ?? ''), 'omitido')) {
                $this->comment($linea);
            } else {
                $this->line($linea);
            }
        }

        $resumen = $informe['resumen'];
        $this->newLine();
        $this->info(sprintf(
            'Resumen: creados %d · actualizados %d · omitidos %d · errores %d',
            (int) $resumen['creados'],
            (int) $resumen['actualizados'],
            (int) $resumen['omitidos'],
            (int) $resumen['errores'],
        ));

        if ($dryRun) {
            $this->comment('Dry-run: no se persistió nada. Sin --dry-run graba las empresas pendientes.');
        }

        return ((int) $resumen['errores']) > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function resolverEmpresas(): array
    {
        $opt = trim((string) ($this->option('empresas') ?? ''));
        if ($opt !== '') {
            return array_values(array_filter(array_map(
                'intval',
                array_map('trim', explode(',', $opt))
            ), fn (int $id) => $id > 0));
        }

        return array_values(array_filter(array_map(
            'intval',
            (array) config('caja.flash_calculo_diario.empresas_ids', [1, 2, 3])
        ), fn (int $id) => $id > 0));
    }
}
