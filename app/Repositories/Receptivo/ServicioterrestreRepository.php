<?php

namespace App\Repositories\Receptivo;

use App\Models\Receptivo\Servicioterrestre;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;

class ServicioterrestreRepository implements ServicioterrestreRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'servterr';
    protected $keyField = 'codigo';
	protected $keyFieldAnita = 'servt_servicio';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Servicioterrestre $servicioterrestre)
    {
        $this->model = $servicioterrestre;
    }

    public function all()
    {
        $serviciosterrestres = $this->model->where('nombre', '!=', ' ')->orderBy('nombre')->get();
		if ($serviciosterrestres->isEmpty())
		{
        	self::sincronizarConAnita();

			$serviciosterrestres = $this->model->with('tiposervicioterrestres')->orderBy('nombre')->get();
		}
		return $serviciosterrestres;
    }

    public function create(array $data)
    {
		$codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;

        $servicioterrestre = $this->model->create($data);

        // Graba anita
		self::guardarAnita($data);

		return $servicioterrestre;
    }

    public function update(array $data, $id)
    {
        $servicioterrestre = $this->model->findOrFail($id)->update($data);

		// Actualiza anita
		self::actualizarAnita($data, $id);

        return $servicioterrestre;
    }

    public function delete($id)
    {
    	$servicioterrestre = $this->model->find($id);
		$codigo = $servicioterrestre->codigo;

        $servicioterrestre = $this->model->destroy($id);
        
        self::eliminarAnita($codigo);

		return $servicioterrestre;
    }

    public function find($id)
    {
        if (null == $servicioterrestre = $this->model->with('tiposervicioterrestres')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $servicioterrestre;
    }

	public function findPorId($id)
    {
		$servicioterrestre = $this->model->where('id', $id)->with('tiposervicioterrestres')->first();

		return $servicioterrestre;
    }

	public function findPorCodigo($codigo)
    {
		return $this->model->where('codigo', $codigo)->with('tiposervicioterrestres')->first();
    }

    public function findOrFail($id)
    {
        if (null == $servicioterrestre = $this->model->with('tiposervicioterrestres')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $servicioterrestre;
    }

    public function sincronizarConAnita(){

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'receptivo',
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'orderBy' => $this->keyField,
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));
        $datosLocal = $this->model->get();
        $datosLocalArray = [];

        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }

        foreach ($dataAnita as $value) {
            if (!in_array($value->{$this->keyField}, $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
            }
        }
    }

    public function traerRegistroDeAnita($key){

        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
			'sistema' => 'receptivo',
            'campos' => '
                servt_servicio,
				servt_desc,
				servt_tipo_serv,
				servt_precio_indiv,
				servt_cod_mon,
				servt_observacion,
				servt_abreviatura,
				servt_ubicacion,
				servt_tipo_iva,
				servt_costo_civa,
				servt_cod_mon_cos,
				servt_modo_exento,
				servt_valor_exento,
				servt_porc_gcia,
				servt_prepago
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));
		if (count($dataAnita) > 0) 
		{
            $data = $dataAnita[0];

			$tiposervicioterrestre_id = 1;
			switch($data->servt_tipo_serv)
			{
				case 'I':
					$tiposervicioterrestre_id = 1;
					break;
				case 'O':
					$tiposervicioterrestre_id = 2;
					break;
				case 'E':
					$tiposervicioterrestre_id = 3;
					break;
				case 'P':
					$tiposervicioterrestre_id = 4;
					break;
			}
			if ($data->servt_tipo_iva < 1 || $data->servt_tipo_iva > 4)
				$data->servt_tipo_iva = 1;

			// Crea registro 
            $servicioterrestre = $this->model->create([
                'id' => $key,
                'nombre' => $data->servt_desc,
				'codigo' => $data->servt_servicio,
				'tiposervicioterrestre_id' => $tiposervicioterrestre_id,
				'moneda_id' => $data->servt_cod_mon,
				'observacion' => $data->servt_observacion,
				'abreviatura' => $data->servt_abreviatura,
				'ubicacion' => $data->servt_ubicacion,
				'impuesto_id' => $data->servt_tipo_iva,
				'precioindividual' => $data->servt_precio_indiv,
				'costoconiva' => $data->servt_costo_civa,
				'modoexento' => $data->servt_modo_exento,
				'monedacosto_id' => $data->servt_cod_mon_cos,
				'valorexento' => $data->servt_valor_exento,
				'porcentajeganancia' => $data->servt_porc_gcia,
				'prepago' => $data->servt_prepago
            ]);
        }
    }

	public function guardarAnita($request) {

        $apiAnita = new ApiAnita();

		switch($request['tiposervicioterrestre_id'])
		{
			case 1:
				$tiposervicioterrestre  = 'I';
				break;
			case 2:
				$tiposervicioterrestre  = 'O';
				break;
			case 3:
				$tiposervicioterrestre  = 'E';
				break;
			case 4:
				$tiposervicioterrestre  = 'P';
				break;
		}

        $data = array( 'tabla' => $this->tableAnita, 
			'acc' => 'insert',
			'sistema' => 'receptivo',
            'campos' => '
                servt_servicio,
				servt_desc,
				servt_tipo_serv,
				servt_precio_indiv,
				servt_cod_mon,
				servt_observacion,
				servt_abreviatura,
				servt_ubicacion,
				servt_tipo_iva,
				servt_costo_civa,
				servt_cod_mon_cos,
				servt_modo_exento,
				servt_valor_exento,
				servt_porc_gcia,
				servt_prepago
					',
            'valores' => " 
						'".$request['codigo']."', 
						'".$request['nombre']."',
						'".$tiposervicioterrestre."',
						'".$request['precioindividual']."',
						'".$request['moneda_id']."',
						'".$request['observacion']."',
						'".$request['abreviatura']."',
						'".$request['ubicacion']."',
						'".$request['impuesto_id']."',
						'".$request['costoconiva']."',
						'".$request['monedacosto_id']."',
						'".$request['modoexento']."',
						'".$request['valorexento']."',
						'".$request['porcentajeganancia']."',
						'".$request['prepago']."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {

        $apiAnita = new ApiAnita();

		switch($request['tiposervicioterrestre_id'])
		{
			case 1:
				$tiposervicioterrestre = 'I';
				break;
			case 2:
				$tiposervicioterrestre = 'O';
				break;
			case 3:
				$tiposervicioterrestre = 'E';
				break;
			case 4:
				$tiposervicioterrestre = 'P';
				break;
		}

		$data = array( 'acc' => 'update', 
				'tabla' => $this->tableAnita,
				'sistema' => 'receptivo',
            	'valores' => " 
				            servt_servicio = '".$request['codigo']."',
							servt_desc = '".$request['nombre']."',
							servt_tipo_serv = '".$tiposervicioterrestre."',
							servt_precio_indiv = '".$request['precioindividual']."',
							servt_cod_mon = '".$request['moneda_id']."',
							servt_observacion = '".$request['observacion']."',
							servt_abreviatura = '".$request['abreviatura']."',
							servt_ubicacion = '".$request['ubicacion']."',
							servt_tipo_iva = '".$request['impuesto_id']."',
							servt_costo_civa = '".$request['costoconiva']."',
							servt_cod_mon_cos = '".$request['monedacosto_id']."',
							servt_modo_exento = '".$request['modoexento']."',
							servt_valor_exento = '".$request['valorexento']."',
							servt_porc_gcia = '".$request['porcentajeganancia']."',
							servt_prepago = '".$request['prepago']."'
							", 
            	'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$request['codigo']."' " 
				);

        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
			'sistema' => 'receptivo',
			'tabla' => $this->tableAnita,
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$id."' " );
        $apiAnita->apiCall($data);
	}    

	// Devuelve ultimo codigo de retenciones de iva + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
		$apiAnita = new ApiAnita();
		$data = array( 'acc' => 'list', 
				'sistema' => 'receptivo',
				'tabla' => $this->tableAnita, 
				'campos' => " max(servt_servicio) as $this->keyFieldAnita "
				);
		$dataAnita = json_decode($apiAnita->apiCall($data));

		if (isset($dataAnita)) 
		{
			$codigo = ltrim($dataAnita[0]->{$this->keyFieldAnita}, '0');
			$codigo = $codigo + 1;
		}
		else	
			$codigo = 1;
	}
		
	public function leeServicioTerrestre($consulta)
    {
        //$columns = ['servt_servicio', 'servt_desc', 'servt_abreviatura', 'servt_precio_indiv', 'servt_cod_mon'];
		$columns = ['servicioterrestre.id', 'servicioterrestre.nombre', 'servicioterrestre.abreviatura', 
					'precioindividual', 'moneda.nombre', 'servicioterrestre.codigo'];
        $columnsOut = ['id', 'descripcion', 'abreviatura', 'precio_indiv', 'nombremoneda', 'codigo'];

		$consulta = strtoupper($consulta);
        //$apiAnita = new ApiAnita();
        //$data = array( 
        //    'acc' => 'list', 'tabla' => $this->tableAnita.',moneda', 
        //    'sistema' => 'receptivo',
        //    'campos' => '
        //        servt_servicio as id,
        //        servt_desc as descripcion,
        //        servt_abreviatura as abreviatura,
        //        servt_precio_indiv as precio_indiv,
		//		mon_desc as moneda
        //    ' , 
        //    'whereArmado' => " WHERE mon_codigo=servt_cod_mon AND (servt_desc like '%".$consulta."%' ".
		//							"or servt_abreviatura = '".$consulta."' ".
		//							"or mon_desc = '".$consulta."' ".
        //                            (is_numeric($consulta) ? " or servt_servicio = ".$consulta." " : "").
        //                            (is_numeric($consulta) ? " or servt_abreviatura = ".$consulta." " : "").
        //                            (is_numeric($consulta) ? " or servt_precio_indiv = ".$consulta.") " : ")"),
        //    'orderBy' => "servt_desc desc" 
        //);
        //$dataAnita = json_decode($apiAnita->apiCall($data));

		$count = count($columns);
		$data = $this->model->select('servicioterrestre.id as id',
									'servicioterrestre.nombre as descripcion',
									'servicioterrestre.abreviatura as abreviatura',
									'servicioterrestre.precioindividual as precio_indiv',
									'moneda.nombre as nombremoneda',
									'servicioterrestre.codigo as codigo')
							->leftJoin('moneda','servicioterrestre.moneda_id','=','moneda.id')
							->orWhere(function ($query) use ($count, $consulta, $columns) {
                        			for ($i = 0; $i < $count; $i++)
                            			$query->orWhere($columns[$i], "LIKE", '%'. $consulta . '%');
                })	
				->get();								

        $output = [];
		$output['data'] = '';	
        $flSinDatos = true;
        $count = count($columns);
		if (count($data) > 0)
		{
			foreach ($data as $row)
			{
                $flSinDatos = false;
                $output['data'] .= '<tr>';
                for ($i = 0; $i < $count; $i++)
                    $output['data'] .= '<td class="'.$columnsOut[$i].'">' . $row->{$columnsOut[$i]} . '</td>';	
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultaservicioterrestre">Elegir</a></td>';
                $output['data'] .= '</tr>';
			}
		}

        if ($flSinDatos)
		{
			$output['data'] .= '<tr>';
			$output['data'] .= '<td>Sin resultados</td>';
			$output['data'] .= '</tr>';
		}
		return(json_encode($output, JSON_UNESCAPED_UNICODE));
    }

}
