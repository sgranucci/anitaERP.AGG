<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Puntoventa;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class PuntoventaRepository implements PuntoventaRepositoryInterface
{
    protected $model;
    protected $empresaRepository;
    protected $tableAnita = 'sucursal';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'suc_numero';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Puntoventa $puntoventa,
                                EmpresaRepositoryInterface $empresaRepository)
    {
        $this->model = $puntoventa;
        $this->empresaRepository = $empresaRepository;
    }

    public function all($estado = null)
    {
        if ($estado == null)
            $puntoventa = $this->model->get();
        else
            $puntoventa = $this->model->where('estado',$estado)->get();

        return $puntoventa;
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
    	$puntoventa = $this->model->find($id);

        if ($puntoventa)
            $puntoventa = $this->model->destroy($id);

		return $puntoventa;
    }

    public function find($id)
    {
        if (null == $puntoventa = $this->model->with('empresas')->with('actividad_arcas')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $puntoventa;
    }

    public function findOrFail($id)
    {
        if (null == $puntoventa = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $puntoventa;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
                        'sistema' => 'ventas',
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        foreach ($dataAnita as $value) {
            $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
        }
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'campos' => '
			suc_numero,
    		suc_empresa,
    		suc_leyenda1,
    		suc_leyenda2,
    		suc_direccion,
    		suc_telefono,
    		suc_localidad,
    		suc_cond_iva,
    		suc_division,
    		suc_sucursal_div,
			suc_fl_retiene_iva,
			suc_cuit,
			suc_nro_ibr,
            suc_fecha_inicio,
            suc_fl_retiene_ibr,
            suc_cod_postal,
            suc_nroemp,
            suc_poliza,
            suc_fiscal,
            suc_suc_remito
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

            $empresa_id = 3;
            if (str_contains(strtoupper($data->suc_empresa), 'ARMETAL'))
                $empresa_id = 3;
            if (str_contains(strtoupper($data->suc_empresa), 'FARLOC'))
                $empresa_id = 1;

            $provincia_id = 3;
            $localidad_id = 108;
            $pais_id = 1;

            $empresa = $this->empresaRepository->find($empresa_id);

            if ($data->suc_empresa == 'BAJA')
                $estado = 'SUSPENDIDA';
            else
                $estado = 'ACTIVA';

            switch($data->suc_fiscal)
            {
                case 'N':
                    $modoFacturacion = 'M';
                    break;
                case 'E':
                    $modoFacturacion = 'E';
                    break;
                case 'L':
                    $modoFacturacion = 'C';
                    break;
                case 'A':
                    $modoFacturacion = 'A';
                    break;
                case 'R':
                    $modoFacturacion = 'R';
                    break;
                case 'M':
                    $modoFacturacion = 'L';
                    break;
                case 'O':
                    $modoFacturacion = 'O';
                    break;
                case 'I':
                    $modoFacturacion = 'I';
                    break;
            }

            $arr_campos = [
				"nombre" => $data->suc_empresa,
				"codigo" => $data->suc_numero,
                "empresa_id" => $empresa_id,
				"domicilio" => $data->suc_direccion,
				"provincia_id" => $provincia_id,
				"localidad_id" => $localidad_id,
                "pais_id" => $pais_id,
				"codigopostal" => '',
				"telefono" => $data->suc_telefono,
				"email" => '',
                "leyenda" => $data->suc_leyenda1,
				"modofacturacion" => $modoFacturacion,
				"estado" => $estado,
				"webservice" => 'wsfev1',
				"pathafip" => $data->suc_leyenda2,
				"actividad_arca_id" => null,
                "division" => $data->suc_division,
                "numeropoliza" => $data->suc_poliza,
                "puntoventa_remito" => $data->suc_suc_remito
            	];
	
        	$puntoventa = $this->model->create($arr_campos);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

		$this->setCondicionIvaAnita($request, $condicioniva_id);

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'campos' => ' 
				expr_codigo,
    			expr_nombre,
    			expr_direccion,
    			expr_localidad,
    			expr_provincia,
    			expr_cod_postal,
    			expr_telefono,
    			expr_cuit,
    			expr_cond_iva,
    			expr_nro_interno,
				expr_pat_vehiculo,
				expr_pag_acoplado,
				expr_hs_entrega
				',
            'valores' => " 
				'".$request['codigo']."', 
				'".$request['nombre']."',
				'".$request['domicilio']."',
				'".$request['desc_localidad']."',
				'".$request['desc_provincia']."',
				'".$request['codigopostal']."',
				'".$request['telefono']."',
				'".$request['nroinscripcion']."',
				'".$condicioniva_id."',
				'0',
				'".$request['patentevehiculo']."',
				'".$request['patenteacoplado']."',
				'".$request['horarioentrega']."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

		$this->setCondicionIvaAnita($request, $condicioniva);

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
				'valores' => " 
                expr_codigo 	                = '".$request['codigo']."',
                expr_nombre 	                = '".$request['nombre']."',
                expr_direccion 	                = '".$request['domicilio']."',
                expr_localidad 	                = '".$request['desc_localidad']."',
                expr_provincia 	                = '".$request['desc_provincia']."',
                expr_cod_postal 	            = '".$request['codigopostal']."',
                expr_telefono 	                = '".$request['telefono']."',
                expr_cuit 	                    = '".$request['nroinscripcion']."',
                expr_cond_iva 	                = '".$condicioniva."',
                expr_pat_vehiculo 	            = '".$request['patentevehiculo']."',
                expr_pag_acoplado 	            = '".$request['patenteacoplado']."',
                expr_hs_entrega	                = '".$request['horarioentrega']."' "
					,
				'whereArmado' => " WHERE expr_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE expr_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

}
