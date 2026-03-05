<?php

namespace App\Repositories\Ticket;

use App\Models\Ticket\Areadestino;
use App\Repositories\Ticket\Tecnico_TicketRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class AreadestinoRepository implements AreadestinoRepositoryInterface
{
    protected $model;
    protected $tecnico_ticketRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Areadestino $areadestino,
                                Tecnico_TicketRepositoryInterface $tecnico_ticketrepository
                                )
    {
        $this->model = $areadestino;
        $this->tecnico_ticketRepository = $tecnico_ticketrepository;
    }

    public function all()
    {
        $areadestino = $this->model;
        
        $permiso = chequeaPermisoTicket();

        // Lee el area de destino
        $usuario_id = Auth::user()->id;
        $tecnico_ticket = $this->tecnico_ticketRepository->leePorUsuarioId($usuario_id);

        $areadestino_id = 0;
        if (count($tecnico_ticket)>0)
        {
            if ($tecnico_ticket)
                $areadestino_id = $tecnico_ticket[0]->areadestino_id;

            if ($areadestino_id != 0)
                $areadestino = $areadestino->where('id', $areadestino_id)->orderBy('nombre')->get();
            else
                $areadestino = $areadestino->orderBy('nombre')->get();
        }
        else
            $areadestino = $areadestino->orderBy('nombre')->get();

        return $areadestino;
    }

    public function create(array $data)
    {
        $areadestino = $this->model->create($data);

        return($areadestino);
    }

    public function update(array $data, $id)
    {
        $areadestino = $this->model->findOrFail($id)->update($data);

		return $areadestino;
    }

    public function delete($id)
    {
    	$areadestino = $this->model->find($id);

        $areadestino = $this->model->destroy($id);

		return $areadestino;
    }

    public function find($id)
    {
        if (null == $areadestino = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $areadestino;
    }

    public function findOrFail($id)
    {
        if (null == $areadestino = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $areadestino;
    }

}
