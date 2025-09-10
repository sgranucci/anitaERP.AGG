<?php

namespace App\Repositories\Ticket;

use App\Models\Ticket\Ticket;
use App\Repositories\Ticket\TicketRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Carbon\Carbon;
use Auth;
use DB;

class TicketRepository implements TicketRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
	public function __construct(Ticket $ticket)
    {
        $this->model = $ticket;
    }

    public function create(array $data)
    {
		$data['usuario_id'] = Auth::user()->id;
		
		return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
		$ticket = $this->model->findOrFail($id)->update($data);

		return $ticket;
    }

    public function delete($id)
    {
		$ticket = $this->model->findOrFail($id);

		if ($ticket)
        	$ticket = $this->model->destroy($id);

		return $ticket;
    }

    public function find($id)
    {
        if (null == $ticket = $this->model->with("ticket_estados")
									->with("ticket_tareas")
									->with("ticket_articulos")
									->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ticket;
    }

    public function findOrFail($id)
    {
        if (null == $ticket = $this->model->with("ticket_estados")
									->with("ticket_tareas")
									->with("ticket_articulos")
									->findOrFail($id))
			{
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $ticket;
    }
}
