<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Caja_Movimiento_Archivo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;

class Caja_Movimiento_ArchivoRepository implements Caja_Movimiento_ArchivoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Caja_Movimiento_Archivo $caja_movimiento_archivo)
    {
        $this->model = $caja_movimiento_archivo;
    }

    public function create($request, $id)
    {
		return self::guardaCaja_Movimiento_Archivo($request, 'create', $id);
    }

    public function update($request, $id)
    {
		return self::guardaCaja_Movimiento_Archivo($request, 'update', $id);
    }

    public function delete($caja_movimiento_id, $codigo)
    {
        return $this->model->where('caja_movimiento_id', $caja_movimiento_id)->delete();
    }

    public function find($id)
    {
        if (null == $caja_movimiento_archivo = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $caja_movimiento_archivo;
    }

    public function findOrFail($id)
    {
        if (null == $caja_movimiento_archivo = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $caja_movimiento_archivo;
    }

	private function guardaCaja_Movimiento_Archivo($request, $funcion, $id = null)
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
					$path = public_path()."/storage/archivos/caja_movimientos/".$id;
    				$file = $archivo->getClientOriginalName();
    				$fileName = $path . '-' . $archivo->getClientOriginalName();
	
    				$archivo->move($path, $fileName);

					// Guarda en ERP
					$caja_movimiento_archivo = $this->model->create([
									'caja_movimiento_id' => $id,
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
					$caja_movimiento_archivo = $this->model->create([
									'caja_movimiento_id' => $id,
									'nombrearchivo' => $request->nombresanteriores[$i_archivo],
									]);
				}
			}
		}
		$retorno = $caja_movimiento_archivo ?? '1';
		return $retorno;
	}

	public function copiaArchivo($id, $nombreArchivo, $idDestino)
	{
		// Guarda fisicamente el archivo
		$path = public_path()."/storage/archivos/caja_movimientos/".$id;
		$pathDestino = public_path()."/storage/archivos/caja_movimientos/".$idDestino;
		$fileName = $path . '-' . $nombreArchivo;

		system("mkdir ".$pathDestino);

		$cmd = "cp ".$path.'/'.$nombreArchivo.' '.$pathDestino.'/'.$nombreArchivo;
		system($cmd);

		$caja_movimiento_archivo = $this->model->create([
			'caja_movimiento_id' => $idDestino,
			'nombrearchivo' => $nombreArchivo,
			]);
	}
}
