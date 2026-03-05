<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Descuentoventa;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class DescuentoventaRepository implements DescuentoventaRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'descuentoventa';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'aba_descuentoventa';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Descuentoventa $descuentoventa)
    {
        $this->model = $descuentoventa;
    }

    public function all()
    {
        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $descuentoventa = $this->model->create($data);
		
		// Graba anita
		self::guardarAnita($data, $descuentoventa->id);
    }

    public function update(array $data, $id)
    {
        $descuentoventa = $this->model->findOrFail($id)
            ->update($data);
		
		// Actualiza anita
		self::actualizarAnita($data, $id);

		return $descuentoventa;
    }

    public function delete($id)
    {
    	$descuentoventa = Descuentoventa::find($id);
		//
		// Elimina anita
		self::eliminarAnita($descuentoventa->codigo);

        $descuentoventa = $this->model->destroy($id);

		return $descuentoventa;
    }

    public function find($id)
    {
        if (null == $descuentoventa = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $descuentoventa;
    }

    public function findOrFail($id)
    {
        if (null == $descuentoventa = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $descuentoventa;
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $tipoDescuento = 'P';
        switch($request['tipodescuento'])
        {
            case 'POR PORCENTAJE':
                $tipoDescuento = 'P';
                break;
            case 'POR MONTO FIJO':
                $tipoDescuento = 'M';
                break;
            case 'POR CANTIDAD VENDIDA':
                $tipoDescuento = 'C';
                break;
        }
        $estado = 'A';
        switch($request['estado'])
        {
            case 'ACTIVO':
                $estado = 'A';
                break;
            case 'SUSPENDIDO':
                $estado = 'S';
                break;
        }
        $data = array( 'tabla' => $this->tableAnita, 'sistema' => 'ventas', 
			'acc' => 'insert',
            'campos' => ' 
				dtov_descuento,
    			dtov_nombre,
    			dtov_tipodto,
                dtov_porcentaje,
                dtov_montodto,
                dtov_cantidadvta,
                dtov_cantidaddto,
                dtov_estado
				',
            'valores' => " 
				'".$id."', 
				'".$request['nombre']."',
				'".$tipoDescuento."',
                '".$request['porcentajedescuento']."',
                '".$request['montodescuento']."',
                '".$request['cantidadventa']."',
                '".$request['cantidaddescuento']."',
                '".$estado."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $tipoDescuento = 'P';
        switch($request['tipodescuento'])
        {
            case 'POR PORCENTAJE':
                $tipoDescuento = 'P';
                break;
            case 'POR MONTO FIJO':
                $tipoDescuento = 'M';
                break;
            case 'POR CANTIDAD VENDIDA':
                $tipoDescuento = 'C';
                break;
        }
        $estado = 'A';
        switch($request['estado'])
        {
            case 'ACTIVO':
                $estado = 'A';
                break;
            case 'SUSPENDIDO':
                $estado = 'S';
                break;
        }        
        $data = array( 'acc' => 'update', 'sistema' => 'ventas', 'tabla' => $this->tableAnita, 
				'valores' => " 
                dtov_nombre 	                = '".$request['nombre']."',
                dtov_tipodto 	                = '".$tipoDescuento."',
                dtov_porcentaje 	            = '".$request['porcentajedescuento']."',
                dtov_montodto 	                = '".$request['montodescuento']."',
                dtov_cantidadvta 	            = '".$request['cantidadventa']."',
                dtov_cantidaddto 	            = '".$request['cantidaddescuento']."',
                dtov_estado 	                = '".$estado."' ",
				'whereArmado' => " WHERE dtov_descuento = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'sistema' => 'ventas', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE dtov_descuento = '".$id."' " );
        $apiAnita->apiCall($data);
	}

}
