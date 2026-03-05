<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Archivo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;

class Articulo_ArchivoRepository implements Articulo_ArchivoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Articulo_Archivo $articulo_archivo)
    {
        $this->model = $articulo_archivo;
    }

    public function create($request, $id)
    {
		return self::guardaArticulo_Archivo($request, 'create', $id);
    }

    public function update($request, $id)
    {
		return self::guardaArticulo_Archivo($request, 'update', $id);
    }

    public function delete($articulo_id, $codigo)
    {
        return $this->model->where('articulo_id', $articulo_id)->delete();
    }

    public function find($id)
    {
        if (null == $articulo_archivo = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $articulo_archivo;
    }

    public function findOrFail($id)
    {
        if (null == $articulo_archivo = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $articulo_archivo;
    }

	private function guardaArticulo_Archivo($request, $funcion, $id = null)
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
					$path = public_path()."/storage/archivos/articulos/".$id;
    				$file = $archivo->getClientOriginalName();
    				$fileName = $path . '-' . $archivo->getClientOriginalName();
	
    				$archivo->move($path, $fileName);

					// Guarda en ERP
					$articulo_archivo = $this->model->create([
									'articulo_id' => $id,
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
					$articulo_archivo = $this->model->create([
									'articulo_id' => $id,
									'nombrearchivo' => $request->nombresanteriores[$i_archivo],
									]);
				}
			}
		}
		$retorno = $articulo_archivo ?? '1';
		return $retorno;
	}

	public function copiaArchivo($id, $nombreArchivo, $idDestino)
	{
		// Guarda fisicamente el archivo
		$path = public_path()."/storage/archivos/cobranzas/".$id;
		$pathDestino = public_path()."/storage/archivos/cobranzas/".$idDestino;
		$fileName = $path . '-' . $nombreArchivo;

		system("mkdir ".$pathDestino);

		$cmd = "cp ".$path.'/'.$nombreArchivo.' '.$pathDestino.'/'.$nombreArchivo;
		system($cmd);

		$articulo_archivo = $this->model->create([
			'articulo_id' => $idDestino,
			'nombrearchivo' => $nombreArchivo,
			]);
	}
}
