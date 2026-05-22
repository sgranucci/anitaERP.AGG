<?php

namespace App\Repositories\Compras;

use App\Models\Compras\RetencionIIBB;
use App\Models\Compras\RetencionIIBB_Condicion;
use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Provincia;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;

class RetencionIIBBRepository implements RetencionIIBBRepositoryInterface
{
    protected $model, $model_Condicion;
    protected $tableAnita = ['provibr'];
    protected $keyField = 'codigo';
	protected $keyFieldAnita = 'proib_provincia';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(RetencionIIBB $retencionIIBB, RetencionIIBB_Condicion $retencionIIBB_condicion)
    {
        $this->model = $retencionIIBB;
        $this->model_Condicion = $retencionIIBB_condicion;
    }

    public function all()
    {
        $retencionesIIBB = $this->model->with("provincias")->with("cuentascontables")->with("retencionIIBB_condiciones")->get();

		if ($retencionesIIBB->isEmpty())
		{
        	self::sincronizarConAnita();

			$retencionesIIBB = $this->model->with("provincias")->with("cuentascontables")->with("retencionIIBB_condiciones")->get();
		}
		return $retencionesIIBB;
    }

    public function create(array $data)
    {
        $retencionIIBB = $this->model->create($data);

        // Graba anita
		self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $retencionIIBB = $this->model->findOrFail($id)->update($data);

		// Actualiza anita
		self::actualizarAnita($data, $id);

        return $retencionIIBB;
    }

    public function delete($id)
    {
    	$retencionIIBB = $this->model->find($id);

		$provincia = Provincia::select('id', 'codigo')->where('id' , $retencionIIBB->provincia_id)->first();
		if ($provincia)
		{
			if (isset($provincia->codigo))
				$provincia_id = $provincia->codigo;
			else
				$provincia_id = $provincia->id;
		}
		else
			$provincia_id = 1;

        $retencionIIBB = $this->model->destroy($id);
        
        self::eliminarAnita($provincia_id);

		return $retencionIIBB;
    }

    public function find($id)
    {
        if (null == $retencionIIBB = $this->model->with("retencionIIBB_condiciones")->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $retencionIIBB;
    }

    public function findOrFail($id)
    {
        if (null == $retencionIIBB = $this->model->with("retencionIIBB_condiciones")->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $retencionIIBB;
    }

    public function sincronizarConAnita(){

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'compras',
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
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
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
            }
        }
    }

