<?php

namespace App\Console\Commands\Contable;

use App\Models\Contable\AperturaPeriodoContable;
use App\Services\Configuracion\ModuloAvisoService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcesarAperturasPeriodoContable extends Command
{
    protected $signature = 'contable:procesar-aperturas-periodo';

    protected $description = 'Vence aperturas programadas, envía recordatorios y avisos de cierre de permiso temporal';

    public function handle(ModuloAvisoService $moduloAvisoService): int
    {
        $horasRecordatorio = max(1, (int) config('contable_cierre.recordatorio_horas_antes_vencimiento', 2));
        $umbralRecordatorio = now()->addHours($horasRecordatorio);

        $activas = AperturaPeriodoContable::query()
            ->where('estado', 'activa')
            ->whereNotNull('vence_en')
            ->get();

        $recordatorios = 0;
        $vencidas = 0;

        foreach ($activas as $apertura) {
            /** @var AperturaPeriodoContable $apertura */
            if ($apertura->vence_en === null) {
                continue;
            }

            if ($apertura->vence_en->lte(now())) {
                $apertura->update(['estado' => 'vencida']);
                if ($apertura->aviso_cierre_enviado_en === null) {
                    $moduloAvisoService->enviar('contable', 'apertura_periodo_cerrada', (int) $apertura->id);
                }
                $vencidas++;

                continue;
            }

            if ($apertura->recordatorio_vencimiento_enviado_en === null
                && $apertura->vence_en->lte($umbralRecordatorio)) {
                $moduloAvisoService->enviar('contable', 'apertura_periodo_recordatorio', (int) $apertura->id);
                $recordatorios++;
            }
        }

        $this->info(sprintf(
            'Aperturas procesadas: %d vencidas, %d recordatorios (umbral %s).',
            $vencidas,
            $recordatorios,
            Carbon::parse($umbralRecordatorio)->format('Y-m-d H:i')
        ));

        return self::SUCCESS;
    }
}
