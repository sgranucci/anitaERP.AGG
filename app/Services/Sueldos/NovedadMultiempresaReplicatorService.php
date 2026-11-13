<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Ausencia_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Novedad_Sueldos;
use App\Support\Sueldos\EmpleadoEstados;
use App\Support\Sueldos\NovedadSueldosCatalogo;
use Illuminate\Support\Collection;

/**
 * Replica novedades y ausencias al mismo legajo en otras empresas.
 * Evita recursión con el flag estático $replicando.
 */
class NovedadMultiempresaReplicatorService
{
    private static bool $replicando = false;

    private AusenciaNovedadSyncService $novedadSync;

    private DevengamientoVacacionesService $devengamiento;

    public function __construct(
        AusenciaNovedadSyncService $novedadSync,
        DevengamientoVacacionesService $devengamiento
    ) {
        $this->novedadSync = $novedadSync;
        $this->devengamiento = $devengamiento;
    }

    /**
     * @return Collection<int, Empleado_Sueldos>
     */
    public function hermanos(Empleado_Sueldos $empleado): Collection
    {
        $legajo = (int) ($empleado->legajo ?? 0);
        if ($legajo <= 0) {
            return collect();
        }

        return Empleado_Sueldos::query()
            ->where('legajo', $legajo)
            ->where('id', '!=', (int) $empleado->id)
            ->where('estado', EmpleadoEstados::ACTIVO)
            ->get();
    }

    public function replicarNovedad(Novedad_Sueldos $origen): void
    {
        if (self::$replicando) {
            return;
        }

        $empleado = Empleado_Sueldos::find($origen->empleado_id);
        if (! $empleado) {
            return;
        }

        $hermanos = $this->hermanos($empleado);
        if ($hermanos->isEmpty()) {
            return;
        }

        self::$replicando = true;
        try {
            foreach ($hermanos as $hermano) {
                $existente = $this->buscarNovedadHermana($origen, $hermano);
                $payload = [
                    'empresa_id' => (int) $hermano->empresa_id,
                    'liquidacion_id' => null,
                    'empleado_id' => (int) $hermano->id,
                    'concepto_id' => (int) $origen->concepto_id,
                    'concepto_codigo' => (int) $origen->concepto_codigo,
                    'valor1' => $origen->valor1,
                    'valor2' => $origen->valor2,
                    'estado' => (string) $origen->estado,
                    'fecha_vto' => $origen->fecha_vto,
                    'fecha_desde' => $origen->fecha_desde,
                    'fecha_hasta' => $origen->fecha_hasta,
                    'nro_interno' => $origen->nro_interno,
                    'periodo' => $origen->periodo,
                    'origen' => NovedadSueldosCatalogo::ORIGEN_MANUAL,
                    'ausencia_id' => null,
                    'usuario_id' => $origen->usuario_id,
                    'observacion' => $this->marcaReplica(
                        (string) ($origen->observacion ?? ''),
                        (int) $origen->id
                    ),
                ];

                if ($existente) {
                    $existente->update($payload);
                } else {
                    Novedad_Sueldos::create($payload);
                }
            }
        } finally {
            self::$replicando = false;
        }
    }

    public function anularNovedadHermanas(Novedad_Sueldos $origen): void
    {
        if (self::$replicando) {
            return;
        }

        $empleado = Empleado_Sueldos::find($origen->empleado_id);
        if (! $empleado) {
            return;
        }

        self::$replicando = true;
        try {
            foreach ($this->hermanos($empleado) as $hermano) {
                $hermana = $this->buscarNovedadHermana($origen, $hermano);
                if ($hermana) {
                    $hermana->update([
                        'estado' => NovedadSueldosCatalogo::ESTADO_ANULADA,
                        'observacion' => $this->marcaReplica(
                            'Anulada por réplica multiempresa de novedad #'.$origen->id,
                            (int) $origen->id
                        ),
                    ]);
                }
            }
        } finally {
            self::$replicando = false;
        }
    }

