<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Aprobacion_Indumentaria_Nivel_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Prenda_Agrupamiento_Sueldos;
use App\Models\Sueldos\Prenda_Articulo_Sueldos;
use App\Models\Sueldos\Solicitud_Prenda_Aprobacion_Sueldos;
use App\Models\Sueldos\Solicitud_Prenda_Sueldos;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Solicitud de indumentaria con aprobación propia (aislada del motor central).
 * Los niveles de aprobadores se configuran por empresa (opcionalmente por agrupamiento);
 * si no hay niveles, la solicitud se aprueba directo y puede entregarse en un clic.
 */
class SolicitudPrendaService
{
    public function __construct(
        private EntregaPrendaService $entregaService,
    ) {}

    /**
     * Niveles de aprobación aplicables. El agrupamiento específico tiene prioridad;
     * si no hay filas para ese agrupamiento se usan las de empresa (agrupamiento NULL).
     *
     * @return array<int, list<int>>  [nivel => [usuario_id, ...]] ordenado por nivel
     */
    public function nivelesConfigurados(int $empresaId, ?int $agrupamientoId): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        $filas = collect();
        if ($agrupamientoId) {
            $filas = Aprobacion_Indumentaria_Nivel_Sueldos::query()
                ->where('empresa_id', $empresaId)
                ->where('agrupamiento_id', $agrupamientoId)
                ->orderBy('nivel')->orderBy('orden')->get();
        }
        if ($filas->isEmpty()) {
            $filas = Aprobacion_Indumentaria_Nivel_Sueldos::query()
                ->where('empresa_id', $empresaId)
                ->whereNull('agrupamiento_id')
                ->orderBy('nivel')->orderBy('orden')->get();
        }

        $niveles = [];
        foreach ($filas as $f) {
            $niveles[(int) $f->nivel][] = (int) $f->usuario_id;
        }
        ksort($niveles);

