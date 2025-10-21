<?php

namespace App\Queries\Ticket;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\Ticket_Estado;
use App\Models\Ticket\Ticket_Tarea;
use App\Repositories\Ticket\Tecnico_TicketRepositoryInterface;
use App\Models\Admin\Permiso;
use Auth;
use DB;

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
        $flUsuario = $flTecnico = $flSupervisor = $flEncargado = false;

        $rolId = session()->get('rol_id');
        $permisos = cache()->tags('Permiso')->rememberForever("Permiso.rolid.$rolId", function () {
                return Permiso::whereHas('roles', function ($query) {
                    $query->where('rol_id', session()->get('rol_id'));
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

        $select = [ 'ticket.id as id',
                    'ticket.fecha as fecha',
                    'sala.nombre as nombresala',
                    'sector_ticket.nombre as nombresector',
                    'areadestino.nombre as nombreareadestino',
                    'subcategoria_ticket.nombre as nombresubcategoria_ticket',
                    'categoria_ticket.nombre as nombrecategoria_ticket',
                    'ticket.detalle as detalle',
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
                                ->join('subcategoria_ticket', 'subcategoria_ticket.id', '=', 'ticket.subcategoria_ticket_id')
                                ->join('categoria_ticket', 'categoria_ticket.id', '=', 'subcategoria_ticket.categoria_ticket_id');

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
        $columns[] = ['columna' => 'ticket.detalle',
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

        // Filtra tickets por tipo de usuario
        if ($flEncargado) // Encargado ve todo lo de su area
            $tickets->where('ticket.areadestino_id', $areadestino_id);

        if ($flTecnico) // Tecnico ve solo sus tickets de su area
            $tickets->where('ticket.areadestino_id', $areadestino_id)
                    ->where('ticket.usuario_id', $usuario_id);

        if ($flUsuario) // Usuario ve solo sus tickets de cualquier area
            $tickets->where('ticket.usuario_id', $usuario_id);

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

    public function leeTicketAdministracion($busqueda, $caja_id, $flPaginando = null)
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
        $flUsuario = $flTecnico = $flSupervisor = $flEncargado = false;

        $rolId = session()->get('rol_id');
        $permisos = cache()->tags('Permiso')->rememberForever("Permiso.rolid.$rolId", function () {
                return Permiso::whereHas('roles', function ($query) {
                    $query->where('rol_id', session()->get('rol_id'));
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

        $select = [ 'ticket.id as id',
                    'ticket.fecha as fecha',
                    'sala.nombre as nombresala',
                    'sector_ticket.nombre as nombresector',
                    'areadestino.nombre as nombreareadestino',
                    'subcategoria_ticket.nombre as nombresubcategoria_ticket',
                    'categoria_ticket.nombre as nombrecategoria_ticket',
                    'ticket.detalle as detalle',
                    'ticket.usuario_id as usuario_id',
                    'usuario.nombre as nombreusuario',
                    'ticket.estado_ticket as estado',
                    'nombretecnico'
                    ];

        $tickets = $this->ticketModel->select($select)
                                ->leftJoinSub(function ($query) {
                                    $query->select('ticket_tarea.tecnico_id', 'ticket_tarea.ticket_id', 'tecnico_ticket.nombre as nombretecnico')
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
                                ->join('subcategoria_ticket', 'subcategoria_ticket.id', '=', 'ticket.subcategoria_ticket_id')
                                ->join('categoria_ticket', 'categoria_ticket.id', '=', 'subcategoria_ticket.categoria_ticket_id');

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
        $columns[] = ['columna' => 'ticket.detalle',
                    'clausula' => 'LIKE'];    
        $columns[] = ['columna' => 'usuario.nombre',
                    'clausula' => 'LIKE'];       
        $columns[] = ['columna' => 'nombretecnico',
                    'clausula' => 'LIKE'];   
        $columns[] = ['columna' => 'estado_ticket',
                    'clausula' => 'LIKE'];                                                                        
        $columns[] = ['columna' => 'ticket.id',
                    'clausula' => '='];
        $columns[] = ['columna' => 'ticket.fecha',
                    'clausula' => '='];
        $count = count($columns);

        $tickets->where('deleted_at', null);
        
        $tickets->where(function ($query) use ($count, $busqueda, $columns, $flSupervisor, $flTecnico, 
                                                $flUsuario, $flEncargado,
                                                $usuario_id, $areadestino_id) {
                                    
                                    // Filtra tickets por tipo de usuario
                                    if ($flEncargado) // Encargado ve todo lo de su area
                                        $query->where('ticket.areadestino_id', $areadestino_id);

                                    if ($flTecnico) // Tecnico ve solo sus tickets de su area
                                        $query->where('ticket.areadestino_id', $areadestino_id);

                                    if ($flUsuario) // Usuario ve solo sus tickets de cualquier area
                                        $query->where('ticket.usuario_id', $usuario_id);

                                    $query->orWhere(DB::raw('(select ticket_estado.estado from ticket_estado 
                                            where ticket_estado.ticket_id = ticket.id order by id desc limit 1)'), 'LIKE', '%'.$busqueda.'%');

                        			for ($i = 0; $i < $count; $i++)
                                    {
                                        if ($columns[$i]['clausula'] == 'LIKE')
                            			    $query->orWhere($columns[$i]['columna'], "LIKE", '%'. $busqueda . '%');
                                        else
                                            $query->orWhere($columns[$i]['columna'], $columns[$i]['clausula'], $busqueda);
                                    }
                            });

        // Ordena desc. por ID
        $tickets->orderBy('ticket.id', 'desc');

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
}

