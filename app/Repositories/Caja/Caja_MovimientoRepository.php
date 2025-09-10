<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Repositories\Caja\Caja_MovimientoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;
use DB;

class Caja_MovimientoRepository implements Caja_MovimientoRepositoryInterface
{
    protected $model;
	protected $empresaRepository;
    protected $tableAnita = ['tesmov'];
    protected $keyField = 'numerotransaccion';
    protected $keyFieldAnita = ['tesv_nro'];

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Caja_Movimiento $caja_movimiento,
								EmpresaRepositoryInterface $empresarepository)
    {
        $this->model = $caja_movimiento;
		$this->empresaRepository = $empresarepository;
    }

    public function create(array $data)
    {
		$data['numerotransaccion'] = self::ultimoNumeroTransaccion($data['empresa_id'], $data['tipotransaccion_caja_id']);
		$data['usuario_id'] = Auth::user()->id;
		
		if (!isset($data['detalle']))
			$data['detalle'] = "Movimiento de caja";

		$caja_movimiento = $this->model->create($data);

		// Graba anita
		$anita = self::guardarAnita($data);

		if (strpos($anita, 'Error') !== false)
			throw new Exception($anita);

		return $caja_movimiento;
    }

    public function update(array $data, $id)
    {
		$data['usuario_id'] = Auth::user()->id;
		
		$caja_movimiento = $this->model->findOrFail($id)->update($data);

		// Actualiza anita
		$anita = self::actualizarAnita($data);

		if (strpos($anita, 'Error') !== false)
			throw new Exception($anita);

		return $caja_movimiento;
    }

    public function delete($id)
    {
		$caja_movimiento = $this->model->findOrFail($id);

		// Elimina anita
		if ($caja_movimiento)
		{
			$empresa = $this->empresaRepository->findPorId($caja_movimiento->empresa_id);
			if ($empresa)
				$codigoEmpresa = $empresa->codigo;
			else
				$codigoEmpresa = 1;
						
			$anita = self::eliminarAnita($codigoEmpresa, $caja_movimiento->tipotransaccion_caja_id,
										$caja_movimiento->numerotransaccion);

			if (strpos($anita, 'Error') !== false)
				return 'Error';

        	$caja_movimiento = $this->model->destroy($id);
		}

		return $caja_movimiento;
    }

    public function find($id)
    {
        if (null == $caja_movimiento = $this->model->with("caja_movimiento_cuentacajas")
									->with("caja_movimiento_estados")
									->with("caja_movimiento_archivos")
									->with("asientos")
									->with("empresas")
									->with("conceptogastos")
									->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $caja_movimiento;
    }

    public function findOrFail($id)
    {
        if (null == $caja_movimiento = $this->model->with("caja_movimiento_cuentacajas")
											->with("caja_movimiento_archivos")
											->with("caja_movimiento_estados")
											->with("asientos")
											->with("empresas")
											->with("conceptogastos")
											->findOrFail($id))
			{
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $caja_movimiento;
    }

    public function sincronizarConAnita(){
    }

    private function traerRegistroDeAnita($empresa, $caja_movimiento, $linea){
    }

	private function guardarAnita($request) 
	{
		return 'Success';
	}

	private function actualizarAnita($request) 
	{
		return 'Success';
	}

	private function eliminarAnita($empresa, $codigo) 
	{
	}

	// Devuelve ultimo codigo de caja_movimientos + 1 para agregar nuevos en Anita

	private function ultimoNumeroTransaccion($empresa_id, $tipotransaccion_caja_id) 
	{
		$caja_movimiento = $this->model->select('numerotransaccion')
										->where('empresa_id', $empresa_id)
										->where('tipotransaccion_caja_id', $tipotransaccion_caja_id)
										->orderBy('id', 'desc')->first();
		
		$numerotransaccion = 0;
        if ($caja_movimiento) 
		{
			$numerotransaccion = $caja_movimiento->numerotransaccion;
			$numerotransaccion = $numerotransaccion + 1;
		}
		else	
			$numerotransaccion = 1;

		return $numerotransaccion;
	}

	// Lee gastos anteriores por orden de servicio

	public function leeGastoAnterior($ordenservicio_id)
	{
		$caja_movimiento = $this->model->select('caja_movimiento.id as id',
												'caja_movimiento.tipotransaccion_caja_id as tipotransaccion_caja_id',
												'tipotransaccion_caja.abreviatura as abreviatura',
												'tipotransaccion_caja.signo as signo',
												'caja_movimiento.conceptogasto_id as conceptogasto_id',
												'conceptogasto.nombre as nombreconceptogasto',
												'cuentacaja.codigo as codigocuentacaja',
												'cuentacaja.nombre as nombrecuentacaja',
												'caja_movimiento_cuentacaja.moneda_id as moneda_id',
												'moneda.abreviatura as abreviaturamoneda',
												'caja_movimiento_cuentacaja.monto as monto',
												'caja_movimiento_cuentacaja.cotizacion as cotizacion',
												'caja_movimiento.ordenservicio_id as ordenservicio_id')
										->leftJoin('tipotransaccion_caja', 'tipotransaccion_caja.id', 'caja_movimiento.tipotransaccion_caja_id')
										->leftJoin('conceptogasto', 'conceptogasto.id', 'caja_movimiento.conceptogasto_id')
										->leftJoin('caja_movimiento_cuentacaja', 'caja_movimiento_cuentacaja.caja_movimiento_id', 'caja_movimiento.id')
										->leftJoin('cuentacaja', 'cuentacaja.id', 'caja_movimiento_cuentacaja.cuentacaja_id')
										->leftJoin('moneda', 'moneda.id', 'caja_movimiento_cuentacaja.moneda_id')
										->where([['caja_movimiento.ordenservicio_id', $ordenservicio_id],
												['tipotransaccion_caja.signo', -1]])
										->get();

		return $caja_movimiento;
	}

	public function leeOrdenServicioCajaMovimiento()
	{
		$caja_movimiento = $this->model->select('caja_movimiento.ordenservicio_id as ordenservicio_id')
						->whereNotExists(function ($query) {
							$query->select(DB::raw(1))
									->from('rendicionreceptivo')
									->where('deleted_at', null)
									->whereColumn('caja_movimiento.ordenservicio_id', 'rendicionreceptivo.ordenservicio_id');
						})
						->where('caja_movimiento.ordenservicio_id', '!=', null)
						->get();

		return $caja_movimiento;
	}
}
