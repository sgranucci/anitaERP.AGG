<?php

namespace App\Services\Ticket;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\Ticket_Estado;
use App\Models\Ticket\Ticket_Tarea;
use App\Models\Ticket\Ticket_Tarea_Novedad;
use App\Models\Ticket\Tecnico_Ticket;
use App\Repositories\Ticket\Tecnico_TicketRepositoryInterface;
use App\Repositories\Ticket\Ticket_EstadoRepositoryInterface;
use App\Repositories\Ticket\Ticket_Tarea_NovedadRepositoryInterface;
use App\Support\Ticket\TicketModoOperacionSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TicketBandejaService
{
    public const TAB_COLA = 'cola';

    public const TAB_MIOS = 'mios';

    public const TAB_TODOS = 'todos';

    public function __construct(
        private Tecnico_TicketRepositoryInterface $tecnicoTicketRepository,
        private Ticket_EstadoRepositoryInterface $ticketEstadoRepository,
        private Ticket_Tarea_NovedadRepositoryInterface $ticketTareaNovedadRepository,
        private TicketTareaAsignadaNotificacionService $ticketTareaAsignadaNotificacionService
    ) {
    }

    /**
     * @return list<string>
     */
    public static function tabs(): array
    {
        return [self::TAB_COLA, self::TAB_MIOS, self::TAB_TODOS];
    }

    public function normalizarTab(?string $tab): string
    {
        $tab = strtolower(trim((string) $tab));

        return in_array($tab, self::tabs(), true) ? $tab : self::TAB_COLA;
    }

    /**
     * Áreas a las que el usuario puede acceder desde la bandeja
     * (cualquier modo: claim o asignación por administrador).
     *
     * @return list<int>
     */
    public function areadestinoIdsAccesibles(): array
    {
        if ($this->esSupervisorOAdmin()) {
            return $this->todasLasAreasDestino();
        }

        $usuarioId = (int) Auth::id();
        $mios = $this->tecnicoTicketRepository->leePorUsuarioId($usuarioId)
            ->pluck('areadestino_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($this->esEncargado()) {
            // Encargado sin ficha (p. ej. Enc-mantenimiento): áreas en modo cola.
            if ($mios === []) {
                return $this->claimAreaIds();
            }

            return $mios;
        }

        // Técnico operativo: solo áreas de sus fichas (Tecnología, Mantenimiento, etc.).
        return $mios;
    }

    /**
     * Subconjunto claim de las áreas accesibles (para Cola / Tomar / Liberar).
     *
     * @return list<int>
     */
    public function areadestinoIdsClaimAccesibles(): array
    {
        return array_values(array_filter(
            $this->areadestinoIdsAccesibles(),
            static fn (int $id) => TicketModoOperacionSupport::esClaim($id)
        ));
    }

    public function puedeVerTabTodos(): bool
    {
        return $this->esSupervisorOAdmin() || $this->esEncargado();
    }

    public function puedeVerTabCola(): bool
    {
        return $this->areadestinoIdsClaimAccesibles() !== [];
    }

    /**
     * Contador del badge (header/menú): cola si opera claim; si no, tickets míos.
     */
    public function contarBadge(): int
    {
        if ($this->puedeVerTabCola()) {
            return $this->contarCola();
        }

        return $this->contarMios();
    }

    /**
     * Contador para badge de menú: tickets en cola (sin técnico) de áreas claim accesibles.
     */
    public function contarCola(): int
    {
        $areas = $this->areadestinoIdsClaimAccesibles();
        if ($areas === []) {
            return 0;
        }

        return (int) $this->baseQuery($areas)
            ->tap(fn ($q) => $this->aplicarTab($q, self::TAB_COLA))
            ->count();
    }

    public function contarMios(): int
    {
        $areas = $this->areadestinoIdsAccesibles();
        if ($areas === []) {
            return 0;
        }

        return (int) $this->baseQuery($areas)
            ->tap(fn ($q) => $this->aplicarTab($q, self::TAB_MIOS))
            ->count();
    }

    /**
     * @param  array{tab?: string, q?: string}  $filtros
     * @return array{
     *     tab: string,
     *     items: Collection<int, object>,
     *     contadores: array{cola: int, mios: int, todos: int},
     *     areas: list<int>,
     *     puede_todos: bool,
     *     puede_cola: bool,
     *     tecnicos_por_area: array<int, list<object>>
     * }
     */
    public function listar(array $filtros = []): array
    {
        $areas = $this->areadestinoIdsAccesibles();
        $areasClaim = $this->areadestinoIdsClaimAccesibles();
        $puedeTodos = $this->puedeVerTabTodos();
        $puedeCola = $areasClaim !== [];

        $tabRequest = $filtros['tab'] ?? null;
        if ($tabRequest === null || $tabRequest === '') {
            $tab = $puedeCola ? self::TAB_COLA : self::TAB_MIOS;
        } else {
            $tab = $this->normalizarTab((string) $tabRequest);
        }

        if ($tab === self::TAB_TODOS && ! $puedeTodos) {
            $tab = $puedeCola ? self::TAB_COLA : self::TAB_MIOS;
        }
        if ($tab === self::TAB_COLA && ! $puedeCola) {
            $tab = self::TAB_MIOS;
        }

        $contadores = [
            'cola' => $puedeCola
                ? (int) $this->baseQuery($areasClaim)->tap(fn ($q) => $this->aplicarTab($q, self::TAB_COLA))->count()
                : 0,
            'mios' => $areas === [] ? 0 : (int) $this->baseQuery($areas)->tap(fn ($q) => $this->aplicarTab($q, self::TAB_MIOS))->count(),
            'todos' => ($puedeTodos && $areas !== [])
                ? (int) $this->baseQuery($areas)->tap(fn ($q) => $this->aplicarTab($q, self::TAB_TODOS))->count()
                : 0,
        ];

        $items = collect();
        if ($areas !== []) {
            $areasTab = $tab === self::TAB_COLA ? $areasClaim : $areas;
            if ($areasTab !== []) {
                $q = $this->baseQuery($areasTab);
                $this->aplicarTab($q, $tab);
                $this->aplicarBusqueda($q, (string) ($filtros['q'] ?? ''));
                $items = $q->orderByDesc('ticket.id')->limit(200)->get()->map(fn ($row) => $this->mapearFila($row));
            }
        }

        return [
            'tab' => $tab,
            'items' => $items,
            'contadores' => $contadores,
            'areas' => $areas,
            'puede_todos' => $puedeTodos,
            'puede_cola' => $puedeCola,
            'tecnicos_por_area' => $puedeTodos ? $this->tecnicosOperativosPorAreas($areas) : [],
        ];
    }

    /**
     * El técnico toma el ticket (asignación atómica).
     *
     * @return array{ticket_id: int, ticket_tarea_id: int}
     */
    public function tomar(int $ticketId): array
    {
        $usuarioId = (int) Auth::id();
        $ticket = Ticket::query()->findOrFail($ticketId);
        $this->assertAreaAccesible((int) $ticket->areadestino_id);

        if (! TicketModoOperacionSupport::esClaim((int) $ticket->areadestino_id)) {
            throw new RuntimeException('Este ticket no pertenece a un área en modo cola.');
        }

        $tecnico = $this->resolverTecnicoUsuarioParaArea($usuarioId, (int) $ticket->areadestino_id);
        if (! $tecnico) {
            throw new RuntimeException(
                'No tiene ficha de técnico vinculada a su usuario en esta área. '
                .'Pedí al administrador que edite Técnicos (ticket) y asocie su usuario ERP a su ficha.'
            );
        }

        return DB::transaction(function () use ($ticket, $tecnico, $usuarioId) {
            $tarea = $this->asegurarTareaTomable((int) $ticket->id, $usuarioId);

            $claimed = Ticket_Tarea::query()
                ->whereKey($tarea->id)
                ->whereNull('tecnico_id')
                ->update(['tecnico_id' => $tecnico->id]);

            if ($claimed !== 1) {
                throw new RuntimeException('El ticket ya fue tomado por otro técnico.');
            }

            $estadoPendiente = Ticket_Estado::$enumEstado[1]['nombre'];
            $ticket->update(['estado_ticket' => $estadoPendiente]);

            $comentario = 'Toma el ticket: '.$tecnico->nombre;
            $this->ticketEstadoRepository->creaEstado(
                (int) $ticket->id,
                Carbon::now(),
                $estadoPendiente,
                $usuarioId,
                $comentario
            );

            $this->ticketTareaNovedadRepository->createUnique([
                'ticket_tarea_id' => (int) $tarea->id,
                'desdefecha' => Carbon::now(),
                'hastafecha' => Carbon::now(),
                'comentario' => $comentario,
                'estado' => Ticket_Tarea_Novedad::$enumEstado[7]['nombre'] ?? 'Asigna técnico',
                'usuario_id' => $usuarioId,
            ]);

            $this->ticketTareaAsignadaNotificacionService->notificar((int) $ticket->id, [[
                'nombre_tarea' => (string) ($tarea->detalle ?? 'Atención del ticket'),
                'fechacarga' => Carbon::now()->toDateString(),
                'fechaprogramacion' => Carbon::now()->toDateString(),
                'tecnico_id' => (int) $tecnico->id,
                'turno_id' => null,
            ]]);

            return [
                'ticket_id' => (int) $ticket->id,
                'ticket_tarea_id' => (int) $tarea->id,
            ];
        });
    }

    /**
     * Devuelve el ticket a la cola (sin técnico).
     */
    public function liberar(int $ticketId): void
    {
        $usuarioId = (int) Auth::id();
        $ticket = Ticket::query()->with('ticket_tareas')->findOrFail($ticketId);
        $this->assertAreaAccesible((int) $ticket->areadestino_id);

        if (! TicketModoOperacionSupport::esClaim((int) $ticket->areadestino_id)) {
            throw new RuntimeException('Este ticket no pertenece a un área en modo cola.');
        }

        $tarea = $this->resolverTareaPrincipal($ticket);
        if (! $tarea || ! $tarea->tecnico_id) {
            throw new RuntimeException('El ticket no tiene técnico asignado.');
        }

        $soyAsignado = (int) optional($tarea->tecnicos)->usuario_id === $usuarioId;
        if (! $soyAsignado && ! $this->puedeVerTabTodos()) {
            throw new RuntimeException('Solo el técnico asignado o un encargado puede liberar el ticket.');
        }

        DB::transaction(function () use ($ticket, $tarea, $usuarioId) {
            Ticket_Tarea::query()->whereKey($tarea->id)->update(['tecnico_id' => null]);

            $estadoSin = Ticket_Estado::$enumEstado[0]['nombre'];
            $ticket->update(['estado_ticket' => $estadoSin]);

            $comentario = 'Libera el ticket a la cola del área';
            $this->ticketEstadoRepository->creaEstado(
                (int) $ticket->id,
                Carbon::now(),
                $estadoSin,
                $usuarioId,
                $comentario
            );

            $this->ticketTareaNovedadRepository->createUnique([
                'ticket_tarea_id' => (int) $tarea->id,
                'desdefecha' => Carbon::now(),
                'hastafecha' => Carbon::now(),
                'comentario' => $comentario,
                'estado' => Ticket_Tarea_Novedad::$enumEstado[6]['nombre'] ?? 'Reasignar',
                'usuario_id' => $usuarioId,
            ]);
        });
    }

    /**
     * Encargado asigna un técnico concreto (atajo sin pasar por la cola).
     *
     * @return array{ticket_id: int, ticket_tarea_id: int}
     */
    public function asignar(int $ticketId, int $tecnicoTicketId): array
    {
        if (! $this->puedeVerTabTodos()) {
            throw new RuntimeException('No tiene permiso para asignar técnicos.');
        }

        $usuarioId = (int) Auth::id();
        $ticket = Ticket::query()->findOrFail($ticketId);
        $this->assertAreaAccesible((int) $ticket->areadestino_id);

        $tecnico = Tecnico_Ticket::query()->find($tecnicoTicketId);
        if (! $tecnico || (int) $tecnico->areadestino_id !== (int) $ticket->areadestino_id) {
            throw new RuntimeException('Técnico inválido para esta área.');
        }

        return DB::transaction(function () use ($ticket, $tecnico, $usuarioId) {
            $tarea = $this->asegurarTareaTomable((int) $ticket->id, $usuarioId);

            Ticket_Tarea::query()->whereKey($tarea->id)->update(['tecnico_id' => $tecnico->id]);

            $estadoPendiente = Ticket_Estado::$enumEstado[1]['nombre'];
            $ticket->update(['estado_ticket' => $estadoPendiente]);

            $comentario = 'Asigna técnico '.$tecnico->nombre.' (encargado)';
            $this->ticketEstadoRepository->creaEstado(
                (int) $ticket->id,
                Carbon::now(),
                $estadoPendiente,
                $usuarioId,
                $comentario
            );

            $this->ticketTareaNovedadRepository->createUnique([
                'ticket_tarea_id' => (int) $tarea->id,
                'desdefecha' => Carbon::now(),
                'hastafecha' => Carbon::now(),
                'comentario' => $comentario,
                'estado' => Ticket_Tarea_Novedad::$enumEstado[7]['nombre'] ?? 'Asignada',
                'usuario_id' => $usuarioId,
            ]);

            $this->ticketTareaAsignadaNotificacionService->notificar((int) $ticket->id, [[
                'nombre_tarea' => (string) ($tarea->detalle ?? 'Atención del ticket'),
                'fechacarga' => Carbon::now()->toDateString(),
                'fechaprogramacion' => Carbon::now()->toDateString(),
                'tecnico_id' => (int) $tecnico->id,
                'turno_id' => null,
            ]]);

            return [
                'ticket_id' => (int) $ticket->id,
                'ticket_tarea_id' => (int) $tarea->id,
            ];
        });
    }

    /**
     * Técnicos del área para el selector de asignación (incluye fichas aunque el usuario esté suspendido;
     * la data operativa a veces tiene usuario_id compartido / desactualizado).
     *
     * @param  list<int>  $areadestinoIds
     * @return array<int, list<object{id: int, nombre: string}>>
     */
    public function tecnicosOperativosPorAreas(array $areadestinoIds): array
    {
        if ($areadestinoIds === []) {
            return [];
        }

        $rows = Tecnico_Ticket::query()
            ->whereIn('areadestino_id', $areadestinoIds)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'areadestino_id']);

        $out = [];
        foreach ($rows as $tecnico) {
            $areaId = (int) $tecnico->areadestino_id;
            $out[$areaId][] = (object) [
                'id' => (int) $tecnico->id,
                'nombre' => (string) $tecnico->nombre,
            ];
        }

        return $out;
    }

    public function puedeComentarOEditarClaim(Ticket $ticket): bool
    {
        if (! TicketModoOperacionSupport::esClaim((int) $ticket->areadestino_id)) {
            return true;
        }

        if ($this->esSupervisorOAdmin() || $this->esEncargado()) {
            return true;
        }

        $usuarioId = (int) Auth::id();
        if ((int) $ticket->usuario_id === $usuarioId) {
            return true; // creador siempre puede comentar
        }

        $tarea = $this->resolverTareaPrincipal($ticket->loadMissing('ticket_tareas.tecnicos'));
        if (! $tarea || ! $tarea->tecnico_id) {
            return false;
        }

        return (int) optional($tarea->tecnicos)->usuario_id === $usuarioId;
    }

    private function baseQuery(array $areas)
    {
        return Ticket::query()
            ->from('ticket')
            ->select([
                'ticket.id',
                'ticket.fecha',
                'ticket.titulo',
                'ticket.comentario',
                'ticket.estado_ticket',
                'ticket.areadestino_id',
                'ticket.usuario_id',
                'areadestino.nombre as nombreareadestino',
                'sala.nombre as nombresala',
                'usuario.nombre as nombreusuario',
                'ticket_tarea.id as ticket_tarea_id',
                'ticket_tarea.tecnico_id',
                'tecnico_ticket.nombre as nombretecnico',
                'tecnico_ticket.usuario_id as tecnico_usuario_id',
            ])
            ->join('areadestino', 'areadestino.id', '=', 'ticket.areadestino_id')
            ->join('sala', 'sala.id', '=', 'ticket.sala_id')
            ->join('usuario', 'usuario.id', '=', 'ticket.usuario_id')
            ->leftJoin('ticket_tarea', function ($join) {
                $join->on('ticket_tarea.ticket_id', '=', 'ticket.id')
                    ->whereRaw('ticket_tarea.id = (
                        select max(tt2.id) from ticket_tarea tt2
                        where tt2.ticket_id = ticket.id
                    )');
            })
            ->leftJoin('tecnico_ticket', 'tecnico_ticket.id', '=', 'ticket_tarea.tecnico_id')
            ->whereIn('ticket.areadestino_id', $areas)
            ->whereNotIn('ticket.estado_ticket', ['Finalizado']);
    }

    private function aplicarTab($query, string $tab): void
    {
        $usuarioId = (int) Auth::id();

        if ($tab === self::TAB_COLA) {
            $query->where(function ($q) {
                $q->whereNull('ticket_tarea.tecnico_id')
                    ->orWhereNull('ticket_tarea.id');
            })->where(function ($q) {
                $q->where('ticket.estado_ticket', Ticket_Estado::$enumEstado[0]['nombre'])
                    ->orWhereNull('ticket_tarea.tecnico_id');
            });

            return;
        }

        if ($tab === self::TAB_MIOS) {
            $query->where('tecnico_ticket.usuario_id', $usuarioId);

            return;
        }

        // todos: sin filtro extra de técnico
    }

    private function aplicarBusqueda($query, string $q): void
    {
        $q = trim($q);
        if ($q === '') {
            return;
        }

        $query->where(function ($w) use ($q) {
            if (ctype_digit($q)) {
                $w->orWhere('ticket.id', (int) $q);
            }
            $like = '%'.$q.'%';
            $w->orWhere('ticket.titulo', 'like', $like)
                ->orWhere('ticket.comentario', 'like', $like)
                ->orWhere('usuario.nombre', 'like', $like)
                ->orWhere('sala.nombre', 'like', $like)
                ->orWhere('tecnico_ticket.nombre', 'like', $like);
        });
    }

    private function mapearFila(object $row): object
    {
        $fecha = (string) ($row->fecha ?? '');
        $dias = 0;
        if ($fecha !== '') {
            try {
                $dias = (int) Carbon::parse($fecha)->startOfDay()->diffInDays(Carbon::now()->startOfDay());
            } catch (\Throwable) {
                $dias = 0;
            }
        }

        $sinTecnico = empty($row->tecnico_id);
        $usuarioId = (int) Auth::id();
        $esMio = (int) ($row->tecnico_usuario_id ?? 0) === $usuarioId;
        $esClaim = TicketModoOperacionSupport::esClaim((int) ($row->areadestino_id ?? 0));

        return (object) [
            'id' => (int) $row->id,
            'fecha' => $fecha,
            'fecha_fmt' => $fecha !== '' ? Carbon::parse($fecha)->format('d/m/Y') : '—',
            'titulo' => (string) ($row->titulo ?: 'Sin título'),
            'comentario' => (string) ($row->comentario ?? ''),
            'estado' => (string) ($row->estado_ticket ?? ''),
            'areadestino_id' => (int) ($row->areadestino_id ?? 0),
            'areadestino' => (string) ($row->nombreareadestino ?? ''),
            'sala' => (string) ($row->nombresala ?? ''),
            'usuario' => (string) ($row->nombreusuario ?? ''),
            'tecnico' => (string) ($row->nombretecnico ?? ''),
            'tecnico_id' => (int) ($row->tecnico_id ?? 0),
            'ticket_tarea_id' => (int) ($row->ticket_tarea_id ?? 0),
            'sin_tecnico' => $sinTecnico,
            'es_mio' => $esMio,
            'es_claim' => $esClaim,
            'dias' => $dias,
            'urgencia' => $dias >= 5 ? 'urgente' : ($dias >= 2 ? 'atencion' : 'normal'),
            'puede_tomar' => $esClaim && $sinTecnico,
            'puede_liberar' => $esClaim && ! $sinTecnico && ($esMio || $this->puedeVerTabTodos()),
            'puede_asignar' => $this->puedeVerTabTodos(),
            'url_detalle' => route('edita_administracion_ticket', ['id' => (int) $row->id]),
        ];
    }

    private function asegurarTareaTomable(int $ticketId, int $usuarioId): Ticket_Tarea
    {
        $tarea = Ticket_Tarea::query()
            ->where('ticket_id', $ticketId)
            ->orderByDesc('id')
            ->first();

        if ($tarea) {
            return $tarea;
        }

        $ticket = Ticket::query()->findOrFail($ticketId);

        return Ticket_Tarea::query()->create([
            'ticket_id' => $ticketId,
            'tarea_id' => null,
            'detalle' => $ticket->titulo ?: 'Atención del ticket',
            'fechacarga' => Carbon::now()->toDateString(),
            'fechaprogramacion' => Carbon::now()->toDateString(),
            'tecnico_id' => null,
            'creousuario_id' => $usuarioId,
        ]);
    }

    private function resolverTareaPrincipal(Ticket $ticket): ?Ticket_Tarea
    {
        $tareas = $ticket->relationLoaded('ticket_tareas')
            ? $ticket->ticket_tareas
            : Ticket_Tarea::query()->where('ticket_id', $ticket->id)->orderByDesc('id')->get();

        return $tareas->sortByDesc('id')->first();
    }

    private function resolverTecnicoUsuarioParaArea(int $usuarioId, int $areadestinoId): ?Tecnico_Ticket
    {
        return Tecnico_Ticket::query()
            ->where('usuario_id', $usuarioId)
            ->where('areadestino_id', $areadestinoId)
            ->orderBy('id')
            ->first();
    }

    private function assertAreaAccesible(int $areadestinoId): void
    {
        if (! in_array($areadestinoId, $this->areadestinoIdsAccesibles(), true)) {
            throw new RuntimeException('No tiene acceso a tickets de esta área.');
        }
    }

    private function esSupervisorOAdmin(): bool
    {
        if (session()->get('rol_nombre') === 'administrador') {
            return true;
        }
        $permisos = traePermisosUsuario()['permisos'] ?? [];

        return in_array('supervisor-ticket', $permisos, true);
    }

    private function esEncargado(): bool
    {
        $permisos = traePermisosUsuario()['permisos'] ?? [];

        return in_array('encargado-ticket', $permisos, true);
    }

    /**
     * @return list<int>
     */
    private function claimAreaIds(): array
    {
        $fromTecnicos = Tecnico_Ticket::query()
            ->select('areadestino_id')
            ->distinct()
            ->pluck('areadestino_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => TicketModoOperacionSupport::esClaim($id))
            ->values()
            ->all();

        $fromConfig = DB::table('ticket_configuracion_areadestino')
            ->where('modo_operacion', TicketModoOperacionSupport::MODO_CLAIM)
            ->pluck('areadestino_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($fromTecnicos, $fromConfig)));
    }

    /**
     * @return list<int>
     */
    private function todasLasAreasDestino(): array
    {
        $ids = DB::table('areadestino')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($ids !== []) {
            return $ids;
        }

        return Tecnico_Ticket::query()
            ->select('areadestino_id')
            ->distinct()
            ->pluck('areadestino_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
