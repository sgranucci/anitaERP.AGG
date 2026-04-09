<?php
namespace App\Services\Presupuesto;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Repositories\Presupuesto\CapexRepositoryInterface;
use App\Repositories\Presupuesto\Capex_EstadoRepositoryInterface;
use App\Repositories\Presupuesto\Capex_ArchivoRepositoryInterface;
use App\Repositories\Presupuesto\Capex_PartidaRepositoryInterface;
use App\Repositories\Presupuesto\Capex_Partida_MontoRepositoryInterface;
use App\Repositories\Presupuesto\PresupuestoRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Models\Presupuesto\Capex_Estado;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App;
use Auth;
use DB;
use Exception;
use App\ApiAnita;

class CapexService 
{
	private $capexRepository;
    private $capex_estadoRepository;
    private $capex_archivoRepository;
	private $capex_partidaRepository;
	private $capex_partida_montoRepository;
	private $centrocostoRepository;
	private $presupuestoRepository;
	private $proveedorRepository;

    public function __construct(CapexRepositoryInterface $capexrepository,
                                Capex_EstadoRepositoryInterface $capex_estadorepository,
                                Capex_ArchivoRepositoryInterface $capex_archivorepository,
								Capex_PartidaRepositoryInterface $capex_partidarepository,
								Capex_Partida_MontoRepositoryInterface $capex_partida_montorepository,
								PresupuestoRepositoryInterface $presupuestorepository,
								CentrocostoRepositoryInterface $centrocostorepository,
								ProveedorRepositoryInterface $proveedorrepository
								)
    {
		$this->capexRepository = $capexrepository;
        $this->capex_estadoRepository = $capex_estadorepository;
        $this->capex_archivoRepository = $capex_archivorepository;
		$this->capex_partidaRepository = $capex_partidarepository;
		$this->capex_partida_montoRepository = $capex_partida_montorepository;
		$this->presupuestoRepository = $presupuestorepository;
		$this->centrocostoRepository = $centrocostorepository;
		$this->proveedorRepository = $proveedorrepository;
    }

	public function guardaCapex($request, $origen = null)
	{
		$data = $request->all();

   		// Crea estado
	   	$data['fechas'][] = Carbon::now();
	   	$data['estados'][] = Capex_Estado::$enumEstado[array_search('A', array_column(Capex_Estado::$enumEstado, 'valor'))]['nombre'];
		$data['usuario_ids'][] = Auth::user()->id;
	   	$data['observacionestados'][] = "Alta de Capex";
		$data['creousuario_id'] = Auth::user()->id;

		DB::beginTransaction();
		try
		{
			$capex = $this->capexRepository->create($data);

			if ($capex == 'Error')
				throw new Exception('Error en grabacion');

			// Guarda tablas asociadas
			if ($capex)
			{
				$data['codigo'] = $capex->codigo;

				Self::agrega($data, $capex, $request);

				$anita = Self::grabaAnita($data);

				if (isset($anita['error']))
					throw new Exception('Error en grabacion anita. '.$anita['mensaje']);
			}

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();
			dd($e->getMessage());
			return ['mensaje' => 'error', 'errores' => $e->getMessage()];
		}
        return ['mensaje' => 'ok'];
	}

