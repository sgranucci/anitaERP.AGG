<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Caja\Flash\FlashCrownResultadoAnitaImportService;
use Illuminate\Console\Command;

/**
 * Mesas desde ventas.resultado + slots desde resulol (metodología 1/10) → flash_crown.
 */
class FlashCrownImportarResultadoAnita extends Command
{
    protected $signature = 'flash-crown:importar-resultado-anita
                            {--desde= : Fecha inicial Y-m-d}
                            {--hasta= : Fecha final Y-m-d}
                            {--sala= : Códigos de sala separados por coma (default: todas excepto CIP)}
                            {--incluir-cip : Incluye Cipolletti (por defecto se omite)}
                            {--ejecutar : Graba. Sin este flag solo muestra el plan}';

    protected $description = 'Carga flash_crown para facturar: mesas/ruleta de resultado + slot_r de resulol (online). Dry-run por defecto.';

    public function handle(FlashCrownResultadoAnitaImportService $import): int
    {
        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        if ($desde === '' || $hasta === '') {
            $this->error('Hacen falta --desde y --hasta.');

            return self::FAILURE;
        }

        $salaOpt = trim((string) $this->option('sala'));
        $codigos = $salaOpt === ''
            ? null
            : array_values(array_filter(array_map('trim', explode(',', $salaOpt))));

        $this->info(sprintf(
            'Plan resultado Anita → flash_crown | %s → %s | %s',
            $desde,
            $hasta,
            $this->option('ejecutar') ? 'EJECUTAR' : 'DRY-RUN'
        ));

        try {
            $plan = $import->plan(
                $desde,
                $hasta,
                $codigos,
                (bool) $this->option('incluir-cip'),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $tabla = [];
        foreach ($plan['salas'] as $bloque) {
            $mesa = 0.0;
            $slot = 0.0;
            foreach ($bloque['filas'] as $fila) {
                if ($fila['accion'] === 'omitir' && ($fila['motivo'] ?? '') === 'igual') {
                    continue;
                }
                if (in_array($fila['accion'], ['crear', 'actualizar'], true)) {
                    $mesa += (float) $fila['mesa'];
                    $slot += (float) $fila['slot'];
                }
            }
            $tabla[] = [
                $bloque['codigo'],
                $bloque['sucursal'],
                $bloque['ifx_server'],
                $bloque['crear'],
                $bloque['actualizar'],
                $bloque['omitir'],
                number_format($mesa, 2, ',', '.'),
                number_format($slot, 2, ',', '.'),
                $bloque['error'] ?? '',
            ];
        }

        $this->table(
            ['Sala', 'Suc', 'IFX', 'Crear', 'Act', 'Omitir', 'Mesa±', 'Slot±', 'Error'],
            $tabla
        );
        $this->info(sprintf(
            'Total crear=%d actualizar=%d omitir=%d | %s → %s',
            $plan['crear'],
            $plan['actualizar'],
            $plan['omitir'],
            $plan['desde'],
            $plan['hasta']
        ));
        if ($plan['errores'] !== []) {
            foreach ($plan['errores'] as $err) {
                $this->warn($err);
            }
        }

        if (! $this->option('ejecutar')) {
            $this->warn('Sin --ejecutar no se grabó nada.');
            $this->comment(
                'Para grabar: php artisan flash-crown:importar-resultado-anita'
                .' --desde='.$desde.' --hasta='.$hasta.' --ejecutar'
            );

            return self::SUCCESS;
        }

        $res = $import->aplicar($plan);
        $this->info(sprintf(
            'Grabado: altas=%d cambios=%d omitidos=%d',
            $res['altas'],
            $res['cambios'],
            $res['omitidos']
        ));

        return self::SUCCESS;
    }
}
