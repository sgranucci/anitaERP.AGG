<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cobranza;
use App\Repositories\Caja\Caja_MovimientoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\CobranzaNumeracionTransaccion;
use App\Support\Caja\IngresoEgresoAnitaTesmovSupport;
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
		if (isset($data['cobranza_id'])) {
			return $this->persistirCreate($this->resolverNumerotransaccionConCobranza($data));
		}

		$empresaId = (int) $data['empresa_id'];
		$tipoCajaId = (int) $data['tipotransaccion_caja_id'];

		if (! CobranzaNumeracionTransaccion::usaNumeracionSecuencial($tipoCajaId)) {
			throw new \RuntimeException(
				'Los movimientos de caja sin cobranza asociada requieren un tipo de transacción con numeración secuencial.'
			);
		}

		return CobranzaNumeracionTransaccion::conExclusividad(
			$empresaId,
			$tipoCajaId,
			fn () => $this->persistirCreate($this->prepararDataNumeracionIndependiente($data)),
		);
    }

    public function update(array $data, $id)
    {
		// Conserva el creador: el alcance por centro de costo usa usuario_id original.
		unset($data['usuario_id']);

		$caja_movimiento = $this->model->findOrFail($id)->update($data);

		// Actualiza anita
		$anita = self::actualizarAnita($data);


		return $caja_movimiento;
    }

	public function delete($id)
    {
		$caja_movimiento = $this->model->findOrFail($id);

		// Elimina anita tesmov
		if ($caja_movimiento)
		{
			try {
				IngresoEgresoAnitaTesmovSupport::eliminarDesdeMovimiento($caja_movimiento);
			} catch (\Throwable $e) {
				// Si falla Anita, no bloquea borrado ERP solo si no había escritura habilitada;
				// con escritura activa re-lanzamos para no dejar huérfanos inconsistentes.
				if (IngresoEgresoAnitaTesmovSupport::estaHabilitada()) {
					throw $e;
				}
			}

        	$caja_movimiento = $this->model->destroy($id);
		}

		return $caja_movimiento;
    }

    public function find($id)
    {
        if (null == $caja_movimiento = $this->model->with("caja_movimiento_cuentacajas")
									->with("caja_movimiento_estados")
									->with("caja_movimiento_archivos")
									->with("cheques")
									->with("asientos")
									->with("empresas")
									->with("conceptogastos")
									->with("tipotransaccioncajas")
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
											->with("cheques")
											->with("asientos")
											->with("empresas")
											->with("conceptogastos")
											->with("tipotransaccioncajas")
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

	/**
	 * @param  array<string, mixed>  $data
	 * @return array<string, mixed>
	 */
	private function prepararDataNumeracionIndependiente(array $data): array
	{
		if (empty($data['numerotransaccion'])) {
			$data['numerotransaccion'] = CobranzaNumeracionTransaccion::calcularSiguienteNumeroSecuencialBd(
				(int) $data['empresa_id'],
				(int) $data['tipotransaccion_caja_id'],
			);
		}

		if (! isset($data['usuario_id'])) {
			$data['usuario_id'] = Auth::user()->id;
		}

		if (! isset($data['detalle'])) {
			$data['detalle'] = 'Movimiento de caja';
		}

		return $data;
	}

	/**
	 * Movimiento hijo de cobranza: reutiliza el mismo numerotransaccion (Anita tesmov).
	 *
	 * @param  array<string, mixed>  $data
	 * @return array<string, mixed>
	 */
	private function resolverNumerotransaccionConCobranza(array $data): array
	{
		if (empty($data['numerotransaccion'])) {
			$cobranza = Cobranza::query()->find((int) $data['cobranza_id']);
			if ($cobranza !== null) {
				$data['numerotransaccion'] = $cobranza->numerotransaccion;
			}
		}

		return $data;
	}

	/**
	 * @param  array<string, mixed>  $data
	 */
	private function persistirCreate(array $data)
	{
		$caja_movimiento = $this->model->create($data);

		self::guardarAnita($data);

		return $caja_movimiento;
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
