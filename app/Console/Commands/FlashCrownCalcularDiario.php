<?php

namespace App\Console\Commands;

use App\Models\Configuracion\Sala;
use App\Services\Caja\Flash\FlashCrownService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FlashCrownCalcularDiario extends Command
{
    protected $signature = 'flash-crown:calcular-diario
                            {--fecha= : Y-m-d (default ayer)}
                            {--sala= : ID de sala}
                            {--forzar : Recalcula aunque esté validado}
                            {--dry-run : No persiste}';

    protected $description = 'Calcula/persiste el Flash Crown online de la jornada (default: ayer, todas las salas)';

    public function handle(FlashCrownService $service): int
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            $this->line('Flash Crown solo aplica en entorno CROWN.');

            return self::SUCCESS;
        }
        if (! (bool) config('caja.flash_crown_calculo_diario.habilitado', true)) {
            $this->line('Cálculo diario Flash Crown deshabilitado.');

            return self::SUCCESS;
        }

        $fecha = $this->option('fecha')
            ? Carbon::parse((string) $this->option('fecha'))->toDateString()
            : now()->subDay()->toDateString();
        $dry = (bool) $this->option('dry-run');
        $forzar = (bool) $this->option('forzar');
        $salaOpt = $this->option('sala');

        $salas = Sala::query()
            ->where(function ($q) {
                $q->whereNull('codigo')->orWhere('codigo', '!=', 'AC');
            })
            ->when($salaOpt, fn ($q) => $q->whereKey((int) $salaOpt))
            ->orderBy('codigo')
            ->get();

        $this->line(sprintf('Flash Crown diario %s | %d sala(s)%s', $fecha, $salas->count(), $dry ? ' (dry-run)' : ''));

        foreach ($salas as $sala) {
            try {
                $flash = $service->sincronizar((int) $sala->id, $fecha, [], ! $dry, $forzar);
                $this->line(sprintf(
                    '  %s %s → id=%s gaming=%.2f combined=%.2f %s',
                    $sala->codigo,
                    $sala->nombre,
                    $flash->id ?: '—',
                    $flash->gaming,
                    $flash->combined,
                    $flash->estaValidado() ? 'VALIDADO' : ''
                ));
            } catch (\Throwable $e) {
                $this->error('  '.$sala->codigo.': '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
