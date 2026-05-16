<?php

namespace App\Repositories\Ventas;

use App\ApiAnita;
use App\Models\Ventas\Puntoventa;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PuntoventaRepository implements PuntoventaRepositoryInterface
{
    protected $model;

    protected $tableAnita = 'sucursal';

    /**
     * PostRepository constructor.
     *
     * @param  Post  $post
     */
    public function __construct(Puntoventa $puntoventa)
    {
        $this->model = $puntoventa;
    }

    public function all($estado = null)
    {
        if ($estado == null) {
            $puntoventa = $this->model->get();
        } else {
            $puntoventa = $this->model->where('estado', $estado)->get();
        }

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

        if ($puntoventa) {
            $puntoventa = $this->model->destroy($id);
        }

        return $puntoventa;
    }

    public function find($id)
    {
        if (null == $puntoventa = $this->model->with('empresas')->with('actividad_arcas')->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $puntoventa;
    }

    public function findOrFail($id)
    {
        if (null == $puntoventa = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $puntoventa;
    }

    public function guardarAnita($request)
    {
        $apiAnita = new ApiAnita;

        $this->setCondicionIvaAnita($request, $condicioniva_id);

        $data = ['tabla' => $this->tableAnita, 'acc' => 'insert',
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
				'".$request['horarioentrega']."' ",
        ];
        $apiAnita->apiCall($data);
    }

    public function actualizarAnita($request, $id)
    {
        $apiAnita = new ApiAnita;

        $this->setCondicionIvaAnita($request, $condicioniva);

        $data = ['acc' => 'update', 'tabla' => $this->tableAnita,
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
                expr_hs_entrega	                = '".$request['horarioentrega']."' ",
            'whereArmado' => " WHERE expr_codigo = '".$id."' "];
        $apiAnita->apiCall($data);
    }

    public function eliminarAnita($id)
    {
        $apiAnita = new ApiAnita;
        $data = ['acc' => 'delete', 'tabla' => $this->tableAnita,
            'whereArmado' => " WHERE expr_codigo = '".$id."' "];
        $apiAnita->apiCall($data);
    }
}
