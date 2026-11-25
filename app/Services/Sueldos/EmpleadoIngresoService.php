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
 *
 * La tabla tiene unique (empleado_id, fecha_ingreso): no se inserta otra fila
 * con la misma fecha de ingreso; se actualiza el período abierto / BP.
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
            $payload = [
                'fecha_egreso' => $fechaEgreso,
                'motivoegreso_id' => $motivoegresoId,
                'comentario_baja' => $comentario,
                'tipo_movimiento' => EmpleadoBajaSupport::MOV_BAJA_PROVISORIA,
                'usuario_id' => Auth::id(),
            ];

            $periodo = $this->periodoAbiertoOProvisorio($empleado);
            if ($periodo) {
                $periodo->update($payload);
            } else {
                // Unique (empleado_id, fecha_ingreso): no insertar duplicado del período vigente.
                $fechaIngreso = $empleado->fecha_ingreso
                    ? $empleado->fecha_ingreso->format('Y-m-d')
                    : $fechaEgreso;
                $existente = Empleado_Ingreso_Sueldos::query()
                    ->where('empleado_id', $empleado->id)
                    ->whereDate('fecha_ingreso', $fechaIngreso)
                    ->first();
                if ($existente) {
                    $existente->update($payload);
                } else {
                    Empleado_Ingreso_Sueldos::create(array_merge($payload, [
                        'empleado_id' => $empleado->id,
                        'fecha_ingreso' => $fechaIngreso,
                    ]));
                }
            }

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
     * Baja definitiva: estado B, cierra período abierto / BP, historia B.
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
            $periodo = $this->periodoAbiertoOProvisorio($empleado);

            if ($periodo) {
                $periodo->update([
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
     * Corrige fecha / motivo / comentario de una baja provisoria vigente.
     */
    public function modificarBajaProvisoria(
        Empleado_Sueldos $empleado,
        string $fechaEgreso,
        ?int $motivoegresoId,
        ?string $comentario
    ): Empleado_Sueldos {
        if (! EmpleadoBajaSupport::esBajaProvisoria($empleado)) {
            throw new InvalidArgumentException('El empleado no tiene una baja provisoria para modificar.');
        }

        return DB::transaction(function () use ($empleado, $fechaEgreso, $motivoegresoId, $comentario) {
            $payload = [
                'fecha_egreso' => $fechaEgreso,
                'motivoegreso_id' => $motivoegresoId,
                'comentario_baja' => $comentario,
                'tipo_movimiento' => EmpleadoBajaSupport::MOV_BAJA_PROVISORIA,
                'usuario_id' => Auth::id(),
            ];

            $periodo = $this->periodoAbiertoOProvisorio($empleado);
            if ($periodo) {
                $periodo->update($payload);
            }

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
     * Anula una baja provisoria: reabre el período BP → I y deja rastro XA.
     */
    public function anularBajaProvisoria(Empleado_Sueldos $empleado, ?string $comentario = null): Empleado_Sueldos
    {
        if (! EmpleadoBajaSupport::esBajaProvisoria($empleado)) {
            throw new InvalidArgumentException('El empleado no tiene una baja provisoria para anular.');
        }

        return DB::transaction(function () use ($empleado, $comentario) {
            $fechaEgresoAnulada = $empleado->fecha_egreso
                ? $empleado->fecha_egreso->format('Y-m-d')
                : now()->toDateString();
            $motivoAnulado = $empleado->motivoegreso_id;

            $periodo = $this->periodoAbiertoOProvisorio($empleado);
            if ($periodo) {
                $periodo->update([
                    'fecha_egreso' => null,
                    'motivoegreso_id' => null,
                    'comentario_baja' => null,
                    'tipo_movimiento' => EmpleadoBajaSupport::MOV_INGRESO,
                    'usuario_id' => Auth::id(),
                ]);
            }

            // Rastro XA con fecha distinta al período abierto (unique empleado+feing).
            $fechaEvento = $fechaEgresoAnulada;
            $existeFeing = Empleado_Ingreso_Sueldos::query()
                ->where('empleado_id', $empleado->id)
                ->whereDate('fecha_ingreso', $fechaEvento)
                ->exists();
            if ($existeFeing) {
                $fechaEvento = now()->toDateString();
                $existeFeing = Empleado_Ingreso_Sueldos::query()
                    ->where('empleado_id', $empleado->id)
                    ->whereDate('fecha_ingreso', $fechaEvento)
                    ->exists();
            }
            if (! $existeFeing) {
                Empleado_Ingreso_Sueldos::create([
                    'empleado_id' => $empleado->id,
                    'fecha_ingreso' => $fechaEvento,
                    'fecha_egreso' => $fechaEgresoAnulada,
                    'motivoegreso_id' => $motivoAnulado,
                    'comentario_baja' => $comentario ?: 'Anulación de baja provisoria',
                    'tipo_movimiento' => EmpleadoBajaSupport::MOV_ANULA_PROVISORIA,
                    'usuario_id' => Auth::id(),
                ]);
            }

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

    /**
     * Elimina un renglón de historia (emping). Si era la BP vigente del legajo, limpia el egreso.
     */
    public function eliminarHistoria(Empleado_Sueldos $empleado, int $ingresoId): void
    {
        DB::transaction(function () use ($empleado, $ingresoId) {
            $fila = Empleado_Ingreso_Sueldos::query()
                ->where('empleado_id', $empleado->id)
                ->where('id', $ingresoId)
                ->first();
            if ($fila === null) {
                throw new InvalidArgumentException('Registro de historia no encontrado.');
            }

            $eraBpVigente = EmpleadoBajaSupport::esBajaProvisoria($empleado)
                && strtoupper((string) $fila->tipo_movimiento) === EmpleadoBajaSupport::MOV_BAJA_PROVISORIA;

            $fila->delete();

            if ($eraBpVigente) {
                $empleado->update([
                    'fecha_egreso' => null,
                    'motivoegreso_id' => null,
                    'comentario_baja' => null,
                    'estado' => EmpleadoEstados::ACTIVO,
                ]);
            }
        });
    }

    /**
     * Período laboral vigente: ingreso abierto (sin egreso) o baja provisoria.
     */
    private function periodoAbiertoOProvisorio(Empleado_Sueldos $empleado): ?Empleado_Ingreso_Sueldos
    {
        $abierto = Empleado_Ingreso_Sueldos::query()
            ->where('empleado_id', $empleado->id)
            ->whereNull('fecha_egreso')
            ->orderByDesc('fecha_ingreso')
            ->orderByDesc('id')
            ->first();
        if ($abierto) {
            return $abierto;
        }

        return Empleado_Ingreso_Sueldos::query()
            ->where('empleado_id', $empleado->id)
            ->where('tipo_movimiento', EmpleadoBajaSupport::MOV_BAJA_PROVISORIA)
            ->orderByDesc('fecha_ingreso')
            ->orderByDesc('id')
            ->first();
    }
}
