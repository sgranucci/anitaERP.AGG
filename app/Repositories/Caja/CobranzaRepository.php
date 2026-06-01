<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Cobranza;
use App\Repositories\Caja\CobranzaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use App\ApiAnita;
use App\Support\Caja\CobranzaNumeracionTransaccion;
use Carbon\Carbon;
use Auth;
use DB;
use Illuminate\Database\QueryException;

class CobranzaRepository implements CobranzaRepositoryInterface
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
    public function __construct(Cobranza $cobranza,
								EmpresaRepositoryInterface $empresarepository)
    {
        $this->model = $cobranza;
		$this->empresaRepository = $empresarepository;
    }

    public function create(array $data)
    {
		$data['monto'] = $data['totalfinalcobranza'];
		$data['moneda_id'] = $data['monedafinalcobranza_id'];

		$intentos = 0;
		while (true) {
			try {
				$cobranza = $this->model->create($data);
				self::guardarAnita($data);

				return $cobranza;
			} catch (QueryException $e) {
				if (
					++$intentos > 2
					|| ! CobranzaNumeracionTransaccion::esViolacionUnicidadNumeracion($e)
					|| empty($data['empresa_id'])
					|| empty($data['tipotransaccion_caja_id'])
				) {
					throw $e;
				}

				if (CobranzaNumeracionTransaccion::usaNumeracionSecuencial((int) $data['tipotransaccion_caja_id'])) {
					$data['numerotransaccion'] = CobranzaNumeracionTransaccion::calcularSiguienteNumeroSecuencialBd(
						(int) $data['empresa_id'],
						(int) $data['tipotransaccion_caja_id'],
					);
				}
			}
		}
    }

    public function update(array $data, $id)
    {
		$data['usuario_id'] = Auth::user()->id;
		$data['monto'] = $data['totalfinalcobranza'];
		$data['moneda_id'] = $data['monedafinalcobranza_id'];

		$cobranza = $this->model->findOrFail($id)->update($data);

		// Actualiza anita
		$anita = self::actualizarAnita($data);


		return $cobranza;
    }

    public function delete($id)
    {
		$cobranza = $this->model->findOrFail($id);

		// Elimina anita
		if ($cobranza)
		{
			$empresa = $this->empresaRepository->findPorId($cobranza->empresa_id);
			if ($empresa)
				$codigoEmpresa = $empresa->codigo;
			else
				$codigoEmpresa = 1;
						
			$anita = self::eliminarAnita($codigoEmpresa, $cobranza->tipotransaccion_caja_id,
										$cobranza->numerotransaccion);


        	$cobranza = $this->model->destroy($id);
		}

		return $cobranza;
    }

    public function find($id)
    {
        if (null == $cobranza = $this->model->with("caja_movimientos")
									->with('cobranza_comprobantes')
									->with('cobranza_retenciones')
									->with("cobranza_estados")
									->with("cobranza_archivos")
									->with("asientos")
									->with("empresas")
									->with("cheques")
									->with("cliente_cuentacorrientes")
									->with("tipotransaccioncajas")
									->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cobranza;
    }

    public function findOrFail($id)
    {
        if (null == $cobranza = $this->model->with("caja_movimientos")
											->with('cobranza_comprobantes')
											->with('cobranza_retenciones')			
											->with("cobranza_estados")
											->with("cobranza_archivos")
											->with("asientos")
											->with("empresas")
											->with("cheques")
											->with("cliente_cuentacorrientes")
											->with("tipotransaccioncajas")
											->findOrFail($id))
			{
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $cobranza;
    }

    public function sincronizarConAnita(){
    }

    private function traerRegistroDeAnita($empresa, $cobranza, $linea){
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

	public function ultimoNumeroTransaccion($empresa_id, $tipotransaccion_caja_id) 
	{
		return CobranzaNumeracionTransaccion::siguienteNumeroSecuencial(
			(int) $empresa_id,
			(int) $tipotransaccion_caja_id,
		);
	}

}
