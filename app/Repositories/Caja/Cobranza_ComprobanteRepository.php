<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Cobranza_Comprobante;
use App\Repositories\Caja\Tipotransaccion_CajaRepositoryInterface;
use App\Repositories\Ventas\Cliente_CuentacorrienteRepositoryInterface;
use App\Repositories\Ventas\Cliente_Cuentacorriente_AplicacionRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Cobranza_ComprobanteRepository implements Cobranza_ComprobanteRepositoryInterface
{
    protected $model;
	protected $tipotransaccion_cajaRepository;
	protected $cliente_cuentacorrienteRepository;
	protected $cliente_cuentacorriente_aplicacionRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cobranza_Comprobante $cobranza_comprobante,
								TipoTransaccion_CajaRepositoryInterface $tipotransaccion_cajarepository,
								Cliente_CuentacorrienteRepositoryInterface $cliente_cuentacorrienteRepository,
								Cliente_Cuentacorriente_AplicacionRepositoryInterface $cliente_cuentacorriente_aplicacionRepository)
    {
        $this->model = $cobranza_comprobante;
		$this->tipotransaccion_cajaRepository = $tipotransaccion_cajarepository;
		$this->cliente_cuentacorrienteRepository = $cliente_cuentacorrienteRepository;
		$this->cliente_cuentacorriente_aplicacionRepository = $cliente_cuentacorriente_aplicacionRepository;
    }

    public function create(array $data, $id)
    {
		return self::guardarCobranza_Comprobante($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$cobranza_comprobante = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarCobranza_Comprobante($data, 'update', $id);
    }

    public function delete($cobranza_id, $codigo)
    {
        return $this->model->where('cobranza_id', $cobranza_id)->delete();
    }

    public function find($id)
    {
        if (null == $cobranza_comprobante = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cobranza_comprobante;
    }

    public function findOrFail($id)
    {
        if (null == $cobranza_comprobante = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cobranza_comprobante;
    }

	private function guardarCobranza_Comprobante($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$cobranza_comprobante = $this->model->where('cobranza_id', $id)->get()->pluck('id')->toArray();
			$q_cobranza_comprobante = count($cobranza_comprobante);
		}

		if (isset($data['idcuentacorrientes']))
		{
			$tipotransaccion_caja = $this->tipotransaccion_cajaRepository->find($data['tipotransaccion_caja_id']);

			$signo = 1;
			if ($tipotransaccion_caja)
			{
				if ($tipotransaccion_caja->signo == 'I')
					$signo = 1;
				else
					$signo = -1;

				$numerotransaccion = $tipotransaccion_caja->abreviatura.' '.$data['numerotransaccion'];
			}
			else
				$numerotransaccion = $data['numerotransaccion'];

			$cliente_cuentacorriente_ids = $data['idcuentacorrientes'];
			$venta_ids = $data['idventas'];
			$moneda_ids = $data['monedacomprobante_ids'];
			$montos = $data['montoaplicadocomprobantes'];
			$cotizaciones = $data['cotizacioncomprobantes'];
			$codigocomprobantes = $data['codigocomprobantes'];

			if ($funcion == 'update')
			{
				$_id = $cobranza_comprobante;

				// Borra los que sobran
				if ($q_cobranza_comprobante > count($cliente_cuentacorriente_ids))
				{
					for ($d = count($cliente_cuentacorriente_ids); $d < $q_cobranza_comprobante; $d++)
					{
						// Borra archivo de cobranza
						$this->model->find($_id[$d])->delete();

						// Borra aplicacion en cuenta corriente
						$this->cliente_cuentacorriente_aplicacionRepository
							->borraPorCuentaCorrienteCobranza($cliente_cuentacorriente_ids[$i], $id);

						// Borra cuenta corriente por cobranza y comprobante
						$cliente_cuentacorriente = $this->cliente_cuentacorrienteRepository->find($cliente_cuentacorriente_ids[$i]);

						$venta_id = null;
						if ($cliente_cuentacorriente)
						{
							$venta_id = $cliente_cuentacorriente->venta_id;

							$cliente_cuentacorriente = $this->cliente_cuentacorrienteRepository->buscaPorVentaCobranza($venta_id, $data['cobranza_id']);

							// Borra las aplicaciones de la cobranza que sobran
							foreach($cliente_cuentacorriente as $cobranza)
								$this->cliente_cuentacorrienteRepository->find($cobranza->id)->delete();
						}
					}
				}
				// Actualiza los que ya existian
				for ($i = 0; $i < $q_cobranza_comprobante && $i < count($cliente_cuentacorriente_ids); $i++)
				{
					if ($i < count($cliente_cuentacorriente_ids) && $montos[$i] != null)
					{
						$monto = 0;
						if ($montos[$i] != null && $montos[$i] != 0)
							$monto = $montos[$i] * $signo;

						$cobranza_comprobante = $this->model->findOrFail($_id[$i])->update([
									"cobranza_id" => $id,
									"cliente_cuentacorriente_id" => $cliente_cuentacorriente_ids[$i],
									"moneda_id" => $moneda_ids[$i],
									"montoaplicado" => $monto,
									"cotizacion" => $cotizaciones[$i]
									]);

						// Graba aplicacion de cuenta corriente del comprobante
						// Busca la aplicacion por id de cuenta corriente y id de cobranza
						$cliente_cuentacorriente_aplicacion_cobranza = $this->cliente_cuentacorriente_aplicacionRepository
																	->buscaPorCuentaCorrienteCobranza($cliente_cuentacorriente_ids[$i], $id);

						if ($cliente_cuentacorriente_aplicacion_cobranza)
						{
							// Guarda cobranza del comprobante en cuenta corriente
							// Lee la aplicacion de la cobranza en el comprobante
							$cliente_cuentacorriente = $this->cliente_cuentacorrienteRepository->buscaPorVentaCobranza($venta_ids[$i], $data['cobranza_id']);
							
							if ($cliente_cuentacorriente)
							{
								$idCobranza = $cliente_cuentacorriente[0]->id;

								$cliente_cuentacorriente = $this->cliente_cuentacorrienteRepository->update([
										'fecha' => $data['fecha'],
										'fechavencimiento' => $data['fecha'],
										'cliente_id' => $data['cliente_id'],
										'total' => -$monto,
										'moneda_id' => $moneda_ids[$i],
										'cotizacion' => $cotizaciones[$i],
										'venta_id' => $venta_ids[$i],
										'cobranza_id' => $data['cobranza_id'],
										'empresa_id' => $data['empresa_id']
								], $idCobranza);				

								// Guarda aplicacion de la cobranza
								$this->cliente_cuentacorriente_aplicacionRepository->update([
										"fecha" => $data['fecha'],
										"cobranza_id" => $id,
										"cliente_cuentacorriente_id" => $cliente_cuentacorriente_ids[$i],
										"moneda_id" => $moneda_ids[$i],
										"total" => -$monto,
										"cotizacion" => $cotizaciones[$i],
										"comprobanteaplicado" => $numerotransaccion, // apunta a la cobranza
										"ventaaplicado_id" => null, // No aplica ningun comprobante de ventas porque es una cobranza
										"cliente_cuentacorriente_aplicado_id" => $idCobranza // aca va id de la cobranza en cuenta corriente
										], $cliente_cuentacorriente_aplicacion_cobranza->id);
							}
						}
						// Debe leer aplicacion de la cobranza del comprobante en cuestion
						$cliente_cuentacorriente_aplicacion_comprobante = $this->cliente_cuentacorriente_aplicacionRepository
																	->buscaPorCuentaCorrienteComprobanteAplicado($cliente_cuentacorriente_ids[$i], $id);

						if ($cliente_cuentacorriente_aplicacion_comprobante)
						{
							// Guarda aplicacion de la cobranza (comprobante que aplica)
							$this->cliente_cuentacorriente_aplicacionRepository->update([
									"fecha" => $data['fecha'],
									"cobranza_id" => $data['cobranza_id'],
									// no deberia cambiar id "cliente_cuentacorriente_id" => $cliente_cuentacorriente->id,
									"moneda_id" => $moneda_ids[$i],
									"total" => $monto,
									"cotizacion" => $cotizaciones[$i],
									"comprobanteaplicado" => $codigocomprobantes[$i],
									"ventaaplicado_id" => null,
									"cliente_cuentacorriente_aplicado_id" => $cliente_cuentacorriente_ids[$i] // aca va id del comprobante que aplica en cuenta corriente
									], $cliente_cuentacorriente_aplicacion_comprobante->id);		
						}
					}
				}
				if ($q_cobranza_comprobante > count($cliente_cuentacorriente_ids))
					$i = $d; 
			}
			else
				$i = 0;
			for ($i_movimiento = $i; $i_movimiento < count($cliente_cuentacorriente_ids); $i_movimiento++)
			{
				if ($cliente_cuentacorriente_ids[$i_movimiento] != '' && $montos[$i_movimiento] != null) 
				{
					$monto = 0;
					if ($montos[$i_movimiento] != null && $montos[$i_movimiento] != 0)
						$monto = $montos[$i_movimiento] * $signo;

					$cobranza_comprobante = $this->model->create([
						"cobranza_id" => $id,
						"cliente_cuentacorriente_id" => $cliente_cuentacorriente_ids[$i_movimiento],
						"moneda_id" => $moneda_ids[$i_movimiento],
						"montoaplicado" => $monto,
						"cotizacion" => $cotizaciones[$i_movimiento]
						]);

					// Lee el comprobante de la cuenta corriente
					$cliente_cuentacorriente = $this->cliente_cuentacorrienteRepository->find($cliente_cuentacorriente_ids[$i_movimiento]);

					// Graba aplicacion
					if ($cliente_cuentacorriente)
					{
						$venta_id = $cliente_cuentacorriente->venta_id;

						//$this->cliente_cuentacorriente_aplicacionRepository->create([
					    //		"fecha" => $data['fecha'],
						//		"cobranza_id" => $id,
						//		"cliente_cuentacorriente_id" => $cliente_cuentacorriente_ids[$i_movimiento],
						//		"moneda_id" => $moneda_ids[$i_movimiento],
						//		"total" => -$monto,
						//		"cotizacion" => $cotizaciones[$i_movimiento],
						//		"comprobanteaplicado" => $numerotransaccion,
						//		"ventaaplicado_id" => null,
						//		
						//		]);		

						// Guarda cobranza del comprobante en cuenta corriente
						$cliente_cuentacorriente = $this->cliente_cuentacorrienteRepository->create([
								'fecha' => $data['fecha'],
								'fechavencimiento' => $data['fecha'],
								'cliente_id' => $data['cliente_id'],
								'total' => -$monto,
								'moneda_id' => $moneda_ids[$i_movimiento],
								'cotizacion' => $cotizaciones[$i_movimiento],
								'venta_id' => $venta_id,
								'cobranza_id' => $data['cobranza_id'],
								'empresa_id' => $data['empresa_id']
						]);				
						
						$this->cliente_cuentacorriente_aplicacionRepository->create([
								"fecha" => $data['fecha'],
								"cobranza_id" => $id,
								"cliente_cuentacorriente_id" => $cliente_cuentacorriente_ids[$i_movimiento],
								"moneda_id" => $moneda_ids[$i_movimiento],
								"total" => -$monto,
								"cotizacion" => $cotizaciones[$i_movimiento],
								"comprobanteaplicado" => $numerotransaccion,
								"ventaaplicado_id" => null,
								"cliente_cuentacorriente_aplicado_id" => $cliente_cuentacorriente->id
								]);		

						// Guarda aplicacion de la cobranza
						$this->cliente_cuentacorriente_aplicacionRepository->create([
								"fecha" => $data['fecha'],
								"cobranza_id" => $data['cobranza_id'],
								"cliente_cuentacorriente_id" => $cliente_cuentacorriente->id,
								"moneda_id" => $moneda_ids[$i_movimiento],
								"total" => $monto,
								"cotizacion" => $cotizaciones[$i_movimiento],
								"comprobanteaplicado" => $codigocomprobantes[$i_movimiento],
								"ventaaplicado_id" => null,
								"cliente_cuentacorriente_aplicado_id" => $cliente_cuentacorriente_ids[$i_movimiento] // aca va id del comprobante que aplica en cuenta corriente
								]);		
					}
					else
						throw new ModelNotFoundException("No encontro comprobante en la cuenta corriente"); 
				}
			}
		}
		else
		{
			$cobranza_comprobante = $this->model->where('cobranza_id', $id)->delete();
		}

		return $cobranza_comprobante;
	}
}