    public function traerRegistroDeAnita($key){

        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[0], 
			'sistema' => 'compras',
            'campos' => '
					proib_provincia,
					proib_min_convenio,
					proib_por_convenio,
					proib_minimo_local,
					proib_porc_local,
					proib_minimo_noins,
					proib_porc_noinsc,
					proib_cta_contable,
					proib_desc_prov 
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));
		if (count($dataAnita) > 0) 
		{
            $data = $dataAnita[0];

			$cuenta = Cuentacontable::select('id', 'codigo')->where('codigo' , $data->proib_cta_contable)->first();
			if ($cuenta)
				$cuentacontable_id = $cuenta->id;
			else
				$cuentacontable_id = NULL;

			$provincia = Provincia::select('id', 'nombre')->where('id' , '=', $data->proib_provincia)->first();
			if ($provincia)
				$provincia_id = $provincia->id;
			else
				$provincia_id = 1;
	
			// Crea registro 
            $retencionIIBB = $this->model->create([
                'id' => $key,
                'nombre' => $data->proib_desc_prov,
				'provincia_id' => $provincia_id,
				'cuentacontable_id' => $cuentacontable_id
            ]);

			if ($retencionIIBB)
			{
				$retencionIIBB_condicion = $this->model_Condicion->create([
													'retencionIIBB_id' => $retencionIIBB->id,
													'condicionIIBB_id' => 1,
													'minimoimponible' => $data->proib_min_convenio,
													'minimoretencion' => 0,
													'porcentajeretencion' => $data->proib_por_convenio,
													]);

				$retencionIIBB_condicion = $this->model_Condicion->create([
													'retencionIIBB_id' => $retencionIIBB->id,
													'condicionIIBB_id' => 2,
													'minimoimponible' => $data->proib_minimo_local,
													'minimoretencion' => 0,
													'porcentajeretencion' => $data->proib_porc_local,
													]);													

				$retencionIIBB_condicion = $this->model_Condicion->create([
													'retencionIIBB_id' => $retencionIIBB->id,
													'condicionIIBB_id' => 3,
													'minimoimponible' => $data->proib_minimo_noins,
													'minimoretencion' => 0,
													'porcentajeretencion' => $data->proib_porc_noinsc,
													]);																										
			}
        }
    }

	public function guardarAnita($data) {

		$provincia = Provincia::select('id', 'codigo')->where('id' , $data['provincia_id'])->first();
		if ($provincia)
		{
			if (isset($provincia->codigo))
				$provincia_id = $provincia->codigo;
			else
				$provincia_id = $provincia->id;
		}
		else
			$provincia_id = 1;

		$cuenta = Cuentacontable::select('id', 'codigo')->where('id' , $data['cuentacontable_id'])->first();
		if ($cuenta)
			$cuentaContable = $cuenta->codigo;
		else
			$cuentaContable = 0;

		$condicionIIBB_ids = $data->condicionIIBB_ids;
		$minimoRetenciones = $data->minimoretenciones;
		$minimoImponibles = $data->minimoimponibles;
		$porcentajeRetenciones = $data->porcentajeretenciones;

		$minimoConvenio = $minimoLocal = $minimoExento = 0;
		$porcentajeConvenio = $porcentajeLocal = $porcentajeExento = 0;
		for ($i = 0; $i < count($condicionIIBB_ids); $i++)
		{
			switch($condicionIIBB_ids[$i])
		 	{
			case 1:
				$minimoConvenio = $minimoImponibles[$i];
				$porcentajeConvenio = $porcentajeRetenciones[$i];
				break;
			case 2:
				$minimoLocal = $minimoImponibles[$i];
				$porcentajeLocal = $porcentajeRetenciones[$i];
				break;
			case 3:
				$minimoExento = $minimoImponibles[$i];
				$porcentajeExento = $porcentajeRetenciones[$i];
				break;				
			}
		}

        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita[0], 
			'acc' => 'insert',
			'sistema' => 'compras',
            'campos' => '
					proib_provincia,
					proib_min_convenio,
					proib_por_convenio,
					proib_minimo_local,
					proib_porc_local,
					proib_minimo_noins,
					proib_porc_noins,
					proib_cta_contable,
					proib_desc_prov 
					',
            'valores' => " 
						'".$provincia_id."', 
						'".$minimoConvenio."',
						'".$porcentajeConvenio."',
						'".$minimoLocal."',
						'".$porcentajeLocal."',
						'".$minimoExento."',
						'".$porcentajeExento."',
						'".$cuentaContable."',
						'".$request['nombre']."' "
        );
        $apiAnita->apiCallEscritura($data);
	}

	public function actualizarAnita($request, $id) {

		$provincia = Provincia::select('id', 'codigo')->where('id' , $request['provincia_id'])->first();
		if ($provincia)
		{
			if (isset($provincia->codigo))
				$provincia_id = $provincia->codigo;
			else
				$provincia_id = $provincia->id;
		}
		else
			$provincia_id = 1;

		$cuenta = Cuentacontable::select('id', 'codigo')->where('id' , $request['cuentacontable_id'])->first();
		if ($cuenta)
			$cuentaContable = $cuenta->codigo;
		else
			$cuentaContable = 0;

		$condicionIIBB_ids = $request['condicionIIBB_ids'];
		$minimoRetenciones = $request['minimoretenciones'];
		$minimoImponibles = $request['minimoimponibles'];
		$porcentajeRetenciones = $request['porcentajeretenciones'];

		$minimoConvenio = $minimoLocal = $minimoExento = 0;
		$porcentajeConvenio = $porcentajeLocal = $porcentajeExento = 0;
		for ($i = 0; $i < count($condicionIIBB_ids); $i++)
		{
			switch($condicionIIBB_ids[$i])
		 	{
			case 1:
				$minimoConvenio = $minimoImponibles[$i];
				$porcentajeConvenio = $porcentajeRetenciones[$i];
				break;
			case 2:
				$minimoLocal = $minimoImponibles[$i];
				$porcentajeLocal = $porcentajeRetenciones[$i];
				break;
			case 3:
				$minimoExento = $minimoImponibles[$i];
				$porcentajeExento = $porcentajeRetenciones[$i];
				break;				
			}
		}

        $apiAnita = new ApiAnita();

		$data = array( 'acc' => 'update', 
				'tabla' => $this->tableAnita[0],
				'sistema' => 'compras',
            	'valores' => " 
							proib_provincia = '".$provincia_id."',
							proib_min_convenio= '".$minimoConvenio."',
							proib_por_convenio = '".$porcentajeConvenio."',
							proib_minimo_local = '".$minimoLocal."',
							proib_porc_local = '".$porcentajeLocal."',
							proib_minimo_noins = '".$minimoExento."',
							proib_porc_noins = '".$porcentajeExento."',
							proib_cta_contable = '".$cuentaContable."',
							proib_desc_prov = '".$request['nombre']."'
							", 
            	'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$provincia_id."' " 
				);
        $apiAnita->apiCallEscritura($data);

	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
			'sistema' => 'compras',
			'tabla' => $this->tableAnita[0],
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}    
}
