<?php

namespace App\Queries\Ticket;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\Ticket_Estado;
use App\Models\Ticket\Ticket_Tarea;
use App\Repositories\Ticket\Tecnico_TicketRepositoryInterface;
use App\Models\Admin\Permiso;
use App\Support\Cache\PermisoCacheSupport;
use App\Support\Ticket\TicketAlcanceCentrocostoSupport;
use Auth;
use DB;
use Carbon\Carbon;

class TicketQuery implements TicketQueryInterface
{
    protected $ticketModel;
    protected $tecnico_ticketRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Ticket $ticketmodel,
                                Tecnico_TicketRepositoryInterface $tecnico_ticketrepository)
    {
        $this->ticketModel = $ticketmodel;
        $this->tecnico_ticketRepository = $tecnico_ticketrepository;
    }

    public function first()
    {
        return $this->ticketModel->first();
    }

    public function all()
    {
        return $this->ticketModel->get();
    }

    public function allQuery(array $campos)
    {
        return $this->ticketModel->select($campos)->get();
    }

    public function leeTicket($busqueda, $caja_id, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        // lee usuario para setear filtros
        $usuario_id = Auth::user()->id;

        // Lee el area de destino
        $tecnico_ticket = $this->tecnico_ticketRepository->leePorUsuarioId($usuario_id);

        $areadestino_id = 0;
        if (count($tecnico_ticket)>0)            
            $areadestino_id = $tecnico_ticket[0]->areadestino_id;

        // Verifica permisos
        $flUsuario = $flTecnico = $flSupervisor = $flEncargado = $flAdminSector = false;

        $rolId = session()->get('rol_id');
        $permisos = PermisoCacheSupport::rememberSlugsPorRol($rolId, function () use ($rolId) {
                return Permiso::whereHas('roles', function ($query) use ($rolId) {
                    $query->where('rol.id', $rolId);
                })->get()->pluck('slug')->toArray();
            });
        if (in_array('usuario-ticket', $permisos))         
            $flUsuario = true;

        if (in_array('tecnico-ticket', $permisos))   
            $flTecnico = true;

        if (in_array('encargado-ticket', $permisos))   
            $flEncargado = true;

        if (in_array('supervisor-ticket', $permisos))   
            $flSupervisor = true;

        if (in_array('admin-ticket-sector', $permisos)) {
            $flAdminSector = true;
        }

        $select = [ 'ticket.id as id',
                    'ticket.fecha as fecha',
                    'sala.nombre as nombresala',
                    'sector_ticket.nombre as nombresector',
                    'areadestino.nombre as nombreareadestino',
                    'subcategoria_ticket.nombre as nombresubcategoria_ticket',
                    'categoria_ticket.nombre as nombrecategoria_ticket',
                    'ticket.titulo as titulo',
                    'ticket.comentario as comentario',
                    'ticket.usuario_id as usuario_id',
                    'ticket.estado_ticket as estado',
                    'usuario.nombre as nombreusuario'
                    ];

        $tickets = $this->ticketModel->select($select)
                                    ->addSelect([
                                        'tecnico_id' => Ticket_Tarea::query()
                                            ->select('ticket_tarea.tecnico_id')
                                            ->where('deleted_at', null)
                                            ->whereColumn('ticket_tarea.ticket_id', 'ticket.id')
                                            ->latest()
                                            ->take(1)
                                    ])
                                ->join('sala', 'sala.id', '=', 'ticket.sala_id')
                                ->join('sector_ticket', 'sector_ticket.id', '=', 'ticket.sector_id')
                                ->join('areadestino', 'areadestino.id', '=', 'ticket.areadestino_id')
                                ->join('usuario', 'usuario.id', '=', 'ticket.usuario_id')
                                ->leftJoin('subcategoria_ticket', 'subcategoria_ticket.id', '=', 'ticket.subcategoria_ticket_id')
                                ->leftJoin('categoria_ticket', 'categoria_ticket.id', '=', 'subcategoria_ticket.categoria_ticket_id');

        $clausulaOrWhere2 = [
            ['ticket.id', '=', $busqueda],
            ['ticket.fecha', '=', $busqueda]
        ];

        $columns[] = ['columna' => 'sala.nombre', 
                    'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'sector_ticket.nombre',
                    'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'areadestino.nombre',
                    'clausula' => 'LIKE']; 
        $columns[] = ['columna' => 'categoria_ticket.nombre',
                    'clausula' => 'LIKE']; 
        $columns[] = ['columna' => 'subcategoria_ticket.nombre',
                    'clausula' => 'LIKE']; 
        $columns[] = ['columna' => 'ticket.titulo',
                    'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'ticket.comentario',
                    'clausula' => 'LIKE'];    
        $columns[] = ['columna' => 'usuario.nombre',
                    'clausula' => 'LIKE'];            
        $columns[] = ['columna' => 'estado_ticket',
                    'clausula' => 'LIKE'];                                                            
        $columns[] = ['columna' => 'ticket.id',
                    'clausula' => '='];
        $columns[] = ['columna' => 'ticket.fecha',
                    'clausula' => '='];
        $count = count($columns);

        $tickets->where('deleted_at', null);

        // Carga de Tickets — prioridad:
        // supervisor (sin filtro) > admin-ticket-sector (mismo CC del emisor)
        // > usuario-ticket (propios) > encargado / tecnico por área.
        if ($flSupervisor) {
            // sin filtro de alcance
        } elseif ($flAdminSector) {
            TicketAlcanceCentrocostoSupport::aplicarFiltroEmisoresMismoCentrocosto($tickets);
        } elseif ($flUsuario) {
            $tickets->where('ticket.usuario_id', $usuario_id);
        } elseif ($flEncargado) {
            $tickets->where('ticket.areadestino_id', $areadestino_id);
        } elseif ($flTecnico) {
            $tickets->where('ticket.areadestino_id', $areadestino_id)
                ->where('ticket.usuario_id', $usuario_id);
        }

        $tickets->where(function ($query) use ($count, $busqueda, $columns, $flSupervisor, $flTecnico, $flUsuario, $flEncargado,
                                                $usuario_id, $areadestino_id) {

                        			for ($i = 0; $i < $count; $i++)
                                    {
                                        if ($columns[$i]['clausula'] == 'LIKE')
                            			    $query->orWhere($columns[$i]['columna'], "LIKE", '%'. $busqueda . '%');
                                        else
                                            $query->orWhere($columns[$i]['columna'], $columns[$i]['clausula'], $busqueda);
                                    }
                            });

        // Ordena desc. por ID
        $tickets->orderBy('id', 'desc');

        if (isset($flPaginando))
        {
            if ($flPaginando)
                $tickets = $tickets->paginate(10);
            else
                $tickets = $tickets->get();
        }
        else
            $tickets = $tickets->get();

        return $tickets;
    }

    public function leeTicketAdministracion(array $filtros, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if (! is_array($filtros)) {
            $filtros = \App\Support\Ticket\AdministracionTicketListadoFiltros::filtrosVacios();
        }

        $usuario_id = Auth::user()->id;

        $tecnico_ticket = $this->tecnico_ticketRepository->leePorUsuarioId($usuario_id);

        $areadestino_id = 0;
        if (count($tecnico_ticket) > 0) {
            $areadestino_id = (int) $tecnico_ticket[0]->areadestino_id;
        }

        $flUsuario = $flTecnico = $flSupervisor = $flEncargado = false;

        $rolId = session()->get('rol_id');
        $permisos = PermisoCacheSupport::rememberSlugsPorRol($rolId, function () use ($rolId) {
            return Permiso::whereHas('roles', function ($query) use ($rolId) {
                $query->where('rol.id', $rolId);
            })->get()->pluck('slug')->toArray();
        });
        if (in_array('usuario-ticket', $permisos)) {
            $flUsuario = true;
        }

        if (in_array('tecnico-ticket', $permisos)) {
            $flTecnico = true;
        }

        if (in_array('encargado-ticket', $permisos)) {
            $flEncargado = true;
        }

        if (in_array('supervisor-ticket', $permisos)) {
            $flSupervisor = true;
        }

        $select = ['ticket.id as id',
            'ticket.fecha as fecha',
            'sala.nombre as nombresala',
            'sector_ticket.nombre as nombresector',
            'areadestino.nombre as nombreareadestino',
            'subcategoria_ticket.nombre as nombresubcategoria_ticket',
            'categoria_ticket.nombre as nombrecategoria_ticket',
            'ticket.titulo as titulo',
            'ticket.comentario as comentario',
            'ticket.usuario_id as usuario_id',
            'usuario.nombre as nombreusuario',
            'ticket.estado_ticket as estado',
            'ticket.fecha_resolucion as fecha_resolucion',
            'ticket.hora_resolucion as hora_resolucion',
            'ticket.tiempo_insumido_total as tiempo_insumido_total',
            'nombretecnico',
            'tecnico_id',
            'tecnico_usuario_id',
        ];

        $tickets = $this->ticketModel->select($select)
            ->leftJoinSub(function ($query) {
                $query->select('ticket_tarea.tecnico_id as tecnico_id',
                    'ticket_tarea.ticket_id',
                    'tecnico_ticket.nombre as nombretecnico',
                    'tecnico_ticket.usuario_id as tecnico_usuario_id')
                    ->from('ticket_tarea')
                    ->where('deleted_at', null)
                    ->join('tecnico_ticket', 'tecnico_ticket.id', '=', 'ticket_tarea.tecnico_id')
                    ->groupBy('ticket_tarea.ticket_id')
                    ->orderBy('ticket_tarea.id', 'desc');
            }, 'tickets_tarea', function ($join) {
                $join->on('tickets_tarea.ticket_id', '=', 'ticket.id');
            })
            ->join('sala', 'sala.id', '=', 'ticket.sala_id')
            ->join('sector_ticket', 'sector_ticket.id', '=', 'ticket.sector_id')
            ->join('areadestino', 'areadestino.id', '=', 'ticket.areadestino_id')
            ->join('usuario', 'usuario.id', '=', 'ticket.usuario_id')
            ->leftJoin('subcategoria_ticket', 'subcategoria_ticket.id', '=', 'ticket.subcategoria_ticket_id')
            ->leftJoin('categoria_ticket', 'categoria_ticket.id', '=', 'subcategoria_ticket.categoria_ticket_id')
            ->with('ticket_tareas');

        $tickets->where('deleted_at', null);

        $tecnicoUsuarioFiltro = (int) ($filtros['tecnico_usuario_id'] ?? 0);
        $sistemasAreaId = (int) config('ticket.administracion_sistemas_areadestino_id', 1);

        // Adm. de Tickets: no aplica usuario-ticket (ese alcance es solo en Carga).
        // Prioridad: filtro técnico UI > encargado > tecnico > supervisor (todo) > área sistemas.
        if ($tecnicoUsuarioFiltro > 0) {
            $tickets->whereHas('ticket_tareas.tecnicos', function ($query) use ($tecnicoUsuarioFiltro) {
                $query->where('usuario_id', $tecnicoUsuarioFiltro);
            });
        } elseif ($flEncargado) {
            $tickets->where('ticket.areadestino_id', $areadestino_id);
        } elseif ($flTecnico) {
            $tickets->where('ticket.areadestino_id', $areadestino_id);
        } elseif (! $flSupervisor) {
            $tickets->where('ticket.areadestino_id', $sistemasAreaId);
        }

        \App\Support\Ticket\AdministracionTicketListadoFiltros::aplicar($tickets, $filtros);

        $tickets->orderBy('ticket.id', 'desc');

        if (isset($flPaginando)) {
            if ($flPaginando) {
                $tickets = $tickets->paginate(10);
            } else {
                $tickets = $tickets->get();
            }
        } else {
            $tickets = $tickets->get();
        }

        return $tickets;
    }
}

