<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\ModuloAvisoEventoSuscripcion;
use App\Models\Configuracion\ModuloAvisoTipo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Motor liviano de eventos → ModuloAviso.
 * Suscripciones en modulo_aviso_evento_suscripcion (+ condiciones JSON).
 */
class ModuloAvisoEventBus
{
    public function __construct(
        private ModuloAvisoService $moduloAvisoService,
    ) {}

    /**
     * @param  array<string, mixed>  $contexto  entity_id, empresa_id, opciones para enviar()
     */
    public function emit(string $evento, array $contexto = []): int
    {
        $evento = trim($evento);
        if ($evento === '') {
            return 0;
        }

        $disparados = 0;

        if (Schema::hasTable('modulo_aviso_evento_suscripcion')) {
            $subs = ModuloAvisoEventoSuscripcion::query()
                ->where('evento', $evento)
                ->where('activo', true)
                ->with('tipo')
                ->get();

            foreach ($subs as $sub) {
                /** @var ModuloAvisoTipo|null $tipo */
                $tipo = $sub->tipo;
                if (! $tipo || ! $tipo->activo) {
                    continue;
                }
                if (! \App\Support\Configuracion\AvisoReglaCondicionSupport::cumple($sub->condiciones, $contexto)) {
                    continue;
                }

                $entityId = (int) ($contexto['entity_id'] ?? 0);
                $opciones = is_array($contexto['opciones'] ?? null) ? $contexto['opciones'] : $contexto;
                unset($opciones['entity_id'], $opciones['opciones']);

                try {
                    $this->moduloAvisoService->enviar($tipo->modulo, $tipo->codigo, $entityId, $opciones);
                    $disparados++;
                } catch (\Throwable $e) {
                    Log::error('ModuloAvisoEventBus: falló envío', [
                        'evento' => $evento,
                        'tipo' => $tipo->claveCompleta(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        try {
            $disparados += app(AvisoReglaMotorService::class)->evaluarEvento($evento, $contexto);
        } catch (\Throwable $e) {
            Log::error('ModuloAvisoEventBus: motor de reglas falló', [
                'evento' => $evento,
                'error' => $e->getMessage(),
            ]);
        }

        return $disparados;
    }
}
