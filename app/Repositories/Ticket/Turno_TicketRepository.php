<?php

namespace App\Repositories\Ticket;

use App\Models\Ticket\Turno_Ticket;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Turno_TicketRepository implements Turno_TicketRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Turno_Ticket $turno
                                )
    {
        $this->model = $turno;
    }

    public function all()
    {
        $turno = $this->model;

        return $turno->get();
    }

    public function create(array $data)
    {
        $turno = $this->model->create($data);

        return($turno);
    }

    public function update(array $data, $id)
    {
        $turno = $this->model->findOrFail($id)->update($data);

		return $turno;
    }

    public function delete($id)
    {
    	$turno = $this->model->find($id);

        $turno = $this->model->destroy($id);

		return $turno;
    }

    public function find($id)
    {
        if (null == $turno = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $turno;
    }

    public function findOrFail($id)
    {
        if (null == $turno = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $turno;
    }

}
