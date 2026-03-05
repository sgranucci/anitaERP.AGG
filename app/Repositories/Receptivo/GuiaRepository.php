<?php

namespace App\Repositories\Receptivo;

use App\Models\Receptivo\Guia;
use App\Models\Receptivo\Guia_Idioma;
use App\Repositories\Receptivo\IdiomaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;

class GuiaRepository implements GuiaRepositoryInterface
{
    protected $model, $model_guia_idioma;
    protected $tableAnita = ['guia', 'chofer'];
    protected $keyField = 'codigo';
	protected $keyFieldAnita = ['gui_guia','cho_chofer'];
	private $idiomaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Guia $guia, 
								Guia_Idioma $guia_idioma,
								IdiomaRepositoryInterface $idiomarepository
								)
    {
        $this->model = $guia;
        $this->model_guia_idioma = $guia_idioma;
		$this->idiomaRepository = $idiomarepository;
    }

    public function all()
    {
        $guias = $this->model->with("guia_idiomas")->with("provincias")->with("localidades")->with("paises")->get();

		if ($guias->isEmpty())
		{
        	self::sincronizarConAnita();

			$guias = $this->model->with("guia_idiomas")->with("provincias")->with("localidades")->with("paises")->get();
		}
		return $guias;
    }

    public function create(array $data)
    {
		$codigo = '';
		self::ultimoCodigo($codigo, $data['tipoguia']);
		$data['codigo'] = $codigo;

		$guia = $this->model->create($data);

        // Graba anita
		self::guardarAnita($data);

		return $guia;
    }

    public function update(array $data, $id)
    {
        $guia = $this->model->findOrFail($id)->update($data);

		// Actualiza anita
		self::actualizarAnita($data, $id);

        return $guia;
    }

    public function delete($id)
    {
    	$guia = $this->model->find($id);
		$codigo = $guia->codigo;
		$tipoGuia = $guia->tipoguia;

        $guia = $this->model->destroy($id);
        
        self::eliminarAnita($codigo, $tipoGuia);

		return $guia;
    }

    public function find($id)
    {
        if (null == $guia = $this->model->with("guia_idiomas")->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $guia;
    }

	public function findPorId($id)
    {
		$guia = $this->model->where('id', $id)->first();

		return $guia;
    }

	public function findPorCodigo($codigo)
    {
		return $this->model->where('codigo', $codigo)->first();
    }

    public function findOrFail($id)
    {
        if (null == $guia = $this->model->with("guia_idiomas")->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $guia;
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'receptivo',
						'campos' => $this->keyFieldAnita[0]." as ".$this->keyField." , ".$this->keyFieldAnita[0], 
						'orderBy' => $this->keyField,
						'tabla' => $this->tableAnita[0] );
        $dataAnita = json_decode($apiAnita->apiCall($data));
        $datosLocal = $this->model->get();
        $datosLocalArray = [];

        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }
		
        foreach ($dataAnita as $value) {
            if (!in_array($value->{$this->keyField}, $datosLocalArray)) {
                $this->traerRegistroDeAnitaGuia($value->{$this->keyFieldAnita[0]});
            }
        }

		// Lee choferes
		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'receptivo',
						'campos' => $this->keyFieldAnita[1]." as ".$this->keyField." , ".$this->keyFieldAnita[1], 
						'orderBy' => $this->keyField,
						'tabla' => $this->tableAnita[1] );
        $dataAnita = json_decode($apiAnita->apiCall($data));
        $datosLocal = $this->model->get();
        $datosLocalArray = [];

        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }
		
        foreach ($dataAnita as $value) {
            if (!in_array($value->{$this->keyField}, $datosLocalArray)) {
                $this->traerRegistroDeAnitaChofer($value->{$this->keyFieldAnita[1]});
            }
        }
    }

    public function traerRegistroDeAnitaGuia($key){

		$dataAnita = Self::leeGuiaAnita($key);

		if (count($dataAnita) > 0) 
		{
            $data = $dataAnita[0];
			$nombre = $data->gui_nombre;
			$codigo = $data->gui_guia;
			$tipoDocumento = '';
			$numeroDocumento = '';
			$maneja = $data->gui_maneja;
			$tipoGuia = $data->gui_tipo_guia;
			$carnetGuia = $data->gui_carnet;
			$carnetConducir = '';
			$categoriaCarnetConducir = '';
			$carnetSanidad = '';
			$observacion = $data->gui_observacion;
			$email = '';
			$telefono = $data->gui_telefono;
			$domicilio = $data->gui_direccion;
			$localidad_id = null;
			$provincia_id = null;
			$pais_id = 1;
			$codigopostal = '';

			$guia = Self::grabaGuia($nombre, $codigo, $tipoDocumento, $numeroDocumento, $maneja, 
							$tipoGuia, $carnetGuia, $carnetConducir, $categoriaCarnetConducir, 
							$carnetSanidad, $observacion, $email, $telefono, $domicilio,
							$localidad_id, $provincia_id, $pais_id, $codigopostal);

			if ($guia)
			{
				$idioma = [];
				$idioma[] = $data->gui_idioma1;
				$idioma[] = $data->gui_idioma2;
				$idioma[] = $data->gui_idioma3;
				$idioma[] = $data->gui_idioma4;
				$q = count($idioma);
				for ($i = 0; $i < $q; $i++)
				{
					if ($idioma[$i] > 0)
					{
						// Busca idioma
						$idioma = $this->idiomaRepository->findPorCodigo($idioma[$i]);
						if ($idioma)
							$idioma_id = $idioma->id;
						else
							$idioma_id = 1;
									
        				$guia_idioma = $this->model_guia_idioma->create([
            											'guia_id' => $guia->id,
            											'idioma_id' => $idioma_id
														]);
					}
				}
			}
        }
    }

	private function leeGuiaAnita($key)
	{
		$apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[0], 
			'sistema' => 'receptivo',
            'campos' => '
				gui_guia,    
				gui_nombre,      
				gui_idioma1,     
				gui_idioma2,     
				gui_idioma3,     
				gui_idioma4,     
				gui_direccion,   
				gui_telefono,    
				gui_maneja,      
				gui_carnet,      
				gui_observacion,
				gui_tipo_guia
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita[0]." = '".$key."' " 
        );
        return json_decode($apiAnita->apiCall($data));
	}

	public function traerRegistroDeAnitaChofer($key){

		$dataAnita = Self::leeChoferAnita($key);

		if (count($dataAnita) > 0) 
		{
            $data = $dataAnita[0];
			$nombre = $data->cho_nombre;
			$codigo = $data->cho_chofer;
			$tipoDocumento = $data->cho_tipo_documento;
			$numeroDocumento = $data->cho_nro_documento;
			$maneja = 'S';
			$tipoGuia = 'R'; // Chofer
			$carnetGuia = '';
			$carnetConducir = $data->cho_carnet_cond;
			$categoriaCarnetConducir = $data->cho_categ_carnet;
			$carnetSanidad = $data->cho_carnet_sanidad;
			$observacion = $data->cho_observacion;
			$email = '';
			$telefono = $data->cho_telefono;
			$domicilio = $data->cho_direccion;
			$localidad_id = null;
			$provincia_id = null;
			$pais_id = 1;
			$codigopostal = null;

			$guia = Self::grabaGuia($nombre, $codigo, $tipoDocumento, $numeroDocumento, $maneja, 
							$tipoGuia, $carnetGuia, $carnetConducir, $categoriaCarnetConducir, 
							$carnetSanidad, $observacion, $email, $telefono, $domicilio,
							$localidad_id, $provincia_id, $pais_id, $codigopostal);

			if ($guia)
			{
				$idioma = [];
				$idioma[] = $data->cho_idioma1;
				$idioma[] = $data->cho_idioma2;
				$idioma[] = $data->cho_idioma3;
				$idioma[] = $data->cho_idioma4;

				$q = count($idioma);
				for ($i = 0; $i < $q; $i++)
				{
					if ($idioma[$i] > 0)
					{
						// Busca idioma
						$idioma = $this->idiomaRepository->findPorCodigo($idioma[$i]);
						if ($idioma)
							$idioma_id = $idioma->id;
						else
							$idioma_id = 1;
									
        				$guia_idioma = $this->model_guia_idioma->create([
            											'guia_id' => $guia->id,
            											'idioma_id' => $idioma_id
														]);
					}
				}
			}
        }
    }

	private function leeChoferAnita($key)
	{
		$apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[1], 
			'sistema' => 'receptivo',
            'campos' => '
				cho_chofer,    
				cho_nombre,      
				cho_idioma1,     
				cho_idioma2,     
				cho_idioma3,     
				cho_idioma4,     
				cho_direccion,   
				cho_telefono,    
				cho_carnet_cond,      
				cho_categ_carnet,      
				cho_carnet_sanidad,
				cho_tipo_documento,
				cho_nro_documento,
				cho_observacion
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita[1]." = '".$key."' " 
        );
        return json_decode($apiAnita->apiCall($data));
	}

	private function grabaGuia($nombre, $codigo, $tipoDocumento, $numeroDocumento, $maneja, 
							$tipoGuia, $carnetGuia, $carnetConducir, $categoriaCarnetConducir, 
							$carnetSanidad, $observacion, $email, $telefono, $domicilio,
							$localidad_id, $provincia_id, $pais_id, $codigopostal)
	{
		// Crea registro 
		$guia = $this->model->create([
			'nombre' => $nombre, 
			'codigo' => $codigo,
			'tipodocumento' => $tipoDocumento, 
			'numerodocumento' => $numeroDocumento, 
			'maneja' => $maneja, 
			'tipoguia' => $tipoGuia, 
			'carnetguia' => $carnetGuia,
			'carnetconducir' => $carnetConducir, 
			'categoriacarnetconducir' => $categoriaCarnetConducir, 
			'carnetsanidad' => $carnetSanidad, 
			'observacion' => $observacion, 
			'email' => $email,
			'telefono' => $telefono, 
			'domicilio' => $domicilio,
			'localidad_id' => $localidad_id, 
			'provincia_id' => $provincia_id, 
			'pais_id' => $pais_id, 
			'codigopostal' => $codigopostal
		]);

		return $guia;
	}

	public function guardarAnita($request) {

        $apiAnita = new ApiAnita();

		$idiomas = [];

		if (isset($request['idioma_ids']))
			$idiomas = $request['idioma_ids'];

		$idioma1 = $idioma2 = $idioma3 = $idioma4 = 0;
		for ($i = 0; $i < count($idiomas); $i++)
		{
			// Busca idioma
			$idioma = $this->idiomaRepository->findPorId($idiomas[$i]);
			if ($idioma)
				$idioma_codigo = $idioma->codigo;
			else
				$idioma_codigo = 0;
						
			switch($i)
			{
			case 0:
				$idioma1 = $idioma_codigo;
				break;
			case 1:
				$idioma2 = $idioma_codigo;
				break;
			case 2:
				$idioma3 = $idioma_codigo;
				break;
			case 3:
				$idioma4 = $idioma_codigo;
				break;
			}
		}

		if ($request['tipoguia'] != 'R')
		{
			$data = array( 'tabla' => $this->tableAnita[0], 
							'acc' => 'insert',
							'sistema' => 'receptivo',
							'campos' => '
									gui_guia,    
									gui_nombre,      
									gui_idioma1,     
									gui_idioma2,     
									gui_idioma3,     
									gui_idioma4,     
									gui_direccion,   
									gui_telefono,    
									gui_maneja,      
									gui_carnet,      
									gui_observacion,
									gui_tipo_guia
									',
							'valores' => " 
										'".$request['codigo']."', 
										'".$request['nombre']."',
										'".$idioma1."',
										'".$idioma2."',
										'".$idioma3."',
										'".$idioma4."',
										'".$request['domicilio']."',
										'".$request['telefono']."',
										'".$request['maneja']."',
										'".$request['carnetguia']."',
										'".$request['observacion']."',
										'".$request['tipoguia']."' "
							);
		}
		else
		{
			$data = array( 'tabla' => $this->tableAnita[1], 
							'acc' => 'insert',
							'sistema' => 'receptivo',
							'campos' => '
									cho_chofer,    
									cho_nombre,      
									cho_idioma1,     
									cho_idioma2,     
									cho_idioma3,     
									cho_idioma4,     
									cho_direccion,   
									cho_telefono,    
									cho_carnet_cond,      
									cho_categ_carnet,      
									cho_carnet_sanidad,
									cho_tipo_documento,
									cho_nro_documento,
									cho_observacion
									',
							'valores' => " 
										'".$request['codigo']."', 
										'".$request['nombre']."',
										'".$idioma1."',
										'".$idioma2."',
										'".$idioma3."',
										'".$idioma4."',
										'".$request['domicilio']."',
										'".$request['telefono']."',
										'".$request['carnetconducir']."',
										'".$request['categoriacarnetconducir']."',
										'".$request['carnetsanidad']."',
										'".$request['tipodocumento']."',
										'".$request['numerodocumento']."',
										'".$request['observacion']."' "
							);
		}
		$apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {

        $apiAnita = new ApiAnita();

		$idiomas = [];

		if (isset($request['idioma_ids']))
			$idiomas = $request['idioma_ids'];
		
		$idioma1 = $idioma2 = $idioma3 = $idioma4 = 0;
		for ($i = 0; $i < count($idiomas); $i++)
		{
			// Busca idioma
			$idioma = $this->idiomaRepository->findPorId($idiomas[$i]);
			if ($idioma)
				$idioma_codigo = $idioma->codigo;
			else
				$idioma_codigo = 0;
						
			switch($i)
			{
			case 0:
				$idioma1 = $idioma_codigo;
				break;
			case 1:
				$idioma2 = $idioma_codigo;
				break;
			case 2:
				$idioma3 = $idioma_codigo;
				break;
			case 3:
				$idioma4 = $idioma_codigo;
				break;
			}
		}
		if ($request['tipoguia'] != 'R')
		{
			$data = array( 'acc' => 'update', 
					'tabla' => $this->tableAnita[0],
					'sistema' => 'receptivo',
					'valores' => " 
								gui_guia = '".$request['codigo']."',
								gui_nombre = '".$request['nombre']."',       
								gui_idioma1 = '".$idioma1."',     
								gui_idioma2 = '".$idioma2."',     
								gui_idioma3 = '".$idioma3."',     
								gui_idioma4 = '".$idioma4."',     
								gui_direccion = '".$request['domicilio']."',   
								gui_telefono = '".$request['telefono']."',    
								gui_maneja = '".$request['maneja']."',      
								gui_carnet = '".$request['carnetguia']."',      
								gui_observacion = '".$request['observacion']."',
								gui_tipo_guia = '".$request['tipoguia']."'
								", 
					'whereArmado' => " WHERE ".$this->keyFieldAnita[0]." = '".$request['codigo']."' " 
					);
		}
		else
		{
			$data = array( 'acc' => 'update', 
					'tabla' => $this->tableAnita[1],
					'sistema' => 'receptivo',
					'valores' => " 
								cho_chofer = '".$request['codigo']."',
								cho_nombre = '".$request['nombre']."',       
								cho_idioma1 = '".$idioma1."',     
								cho_idioma2 = '".$idioma2."',     
								cho_idioma3 = '".$idioma3."',     
								cho_idioma4 = '".$idioma4."',     
								cho_direccion = '".$request['domicilio']."',   
								cho_telefono = '".$request['telefono']."',    
								cho_carnet_cond = '".$request['carnetconducir']."',      
								cho_categ_carnet = '".$request['categoriacarnetconducir']."',      
								cho_carnet_sanidad = '".$request['carnetsanidad']."',      
								cho_tipo_documento = '".$request['tipodocumento']."',      
								cho_nro_documento = '".$request['numerodocumento']."',      
								cho_observacion = '".$request['observacion']."'
								", 
					'whereArmado' => " WHERE ".$this->keyFieldAnita[1]." = '".$request['codigo']."' " 
					);			
		}
		$apiAnita->apiCall($data);
	}

	public function eliminarAnita($id, $tipoGuia) {
		$apiAnita = new ApiAnita();
		$data = array( 'acc' => 'delete', 
			'sistema' => 'receptivo',
			'tabla' => $tipoGuia != 'R' ? $this->tableAnita[0] : $this->tableAnita[1],
			'whereArmado' => " WHERE ".($tipoGuia != 'R' ? $this->keyFieldAnita[0] : $this->keyFieldAnita[1])." = '".$id."' " );
		$apiAnita->apiCall($data);
	}    

		// Devuelve ultimo codigo de clientes + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo, $tipoGuia) {
		$apiAnita = new ApiAnita();
		$data = array( 'acc' => 'list', 
				'sistema' => 'receptivo',
				'tabla' => ($tipoGuia != 'R' ? $this->tableAnita[0] : $this->tableAnita[1]), 
				'campos' => " max(".($tipoGuia != 'R' ? $this->keyFieldAnita[0] : $this->keyFieldAnita[1]). ") as ".($tipoGuia != 'R' ? $this->keyFieldAnita[0] : $this->keyFieldAnita[1])
				);
		$dataAnita = json_decode($apiAnita->apiCall($data));
		if (count($dataAnita) > 0) 
		{
			$codigo = ltrim($dataAnita[0]->{($tipoGuia != 'R' ? $this->keyFieldAnita[0] : $this->keyFieldAnita[1])}, '0');
			$codigo = $codigo + 1;
		}
		else	
			$codigo = 1;
	}
		
	public function leeGuia($consulta)
    {
		$columns = ['guia.id', 'guia.nombre', 'guia.codigo', 'guia.email', 'guia.maneja'];
        $columnsOut = ['id', 'nombre', 'codigo', 'email', 'maneja'];

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('guia.id as id',
									'guia.nombre as nombre',
									'guia.codigo as codigo',
									'guia.email as email',
									'guia.maneja as maneja')
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
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultaguia">Elegir</a></td>';
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