	// Agrega tablas asociadas
	private function agrega(&$data, $capex, $request)
	{
		$capex_estado = $this->capex_estadoRepository->create($data, $capex->id);
		$capex_archivo = $this->capex_archivoRepository->create($request, $capex->id);

		if (isset($data['moneda_ids']))
			$capex_partida = $this->capex_partidaRepository->create($data, $capex->id);

		if (isset($data['periodo_monto_armados']))
		{
			for ($i = 0; $i < count($data['moneda_ids']); $i++)
			{
				$dataPartidaMonto['periodos'] = [];
				$dataPartidaMonto['montos'] = [];
				$dataPartidaMonto['creousuario_ids'] = [];
				$dataPartidaMonto['capex_partida_ids'] = [];
				$dataPartidaMonto['capex_ids'] = [];

				// Busca en todo el array los items que corresponden a cada partida
				for ($j = 0; $j < count($data['periodo_monto_armados']); $j++)
				{
					if ($data['items'][$i] == $data['item_monto_armados'][$j])
					{
						$dataPartidaMonto['capex_partida_ids'][] = $data['capex_partida_ids'][$i];
						$dataPartidaMonto['capex_ids'][] = $capex->id;
						$dataPartidaMonto['periodos'][] = $data['periodo_monto_armados'][$j];
						$dataPartidaMonto['montos'][] = $data['monto_armados'][$j];
						$dataPartidaMonto['creousuario_ids'][] = $data['creousuario_id_monto_armados'][$j];						
					}
				}

				$capex_partida_monto = $this->capex_partida_montoRepository->create($dataPartidaMonto);
			}
		}
	}

    public function actualizaCapex($request, $id, $origen = null)
    {
		$data = $request->all();

		DB::beginTransaction();
		try
		{
			Self::actualiza($data, $id, $request);

			$anita = Self::actualizaAnita($data);

			if (isset($anita['error']))
				throw new Exception('Error en actualización anita. '.$anita['mensaje']);

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();
			dd($e->getMessage());
			return ['mensaje' => 'error', 'errores' => $e->getMessage()];
		}
        return ['mensaje' => 'ok'];
    }

	private function actualiza(&$data, $id, $request)
	{
		// Graba capex
		$capex = $this->capexRepository->find($id);

		if ($capex)
			$capex = $this->capexRepository->update($data, $id);
		else
		{
			$capex = $this->capexRepository->create($data);

			$id = $capex->id;
		}

		if ($capex === 'Error')
			throw new Exception('Error en grabacion capex.');

		// Graba movimientos de estados y archivos
		$this->capex_archivoRepository->update($request, $id);
		$this->capex_partidaRepository->update($data, $id);

		// Si hay algun monto de partida para agregar o actualizar procesa
		if (isset($data['periodo_monto_armados']))
		{
			for ($i = 0; $i < count($data['moneda_ids']); $i++)
			{
				$dataPartidaMonto['periodos'] = [];
				$dataPartidaMonto['montos'] = [];
				$dataPartidaMonto['creousuario_ids'] = [];
				$dataPartidaMonto['capex_partida_ids'] = [];
				$dataPartidaMonto['capex_ids'] = [];

				// Busca en todo el array los items que corresponden a cada partida
				for ($j = 0; $j < count($data['periodo_monto_armados']); $j++)
				{
					if ($data['items'][$i] == $data['item_monto_armados'][$j])
					{
						$dataPartidaMonto['capex_partida_ids'][] = $data['capex_partida_ids'][$i];
						$dataPartidaMonto['capex_ids'][] = $id;
						$dataPartidaMonto['periodos'][] = $data['periodo_monto_armados'][$j];
						$dataPartidaMonto['montos'][] = $data['monto_armados'][$j];
						$dataPartidaMonto['creousuario_ids'][] = $data['creousuario_id_monto_armados'][$j];
					}
				}
				if (count($dataPartidaMonto['capex_partida_ids']) > 0)
					$capex_partida_monto = $this->capex_partida_montoRepository->update($dataPartidaMonto);
			}
		}		
	}

