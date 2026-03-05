<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Retencionsuss;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;

class RetencionsussRepository implements RetencionsussRepositoryInterface
{
    protected $model, $model_Escala;
    protected $tableAnita = 'retsmae';
    protected $keyField = 'codigo';
	protected $keyFieldAnita = 'retsm_codigo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Retencionsuss $retencionsuss)
    {
        $this->model = $retencionsuss;
    }

    public function all()
    {
        $retencionessuss = $this->model->get();

		if ($retencionessuss->isEmpty())
		{
        	self::sincronizarConAnita();

			$retencionessuss = $this->model->get();
		}
		return $retencionessuss;
    }

    public function create(array $data)
    {
		$codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;

        $retencionsuss = $this->model->create($data);

        // Graba anita
		self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $retencionsuss = $this->model->findOrFail($id)->update($data);

		// Actualiza anita
		self::actualizarAnita($data, $id);

        return $retencionsuss;
    }

    public function delete($id)
    {
    	$retencionsuss = $this->model->find($id);
		$codigo = $retencionsuss->codigo;

        $retencionsuss = $this->model->destroy($id);
        
        self::eliminarAnita($codigo);

		return $retencionsuss;
    }

    public function find($id)
    {
        if (null == $retencionsuss = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $retencionsuss;
    }

    public function findOrFail($id)
    {
        if (null == $retencionsuss = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $retencionsuss;
    }

    public function findPorId($id)
    {
		$retencionsuss = $this->model->where('id', $id)->first();

		return $retencionsuss;
    }

    public function findPorCodigo($codigo)
    {
		return $this->model->where('codigo', $codigo)->first();
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
                retsm_codigo,
				retsm_regimen,
				retsm_desc,
				retsm_f_calculo,
				retsm_valor,
				retsm_minimo
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));
		if (count($dataAnita) > 0) 
		{
            $data = $dataAnita[0];

			// Crea registro 
            $retencionsuss = $this->model->create([
                'id' => $key,
                'nombre' => $data->retsm_desc,
				'codigo' => $data->retsm_codigo,
				'regimen' => $data->retsm_regimen,
				'formacalculo' => $data->retsm_f_calculo,
				'valorretencion' => $data->retsm_valor,
				'minimoimponible' => $data->retsm_minimo
            ]);
        }
    }

	public function guardarAnita($request) {

        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 
			'acc' => 'insert',
			'sistema' => 'compras',
            'campos' => '
                retsm_codigo,
				retsm_regimen,
				retsm_desc,
				retsm_f_calculo,
				retsm_valor,
				retsm_minimo
					',
            'valores' => " 
						'".$request['codigo']."', 
						'".$request['regimen']."',
						'".$request['nombre']."',
						'".$request['formacalculo']."',
						'".$request['valorretencion']."',
						'".$request['minimoimponible']."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {

        $apiAnita = new ApiAnita();

		$data = array( 'acc' => 'update', 
				'tabla' => $this->tableAnita,
				'sistema' => 'compras',
            	'valores' => " 
							retsm_codigo = '".$request['codigo']."', 
							retsm_regimen = '".$request['regimen']."',
							retsm_desc = '".$request['nombre']."',
							retsm_f_calculo = '".$request['formacalculo']."',
							retsm_valor = '".$request['valorretencion']."',
							retsm_minimo = '".$request['minimoimponible']."'
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

	// Devuelve ultimo codigo de retenciones de suss + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
		$apiAnita = new ApiAnita();
		$data = array( 'acc' => 'list', 
				'sistema' => 'compras',
				'tabla' => $this->tableAnita, 
				'campos' => " max(retsm_codigo) as $this->keyFieldAnita "
				);
		$dataAnita = json_decode($apiAnita->apiCall($data));

		if (isset($dataAnita) > 0) 
		{
			$codigo = intval(ltrim($dataAnita[0]->{$this->keyFieldAnita}, '0'));
			$codigo = $codigo + 1;
		}
        else    
            $codigo = 1;
	}
		
}
