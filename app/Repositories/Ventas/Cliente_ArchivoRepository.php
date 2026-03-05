<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Cliente;
use App\Models\Ventas\Cliente_Archivo;
use App\Http\Requests\ValidacionCliente;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;

class Cliente_ArchivoRepository implements Cliente_ArchivoRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'climarch';
    protected $keyFieldAnita = 'clima_cliente';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente_Archivo $cliente_archivo)
    {
        $this->model = $cliente_archivo;
    }

    public function create(ValidacionCliente $request, $id)
    {
		return self::guardaCliente_Archivo($request, 'create', $id);
    }

    public function update(ValidacionCliente $request, $id)
    {
		return self::guardaCliente_Archivo($request, 'update', $id);
    }

    public function delete($cliente_id, $codigo)
    {
		// Elimina anita
		self::eliminarAnita($codigo);

        $cliente_archivo = $this->model->where('cliente_id', $cliente_id)->delete();

		return $cliente_archivo;
    }

    public function find($id)
    {
        if (null == $cliente_archivo = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_archivo;
    }

    public function findOrFail($id)
    {
        if (null == $cliente = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente;
    }

	private function guardaCliente_Archivo($request, $funcion, $id = null)
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
					$path = public_path()."/storage/archivos/clientes/".$id;
    				$file = $archivo->getClientOriginalName();
    				$fileName = $path . '-' . $archivo->getClientOriginalName();
	
    				$archivo->move($path, $fileName);
	
					// Guarda en ERP
					$cliente_archivo = $this->model->create([
									'cliente_id' => $id,
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
					$cliente_archivo = $this->model->create([
									'cliente_id' => $id,
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
						'sistema' => 'ventas',
						'campos' => "
							clima_cliente, 
							clima_nro_linea, 
							clima_archivo, 
							clima_usuario, 
							clima_fecha_act,
							clima_hora_act
								", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        foreach ($dataAnita as $value) {
            $this->traerRegistroDeAnita($value->clima_cliente, $value->clima_nro_linea);
        }
    }

    private function traerRegistroDeAnita($cliente, $linea){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
			'sistema' => 'ventas',
            'campos' => '
							clima_cliente, 
							clima_nro_linea, 
							clima_archivo, 
							clima_usuario, 
							clima_fecha_act,
							clima_hora_act
						',
            'whereArmado' => " WHERE clima_cliente = '".$cliente."' and clima_nro_linea = '".$linea."' "
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

       		$cliente = Cliente::where('codigo' , ltrim($cliente, '0'))->first();

			if ($cliente)
			{
				$arr_campos = [
					"cliente_id" => $cliente->id,
					"nombrearchivo" => $cliente->id.'-'.$data->clima_archivo,
            		];
		
            	$this->model->create($arr_campos);

				$cmd = "mkdir /var/www/html/anitaERP/public/storage/archivos/clientes/".$cliente->id." 1>&2 2>/dev/null";
				system($cmd);

				$cmd = "scp sergio@".env('ANITA_SERVER').":".env('ANITA_BDD_PATH')."/var/clim_files/CLIM-".
						$data->clima_cliente.".".$data->clima_archivo.
						" /var/www/html/anitaERP/public/storage/archivos/clientes/".
						$cliente->id."/".$cliente->id."-".$data->clima_archivo;
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
			'sistema' => 'ventas',
            'campos' => ' 
							clima_cliente, 
							clima_nro_linea, 
							clima_archivo, 
							clima_usuario, 
							clima_fecha_act,
							clima_hora_act
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

	private function eliminarAnita($cliente) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'sistema' => 'ventas',
				'whereArmado' => " WHERE clima_cliente = '".str_pad($cliente, 6, "0", STR_PAD_LEFT)."' ");
        $apiAnita->apiCall($data);
	}
}
