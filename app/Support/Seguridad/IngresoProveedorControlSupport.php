<?php

namespace App\Support\Seguridad;

use App\Models\Seguridad\IngresoProveedor;
use App\Models\Seguridad\IngresoProveedorPersona;
use App\Repositories\Configuracion\EmpresaRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Portería: busca por DNI y registra ENTRO / SALIO para KPIs.
 */
final class IngresoProveedorControlSupport
{
    public static function normalizarDni(?string $documento): string
    {
        return preg_replace('/\D+/', '', (string) $documento) ?? '';
    }

    /**
     * Ticket vigente para el DNI: primero quien está en planta, luego el de hoy, luego el abierto más reciente.
     */
    public static function buscarPorDni(string $documento, ?int $empresaId = null): ?IngresoProveedorPersona
    {
        $dni = self::normalizarDni($documento);
        if ($dni === '' || strlen($dni) < 6) {
            return null;
        }

        $base = self::queryBasePorDni($dni, $empresaId);
        $abierto = (clone $base)->whereHas(
            'ingreso',
            static fn ($q) => $q->where('estado', '!=', IngresoProveedorEstados::RECHAZADO)
        );

        $enPlanta = (clone $abierto)
            ->whereNotNull('fecha_ingreso')
            ->whereNull('fecha_egreso')
            ->orderByDesc('fecha_ingreso')
            ->orderByDesc('id')
            ->first();
        if ($enPlanta) {
            return $enPlanta;
        }

        $hoy = (clone $abierto)
            ->whereHas('ingreso', fn ($q) => $q->whereDate('fecha', now()->toDateString()))
            ->whereNull('fecha_egreso')
            ->orderByDesc('id')
            ->first();
        if ($hoy) {
            return $hoy;
        }

        $abiertoReciente = (clone $abierto)
            ->whereNull('fecha_egreso')
            ->orderByDesc('id')
            ->first();
        if ($abiertoReciente) {
            return $abiertoReciente;
        }

        $rechazadoHoy = (clone $base)
            ->whereHas('ingreso', static function ($q) {
                $q->where('estado', IngresoProveedorEstados::RECHAZADO)
                    ->whereDate('fecha', now()->toDateString());
            })
            ->orderByDesc('id')
            ->first();
        if ($rechazadoHoy) {
            return $rechazadoHoy;
        }

        return (clone $base)
            ->whereHas('ingreso', static fn ($q) => $q->where('estado', IngresoProveedorEstados::RECHAZADO))
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<IngresoProveedorPersona>
     */
    private static function queryBasePorDni(string $dni, ?int $empresaId)
    {
        return IngresoProveedorPersona::query()
            ->where('documento_norm', $dni)
            ->whereHas('ingreso', function ($q) use ($empresaId) {
                app(EmpresaRepository::class)->aplicarFiltroEmpresasAsignadas($q, 'empresa_id');
                if ($empresaId && $empresaId > 0) {
                    $q->where('empresa_id', $empresaId);
                }
            })
            ->with([
                'ingreso.proveedores:id,codigo,nombre',
                'ingreso.motivos:id,nombre',
                'ingreso.puntos:id,nombre',
                'ingreso.areas:id,nombre',
                'ingreso.sectores:id,nombre',
                'ingreso.empresas:id,nombre',
                'ingreso.usuarios:id,nombre',
                'ingreso.usuarioAutorizo:id,nombre',
            ]);
    }

    public static function marcarEntro(int $personaId): IngresoProveedorPersona
    {
        $persona = IngresoProveedorPersona::query()->with('ingreso.personas')->findOrFail($personaId);
        $ticket = $persona->ingreso;
        if (! $ticket) {
            throw new RuntimeException('El ticket de ingreso no existe.');
        }
        if ((string) $ticket->estado === IngresoProveedorEstados::RECHAZADO) {
            throw new RuntimeException('El ticket está rechazado.');
        }
        if (! IngresoProveedorEstados::permiteEntro((string) $ticket->estado)) {
            throw new RuntimeException(
                'El ticket todavía no está autorizado por Seguridad. Estado: '
                .IngresoProveedorEstados::etiqueta((string) $ticket->estado).'.'
            );
        }
        if ($persona->fecha_ingreso && ! $persona->fecha_egreso) {
            throw new RuntimeException('Esta persona ya está en planta.');
        }
        if ($persona->fecha_ingreso && $persona->fecha_egreso) {
            throw new RuntimeException('Esta visita ya tiene ingreso y egreso. Cargue un ticket nuevo.');
        }

        $ahora = Carbon::now();
        $persona->forceFill([
            'fecha_ingreso' => $ahora->toDateString(),
            'hora_ingreso' => $ahora->format('H:i:s'),
            'usuario_ingreso_id' => Auth::id(),
        ])->save();

        if (! $ticket->fecha_ingreso) {
            $ticket->fecha_ingreso = $ahora->toDateString();
            $ticket->hora_ingreso = $ahora->format('H:i:s');
        }
        $ticket->estado = IngresoProveedorEstados::INGRESADO;
        $ticket->save();

        return $persona->fresh(['ingreso.proveedores', 'ingreso.motivos', 'ingreso.puntos', 'ingreso.areas', 'ingreso.sectores', 'ingreso.empresas']);
    }

    public static function marcarSalio(int $personaId): IngresoProveedorPersona
    {
        $persona = IngresoProveedorPersona::query()->with('ingreso.personas')->findOrFail($personaId);
        $ticket = $persona->ingreso;
        if (! $ticket) {
            throw new RuntimeException('El ticket de ingreso no existe.');
        }
        if (! $persona->fecha_ingreso) {
            throw new RuntimeException('Todavía no se registró el ingreso (ENTRO).');
        }
        if ($persona->fecha_egreso) {
            throw new RuntimeException('Esta persona ya registró la salida.');
        }

        $ahora = Carbon::now();
        $desde = Carbon::parse($persona->fecha_ingreso->format('Y-m-d').' '.$persona->hora_ingreso);
        $persona->forceFill([
            'fecha_egreso' => $ahora->toDateString(),
            'hora_egreso' => $ahora->format('H:i:s'),
            'minutos_en_planta' => max(0, $desde->diffInMinutes($ahora)),
            'usuario_egreso_id' => Auth::id(),
        ])->save();

        $ticket->fecha_egreso = $ahora->toDateString();
        $ticket->hora_egreso = $ahora->format('H:i:s');
        $ticket->recalcularMinutosEnPlanta();
        self::sincronizarEstadoTicket($ticket);
        $ticket->save();

        return $persona->fresh(['ingreso.proveedores', 'ingreso.motivos', 'ingreso.puntos', 'ingreso.areas', 'ingreso.sectores', 'ingreso.empresas']);
    }

    public static function sincronizarEstadoTicket(IngresoProveedor $ticket): void
    {
        $ticket->unsetRelation('personas');
        $ticket->load('personas');
        $enPlanta = $ticket->personas->filter(fn ($p) => $p->fecha_ingreso && ! $p->fecha_egreso)->count();
        $algunaEntrada = $ticket->personas->filter(fn ($p) => $p->fecha_ingreso)->count();

        if ($enPlanta > 0) {
            $ticket->estado = IngresoProveedorEstados::INGRESADO;

            return;
        }
        if ($algunaEntrada > 0) {
            $ticket->estado = IngresoProveedorEstados::FINALIZADO;
        }
    }

    /**
     * Grilla del día + personas aún en planta (para la portería).
     *
     * @return Collection<int, IngresoProveedorPersona>
     */
    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function grillaDelDia(array $filtros = []): Collection
    {
        $query = IngresoProveedorPersona::query()
            ->select('ingreso_proveedor_persona.*')
            ->join('ingreso_proveedor', 'ingreso_proveedor.id', '=', 'ingreso_proveedor_persona.ingreso_proveedor_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'ingreso_proveedor.proveedor_id')
            ->leftJoin('ingreso_proveedor_motivo', 'ingreso_proveedor_motivo.id', '=', 'ingreso_proveedor.motivo_id')
            ->leftJoin('ingreso_proveedor_punto', 'ingreso_proveedor_punto.id', '=', 'ingreso_proveedor.punto_id')
            ->leftJoin('ingreso_proveedor_sector', 'ingreso_proveedor_sector.id', '=', 'ingreso_proveedor.sector_id')
            ->leftJoin('ingreso_proveedor_area', 'ingreso_proveedor_area.id', '=', 'ingreso_proveedor.area_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'ingreso_proveedor.usuario_id')
            ->leftJoin('empresa', 'empresa.id', '=', 'ingreso_proveedor.empresa_id')
            ->where(function ($q) {
                $q->whereDate('ingreso_proveedor.fecha', now()->toDateString())
                    ->orWhere(function ($enPlanta) {
                        $enPlanta->whereNotNull('ingreso_proveedor_persona.fecha_ingreso')
                            ->whereNull('ingreso_proveedor_persona.fecha_egreso');
                    });
            });

        app(EmpresaRepository::class)->aplicarFiltroEmpresasAsignadas($query, 'ingreso_proveedor.empresa_id');
        IngresoProveedorListadoFiltros::aplicarEmpresa($query, $filtros);
        if (IngresoProveedorListadoFiltros::tieneCriteriosTexto($filtros)) {
            IngresoProveedorListadoFiltros::aplicar($query, $filtros);
        }

        return $query
            ->with([
                'ingreso.proveedores:id,codigo,nombre',
                'ingreso.motivos:id,nombre',
                'ingreso.puntos:id,nombre',
                'ingreso.areas:id,nombre',
                'ingreso.sectores:id,nombre',
                'ingreso.usuarios:id,nombre',
                'ingreso.usuarioAutorizo:id,nombre',
            ])
            ->orderByRaw('ingreso_proveedor_persona.fecha_egreso IS NULL DESC')
            ->orderByDesc('ingreso_proveedor.fecha')
            ->orderByDesc('ingreso_proveedor_persona.id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public static function payloadPersona(?IngresoProveedorPersona $persona): array
    {
        if (! $persona) {
            return [];
        }
        $ticket = $persona->ingreso;
        $enPlanta = (bool) ($persona->fecha_ingreso && ! $persona->fecha_egreso);
        $finalizada = (bool) ($persona->fecha_ingreso && $persona->fecha_egreso);
        $estadoCodigo = (string) ($ticket?->estado ?? '');
        $puedeEntro = ! $enPlanta && ! $finalizada && IngresoProveedorEstados::permiteEntro($estadoCodigo);
        $mensajeBloqueo = null;
        if (! $puedeEntro && ! $enPlanta && ! $finalizada && $estadoCodigo === IngresoProveedorEstados::PENDIENTE) {
            $mensajeBloqueo = 'Ticket pendiente de autorización de Seguridad. No puede ingresar.';
        } elseif ($estadoCodigo === IngresoProveedorEstados::RECHAZADO) {
            $mensajeBloqueo = self::mensajeRechazo($ticket);
        }

        return [
            'persona_id' => (int) $persona->id,
            'ticket_id' => (int) $persona->ingreso_proveedor_id,
            'nombre' => (string) $persona->nombre,
            'documento' => (string) $persona->documento,
            'fecha' => optional($ticket?->fecha)->format('d/m/Y'),
            'proveedor' => IngresoProveedorVisitanteSupport::etiquetaOrigen($ticket),
            'motivo' => $ticket?->motivos?->nombre,
            'punto' => $ticket?->puntos?->nombre,
            'area' => $ticket?->areas?->nombre,
            'sector' => $ticket?->sectores?->nombre,
            'patente' => $ticket?->patente,
            'titulo' => $ticket?->titulo,
            'comentario' => $ticket?->comentario,
            'estado' => IngresoProveedorEstados::etiqueta($estadoCodigo),
            'estado_codigo' => $estadoCodigo,
            'empresa' => $ticket?->empresas?->nombre,
            'empresa_id' => (int) ($ticket?->empresa_id ?? 0),
            'generado_por' => $ticket?->usuarios?->nombre,
            'hora_ingreso' => $persona->hora_ingreso ? substr((string) $persona->hora_ingreso, 0, 5) : null,
            'hora_egreso' => $persona->hora_egreso ? substr((string) $persona->hora_egreso, 0, 5) : null,
            'minutos_en_planta' => $persona->minutos_en_planta,
            'puede_entro' => $puedeEntro,
            'puede_salio' => $enPlanta,
            'mensaje_bloqueo' => $mensajeBloqueo,
            'en_planta' => $enPlanta,
        ];
    }

    private static function mensajeRechazo(?IngresoProveedor $ticket): string
    {
        $quien = trim((string) (optional($ticket?->usuarioAutorizo)->nombre ?? ''));
        $texto = $quien !== ''
            ? 'Ticket rechazado por '.$quien.'. No puede ingresar.'
            : 'Ticket rechazado por Seguridad. No puede ingresar.';
        $comentario = trim((string) ($ticket?->comentario ?? ''));
        if ($comentario !== '') {
            $texto .= ' Motivo: '.$comentario;
        }

        return $texto;
    }
}
