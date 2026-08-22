<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Sancion_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Novedad_Sueldos;
use App\Models\Sueldos\Tipo_Sancion_Sueldos;
use App\Support\Sueldos\EmpleadoSancionSupport;
use App\Support\Sueldos\NovedadSueldosCatalogo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Genera / actualiza / anula la novedad de liquidación desde el expediente.
 * Solo si el tipo tiene genera_novedad + concepto_id. Idempotente vía novedad.sancion_id.
 */
class SancionNovedadSyncService
{
    public function sincronizar(Empleado_Sancion_Sueldos $sancion): ?Novedad_Sueldos
    {
        if (! Schema::hasColumn('novedad_sueldos', 'sancion_id')) {
            return null;
        }

        $sancion->loadMissing(['tipo.concepto', 'empleado']);
        $tipo = $sancion->tipo;
        $empleado = $sancion->empleado;

        if (! $tipo instanceof Tipo_Sancion_Sueldos || ! $empleado instanceof Empleado_Sueldos) {
            return null;
        }

        $conceptoId = (int) ($tipo->concepto_id ?? 0);
        $debeGenerar = $tipo->genera_novedad
            && $conceptoId > 0
            && EmpleadoSancionSupport::generaNovedad((string) $sancion->estado)
            && ((int) $sancion->cant_dias > 0 || (float) $sancion->importe_perdida > 0);

        $existente = Novedad_Sueldos::query()->where('sancion_id', $sancion->id)->first();

        if (! $debeGenerar) {
            if ($existente) {
                $existente->update([
                    'estado' => NovedadSueldosCatalogo::ESTADO_ANULADA,
                    'observacion' => $this->observacion($sancion, 'Anulada por estado/tipo sin concepto'),
                ]);
            }

            return $existente;
        }

        $concepto = $tipo->concepto;
        $codigo = (int) ($concepto->codigo ?? 0);
        if ($codigo <= 0) {
            return $existente;
        }

        $desde = $sancion->fecha_desde
            ? Carbon::parse($sancion->fecha_desde)->startOfDay()
            : Carbon::parse($sancion->fecha_hecho)->startOfDay();
        $hasta = $sancion->fecha_hasta
            ? Carbon::parse($sancion->fecha_hasta)->startOfDay()
            : $desde->copy();

        $payload = [
            'empresa_id' => (int) $empleado->empresa_id,
            'liquidacion_id' => null,
            'empleado_id' => (int) $empleado->id,
            'concepto_id' => $conceptoId,
            'concepto_codigo' => $codigo,
            'valor1' => (float) $sancion->cant_dias,
            'valor2' => (float) $sancion->importe_perdida,
            'estado' => NovedadSueldosCatalogo::ESTADO_PENDIENTE,
            'fecha_desde' => $desde->toDateString(),
            'fecha_hasta' => $hasta->toDateString(),
            'periodo' => (int) $desde->format('Ym'),
            'origen' => NovedadSueldosCatalogo::ORIGEN_SANCION,
            'sancion_id' => (int) $sancion->id,
            'usuario_id' => $sancion->usuario_id ? (int) $sancion->usuario_id : null,
            'observacion' => $this->observacion($sancion),
        ];

        if ($existente) {
            $existente->update($payload);

            return $existente->fresh();
        }

        return Novedad_Sueldos::create($payload);
    }

    public function anularPorSancion(int $sancionId): void
    {
        if (! Schema::hasColumn('novedad_sueldos', 'sancion_id') || $sancionId <= 0) {
            return;
        }

        Novedad_Sueldos::query()
            ->where('sancion_id', $sancionId)
            ->where('estado', '!=', NovedadSueldosCatalogo::ESTADO_ANULADA)
            ->update([
                'estado' => NovedadSueldosCatalogo::ESTADO_ANULADA,
                'observacion' => 'Anulada: sanción eliminada #'.$sancionId,
            ]);
    }

    private function observacion(Empleado_Sancion_Sueldos $sancion, ?string $extra = null): string
    {
        $tipo = optional($sancion->tipo)->nombre ?? 'Sanción';
        $txt = sprintf(
            'Desde sanción #%d · %s · hecho %s · %s días',
            (int) $sancion->id,
            $tipo,
            optional($sancion->fecha_hecho)->format('d/m/Y') ?? '',
            (int) $sancion->cant_dias
        );

        return $extra ? $txt.' · '.$extra : $txt;
    }
}
