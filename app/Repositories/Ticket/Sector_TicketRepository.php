<?php

namespace App\Repositories\Ticket;

use App\Models\Ticket\Sector_Ticket;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Sector_TicketRepository implements Sector_TicketRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Sector_Ticket $sector_ticket
                                )
    {
        $this->model = $sector_ticket;
    }

    public function all()
    {
        $sector_ticket = $this->model;

        return $sector_ticket->orderBy('nombre')->get();
    }

    public function create(array $data)
    {
        $sector_ticket = $this->model->create($data);

        return($sector_ticket);
    }

    public function update(array $data, $id)
    {
        $sector_ticket = $this->model->findOrFail($id)->update($data);

		return $sector_ticket;
    }

    public function delete($id)
    {
    	$sector_ticket = $this->model->find($id);

        $sector_ticket = $this->model->destroy($id);

		return $sector_ticket;
    }

    public function find($id)
    {
        if (null == $sector_ticket = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $sector_ticket;
    }

    public function findOrFail($id)
    {
        if (null == $sector_ticket = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $sector_ticket;
    }

}
