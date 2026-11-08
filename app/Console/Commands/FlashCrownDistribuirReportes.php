<?php

namespace App\Console\Commands;

use App\Models\Caja\Flash\FlashCrownSuscripcion;
use App\Services\Caja\Flash\FlashCrownDistribucionService;
use App\Support\Caja\Flash\FlashCrownSuscripcionSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;

class FlashCrownDistribuirReportes extends Command
{
    protected $signature = 'flash-crown:distribuir-reportes
                            {--suscripcion= : ID de suscripción}
                            {--forzar : Ignora horario}
                            {--dry-run : No envía mail}';

    protected $description = 'Envía por mail el Flash Report Crown según suscripciones (solo días validados por defecto)';

    public function handle(
        FlashCrownSuscripcionSupport $support,
        FlashCrownDistribucionService $service,
    ): int {
        if (! EntornoEmpresaSupport::esCrown()) {
            $this->line('Flash Crown solo aplica en entorno CROWN.');

            return self::SUCCESS;
        }
        if (! (bool) config('caja.flash_reporte_crown.distribucion_habilitada', true)) {
            $this->line('Distribución Flash Report Crown deshabilitada.');

            return self::SUCCESS;
        }

        $id = $this->option('suscripcion') !== null ? (int) $this->option('suscripcion') : null;
        $forzar = (bool) $this->option('forzar');
        $dry = (bool) $this->option('dry-run');
        $vencidas = $support->vencidas(now(), $id, $forzar);

        $this->line(sprintf('Distribución Flash Crown | %d envío(s)%s', $vencidas->count(), $dry ? ' (dry-run)' : ''));

        $errores = 0;
        foreach ($vencidas as $suscripcion) {
            $resultado = $service->enviar($suscripcion, $dry);
            $etiqueta = '#'.$suscripcion->id.' '.$suscripcion->nombre;
            match ($resultado['estado']) {
                FlashCrownSuscripcion::ESTADO_OK => $this->line('  OK    '.$etiqueta.' — '.$resultado['mensaje']),
                FlashCrownSuscripcion::ESTADO_OMITIDA => $this->line('  OMIT  '.$etiqueta.' — '.$resultado['mensaje']),
                default => $this->error('  ERR   '.$etiqueta.' — '.$resultado['mensaje']),
            };
            if (! $dry) {
                $support->marcarResultado($suscripcion, $resultado['estado'], $resultado['mensaje']);
            }
            if ($resultado['estado'] === FlashCrownSuscripcion::ESTADO_ERROR) {
                $errores++;
            }
        }

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
