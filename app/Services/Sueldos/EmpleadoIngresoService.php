<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Ingreso_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Support\Sueldos\EmpleadoEstados;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Baja y reincorporación con historia (Anita emping).
 */
class EmpleadoIngresoService
{
    public function darDeBaja(
        Empleado_Sueldos $empleado,
        string $fechaEgreso,
        ?int $motivoegresoId,
        ?string $comentario
    ): Empleado_Sueldos {
        if (EmpleadoEstados::esBaja($empleado->estado)) {
            throw new InvalidArgumentException('El empleado ya está de baja.');
        }

        return DB::transaction(function () use ($empleado, $fechaEgreso, $motivoegresoId, $comentario) {
            $abierto = Empleado_Ingreso_Sueldos::query()
                ->where('empleado_id', $empleado->id)
                ->whereNull('fecha_egreso')
                ->orderByDesc('fecha_ingreso')
                ->orderByDesc('id')
                ->first();

            if ($abierto) {
                $abierto->update([
                    'fecha_egreso' => $fechaEgreso,
                    'motivoegreso_id' => $motivoegresoId,
                    'comentario_baja' => $comentario,
                    'tipo_movimiento' => 'B',
                    'usuario_id' => Auth::id(),
                ]);
            } else {
                Empleado_Ingreso_Sueldos::create([
                    'empleado_id' => $empleado->id,
                    'fecha_ingreso' => $empleado->fecha_ingreso ?? $fechaEgreso,
                    'fecha_egreso' => $fechaEgreso,
                    'motivoegreso_id' => $motivoegresoId,
                    'comentario_baja' => $comentario,
                    'tipo_movimiento' => 'B',
                    'usuario_id' => Auth::id(),
                ]);
            }

            $empleado->update([
                'estado' => EmpleadoEstados::BAJA,
                'fecha_egreso' => $fechaEgreso,
                'motivoegreso_id' => $motivoegresoId,
                'comentario_baja' => $comentario,
            ]);

            return $empleado->fresh(['ingresos.motivoegreso']);
        });
    }

    public function reincorporar(Empleado_Sueldos $empleado, string $fechaIngreso): Empleado_Sueldos
    {
        if (! EmpleadoEstados::esBaja($empleado->estado)) {
            throw new InvalidArgumentException('Solo se puede reincorporar un empleado de baja.');
        }

        return DB::transaction(function () use ($empleado, $fechaIngreso) {
            $existe = Empleado_Ingreso_Sueldos::query()
                ->where('empleado_id', $empleado->id)
                ->whereDate('fecha_ingreso', $fechaIngreso)
                ->exists();

            if ($existe) {
                throw new InvalidArgumentException('Ya existe un período con esa fecha de ingreso.');
            }

            Empleado_Ingreso_Sueldos::create([
                'empleado_id' => $empleado->id,
                'fecha_ingreso' => $fechaIngreso,
                'fecha_egreso' => null,
                'tipo_movimiento' => 'I',
                'usuario_id' => Auth::id(),
            ]);

            $empleado->update([
                'estado' => EmpleadoEstados::ACTIVO,
                'fecha_ingreso' => $fechaIngreso,
                'fecha_egreso' => null,
                'motivoegreso_id' => null,
                'comentario_baja' => null,
            ]);

            return $empleado->fresh(['ingresos.motivoegreso']);
        });
    }

    public function autorizarAlta(Empleado_Sueldos $empleado, ?int $usuarioId = null): Empleado_Sueldos
    {
        if (! EmpleadoEstados::esProvisorio($empleado->estado)) {
            throw new InvalidArgumentException('El empleado no está en alta provisoria.');
        }

        $empleado->update([
            'estado' => EmpleadoEstados::ACTIVO,
            'usuario_autoriza_id' => $usuarioId ?? Auth::id(),
            'autorizado_at' => now(),
        ]);

        return $empleado->fresh();
    }
}
