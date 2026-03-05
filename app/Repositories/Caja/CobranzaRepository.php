<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Cobranza;
use App\Repositories\Caja\CobranzaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;
use DB;

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

		$cobranza = $this->model->create($data);

		// Graba anita
		$anita = self::guardarAnita($data);

		if (strpos($anita, 'Error') !== false)
			throw new Exception($anita);

		return $cobranza;
    }

    public function update(array $data, $id)
    {
		$data['usuario_id'] = Auth::user()->id;
		$data['monto'] = $data['totalfinalcobranza'];
		$data['moneda_id'] = $data['monedafinalcobranza_id'];

		$cobranza = $this->model->findOrFail($id)->update($data);

		// Actualiza anita
		$anita = self::actualizarAnita($data);

		if (strpos($anita, 'Error') !== false)
			throw new Exception($anita);

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

			if (strpos($anita, 'Error') !== false)
				return 'Error';

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
		$cobranza = $this->model->select('numerotransaccion')
										->where('empresa_id', $empresa_id)
										->where('tipotransaccion_caja_id', $tipotransaccion_caja_id)
										->where('deleted_at', null)
										->orderBy('id', 'desc')->first();
		
		$numerotransaccion = 0;
        if ($cobranza) 
		{
			$numerotransaccion = $cobranza->numerotransaccion;
			$numerotransaccion = $numerotransaccion + 1;
		}
		else	
			$numerotransaccion = 1;

		return $numerotransaccion;
	}

}
