<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Archivo;
use App\Http\Requests\ValidacionProveedor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;

class Proveedor_ArchivoRepository implements Proveedor_ArchivoRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'proarch';
    protected $keyFieldAnita = 'proma_proveedor';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Proveedor_Archivo $proveedor_archivo)
    {
        $this->model = $proveedor_archivo;
    }

    public function create(ValidacionProveedor $request, $id)
    {
		return self::guardaProveedor_Archivo($request, 'create', $id);
    }

    public function update(ValidacionProveedor $request, $id)
    {
		return self::guardaProveedor_Archivo($request, 'update', $id);
    }

    public function delete($proveedor_id, $codigo)
    {
		// Elimina anita
		self::eliminarAnita($codigo);

        $proveedor_archivo = $this->model->where('proveedor_id', $proveedor_id)->delete();

		return $proveedor_archivo;
    }

    public function find($id)
    {
        if (null == $proveedor_archivo = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor_archivo;
    }

    public function findOrFail($id)
    {
        if (null == $proveedor_archivo = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor_archivo;
    }

	private function guardaProveedor_Archivo($request, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Borra los registros antes de grabar nuevamente
       		$this->delete($id, $request->codigo);
		}

		$nombrearchivos = $request->file('nombrearchivos');
	  	$lineaAnita = 0;

		// Borra de anita
		self::eliminarAnita($request->codigo);

		// Recorre todos los files nuevos
		if ($nombrearchivos ?? '')
		{
			foreach ($nombrearchivos as $archivo)
			{
		  		if ($archivo)
				{
					// Guarda fisicamente el archivo
					$path = public_path()."/storage/archivos/proveedores/".$id;
    				$file = $archivo->getClientOriginalName();
    				$fileName = $path . '-' . $archivo->getClientOriginalName();
	
    				$archivo->move($path, $fileName);
	
					// Guarda en ERP
					$proveedor_archivo = $this->model->create([
									'proveedor_id' => $id,
									'nombrearchivo' => $id.'-'.$file,
									]);
	
					// Guarda en anita
					self::guardarAnita($request->all(), $lineaAnita++, $id.'-'.$file);
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
					$proveedor_archivo = $this->model->create([
									'proveedor_id' => $id,
									'nombrearchivo' => $request->nombresanteriores[$i_archivo],
									]);

					// Guarda en anita
					self::guardarAnita($request->all(), $lineaAnita++, $request->nombresanteriores[$i_archivo]);
				}
			}
		}
	}

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');
	  	ini_set('memory_limit', '512M');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'compras',
						'campos' => "
							proma_proveedor, 
							proma_nro_linea, 
							proma_archivo, 
							proma_usuario, 
							proma_fecha_act,
							proma_hora_act
								", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        foreach ($dataAnita as $value) {
            $this->traerRegistroDeAnita($value->proma_proveedor, $value->proma_nro_linea);
        }
    }

    private function traerRegistroDeAnita($proveedor, $linea){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
			'sistema' => 'compras',
            'campos' => '
							proma_proveedor, 
							proma_nro_linea, 
							proma_archivo, 
							proma_usuario, 
							proma_fecha_act,
							proma_hora_act
						',
            'whereArmado' => " WHERE proma_proveedor = '".$proveedor."' and proma_nro_linea = '".$linea."' "
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

       		$proveedor = Proveedor::where('codigo' , ltrim($proveedor, '0'))->first();

			if ($proveedor)
			{
				$arr_campos = [
					"proveedor_id" => $proveedor->id,
					"nombrearchivo" => $proveedor->id.'-'.$data->proma_archivo,
            		];
		
            	$this->model->create($arr_campos);

				$cmd = "mkdir /var/www/html/anitaERP/public/storage/archivos/proveedores/".$proveedor->id." 1>&2 2>/dev/null";
				system($cmd);

				$cmd = "scp sergio@".env('ANITA_SERVER').":".env('ANITA_BDD_PATH')."/var/prom_files/PROM-".
						$data->proma_proveedor.".".$data->proma_archivo.
						" /var/www/html/anitaERP/public/storage/archivos/proveedors/".
						$proveedor->id."/".$proveedor->id."-".$data->proma_archivo;
				system($cmd);
			}
        }
    }

	private function guardarAnita($data, $linea, $nombrearchivo) {
        $apiAnita = new ApiAnita();

		$usuario = Auth::user()->nombre;
        $fecha = Carbon::now();
		$fechahoy = $fecha->format('Ymd');
		$hora = $fecha->format('Hi');

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
			'sistema' => 'compras',
            'campos' => ' 
							proma_proveedor, 
							proma_nro_linea, 
							proma_archivo, 
							proma_usuario, 
							proma_fecha_act,
							proma_hora_act
				',
            'valores' => " 
				'".str_pad($data['codigo'], 6, "0", STR_PAD_LEFT)."', 
				'".$linea."',
				'".$nombrearchivo."',
				'".$usuario."',
				'".$fechahoy."',
				'".$hora."' "
        );
        $apiAnita->apiCall($data);
	}

	private function eliminarAnita($proveedor) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'sistema' => 'compras',
				'whereArmado' => " WHERE proma_proveedor = '".str_pad($proveedor, 6, "0", STR_PAD_LEFT)."' ");
        $apiAnita->apiCall($data);
	}
}
