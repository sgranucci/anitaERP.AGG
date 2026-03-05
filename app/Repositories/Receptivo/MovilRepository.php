<?php

namespace App\Repositories\Receptivo;

use App\Models\Receptivo\Movil;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;
use App\ApiAnita;

class MovilRepository implements MovilRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'transporte';
    protected $keyField = 'codigo';
	protected $keyFieldAnita = 'tran_transporte';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Movil $tipoempresa)
    {
        $this->model = $tipoempresa;
    }

    public function all()
    {
        $idioma = $this->model->get();

		if ($idioma->isEmpty())
		{
        	self::sincronizarConAnita();

			$idioma = $this->model->orderBy('nombre','ASC')->get();
		}

        return $idioma;
    }

    public function create(array $data)
    {
		$codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;

        $idioma = $this->model->create($data);

        // Graba anita
		self::guardarAnita($data);

        return $idioma;
    }

    public function update(array $data, $id)
    {
        $idioma = $this->model->findOrFail($id)->update($data);

		// Actualiza anita
		self::actualizarAnita($data, $id);

        return $idioma;
    }

    public function delete($id)
    {
    	$idioma = $this->model->find($id);
		$codigo = $idioma->codigo;

        $idioma = $this->model->destroy($id);
        
        self::eliminarAnita($codigo);

		return $idioma;
    }

    public function find($id)
    {
        if (null == $idioma = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $idioma;
    }

    public function findOrFail($id)
    {
        if (null == $idioma = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $idioma;
    }

    public function findPorId($id)
    {
		return $this->model->where('id', $id)->first();
    }

    public function findPorCodigo($codigo)
    {
		return $this->model->where('codigo', $codigo)->first();
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
                tran_transporte,
				tran_desc,
				tran_chofer,
                tran_dominio,
                tran_tipo_movil,
                tran_vt_verif_mun,
                tran_vt_verif_tec,
                tran_vt_service,
                tran_vt_corredor,
                tran_vt_ing_parque,
                tran_vt_seguro
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));
		if (count($dataAnita) > 0) 
		{
            $data = $dataAnita[0];

            $tipoMovil = 'P';
            switch($data->tran_tipo_movil)
            {
                case '0':
                    $tipoMovil = 'P';
                    break;
                case '1':
                    $tipoMovil = 'C';
                    break;
            }

			// Crea registro 
            $this->model->create([
                'id' => $key,
                'nombre' => $data->tran_desc,
				'dominio' => $data->tran_dominio,
                'tipomovil' => $tipoMovil,
                'codigo' => $data->tran_transporte,
                'vencimientoverificacionmunicipal' => $data->tran_vt_verif_mun,
                'vencimientoverificaciontecnica' => $data->tran_vt_verif_tec,
                'vencimientoservice' => $data->tran_vt_service,
                'vencimientocorredor' => $data->tran_vt_corredor,
                'vencimientoingresoparque' => $data->tran_vt_ing_parque, 
                'vencimientoseguro' => $data->tran_vt_seguro,
            ]);
        }
    }

	public function guardarAnita($request) {

        $apiAnita = new ApiAnita();

        $tipoMovil = '0';
        switch($request['tipomovil'])
        {
            case 'P':
                $tipoMovil = '0';
                break;
            case 'C':
                $tipoMovil = '1';
                break;
        }

        $data = array( 'tabla' => $this->tableAnita, 
			'acc' => 'insert',
			'sistema' => 'receptivo',
            'campos' => '
                tran_transporte,
				tran_desc,
				tran_chofer,
                tran_dominio,
                tran_tipo_movil,
                tran_vt_verif_mun,
                tran_vt_verif_tec,
                tran_vt_service,
                tran_vt_corredor,
                tran_vt_ing_parque,
                tran_vt_seguro
					',
            'valores' => " 
						'".$request['codigo']."', 
						'".$request['nombre']."',
                        '0',
                        '".$request['dominio']."',
						'".$tipoMovil."' ,
                        '".date('Ymd', strtotime($request['vencimientoverificacionmunicipal']))."',
                        '".date('Ymd', strtotime($request['vencimientoverificaciontecnica']))."',
                        '".date('Ymd', strtotime($request['vencimientoservice']))."',
                        '".date('Ymd', strtotime($request['vencimientocorredor']))."',
                        '".date('Ymd', strtotime($request['vencimientoingresoparque']))."',
                        '".date('Ymd', strtotime($request['vencimientoseguro']))."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {

        $apiAnita = new ApiAnita();

        $tipoMovil = '0';
        switch($request['tipomovil'])
        {
            case 'P':
                $tipoMovil = '0';
                break;
            case 'C':
                $tipoMovil = '1';
                break;
        }

		$data = array( 'acc' => 'update', 
				'tabla' => $this->tableAnita,
				'sistema' => 'receptivo',
            	'valores' => " 
				            tran_transporte = '".$request['codigo']."',
							tran_desc = '".$request['nombre']."',
							tran_chofer = '".$request['abreviatura']."'
                            tran_dominio = '".$request['dominio']."'
                            tran_tipo_movil = '".$tipoMovil."'
                            tran_vt_verif_mun = '".date('Ymd', strtotime($request['vencimientoverificacionmunicipal']))."'
                            tran_vt_verif_tec = '".date('Ymd', strtotime($request['vencimientoverificaciontecnica']))."'
                            tran_vt_service = '".date('Ymd', strtotime($request['vencimientoservice']))."'
                            tran_vt_corredor = '".date('Ymd', strtotime($request['vencimientocorredor']))."'
                            tran_vt_ing_parque = '".date('Ymd', strtotime($request['vencimientoingresoparque']))."'
                            tran_vt_seguro = '".date('Ymd', strtotime($request['vencimientoseguro']))."' 
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
				'campos' => " max(tran_transporte) as $this->keyFieldAnita "
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
		
    public function leeMovil($consulta)
    {
		$columns = ['movil.id', 'movil.nombre', 'movil.codigo', 'movil.dominio', 'movil.tipomovil'];
        $columnsOut = ['id', 'nombre', 'codigo', 'dominio', 'tipomovil'];

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('movil.id as id',
									'movil.nombre as nombre',
									'movil.codigo as codigo',
									'movil.dominio as dominio',
									'movil.tipomovil as tipomovil')
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
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultamovil">Elegir</a></td>';
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
