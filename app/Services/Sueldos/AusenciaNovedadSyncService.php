<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Ausencia_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Novedad_Sueldos;
use App\Models\Sueldos\Tipo_Ausencia_Sueldos;
use App\Support\Sueldos\NovedadSueldosCatalogo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Genera / actualiza / anula la novedad de liquidación desde el ledger de ausencias.
 * Solo si el tipo tiene concepto_id. Idempotente vía novedad.ausencia_id.
 */
class AusenciaNovedadSyncService
{
    /** Estados del ledger que generan novedad activa. */
    public const ESTADOS_GENERAN = ['aprobada', 'tomada', 'liquidada', 'planificada'];

    public function sincronizar(Empleado_Ausencia_Sueldos $ausencia): ?Novedad_Sueldos
    {
        if (! Schema::hasColumn('novedad_sueldos', 'ausencia_id')) {
            return null;
        }

        $ausencia->loadMissing(['tipo.concepto', 'empleado']);
        $tipo = $ausencia->tipo;
        $empleado = $ausencia->empleado;

        if (! $tipo instanceof Tipo_Ausencia_Sueldos || ! $empleado instanceof Empleado_Sueldos) {
            return null;
        }

        $conceptoId = (int) ($tipo->concepto_id ?? 0);
        $debeGenerar = $conceptoId > 0
            && in_array((string) $ausencia->estado, self::ESTADOS_GENERAN, true);

        $existente = Novedad_Sueldos::query()->where('ausencia_id', $ausencia->id)->first();

        if (! $debeGenerar) {
            if ($existente) {
                $existente->update([
                    'estado' => NovedadSueldosCatalogo::ESTADO_ANULADA,
                    'observacion' => $this->observacion($ausencia, 'Anulada por estado/tipo sin concepto'),
                ]);
            }

            return $existente;
        }

        $concepto = $tipo->concepto;
        $codigo = (int) ($concepto->codigo ?? 0);
        if ($codigo <= 0) {
            return $existente;
        }

        $desde = Carbon::parse($ausencia->fecha_desde)->startOfDay();
        $hasta = Carbon::parse($ausencia->fecha_hasta)->startOfDay();
        $periodo = (int) $desde->format('Ym');

        $payload = [
            'empresa_id' => (int) $empleado->empresa_id,
            'liquidacion_id' => $ausencia->liquidacion_id ? (int) $ausencia->liquidacion_id : null,
            'empleado_id' => (int) $empleado->id,
            'concepto_id' => $conceptoId,
            'concepto_codigo' => $codigo,
            'valor1' => (float) $ausencia->dias,
            'valor2' => 0,
            'estado' => NovedadSueldosCatalogo::ESTADO_PENDIENTE,
            'fecha_desde' => $desde->toDateString(),
            'fecha_hasta' => $hasta->toDateString(),
            'periodo' => $periodo,
            'origen' => NovedadSueldosCatalogo::ORIGEN_AUSENCIA,
            'ausencia_id' => (int) $ausencia->id,
            'usuario_id' => $ausencia->usuario_id ? (int) $ausencia->usuario_id : null,
            'observacion' => $this->observacion($ausencia),
        ];

        if ($existente) {
            $existente->update($payload);

            return $existente->fresh();
        }

        return Novedad_Sueldos::create($payload);
    }

    public function anularPorAusencia(int $ausenciaId): void
    {
        if (! Schema::hasColumn('novedad_sueldos', 'ausencia_id') || $ausenciaId <= 0) {
            return;
        }

        Novedad_Sueldos::query()
            ->where('ausencia_id', $ausenciaId)
            ->where('estado', '!=', NovedadSueldosCatalogo::ESTADO_ANULADA)
            ->update([
                'estado' => NovedadSueldosCatalogo::ESTADO_ANULADA,
                'observacion' => 'Anulada: ausencia eliminada #'.$ausenciaId,
            ]);
    }

    private function observacion(Empleado_Ausencia_Sueldos $ausencia, ?string $extra = null): string
    {
        $tipo = optional($ausencia->tipo)->nombre ?? 'Ausencia';
        $txt = sprintf(
            'Desde ausencia #%d · %s · %s a %s · %s días',
            (int) $ausencia->id,
            $tipo,
            Carbon::parse($ausencia->fecha_desde)->format('d/m/Y'),
            Carbon::parse($ausencia->fecha_hasta)->format('d/m/Y'),
            rtrim(rtrim(number_format((float) $ausencia->dias, 2, '.', ''), '0'), '.')
        );

        return $extra ? $txt.' · '.$extra : $txt;
    }
}
