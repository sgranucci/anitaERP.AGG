<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Cliente_Uif;
use App\Models\Uif\Cliente_Premio_Archivo_Uif;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;

class Cliente_Premio_Archivo_UifRepository implements Cliente_Premio_Archivo_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente_Premio_Archivo_Uif $cliente_premio_archivo_uif)
    {
        $this->model = $cliente_premio_archivo_uif;
    }

    public function create($request, $id)
    {
		return self::guardaCliente_Premio_Archivo_Uif($request, 'create', $id);
    }

    public function update($request, $id)
    {
		return self::guardaCliente_Premio_Archivo_Uif($request, 'update', $id);
    }

    public function delete($cliente_premio_uif_id)
    {
        return $this->model->where('cliente_premio_uif_id', $cliente_premio_uif_id)->delete();
    }

    public function find($id)
    {
        if (null == $cliente_premio_archivo_uif = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_premio_archivo_uif;
    }

    public function findOrFail($id)
    {
        if (null == $cliente_premio_archivo_uif = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_premio_archivo_uif;
    }

	private function guardaCliente_Premio_Archivo_Uif($request, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Borra los registros antes de grabar nuevamente
       		$this->delete($id, $request->codigo);
		}
		$nombrearchivos = $request->file('nombrearchivos');

		// Recorre todos los files nuevos
		if ($nombrearchivos ?? '')
		{
			foreach ($nombrearchivos as $archivo)
			{
		  		if ($archivo)
				{
					// Guarda fisicamente el archivo
					$path = public_path()."/storage/archivos/clientes_premios_uif/";
    				$file = $archivo->getClientOriginalName();
    				$fileName = $path . $id . '-' . $archivo->getClientOriginalName();

    				$archivo->move($path, $fileName);

					// Guarda en ERP
					$cliente_premio_archivo_uif = $this->model->create([
									'cliente_premio_uif_id' => $id,
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
					$cliente_premio_archivo_uif = $this->model->create([
									'cliente_premio_uif_id' => $id,
									'nombrearchivo' => $request->nombresanteriores[$i_archivo],
									]);
				}
			}
		}
		$retorno = $cliente_premio_archivo_uif ?? '1';
		return $retorno;
	}

}