        return $niveles;
    }

    public function tieneAprobacion(int $empresaId, ?int $agrupamientoId): bool
    {
        return $this->nivelesConfigurados($empresaId, $agrupamientoId) !== [];
    }

    /**
     * Crea la solicitud (y la envía a aprobación salvo $enviar=false).
     *
     * @param  array<int, array{prenda_articulo_id?:int, prenda_id?:int, cantidad:float}>  $lineas
     */
    public function crear(
        Empleado_Sueldos $empleado,
        array $lineas,
        ?string $fecha,
        ?string $observacion,
        ?int $usuarioId,
        bool $enviar = true,
    ): Solicitud_Prenda_Sueldos {
        $detalle = $this->resolverLineas($lineas);
        if ($detalle === []) {
            throw new \RuntimeException('Debe indicar al menos una prenda con cantidad mayor a cero.');
        }

        return DB::transaction(function () use ($empleado, $detalle, $fecha, $observacion, $usuarioId, $enviar) {
            $solicitud = Solicitud_Prenda_Sueldos::create([
                'empleado_id' => (int) $empleado->id,
                'empresa_id' => (int) ($empleado->empresa_id ?? 0) ?: null,
                'agrupamiento_id' => (int) ($empleado->agrupamiento_id ?? 0) ?: null,
                'fecha' => $fecha ? Carbon::parse($fecha)->toDateString() : Carbon::today()->toDateString(),
                'estado' => Solicitud_Prenda_Sueldos::BORRADOR,
                'nivel_actual' => 0,
                'observacion' => $observacion ? mb_substr(trim($observacion), 0, 255) : null,
                'solicitante_usuario_id' => $usuarioId,
            ]);

            foreach ($detalle as $d) {
                $v = $d['variante'];
                $solicitud->articulos()->create([
                    'prenda_id' => (int) $v->prenda_id,
                    'prenda_articulo_id' => (int) $v->id,
                    'color_id' => $v->color_id,
                    'talle_id' => $v->talle_id,
                    'articulo_id' => $v->articulo_id,
                    'sku' => $v->sku,
                    'cantidad' => $d['cantidad'],
                ]);
            }

            if ($enviar) {
                $this->enviar($solicitud, $usuarioId);
            }

            return $solicitud->fresh(['articulos.prenda', 'articulos.color', 'articulos.talle']);
        });
    }

    /**
     * Envía la solicitud al circuito: a aprobación si hay niveles, o aprobada directa si no.
     */
    public function enviar(Solicitud_Prenda_Sueldos $solicitud, ?int $usuarioId): Solicitud_Prenda_Sueldos
    {
        if (! in_array($solicitud->estado, [Solicitud_Prenda_Sueldos::BORRADOR, Solicitud_Prenda_Sueldos::RECHAZADA], true)) {
            throw new \RuntimeException('Solo se pueden enviar solicitudes en borrador o rechazadas.');
        }

        $niveles = $this->nivelesConfigurados((int) $solicitud->empresa_id, $solicitud->agrupamiento_id);

        $this->registrarMovimiento($solicitud, 0, $usuarioId, Solicitud_Prenda_Aprobacion_Sueldos::ENVIO, 'Solicitud enviada');

        if ($niveles === []) {
            $solicitud->update(['estado' => Solicitud_Prenda_Sueldos::APROBADA, 'nivel_actual' => 0]);
            $this->registrarMovimiento($solicitud, 0, $usuarioId, Solicitud_Prenda_Aprobacion_Sueldos::APROBO, 'Aprobación automática (sin árbol configurado)');
        } else {
            $primer = (int) array_key_first($niveles);
            $solicitud->update(['estado' => Solicitud_Prenda_Sueldos::PENDIENTE, 'nivel_actual' => $primer]);
        }

        return $solicitud->fresh();
    }

    public function puedeAprobar(Solicitud_Prenda_Sueldos $solicitud, int $usuarioId): bool
    {
        if ($solicitud->estado !== Solicitud_Prenda_Sueldos::PENDIENTE || $usuarioId <= 0) {
            return false;
        }
        $niveles = $this->nivelesConfigurados((int) $solicitud->empresa_id, $solicitud->agrupamiento_id);
        $aprobadores = $niveles[(int) $solicitud->nivel_actual] ?? [];

        return in_array($usuarioId, $aprobadores, true);
    }

    public function aprobar(Solicitud_Prenda_Sueldos $solicitud, int $usuarioId, ?string $observacion = null): Solicitud_Prenda_Sueldos
    {
        if (! $this->puedeAprobar($solicitud, $usuarioId)) {
            throw new \RuntimeException('No está autorizado para aprobar el nivel actual de esta solicitud.');
        }

        $niveles = $this->nivelesConfigurados((int) $solicitud->empresa_id, $solicitud->agrupamiento_id);
        $nivelActual = (int) $solicitud->nivel_actual;
        $this->registrarMovimiento($solicitud, $nivelActual, $usuarioId, Solicitud_Prenda_Aprobacion_Sueldos::APROBO, $observacion);

        $siguientes = array_values(array_filter(array_keys($niveles), fn ($n) => (int) $n > $nivelActual));
        if ($siguientes !== []) {
            $solicitud->update(['nivel_actual' => (int) min($siguientes)]);
        } else {
            $solicitud->update(['estado' => Solicitud_Prenda_Sueldos::APROBADA]);
        }

        return $solicitud->fresh();
    }

    public function rechazar(Solicitud_Prenda_Sueldos $solicitud, int $usuarioId, ?string $observacion = null): Solicitud_Prenda_Sueldos
    {
        if (! $this->puedeAprobar($solicitud, $usuarioId)) {
            throw new \RuntimeException('No está autorizado para rechazar el nivel actual de esta solicitud.');
        }
        $this->registrarMovimiento($solicitud, (int) $solicitud->nivel_actual, $usuarioId, Solicitud_Prenda_Aprobacion_Sueldos::RECHAZO, $observacion);
        $solicitud->update(['estado' => Solicitud_Prenda_Sueldos::RECHAZADA]);

        return $solicitud->fresh();
    }

    /**
     * Convierte una solicitud APROBADA en entrega (descuenta stock + asiento) en un solo paso.
     */
    public function convertirEnEntrega(Solicitud_Prenda_Sueldos $solicitud, ?int $usuarioId, ?string $fecha = null): Solicitud_Prenda_Sueldos
    {
        if ($solicitud->estado !== Solicitud_Prenda_Sueldos::APROBADA) {
            throw new \RuntimeException('Solo se pueden entregar solicitudes aprobadas.');
        }

        $solicitud->loadMissing('articulos', 'empleado');
        $empleado = $solicitud->empleado;
        if ($empleado === null) {
            throw new \RuntimeException('La solicitud no tiene empleado asociado.');
        }

        $lineas = [];
        foreach ($solicitud->articulos as $l) {
            if ((int) $l->prenda_articulo_id <= 0) {
                throw new \RuntimeException('Una línea de la solicitud no tiene variante (color/talle/SKU); complétela antes de entregar.');
            }
            $lineas[] = ['prenda_articulo_id' => (int) $l->prenda_articulo_id, 'cantidad' => (float) $l->cantidad];
        }
        if ($lineas === []) {
            throw new \RuntimeException('La solicitud no tiene líneas para entregar.');
        }

        return DB::transaction(function () use ($solicitud, $empleado, $lineas, $fecha, $usuarioId) {
            $entrega = $this->entregaService->registrar(
                $empleado,
                $lineas,
                $fecha,
                'Solicitud #'.$solicitud->id.($solicitud->observacion ? ' - '.$solicitud->observacion : ''),
                $usuarioId,
                false,
            );

            $solicitud->update([
                'estado' => Solicitud_Prenda_Sueldos::ENTREGADA,
                'entrega_id' => (int) $entrega->id,
            ]);
            $this->registrarMovimiento($solicitud, (int) $solicitud->nivel_actual, $usuarioId, Solicitud_Prenda_Aprobacion_Sueldos::ENTREGO, 'Entrega #'.$entrega->id);

            return $solicitud->fresh(['entrega']);
        });
    }

    public function anular(Solicitud_Prenda_Sueldos $solicitud, ?int $usuarioId = null): Solicitud_Prenda_Sueldos
    {
        if ($solicitud->estado === Solicitud_Prenda_Sueldos::ENTREGADA) {
            throw new \RuntimeException('No se puede anular una solicitud ya entregada; anule la entrega desde el historial.');
        }
        if ($solicitud->estado === Solicitud_Prenda_Sueldos::ANULADA) {
            return $solicitud;
        }
        $this->registrarMovimiento($solicitud, (int) $solicitud->nivel_actual, $usuarioId, Solicitud_Prenda_Aprobacion_Sueldos::RECHAZO, 'Solicitud anulada');
        $solicitud->update(['estado' => Solicitud_Prenda_Sueldos::ANULADA]);

        return $solicitud->fresh();
    }

    /**
     * Solicitudes pendientes que el usuario puede aprobar (según nivel actual).
     *
     * @return \Illuminate\Support\Collection<int, Solicitud_Prenda_Sueldos>
     */
    public function bandejaPendientesDe(int $usuarioId)
    {
        $pendientes = Solicitud_Prenda_Sueldos::query()
            ->with(['empleado:id,legajo,nombre,empresa_id,agrupamiento_id', 'articulos.prenda:id,codigo,descripcion'])
            ->where('estado', Solicitud_Prenda_Sueldos::PENDIENTE)
            ->orderBy('fecha')->orderBy('id')
            ->get();

        return $pendientes->filter(fn ($s) => $this->puedeAprobar($s, $usuarioId))->values();
    }

    /**
     * @param  array<int, array{prenda_articulo_id?:int, prenda_id?:int, cantidad:float}>  $lineas
     * @return array<int, array{variante:Prenda_Articulo_Sueldos, cantidad:float}>
     */
    private function resolverLineas(array $lineas): array
    {
        $detalle = [];
        foreach ($lineas as $l) {
            $varianteId = (int) ($l['prenda_articulo_id'] ?? 0);
            $cantidad = round((float) ($l['cantidad'] ?? 0), 3);
            if ($varianteId <= 0 || $cantidad <= 0) {
                continue;
            }
            $variante = Prenda_Articulo_Sueldos::query()->find($varianteId);
            if ($variante === null) {
                throw new \RuntimeException('Variante de prenda inexistente.');
            }
            $detalle[] = ['variante' => $variante, 'cantidad' => $cantidad];
        }

        return $detalle;
    }

    private function registrarMovimiento(Solicitud_Prenda_Sueldos $solicitud, int $nivel, ?int $usuarioId, string $accion, ?string $observacion): void
    {
        Solicitud_Prenda_Aprobacion_Sueldos::create([
            'solicitud_id' => (int) $solicitud->id,
            'nivel' => $nivel,
            'usuario_id' => $usuarioId,
            'accion' => $accion,
            'observacion' => $observacion ? mb_substr(trim($observacion), 0, 255) : null,
            'fecha' => Carbon::now(),
        ]);
    }
}
