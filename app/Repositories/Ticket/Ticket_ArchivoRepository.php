<?php

namespace App\Repositories\Ticket;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\Ticket_Archivo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Auth;

class Ticket_ArchivoRepository implements Ticket_ArchivoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Ticket_Archivo $ticket_archivo)
    {
        $this->model = $ticket_archivo;
    }

    public function create($request, $id)
    {
		return self::guardaTicket_Archivo($request, 'create', $id);
    }

    public function update($request, $id)
    {
		return self::guardaTicket_Archivo($request, 'update', $id);
    }

    public function delete($ticket_id, $codigo)
    {
        return $this->model->where('ticket_id', $ticket_id)->delete();
    }

    public function find($id)
    {
        if (null == $ticket_archivo = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ticket_archivo;
    }

    public function findOrFail($id)
    {
        if (null == $ticket_archivo = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ticket_archivo;
    }

	private function guardaTicket_Archivo($request, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Borra los registros antes de grabar nuevamente
       		$this->delete($id, $request->codigo);
		}
		$nombrearchivos = $request->file('nombrearchivos');
	  	$lineaAnita = 0;
		// Recorre todos los files nuevos
		if ($nombrearchivos ?? '')
		{
			foreach ($nombrearchivos as $archivo)
			{
		  		if ($archivo)
				{
					// Guarda fisicamente el archivo
					$path = public_path()."/storage/archivos/tickets/".$id;
    				$file = $archivo->getClientOriginalName();
    				$fileName = $path . '-' . $archivo->getClientOriginalName();
	
    				$archivo->move($path, $fileName);

					// Guarda en ERP
					$ticket_archivo = $this->model->create([
									'ticket_id' => $id,
									'nombrearchivo' => $id.'-'.$file,
									]);
				}
			}
		}

		// Recorre los files originales para agregarlos
		if ($request->nombresanteriores ?? '')
		{
			for ($i_archivo = 0; $i_archivo < count($request->nombresanteriores); $i_archivo++)
			{
				// Busca en los files agregados si el archivo es uno nuevo
				$fl_encontro = false;
				if ($nombrearchivos)
				{
					foreach($nombrearchivos as $archivo)
					{
						if ($archivo)
						{
							// Guarda fisicamente el archivo
							$file = $archivo->getClientOriginalName();
		
							if ($file == $request->nombresanteriores[$i_archivo])
								$fl_encontro = true;
						}
					}
				}
				// Agrega el archivo anterior no tocado
				if (!$fl_encontro && $request->nombresanteriores[$i_archivo] != '')
				{
					$ticket_archivo = $this->model->create([
									'ticket_id' => $id,
									'nombrearchivo' => $request->nombresanteriores[$i_archivo],
									]);
				}
			}
		}
		$retorno = $ticket_archivo ?? '1';
		return $retorno;
	}

	public function copiaArchivo($id, $nombreArchivo, $idDestino)
	{
		// Guarda fisicamente el archivo
		$path = public_path()."/storage/archivos/tickets/".$id;
		$pathDestino = public_path()."/storage/archivos/tickets/".$idDestino;
		$fileName = $path . '-' . $nombreArchivo;

		system("mkdir ".$pathDestino);

		$cmd = "cp ".$path.'/'.$nombreArchivo.' '.$pathDestino.'/'.$nombreArchivo;
		system($cmd);

		$ticket_archivo = $this->model->create([
			'ticket_id' => $idDestino,
			'nombrearchivo' => $nombreArchivo,
			]);
	}
}