	public function actualizaEstadoCapex($estado, $id)
	{
		DB::beginTransaction();
		try
		{
			$capex = $this->capexRepository->update($estado, $id);

			// Crea estado
			if (isset($estado['estado']))
			{
				$data = [];
				$data['fechas'][] = Carbon::now();
				$data['usuario_ids'][] = Auth::user()->id;

				switch($estado['estado'])
			 	{
				case 'ANULADO':
					$data['observacionestados'][] = "Anulación de Capex";
					$data['estados'][] = Capex_Estado::$enumEstado[array_search('B', array_column(Capex_Estado::$enumEstado, 'valor'))]['nombre'];
					break;
				case 'CERRADO':
					$data['observacionestados'][] = "Cierre de Capex";
					$data['estados'][] = Capex_Estado::$enumEstado[array_search('C', array_column(Capex_Estado::$enumEstado, 'valor'))]['nombre'];
					break;
				case 'ACTIVO':
					$data['observacionestados'][] = "Activación de Capex";
					$data['estados'][] = Capex_Estado::$enumEstado[array_search('A', array_column(Capex_Estado::$enumEstado, 'valor'))]['nombre'];
				}

				$data['creousuario_id'][] = Auth::user()->id;

				$capex_estado = $this->capex_estadoRepository->create($data, $id);
			}			

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();

			return ['mensaje' => 'error', 'errores' => $e->getMessage()];
		}		
	}

	public function leeHistoriaCapex($capex_id)
	{
		return $this->capex_estadoRepository->leeHistoriaCapex($capex_id);
	}

	// Graba capex en Anita
	public function grabaAnita($data, $servidor = null, $ifx_server = null)
	{
		// Lee centro de costo
		$centrocosto = $this->centrocostoRepository->find($data['centrocosto_id']);

		$codigoCentroCosto = 0;
		if ($centrocosto)
			$codigoCentroCosto = $centrocosto->codigo;

		// Lee el presupuesto
		$presupuesto = $this->presupuestoRepository->find($data['presupuesto_id']);

		$codigoPresupuesto = 0;
		if ($presupuesto)
			$codigoPresupuesto = $presupuesto->codigo;

		// Graba proyectocapex
        $apiAnita = new ApiAnita();

		$grabaAnita = array( 	'tabla' => 'proyectocapex', 
						'acc' => 'insert',
						'sistema' => 'base_admin',
            			'campos' => ' 
							inroproyecto,
							iempresaid,
							iccosid,
							ipresupuestoid,
							ccodigoproyecto,
							cnombre,
							cdescripcion,
							bactivo,
							brevisado,
							iusuarioid,
							ifechaalta,
							choraalta',
            			'valores' => " 
							'".$data['codigo']."', 
							'".$data['empresa_id']."',
							'".$codigoCentroCosto."',
							'".$codigoPresupuesto."',
							'".$data['codigoproyecto']."',
							'".$data['nombre']."',
							'".$data['detalle']."',
							'".'1'."',
							'".'0'."',
							'".Auth::user()->id."',
							'".date_format(Carbon::now(), 'Ymd')."',
							'".date_format(Carbon::now(), 'H:i')."'"
					);
        $proyectocapex = $apiAnita->apiCall($grabaAnita);
		if (strpos($proyectocapex, 'Error') !== false)
			return ['error' => 'Error proyectocapex', 'mensaje' => $proyectocapex];

		// Graba partidas del capex
		if (isset($data['moneda_ids']))
		{
			$nombres = $data['nombres'];
			$proveedor_ids = $data['proveedor_ids'];
			$moneda_ids = $data['moneda_ids'];
			$estados = $data['estados'];
			$codigos = $data['codigos'];
			$usuario_ids = $data['creousuario_ids'];	

			for ($i = 0; $i < count($moneda_ids); $i++)
			{
				// Lee el proveedor
				$proveedor = $this->proveedorRepository->find($proveedor_ids[$i]);

				$codigoProveedor = '';
				if ($proveedor)
					$codigoProveedor = $proveedor->codigo;

				// Busca en todo el array los items que corresponden a cada partida
				$montoMes = [];
				for ($j = 0; $j <= 12; $j++)
					$montoMes[$j] = 0;				
				$montoTotal = 0;
				if (isset($data['periodo_monto_armados']))
				{
					for ($j = 0; $j < count($data['periodo_monto_armados']); $j++)
					{
						if ($data['item_monto_armados'][$j] == $data['items'][$i])
						{
							$mes = substr($data['periodo_monto_armados'][$j], 4, 2);

							$montoMes[(int) $mes] += $data['monto_armados'][$j];
							$montoTotal += $data['monto_armados'][$j];
						}
					}
				}

				// Graba partidacapex
				$apiAnita = new ApiAnita();

				$grabaAnita = array( 	'tabla' => 'partidacapex', 
								'acc' => 'insert',
								'sistema' => 'base_admin',
								'campos' => ' 
									inropartida,
									inroproyecto,
									cdescripcion,
									cproveedor,
									ccodigomoneda,
									iusuarioid,
									ifechaalta,
									choraalta,
									brevisado,
									fmontoenero,
									fmontofebrero,
									fmontomarzo,
									fmontoabril,
									fmontomayo,
									fmontojunio,
									fmontojulio,
									fmontoagosto,
									fmontoseptiembre,
									fmontooctubre,
									fmontonoviembre,
									fmontodiciembre,
									fmontototal,
									ipresupuestoid',
								'valores' => "   
									'".$codigos[$i]."', 
									'".$data['codigo']."',
									'".$nombres[$i]."',
									'".str_pad($codigoProveedor, 6, "0", STR_PAD_LEFT)."',
									'".$moneda_ids[$i]."',
									'".Auth::user()->id."',
									'".date_format(Carbon::now(), 'Ymd')."',
									'".date_format(Carbon::now(), 'H:i')."',
									'".'0'."',
									'".$montoMes[1]."',
									'".$montoMes[2]."',
									'".$montoMes[3]."',
									'".$montoMes[4]."',
									'".$montoMes[5]."',
									'".$montoMes[6]."',
									'".$montoMes[7]."',
									'".$montoMes[8]."',
									'".$montoMes[9]."',
									'".$montoMes[10]."',
									'".$montoMes[11]."',
									'".$montoMes[12]."',
									'".$montoTotal."',
									'".$codigoPresupuesto."'"
							);  
							
				$partidacapex = $apiAnita->apiCall($grabaAnita);

				if (strpos($partidacapex, 'Error') !== false)
					return ['error' => 'Error partidacapex', 'mensaje' => $partidacapex];
			}
		}
		return ['Success'];
	}