    public function replicarAusencia(Empleado_Ausencia_Sueldos $origen): void
    {
        if (self::$replicando) {
            return;
        }

        $empleado = Empleado_Sueldos::find($origen->empleado_id);
        if (! $empleado) {
            return;
        }

        $hermanos = $this->hermanos($empleado);
        if ($hermanos->isEmpty()) {
            return;
        }

        self::$replicando = true;
        try {
            foreach ($hermanos as $hermano) {
                $existente = $this->buscarAusenciaHermana($origen, $hermano);
                $payload = [
                    'empleado_id' => (int) $hermano->id,
                    'tipo_ausencia_id' => (int) $origen->tipo_ausencia_id,
                    'anio_imputacion' => $origen->anio_imputacion,
                    'fecha_desde' => $origen->fecha_desde,
                    'fecha_hasta' => $origen->fecha_hasta,
                    'dias' => $origen->dias,
                    'tipo_dias' => $origen->tipo_dias,
                    'estado' => (string) $origen->estado,
                    'liquidacion_id' => null,
                    'observacion' => $this->marcaReplica(
                        (string) ($origen->observacion ?? ''),
                        (int) $origen->id
                    ),
                    'usuario_id' => $origen->usuario_id,
                ];

                if ($existente) {
                    $existente->update($payload);
                    $ausencia = $existente->fresh(['tipo.concepto', 'empleado']);
                } else {
                    $ausencia = Empleado_Ausencia_Sueldos::create($payload);
                    $ausencia->load(['tipo.concepto', 'empleado']);
                }

                $this->devengamiento->recalcularEmpleado($hermano, $origen->usuario_id ? (int) $origen->usuario_id : null);
                $this->novedadSync->sincronizar($ausencia);
            }
        } finally {
            self::$replicando = false;
        }
    }

    public function eliminarAusenciaHermanas(Empleado_Ausencia_Sueldos $origen): void
    {
        if (self::$replicando) {
            return;
        }

        $empleado = Empleado_Sueldos::find($origen->empleado_id);
        if (! $empleado) {
            return;
        }

        self::$replicando = true;
        try {
            foreach ($this->hermanos($empleado) as $hermano) {
                $hermana = $this->buscarAusenciaHermana($origen, $hermano);
                if (! $hermana) {
                    continue;
                }
                $ausenciaId = (int) $hermana->id;
                $hermana->delete();
                $this->novedadSync->anularPorAusencia($ausenciaId);
                $this->devengamiento->recalcularEmpleado($hermano, $origen->usuario_id ? (int) $origen->usuario_id : null);
            }
        } finally {
            self::$replicando = false;
        }
    }

    private function buscarNovedadHermana(Novedad_Sueldos $origen, Empleado_Sueldos $hermano): ?Novedad_Sueldos
    {
        $q = Novedad_Sueldos::query()
            ->where('empleado_id', (int) $hermano->id)
            ->where('concepto_id', (int) $origen->concepto_id)
            ->where('estado', '!=', NovedadSueldosCatalogo::ESTADO_ANULADA);

        if ($origen->periodo) {
            $q->where('periodo', (int) $origen->periodo);
        }
        if ($origen->fecha_desde) {
            $q->whereDate('fecha_desde', $origen->fecha_desde->toDateString());
        }
        if ($origen->fecha_hasta) {
            $q->whereDate('fecha_hasta', $origen->fecha_hasta->toDateString());
        }

        $marca = 'replica_de:#'.$origen->id;
        $porMarca = Novedad_Sueldos::query()
            ->where('empleado_id', (int) $hermano->id)
            ->where('observacion', 'like', '%'.$marca.'%')
            ->first();

        return $porMarca ?: $q->orderByDesc('id')->first();
    }

    private function buscarAusenciaHermana(Empleado_Ausencia_Sueldos $origen, Empleado_Sueldos $hermano): ?Empleado_Ausencia_Sueldos
    {
        $marca = 'replica_de:#'.$origen->id;
        $porMarca = Empleado_Ausencia_Sueldos::query()
            ->where('empleado_id', (int) $hermano->id)
            ->where('observacion', 'like', '%'.$marca.'%')
            ->first();
        if ($porMarca) {
            return $porMarca;
        }

        return Empleado_Ausencia_Sueldos::query()
            ->where('empleado_id', (int) $hermano->id)
            ->where('tipo_ausencia_id', (int) $origen->tipo_ausencia_id)
            ->whereDate('fecha_desde', $origen->fecha_desde->toDateString())
            ->whereDate('fecha_hasta', $origen->fecha_hasta->toDateString())
            ->where('estado', '!=', 'anulada')
            ->orderByDesc('id')
            ->first();
    }

    private function marcaReplica(string $observacion, int $origenId): string
    {
        $marca = 'replica_de:#'.$origenId;
        $base = trim(preg_replace('/\s*·?\s*replica_de:#\d+/', '', $observacion) ?? '');

        return $base === '' ? $marca : $base.' · '.$marca;
    }
}
