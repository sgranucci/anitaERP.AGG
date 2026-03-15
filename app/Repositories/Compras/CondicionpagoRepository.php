<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Condicionpago;
use App\Models\Compras\Condicionpagocuota;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;

class CondicionpagoRepository implements CondicionpagoRepositoryInterface
{
    protected $model, $modelCuota;
    protected $tableAnita = ['condpmae','condpmov'];
    protected $keyField = 'conpm_codigo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Condicionpago $condicionpago, Condicionpagocuota $condicionpagocuota)
    {
        $this->model = $condicionpago;
        $this->modelCuota = $condicionpagocuota;
    }

    public function all()
    {
        $condicionespago = $this->model->with("condicionpagocuotas")->get();

		if ($condicionespago->isEmpty())
		{
        	self::sincronizarConAnita();

			$condicionespago = $this->model->with("condicionpagocuotas")->get();
		}
		return $condicionespago;
    }

    public function create(array $data)
    {
		$codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;

        $condicionpago = $this->model->create($data);

        // Graba anita
		self::guardarAnita($data, $data['cuotas'], $data['tiposplazo'], $data['plazos'], $data['fechasvencimiento'], 
		$data['porcentajes'], $data['intereses']);
    }

    public function update(array $data, $id)
    {
        $condicionpago = $this->model->findOrFail($id)->update($data);

		// Actualiza anita
		self::actualizarAnita($data, $id, $data['cuotas'], $data['tiposplazo'], $data['plazos'], $data['fechasvencimiento'], 
								$data['porcentajes'], $data['intereses']);

        return $condicionpago;
    }

    public function delete($id)
    {
    	$condicionpago = $this->model->find($id);
		$codigo = $condicionpago->codigo;

        $condicionpago = $this->model->destroy($id);
        
        self::eliminarAnita($codigo);

		return $condicionpago;
    }