	// Graba capex en Anita
	public function actualizaAnita($data, $servidor = null, $ifx_server = null)
	{
		// Lee centro de costo
		$centrocosto = $this->centrocostoRepository->find($data['centrocosto_id']);

		$codigoCentroCosto = 0;
		if ($centrocosto)
			$codigoCentroCosto = $centrocosto->codigo;

		// Lee el presupuesto
		$presupuesto = $this->presupuestoRepository->find($data['presupuesto_id']);

		$codigoPresupuesto = 0;
		if ($presupuesto)
			$codigoPresupuesto = $presupuesto->codigo;

		// Graba proyectocapex
        $apiAnita = new ApiAnita();

		$grabaAnita = array( 	'tabla' => 'proyectocapex', 
						'acc' => 'update',
						'sistema' => 'base_admin',
            			'valores' => "
							iempresaid = '".$data['empresa_id']."',
							iccosid = '".$codigoCentroCosto."',
							ipresupuestoid = '".$codigoPresupuesto."',
							ccodigoproyecto = '".$data['codigoproyecto']."',
							cnombre = '".$data['nombre']."',
							cdescripcion = '".$data['detalle']."',
							iusuarioid = '".Auth::user()->id."' ",
						'whereArmado' => " WHERE inroproyecto='".$data['codigo']."' "
					);

        $proyectocapex = $apiAnita->apiCall($grabaAnita);

		if (strpos($proyectocapex, 'Error') !== false)
			return ['error' => 'Error proyectocapex', 'mensaje' => $proyectocapex];

		// Graba partidas del capex
		if (isset($data['moneda_ids']))
		{
			$nombres = $data['nombres'];
			$proveedor_ids = $data['proveedor_ids'];
			$moneda_ids = $data['moneda_ids'];
			$estados = $data['estado'];
			$codigos = $data['codigos'];
			$usuario_ids = $data['creousuario_ids'];	
			
			for ($i = 0; $i < count($moneda_ids); $i++)
			{
				// Lee el proveedor
				$proveedor = $this->proveedorRepository->find($proveedor_ids[$i]);

				$codigoProveedor = '';
				if ($proveedor)
					$codigoProveedor = $proveedor->codigo;

				// Busca en todo el array los items que corresponden a cada partida
				$montoMes = [];
				$montoTotal = 0;
				$flGraba = false;

				if (isset($data['periodo_monto_armados']))
				{
					for ($j = 0; $j <= 12; $j++)
						$montoMes[$j] = 0;
					for ($j = 0; $j < count($data['periodo_monto_armados']); $j++)
					{
						if ($data['item_monto_armados'][$j] == $data['items'][$i])
						{
							$mes = substr($data['periodo_monto_armados'][$j], 5, 2);

							$montoMes[(int) $mes] += $data['monto_armados'][$j];
							$montoTotal += $data['monto_armados'][$j];

							$flGraba = true;
						}
					}
				}
				// Actualiza partidacapex
				$apiAnita = new ApiAnita();

				if ($flGraba)
				{
					// Busca si existe el registro de partida
					$apiAnita = new ApiAnita();
					$consultaAnita = array( 'acc' => 'list', 
									'sistema' => 'base_admin',
									'tabla' => 'partidacapex', 
									'campos' => 'inroproyecto',
									'whereArmado' => " WHERE inropartida='".$codigos[$i]."' AND inroproyecto='".$data['codigo']."' "
									);
			        $dataAnita = json_decode($apiAnita->apiCall($consultaAnita));

					if (count($dataAnita) == 0)
						$grabaAnita = array( 	'tabla' => 'partidacapex', 
									'acc' => 'insert',
									'sistema' => 'base_admin',
									'campos' => ' 
										inropartida,
										inroproyecto,
										cdescripcion,
										cproveedor,
										ccodigomoneda,
										iusuarioid,
										ifechaalta,
										choraalta,
										brevisado,
										fmontoenero,
										fmontofebrero,
										fmontomarzo,
										fmontoabril,
										fmontomayo,
										fmontojunio,
										fmontojulio,
										fmontoagosto,
										fmontoseptiembre,
										fmontooctubre,
										fmontonoviembre,
										fmontodiciembre,
										fmontototal,
										ipresupuestoid',
									'valores' => "   
										'".$codigos[$i]."', 
										'".$data['codigo']."',
										'".$nombres[$i]."',
										'".str_pad($codigoProveedor, 6, "0", STR_PAD_LEFT)."',
										'".$moneda_ids[$i]."',
										'".Auth::user()->id."',
										'".date_format(Carbon::now(), 'Ymd')."',
										'".date_format(Carbon::now(), 'H:i')."',
										'".'0'."',
										'".$montoMes[1]."',
										'".$montoMes[2]."',
										'".$montoMes[3]."',
										'".$montoMes[4]."',
										'".$montoMes[5]."',
										'".$montoMes[7]."',
										'".$montoMes[6]."',
										'".$montoMes[8]."',
										'".$montoMes[9]."',
										'".$montoMes[10]."',
										'".$montoMes[11]."',
										'".$montoMes[12]."',
										'".$montoTotal."',
										'".$codigoPresupuesto."'"
								);  
					else
						$grabaAnita = array( 	'tabla' => 'partidacapex', 
									'acc' => 'update',
									'sistema' => 'base_admin',
									'valores' => "
										cdescripcion = '".$nombres[$i]."',
										cproveedor = '".str_pad($codigoProveedor, 6, "0", STR_PAD_LEFT)."',
										ccodigomoneda = '".$moneda_ids[$i]."',
										iusuarioid = '".Auth::user()->id."',
										fmontoenero = '".$montoMes[1]."',
										fmontofebrero = '".$montoMes[2]."',
										fmontomarzo = '".$montoMes[3]."',
										fmontoabril = '".$montoMes[4]."',
										fmontomayo = '".$montoMes[5]."',
										fmontojunio = '".$montoMes[6]."',
										fmontojulio = '".$montoMes[7]."',
										fmontoagosto = '".$montoMes[8]."',
										fmontoseptiembre = '".$montoMes[9]."',
										fmontooctubre = '".$montoMes[10]."',
										fmontonoviembre = '".$montoMes[11]."',
										fmontodiciembre = '".$montoMes[12]."',
										fmontototal = '".$montoTotal."',
										ipresupuestoid = '".$codigoPresupuesto."' ",
										'whereArmado' => " WHERE inropartida='".$codigos[$i]."' AND inroproyecto='".$data['codigo']."' "
								);  
				}
				else
				{
					// Verifica si crea o regraba
					if ($data['capex_partida_ids'][$i] == null)
						$grabaAnita = array( 	'tabla' => 'partidacapex', 
									'acc' => 'insert',
									'sistema' => 'base_admin',
									'campos' => ' 
										inropartida,
										inroproyecto,
										cdescripcion,
										cproveedor,
										ccodigomoneda,
										iusuarioid,
										ifechaalta,
										choraalta,
										brevisado,
										ipresupuestoid',
									'valores' => "   
										'".$codigos[$i]."', 
										'".$data['codigo']."',
										'".$nombres[$i]."',
										'".str_pad($codigoProveedor, 6, "0", STR_PAD_LEFT)."',
										'".$moneda_ids[$i]."',
										'".Auth::user()->id."',
										'".date_format(Carbon::now(), 'Ymd')."',
										'".date_format(Carbon::now(), 'H:i')."',
										'".'0'."',
										'".$codigoPresupuesto."'"
								);  
					else
						$grabaAnita = array( 	'tabla' => 'partidacapex', 
									'acc' => 'update',
									'sistema' => 'base_admin',
									'valores' => "
										cdescripcion = '".$nombres[$i]."',
										cproveedor = '".str_pad($codigoProveedor, 6, "0", STR_PAD_LEFT)."',
										ccodigomoneda = '".$moneda_ids[$i]."',
										iusuarioid = '".Auth::user()->id."',
										ipresupuestoid = '".$codigoPresupuesto."' ",
										'whereArmado' => " WHERE inropartida='".$codigos[$i]."' AND inroproyecto='".$data['codigo']."' "
								);  				
				}
								
				$partidacapex = $apiAnita->apiCall($grabaAnita);

				if (strpos($partidacapex, 'Error') !== false)
					return ['error' => 'Error partidacapex', 'mensaje' => $partidacapex];
			}
		}
		// Busca las partidas por numero de proyecto para ver si quedan algunas por borrar
		$apiAnita = new ApiAnita();
		$consultaAnita = array( 'acc' => 'list', 
						'sistema' => 'base_admin',
						'tabla' => 'partidacapex', 
						'campos' => 'inroproyecto, inropartida',
						'whereArmado' => " WHERE inroproyecto='".$data['codigo']."' "
						);
		$dataAnita = json_decode($apiAnita->apiCall($consultaAnita));

		for ($i = 0; $i < count($dataAnita); $i++)
		{
			$nroProyecto = $dataAnita[$i]->inroproyecto;
			$nroPartida = $dataAnita[$i]->inropartida;

			// Busca si la partida existe en las tablas
			for ($j = 0, $flEncontro = false; $j < count($codigos); $j++)
			{
				if ($codigos[$j] == $nroPartida)
				{
					$flEncontro = true;
					break;
				}
			}
			// Si no existe la borra de anita
			if (!$flEncontro)
			{
				$apiAnita = new ApiAnita();
        		$grabaAnita = array( 'acc' => 'delete', 
						'sistema' => 'base_admin',
						'tabla' => 'partidacapex', 
						'whereArmado' => " WHERE inropartida='".$nroPartida."' AND inroproyecto='".$nroProyecto."' "
						);

				$partidacapex = $apiAnita->apiCall($grabaAnita);
			}
		}

		return ['Success'];
	}

