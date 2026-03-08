<?php

namespace App\Repositories\Presupuesto;

use App\Models\Presupuesto\Partidagasto;
use App\Models\Presupuesto\Partidagasto_Archivo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Auth;

class Partidagasto_ArchivoRepository implements Partidagasto_ArchivoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Partidagasto_Archivo $partidagasto_archivo)
    {
        $this->model = $partidagasto_archivo;
    }

    public function create($request, $id)
    {
		return self::guardaPartidagasto_Archivo($request, 'create', $id);
    }

    public function update($request, $id)
    {
		return self::guardaPartidagasto_Archivo($request, 'update', $id);
    }

    public function delete($partidagasto_id)
    {
        return $this->model->where('partidagasto_id', $partidagasto_id)->delete();
    }

    public function find($id)
    {
        if (null == $partidagasto_archivo = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $partidagasto_archivo;
    }

    public function findOrFail($id)
    {
        if (null == $partidagasto_archivo = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $partidagasto_archivo;
    }

	private function guardaPartidagasto_Archivo($request, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Borra los registros antes de grabar nuevamente
       		$this->delete($id);
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
					$path = public_path()."/storage/archivos/partidagasto/".$id;
    				$file = $archivo->getClientOriginalName();
    				$fileName = $path . '-' . $archivo->getClientOriginalName();
	
    				$archivo->move($path, $fileName);

					// Guarda en ERP
					$partidagasto_archivo = $this->model->create([
									'partidagasto_id' => $id,
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
					$partidagasto_archivo = $this->model->create([
									'partidagasto_id' => $id,
									'nombrearchivo' => $request->nombresanteriores[$i_archivo],
									]);
				}
			}
		}
		$retorno = $partidagasto_archivo ?? '1';
		return $retorno;
	}

	public function copiaArchivo($id, $nombreArchivo, $idDestino)
	{
		// Guarda fisicamente el archivo
		$path = public_path()."/storage/archivos/partidagasto/".$id;
		$pathDestino = public_path()."/storage/archivos/partidagasto/".$idDestino;
		$fileName = $path . '-' . $nombreArchivo;

		system("mkdir ".$pathDestino);

		$cmd = "cp ".$path.'/'.$nombreArchivo.' '.$pathDestino.'/'.$nombreArchivo;
		system($cmd);

		$partidagasto_archivo = $this->model->create([
			'partidagasto_id' => $idDestino,
			'nombrearchivo' => $nombreArchivo,
			]);
	}
}
