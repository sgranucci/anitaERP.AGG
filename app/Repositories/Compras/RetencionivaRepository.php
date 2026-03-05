<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Retencioniva;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;

class RetencionivaRepository implements RetencionivaRepositoryInterface
{
    protected $model, $model_Escala;
    protected $tableAnita = 'retiva';
    protected $keyField = 'codigo';
	protected $keyFieldAnita = 'reti_codigo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Retencioniva $retencioniva)
    {
        $this->model = $retencioniva;
    }

    public function all()
    {
        $retencionesiva = $this->model->get();

		if ($retencionesiva->isEmpty())
		{
        	self::sincronizarConAnita();

			$retencionesiva = $this->model->get();
		}
		return $retencionesiva;
    }

    public function create(array $data)
    {
		$codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;

        $retencioniva = $this->model->create($data);

        // Graba anita
		self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $retencioniva = $this->model->findOrFail($id)->update($data);

		// Actualiza anita
		self::actualizarAnita($data, $id);

        return $retencioniva;
    }

    public function delete($id)
    {
    	$retencioniva = $this->model->find($id);
		$codigo = $retencioniva->codigo;

        $retencioniva = $this->model->destroy($id);
        
        self::eliminarAnita($codigo);

		return $retencioniva;
    }

    public function find($id)
    {
        if (null == $retencioniva = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $retencioniva;
    }

	public function findPorId($id)
    {
		$retencioniva = $this->model->where('id', $id)->first();

		return $retencioniva;
    }

	public function findPorCodigo($codigo)
    {
		return $this->model->where('codigo', $codigo)->first();
    }

    public function findOrFail($id)
    {
        if (null == $retencioniva = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $retencioniva;
    }

    public function sincronizarConAnita(){

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'compras',
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
			'sistema' => 'compras',
            'campos' => '
                reti_codigo,
				reti_desc,
				reti_porcentaje,
				reti_minimo,
				reti_cod_regimen,
				reti_aplica,
				reti_base,
				reti_cant_per,
				reti_valor_unit
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));
		if (count($dataAnita) > 0) 
		{
            $data = $dataAnita[0];

			// Crea registro 
            $retencioniva = $this->model->create([
                'id' => $key,
                'nombre' => $data->reti_desc,
				'codigo' => $data->reti_codigo,
				'regimen' => $data->reti_cod_regimen,
				'formacalculo' => $data->reti_aplica,
				'porcentajeretencion' => $data->reti_porcentaje,
				'minimoimponible' => $data->reti_minimo,
				'baseimponible' => $data->reti_base,
				'cantidadperiodoacumula' => $data->reti_cant_per,
				'valorunitario' => $data->reti_valor_unit
            ]);
        }
    }

	public function guardarAnita($request) {

        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 
			'acc' => 'insert',
			'sistema' => 'compras',
            'campos' => '
                reti_codigo,
				reti_desc,
				reti_porcentaje,
				reti_minimo,
				reti_cod_regimen,
				reti_aplica,
				reti_base,
				reti_cant_per,
				reti_valor_unit
					',
            'valores' => " 
						'".$request['codigo']."', 
						'".$request['nombre']."',
						'".$request['porcentajeretencion']."',
						'".$request['minimoimponible']."',
						'".$request['regimen']."',
						'".$request['formacalculo']."',
						'".$request['baseimponible']."',
						'".$request['cantidadperiodoacumula']."',
						'".$request['valorunitario']."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {

        $apiAnita = new ApiAnita();

		$data = array( 'acc' => 'update', 
				'tabla' => $this->tableAnita,
				'sistema' => 'compras',
            	'valores' => " 
							reti_codigo = '".$request['codigo']."', 
							reti_desc = '".$request['nombre']."',
							reti_porcentaje = '".$request['porcentajeretencion']."',
							reti_minimo = '".$request['minimoimponible']."',
							reti_cod_regimen = '".$request['regimen']."',
							reti_aplica = '".$request['formacalculo']."',
							reti_base = '".$request['baseimponible']."',
							reti_cant_per = '".$request['cantidadperiodoacumula']."',
							reti_valor_unit = '".$request['valorunitario']."'
							", 
            	'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$request['codigo']."' " 
				);
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
			'sistema' => 'compras',
			'tabla' => $this->tableAnita,
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$id."' " );
        $apiAnita->apiCall($data);
	}    

	// Devuelve ultimo codigo de retenciones de iva + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
		$apiAnita = new ApiAnita();
		$data = array( 'acc' => 'list', 
				'sistema' => 'compras',
				'tabla' => $this->tableAnita, 
				'campos' => " max(reti_codigo) as $this->keyFieldAnita "
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
		
}