	// Borra capex en Anita
	public function borraAnita($data)
	{
        $apiAnita = new ApiAnita();
        $grabaAnita = array( 'acc' => 'delete', 
						'sistema' => 'base_admin',
						'tabla' => 'proyectocapex', 
						'whereArmado' => " WHERE inroproyecto='".$data['codigo']."' "
						);

		$proyectocapex = $apiAnita->apiCall($grabaAnita);

		if (strpos($proyectocapex, 'Error') !== false)
			return ['error' => 'Error borrando proyectocapex', 'mensaje' => $proyectocapex];	
		
        $apiAnita = new ApiAnita();
        $grabaAnita = array( 'acc' => 'delete', 
						'sistema' => 'base_admin',
						'tabla' => 'partidacapex', 
						'whereArmado' => " WHERE inroproyecto='".$data['codigo']."' "
						);

		$partidacapex = $apiAnita->apiCall($grabaAnita);

		if (strpos($partidacapex, 'Error') !== false)
			return ['error' => 'Error borrando partidacapex', 'mensaje' => $partidacapex];		
	}

	public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => 'inroproyecto',
						'tabla' => 'proyectocapex',
						'sistema' => 'base_admin' );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		foreach ($dataAnita as $value) 
			$this->traerRegistroDeAnita($value->inroproyecto);
    }

    public function traerRegistroDeAnita($key){

        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 
			'tabla' => 'proyectocapex', 
			'sistema' => 'base_admin',
            'campos' => '
					inroproyecto,
					iempresaid,
					iccosid,
					ipresupuestoid,
					ccodigoproyecto,
					cnombre,
					cdescripcion,
					bactivo,
					brevisado,
					iusuarioid,
					ifechaalta,
					choraalta',
            'whereArmado' => " WHERE inroproyecto='".$key."' "
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $dataCapex = $dataAnita[0];

			$presupuesto = $this->presupuestoRepository->findPorCodigo($dataCapex->ipresupuestoid);
			
			if ($presupuesto)
				$presupuesto_id = $presupuesto->id;	
			else
				$presupuesto_id = null;

			$centrocosto = $this->centrocostoRepository->findPorCodigo($dataCapex->iccosid);
			
			if ($centrocosto)
				$centrocosto_id = $centrocosto->id;	
			else
				$centrocosto_id = null;

			$arrayCampos = [
				'empresa_id' => $dataCapex->iempresaid, 
				'presupuesto_id' => $presupuesto_id, 
				'centrocosto_id' => $centrocosto_id, 
				'nombre' => $dataCapex->cnombre, 
				'detalle' => $dataCapex->cdescripcion, 
				'codigoproyecto' => $dataCapex->ccodigoproyecto, 
				'estado' => 'ACTIVO', 
				'codigo' => $dataCapex->inroproyecto, 
				'creousuario_id' => $usuario_id
			];

			$capex = $this->capexRepository->createDesdeAnita($arrayCampos);

			// Arma partidas
			$apiAnita = new ApiAnita();
			$data = array( 
				'acc' => 'list', 
				'tabla' => 'partidacapex', 
				'sistema' => 'base_admin',
				'campos' => '
						inropartida,
						inroproyecto,
						cdescripcion,
						cproveedor,
						ccodigomoneda,
						iusuarioid,
						ifechaalta,
						choraalta,
						brevisado,
						fmontoenero,
						fmontofebrero,
						fmontomarzo,
						fmontoabril,
						fmontomayo,
						fmontojunio,
						fmontojulio,
						fmontoagosto,
						fmontoseptiembre,
						fmontooctubre,
						fmontonoviembre,
						fmontodiciembre,
						fmontototal,
						ipresupuestoid',
				'whereArmado' => " WHERE inroproyecto='".$key."' "
			);
			$dataAnita = json_decode($apiAnita->apiCall($data));

			if (count($dataAnita) > 0) {
				$data = $dataAnita[0];

				// Lee el proveedor
				$proveedor = $this->proveedorRepository->findPorCodigo(ltrim($data->cproveedor,'0'));

				$proveedor_id = null;
				if ($proveedor)
					$proveedor_id = $proveedor->id;

				$arrayCampos = [
					'capex_id' => $capex->id,
					'nombre' => $data->cdescripcion, 
					'proveedor_id' => $proveedor_id, 
					'moneda_id' => $data->ccodigomoneda, 
					'estado' => '', 
					'codigo' => $data->inropartida, 
					'creousuario_id' => $usuario_id
					];

				$capex_partida = $this->capex_partidaRepository->createUnique($arrayCampos);

				// Graba montos mensuales
				for ($i = 1; $i <= 12; $i++)
				{
					switch($i)
					{
						case 1:
							$monto = $data->fmontoenero;
							$periodo = $presupuesto->anio.'-01';
							break;
						case 2:
							$monto = $data->fmontofebrero;
							$periodo = $presupuesto->anio.'-02';
							break;
						case 3:
							$monto = $data->fmontomarzo;
							$periodo = $presupuesto->anio.'-03';							
							break;
						case 4:
							$monto = $data->fmontoabril;
							$periodo = $presupuesto->anio.'-04';							
							break;
						case 5:
							$monto = $data->fmontomayo;
							$periodo = $presupuesto->anio.'-05';							
							break;
						case 6:
							$monto = $data->fmontojunio;
							$periodo = $presupuesto->anio.'-06';
							break;
						case 7:
							$monto = $data->fmontojulio;
							$periodo = $presupuesto->anio.'-07';							
							break;
						case 8:
							$monto = $data->fmontoagosto;
							$periodo = $presupuesto->anio.'-08';							
							break;
						case 9:
							$monto = $data->fmontoseptiembre;
							$periodo = $presupuesto->anio.'-09';							
							break;
						case 10:
							$monto = $data->fmontooctubre;
							$periodo = $presupuesto->anio.'-10';							
							break;
						case 11:
							$monto = $data->fmontonoviembre;
							$periodo = $presupuesto->anio.'-11';							
							break;
						case 12:
							$monto = $data->fmontodiciembre;
							$periodo = $presupuesto->anio.'-12';							
							break;
					}
				
					if ($monto != 0)
					{
						$arrayCampos = [
								'capex_partida_id' => $capex_partida->id,
								'capex_id' => $capex->id, 
								'periodo' => $periodo, 
								'monto' => $monto, 
								'creousuario_id' => $usuario_id
							];

						$capex_partida_monto = $this->capex_partida_montoRepository->createUnique($arrayCampos);
					}
				}
			}
			// Crea estado
			$data = [];
			$data['fechas'][] = Carbon::now();
			$data['estados'][] = Capex_Estado::$enumEstado[array_search('A', array_column(Capex_Estado::$enumEstado, 'valor'))]['nombre'];
			$data['usuario_ids'][] = Auth::user()->id;
			$data['observacionestados'][] = "Alta de Capex desde Anita";

			$data['creousuario_id'] = Auth::user()->id;

			$capex_partida_monto = $this->capex_estadoRepository->create($data, $capex->id);
		}
	}
}
