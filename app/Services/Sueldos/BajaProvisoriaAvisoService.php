<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Sueldos;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Sueldos\EmpleadoBajaSupport;
use App\Support\Sueldos\EmpleadoEstados;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Robot: lista bajas provisorias pendientes y encola aviso (digest) a destinatarios.
 */
class BajaProvisoriaAvisoService
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    /**
     * @return list<array{empresa:string, legajo:int|string, nombre:string, fecha_egreso:string, vencida:bool, id:int}>
     */
    public function pendientes(): array
    {
        $hoy = Carbon::today()->toDateString();
        $filas = [];
        $q = Empleado_Sueldos::query()
            ->with(['empresa:id,nombre'])
            ->where('estado', EmpleadoEstados::ACTIVO)
            ->whereNotNull('fecha_egreso')
            ->orderBy('empresa_id')
            ->orderBy('legajo');

        foreach ($q->cursor() as $emp) {
            if (! EmpleadoBajaSupport::esBajaProvisoria($emp)) {
                continue;
            }
            $egreso = Carbon::parse($emp->fecha_egreso)->toDateString();
            $filas[] = [
                'id' => (int) $emp->id,
                'empresa' => (string) (optional($emp->empresa)->nombre ?? '—'),
                'legajo' => $emp->legajo,
                'nombre' => (string) ($emp->nombre ?? ''),
                'fecha_egreso' => Carbon::parse($egreso)->format('d/m/Y'),
                'vencida' => $egreso <= $hoy,
            ];
        }

        return $filas;
    }

    /**
     * @param  list<array{empresa:string, legajo:int|string, nombre:string, fecha_egreso:string, vencida:bool, id:int}>  $filas
     */
    public function encolar(array $filas): void
    {
        if ($filas === []) {
            return;
        }

        $this->moduloAvisoService->enviar('sueldos', 'empleado_baja_provisoria', 0, [
            'lineas' => $filas,
        ]);

        try {
            app(\App\Services\Configuracion\ModuloAvisoEventBus::class)->emit('sueldos.empleado.baja_provisoria_pendiente', [
                'cantidad' => count($filas),
            ]);
        } catch (\Throwable $e) {
            Log::info('BajaProvisoriaAviso: event bus', ['error' => $e->getMessage()]);
        }
    }
}
