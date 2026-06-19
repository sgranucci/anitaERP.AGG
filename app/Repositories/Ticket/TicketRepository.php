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
		$data = $this->normalizarSubcategoriaTicketId($data);

		return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
		$data = $this->normalizarSubcategoriaTicketId($data);
		$ticket = $this->model->findOrFail($id)->update($data);

		return $ticket;
    }

	private function normalizarSubcategoriaTicketId(array $data): array
	{
		if (! array_key_exists('subcategoria_ticket_id', $data) || $data['subcategoria_ticket_id'] === '' || $data['subcategoria_ticket_id'] === null) {
			$data['subcategoria_ticket_id'] = null;
		}

		return $data;
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
                  ->with("ticket_archivos")
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
                  ->with("ticket_archivos")
									->findOrFail($id))
			{
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $ticket;
    }
}
