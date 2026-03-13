<?php

namespace App\Repositories\Presupuesto;

use App\Models\Presupuesto\Partidagasto;
use App\Repositories\Presupuesto\PartidagastoRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Carbon\Carbon;
use Auth;
use DB;

class PartidagastoRepository implements PartidagastoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
	public function __construct(Partidagasto $partidagasto)
    {
        $this->model = $partidagasto;
    }

	public function all()
	{

	}
	
    public function create(array $data)
    {
		$data['codigo'] = Self::ultimoPartidagasto();

		return $this->model->create($data);
    }

    public function createDesdeAnita(array $data)
    {
		return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
		$partidagasto = $this->model->findOrFail($id)->update($data);

		return $partidagasto;
    }

    public function delete($id)
    {
		$partidagasto = $this->model->findOrFail($id);

		if ($partidagasto)
        	$partidagasto = $this->model->destroy($id);

		return $partidagasto;
    }

    public function find($id)
    {
        if (null == $partidagasto = $this->model->with("partidagasto_estados")
									->with("partidagasto_montos")
									->with("partidagasto_archivos")
									->with("empresas")
									->with("monedas")
									->with("presupuestos")
									->with("presupuesto_escenarios")
									->with("articulos")
									->with("proveedores")
									->with("cuentacontables")
									->with("centrocostos")
									->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }
		return($partidagasto);
	}

    public function findOrFail($id)
    {
        if (null == $partidagasto = $this->model->with("partidagasto_estados")
									->with("partidagasto_montos")
									->with("partidagasto_archivos")
									->with("empresas")
									->with("monedas")
									->with("presupuestos")
									->with("presupuesto_escenarios")
									->with("articulos")
									->with("proveedores")
									->with("cuentacontables")									
									->with("centrocostos")
									->findOrFail($id))
		{
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $partidagasto;
    }

	// Devuelve ultimo numero de partidagasto + 1 (campo codigo)
	private function ultimoPartidagasto()
	{
		$partidagasto = $this->model->select('codigo')->orderBy('id', 'desc')->first();
		
		$numeropartidagasto = 0;
        if ($partidagasto) 
		{
			$numeropartidagasto = $partidagasto->codigo;
			$numeropartidagasto = $numeropartidagasto + 1;
		}
		else	
			$numeropartidagasto = 1;

		return $numeropartidagasto;
	}	

	public function leePartidaGasto($empresa_id, $presupuesto_id, $presupuesto_escenario_id)
	{
		$partidagasto = $this->model->select('partidagasto.id as id', 
											'partidagasto.presupuesto_id as presupuesto_id',
											'partidagasto.presupuesto_escenario_id as escenario_id',
											'partidagasto.empresa_id as empresa_id',
											'empresa.nombre as nombreempresa',
											'partidagasto.codigo as codigopartida',
											'presupuesto.nombre as nombrepresupuesto',
											'centrocosto.codigo as codigocentrocosto',
											'centrocosto.nombre as nombrecentrocosto',
											'articulo.descripcion as descripcionarticulo',
											'proveedor.nombre as nombreproveedor',
											'partidagasto.moneda_id as moneda_id',
											'moneda.codigo as codigomoneda',
											'moneda.abreviatura as abreviaturamoneda',
											'partidagasto.cuentacontable_id as cuentacontable_id',
											'cuentacontable.codigo as codigocuentacontable',
											'cuentacontable.nombre as nombrecuentacontable',
											'partidagasto.detalle as detalle',
											'partidagasto.estado as estado',
											'partidagasto_monto.periodo as periodo',
											'partidagasto_monto.monto as monto')
											->join('empresa', 'empresa.id', '=', 'partidagasto.empresa_id')
											->join('centrocosto', 'centrocosto.id', '=', 'partidagasto.centrocosto_id')
											->join('moneda', 'moneda.id', '=', 'partidagasto.moneda_id')
											->join('presupuesto', 'presupuesto.id', '=', 'partidagasto.presupuesto_id')
											->leftjoin('proveedor', 'proveedor.id', '=', 'partidagasto.proveedor_id')
											->leftjoin('articulo', 'articulo.id', '=', 'partidagasto.articulo_id')
											->leftjoin('cuentacontable', 'cuentacontable.id', '=', 'partidagasto.cuentacontable_id')	
											->join('partidagasto_monto', 'partidagasto_monto.partidagasto_id', '=', 'partidagasto.id')
											->where('partidagasto.empresa_id', $empresa_id)
											->where('partidagasto.presupuesto_id', $presupuesto_id)
											->where('partidagasto.presupuesto_escenario_id', $presupuesto_escenario_id)
											->where('partidagasto_monto.monto', '!=', 0)
											->get();
		return $partidagasto;
	}
}