    public function find($id)
    {
        if (null == $condicionpago = $this->model->with("condicionpagocuotas")->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $condicionpago;
    }

	public function findPorId($id)
    {
		$condicionpago = $this->model->where('id', $id)->first();

		return $condicionpago;
    }

	public function findPorCodigo($codigo)
    {
		return $this->model->where('codigo', $codigo)->first();
    }

    public function findOrFail($id)
    {
        if (null == $condicionpago = $this->model->with("condicionpagocuotas")->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $condicionpago;
    }

    public function sincronizarConAnita(){

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'compras',
						'campos' => $this->keyField, 
						'orderBy' => $this->keyField,
						'tabla' => $this->tableAnita[0] );
        $dataAnita = json_decode($apiAnita->apiCall($data));
        $datosLocal = $this->model->with("condicionpagocuotas")->get();
        $datosLocalArray = [];

        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }
		
        foreach ($dataAnita as $value) {
            if (!in_array($value->{$this->keyField}, $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyField});
            }
        }
    }

    public function traerRegistroDeAnita($key){

	  	$colTipoPlazo = collect([
							['id' => '1', 'valor' => 'D', 'nombre'  => 'Dias'],
    						['id' => '2', 'valor' => 'F', 'nombre'  => 'Vto. fijo'],
    						['id' => '3', 'valor' => 'O', 'nombre'  => 'Vto. por operacion'],
							['id' => '4', 'valor' => 'R', 'nombre'  => 'Vto. por rangos']
								]);

        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[0], 
			'sistema' => 'compras',
            'campos' => '
                conpm_codigo,
				conpm_desc
            ' , 
            'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) 
		{
            $data = $dataAnita[0];

        	$datamov = array( 
            	'acc' => 'list', 
				'sistema' => 'compras',
				'tabla' => $this->tableAnita[1], 
            	'campos' => '
                	conpv_codigo,
					conpv_nro_cuota,
					conpv_tipo_plazo,
					conpv_dia,
					conpv_fecha_vto,
					conpv_porc_monto,
					conpv_porc_interes
            	' , 
            	'whereArmado' => " WHERE conpv_codigo = '".$key."' " 
        	);
        	$dataAnitamov = json_decode($apiAnita->apiCall($datamov));

			// Crea registro 
            $condicionpago = $this->model->create([
                "id" => $key,
                "nombre" => $data->conpm_desc,
				"codigo" => $data->conpm_codigo
            ]);

			if ($condicionpago)
			{
				foreach ($dataAnitamov as $cuota)
				{
					$nrocuota = $cuota->conpv_nro_cuota;
					$tipoplazo = $colTipoPlazo->where('id', $cuota->conpv_tipo_plazo);

					if (!isset($tipoplazo[0]['valor']))
					{
						$tipoplazo = [];

						$tipoplazo[0]['valor'] = '1';
					}

        			$condicionpagocuota = $this->modelCuota->create([
            											'condicionpago_id' => $condicionpago->id,
            											'cuota' => $nrocuota,
														'tipoplazo' => $tipoplazo[0]['valor'],
														'plazo' => $cuota->conpv_dia, 
														'fechavencimiento' => ($cuota->conpv_fecha_vto == 0 ? NULL : $cuota->conpv_fecha_vto),
														'porcentaje' => ($cuota->conpv_porc_monto == 0 ? NULL : $cuota->conpv_porc_monto),
														'interes' => ($cuota->conpv_porc_interes == 0 ? NULL : $cuota->conpv_porc_interes),
														]);
				}
			}
        }
    }

	public function guardarAnita($request, $cuotas, $tiposplazo, $plazos, $fechasvencimiento, $porcentajes, $intereses) {

	  	$colTipoPlazo = collect([
							['id' => '1', 'valor' => 'D', 'nombre'  => 'Dias'],
    						['id' => '2', 'valor' => 'F', 'nombre'  => 'Vto. fijo'],
    						['id' => '3', 'valor' => 'O', 'nombre'  => 'Vto. por operacion'],
							['id' => '4', 'valor' => 'R', 'nombre'  => 'Vto. por rangos']
								]);

        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita[0], 
			'acc' => 'insert',
			'sistema' => 'compras',
            'campos' => 'conpm_codigo, conpm_desc',
            'valores' => " 
						'".$request['codigo']."', 
						'".$request['nombre']."' "
        );
        $apiAnita->apiCall($data);

    	for ($i_cuota=0; $i_cuota < count($cuotas); $i_cuota++) 
		{
			$tipoplazo = $colTipoPlazo->where('valor', $tiposplazo[$i_cuota])->first();
			$fecha = 0;
			if ($tipoplazo['valor'] == 'F')
				$fecha = Carbon::createFromFormat( 'd-m-Y', $fechasvencimiento[$i_cuota])->format('Ymd');

        	$data = array( 'tabla' => $this->tableAnita[1], 
				'acc' => 'insert',
				'sistema' => 'compras',
            	'campos' => 'conpv_codigo, conpv_nro_cuota, conpv_tipo_plazo, conpv_dia, conpv_fecha_vto, conpv_porc_monto, conpv_porc_interes',
            	'valores' => " 
						'".$request['codigo']."', 
						'".$cuotas[$i_cuota]."' ,
						'".$tipoplazo['id']."' ,
						'".$plazos[$i_cuota]."' ,
						'".$fecha."' ,
						'".$porcentajes[$i_cuota]."' ,
						'".$intereses[$i_cuota]."' "
        		);
		}
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id, $cuotas, $tiposplazo, $plazos, $fechasvencimiento, $porcentajes, $intereses) {
	  	$colTipoPlazo = collect([
							['id' => '1', 'valor' => 'D', 'nombre'  => 'Dias'],
    						['id' => '2', 'valor' => 'F', 'nombre'  => 'Vto. fijo'],
    						['id' => '3', 'valor' => 'O', 'nombre'  => 'Vto. por operacion'],
							['id' => '4', 'valor' => 'R', 'nombre'  => 'Vto. por rangos']
								]);

        $apiAnita = new ApiAnita();

		$data = array( 'acc' => 'update', 
				'tabla' => $this->tableAnita[0],
				'sistema' => 'compras',
            	'valores' => " 
							conpm_codigo = '".$request['codigo']."', 
							conpm_desc = '".$request['nombre']."' ", 
            	'whereArmado' => " WHERE ".$this->keyField." = '".$request['codigo']."' " 
				);
        $apiAnita->apiCall($data);

		// Elimina los movimientos
        $data = array( 'acc' => 'delete', 
			'tabla' => $this->tableAnita[1],
			'sistema' => 'compras',
            'whereArmado' => " WHERE conpv_codigo = '".$request['codigo']."' " );
        $apiAnita->apiCall($data);

		// Graba los movimientos
    	for ($i_cuota=0; $i_cuota < count($cuotas); $i_cuota++) 
		{
			$tipoplazo = $colTipoPlazo->where('valor', $tiposplazo[$i_cuota])->first();
			$fecha = 0;
			if ($tipoplazo['valor'] == 'F')
				$fecha = Carbon::createFromFormat( 'd-m-Y', $fechasvencimiento[$i_cuota])->format('Ymd');

        	$data = array( 'tabla' => $this->tableAnita[1], 
				'acc' => 'insert',
				'sistema' => 'compras',
            	'campos' => 'conpv_codigo, conpv_nro_cuota, conpv_tipo_plazo, conpv_dia, conpv_fecha_vto, conpv_porc_monto, conpv_porc_interes',
            	'valores' => " 
						'".$request['codigo']."', 
						'".$cuotas[$i_cuota]."' ,
						'".$tipoplazo['id']."' ,
						'".$plazos[$i_cuota]."' ,
						'".$fecha."' ,
						'".$porcentajes[$i_cuota]."' ,
						'".$intereses[$i_cuota]."' "
        		);
        	$apiAnita->apiCall($data);
		}
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
			'sistema' => 'compras',
			'tabla' => $this->tableAnita[0],
            'whereArmado' => " WHERE ".$this->keyField." = '".$id."' " );
        $apiAnita->apiCall($data);

        $data = array( 'acc' => 'delete', 
			'sistema' => 'compras',
			'tabla' => $this->tableAnita[1],
            'whereArmado' => " WHERE conpv_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}    

		// Devuelve ultimo codigo de clientes + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
		$apiAnita = new ApiAnita();
		$data = array( 'acc' => 'list', 
				'sistema' => 'compras',
				'tabla' => $this->tableAnita[0], 
				'campos' => " max(concm_condicion) as $this->keyFieldAnita "
				);
		$dataAnita = json_decode($apiAnita->apiCall($data));

		if (count($dataAnita) > 0) 
		{
			$codigo = ltrim($dataAnita[0]->{$this->keyFieldAnita}, '0');
			$codigo = $codigo + 1;
		}
	}
		
}
