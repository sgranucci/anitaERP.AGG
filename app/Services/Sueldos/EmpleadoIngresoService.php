<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Ingreso_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Support\Sueldos\EmpleadoBajaSupport;
use App\Support\Sueldos\EmpleadoEstados;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Alta provisoria, baja provisoria / definitiva y reincorporación (Anita emping).
 */
class EmpleadoIngresoService
{
    /**
     * Baja definitiva inmediata (comportamiento ERP histórico).
     */
    public function darDeBaja(
        Empleado_Sueldos $empleado,
        string $fechaEgreso,
        ?int $motivoegresoId,
        ?string $comentario
    ): Empleado_Sueldos {
        return $this->confirmarBajaDefinitiva($empleado, $fechaEgreso, $motivoegresoId, $comentario);
    }

    /**
     * Programar baja (Anita): guarda fecha/motivo, sigue Activo, historia BP.
     */
    public function programarBaja(
        Empleado_Sueldos $empleado,
        string $fechaEgreso,
        ?int $motivoegresoId,
        ?string $comentario
    ): Empleado_Sueldos {
        if (EmpleadoEstados::esBaja($empleado->estado)) {
            throw new InvalidArgumentException('El empleado ya está de baja definitiva.');
        }
        if (EmpleadoEstados::esProvisorio($empleado->estado)) {
            throw new InvalidArgumentException('Autorizá el alta provisoria antes de programar una baja.');
        }

        return DB::transaction(function () use ($empleado, $fechaEgreso, $motivoegresoId, $comentario) {
            Empleado_Ingreso_Sueldos::create([
                'empleado_id' => $empleado->id,
                'fecha_ingreso' => $empleado->fecha_ingreso ?? $fechaEgreso,
                'fecha_egreso' => $fechaEgreso,
                'motivoegreso_id' => $motivoegresoId,
                'comentario_baja' => $comentario,
                'tipo_movimiento' => EmpleadoBajaSupport::MOV_BAJA_PROVISORIA,
                'usuario_id' => Auth::id(),
            ]);

            $empleado->update([
                'estado' => EmpleadoEstados::ACTIVO,
                'fecha_egreso' => $fechaEgreso,
                'motivoegreso_id' => $motivoegresoId,
                'comentario_baja' => $comentario,
            ]);

            return $empleado->fresh(['ingresos.motivoegreso']);
        });
    }

    /**
     * Baja definitiva: estado B, cierra período abierto, historia B.
     */
    public function confirmarBajaDefinitiva(
        Empleado_Sueldos $empleado,
        string $fechaEgreso,
        ?int $motivoegresoId,
        ?string $comentario
    ): Empleado_Sueldos {
        if (EmpleadoEstados::esBaja($empleado->estado)) {
            throw new InvalidArgumentException('El empleado ya está de baja definitiva.');
        }

        return DB::transaction(function () use ($empleado, $fechaEgreso, $motivoegresoId, $comentario) {
            $abierto = Empleado_Ingreso_Sueldos::query()
                ->where('empleado_id', $empleado->id)
                ->whereNull('fecha_egreso')
                ->where('tipo_movimiento', EmpleadoBajaSupport::MOV_INGRESO)
                ->orderByDesc('fecha_ingreso')
                ->orderByDesc('id')
                ->first();

            if ($abierto) {
                $abierto->update([
                    'fecha_egreso' => $fechaEgreso,
                    'motivoegreso_id' => $motivoegresoId,
                    'comentario_baja' => $comentario,
                    'tipo_movimiento' => EmpleadoBajaSupport::MOV_BAJA_DEFINITIVA,
                    'usuario_id' => Auth::id(),
                ]);
            } else {
                Empleado_Ingreso_Sueldos::create([
                    'empleado_id' => $empleado->id,
                    'fecha_ingreso' => $empleado->fecha_ingreso ?? $fechaEgreso,
                    'fecha_egreso' => $fechaEgreso,
                    'motivoegreso_id' => $motivoegresoId,
                    'comentario_baja' => $comentario,
                    'tipo_movimiento' => EmpleadoBajaSupport::MOV_BAJA_DEFINITIVA,
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

    /**
     * Anula una baja provisoria: limpia egreso del legajo y deja rastro XA.
     */
    public function anularBajaProvisoria(Empleado_Sueldos $empleado, ?string $comentario = null): Empleado_Sueldos
    {
        if (! EmpleadoBajaSupport::esBajaProvisoria($empleado)) {
            throw new InvalidArgumentException('El empleado no tiene una baja provisoria para anular.');
        }

        return DB::transaction(function () use ($empleado, $comentario) {
            Empleado_Ingreso_Sueldos::create([
                'empleado_id' => $empleado->id,
                'fecha_ingreso' => $empleado->fecha_ingreso ?? now()->toDateString(),
                'fecha_egreso' => $empleado->fecha_egreso,
                'motivoegreso_id' => $empleado->motivoegreso_id,
                'comentario_baja' => $comentario ?: 'Anulación de baja provisoria',
                'tipo_movimiento' => EmpleadoBajaSupport::MOV_ANULA_PROVISORIA,
                'usuario_id' => Auth::id(),
            ]);

            $empleado->update([
                'estado' => EmpleadoEstados::ACTIVO,
                'fecha_egreso' => null,
                'motivoegreso_id' => null,
                'comentario_baja' => null,
            ]);

            return $empleado->fresh(['ingresos.motivoegreso']);
        });
    }

    public function reincorporar(Empleado_Sueldos $empleado, string $fechaIngreso): Empleado_Sueldos
    {
        if (! EmpleadoEstados::esBaja($empleado->estado)) {
            throw new InvalidArgumentException('Solo se puede reincorporar un empleado de baja definitiva.');
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
                'tipo_movimiento' => EmpleadoBajaSupport::MOV_INGRESO,
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
