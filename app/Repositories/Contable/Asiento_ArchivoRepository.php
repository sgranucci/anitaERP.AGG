<?php

namespace App\Repositories\Contable;

use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Archivo;
use App\Http\Requests\ValidacionAsiento;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;

class Asiento_ArchivoRepository implements Asiento_ArchivoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Asiento_Archivo $asiento_archivo)
    {
        $this->model = $asiento_archivo;
    }

    public function create($request, $id)
    {
		return self::guardaAsiento_Archivo($request, 'create', $id);
    }

    public function update(ValidacionAsiento $request, $id)
    {
		return self::guardaAsiento_Archivo($request, 'update', $id);
    }

    public function delete($asiento_id, $codigo)
    {
        return $this->model->where('asiento_id', $asiento_id)->delete();
    }

    public function find($id)
    {
        if (null == $asiento_archivo = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $asiento_archivo;
    }

    public function findOrFail($id)
    {
        if (null == $asiento_archivo = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $asiento_archivo;
    }

	private function guardaAsiento_Archivo($request, $funcion, $id = null)
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
					$path = public_path()."/storage/archivos/asientos/".$id;
    				$file = $archivo->getClientOriginalName();
    				$fileName = $path . '-' . $archivo->getClientOriginalName();
	
    				$archivo->move($path, $fileName);

					// Guarda en ERP
					$asiento_archivo = $this->model->create([
									'asiento_id' => $id,
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
					$asiento_archivo = $this->model->create([
									'asiento_id' => $id,
									'nombrearchivo' => $request->nombresanteriores[$i_archivo],
									]);
				}
			}
		}
		$retorno = $asiento_archivo ?? '1';
		return $retorno;
	}

	public function copiaArchivo($id, $nombreArchivo, $idDestino)
	{
		// Guarda fisicamente el archivo
		$path = public_path()."/storage/archivos/asientos/".$id;
		$pathDestino = public_path()."/storage/archivos/asientos/".$idDestino;
		$fileName = $path . '-' . $nombreArchivo;

		system("mkdir ".$pathDestino);

		$cmd = "cp ".$path.'/'.$nombreArchivo.' '.$pathDestino.'/'.$nombreArchivo;
		system($cmd);

		$asiento_archivo = $this->model->create([
			'asiento_id' => $idDestino,
			'nombrearchivo' => $nombreArchivo,
			]);
	}
}
