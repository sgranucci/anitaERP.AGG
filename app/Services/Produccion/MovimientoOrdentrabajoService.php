<?php
namespace App\Services\Produccion;

use App\Repositories\Stock\Articulo_CostoRepositoryInterface;
use App\Repositories\Ventas\OrdentrabajoRepositoryInterface;
use App\Repositories\Ventas\Ordentrabajo_Combinacion_TalleRepositoryInterface;
use App\Repositories\Ventas\Ordentrabajo_TareaRepositoryInterface;
use App\Repositories\Ventas\Pedido_CombinacionRepositoryInterface;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use App\Repositories\Produccion\MovimientoOrdentrabajoRepositoryInterface;
use App\Repositories\Produccion\OperacionRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App;
use Auth;
use DB;
use Exception;

class MovimientoOrdentrabajoService 
{
	protected $ordentrabajo_tareaRepository;
	protected $ordentrabajo_combinacion_talleRepository;
	protected $ordentrabajoRepository;
	protected $operacionRepository;
	protected $movimientoordentrabajoRepository;
	protected $articulo_costoRepository;
	protected $pedido_combinacionRepository;
	protected $clienteRepository;

    public function __construct(
								OrdentrabajoRepositoryInterface $ordentrabajorepository,
								Ordentrabajo_Combinacion_TalleRepositoryInterface $ordentrabajocombinaciontallerepository,
								Ordentrabajo_TareaRepositoryInterface $ordentrabajotarearepository,
								MovimientoOrdentrabajoRepositoryInterface $movimientoordentrabajorepository,
								Articulo_CostoRepositoryInterface $articulo_costorepository,
								OperacionRepositoryInterface $operacionrepository,
								Pedido_CombinacionRepositoryInterface $pedido_combinacionrepository,
								ClienteRepositoryInterface $clienterepository
								)
    {
        $this->ordentrabajoRepository = $ordentrabajorepository;
        $this->ordentrabajo_combinacion_talleRepository = $ordentrabajocombinaciontallerepository;
        $this->ordentrabajo_tareaRepository = $ordentrabajotarearepository;
        $this->movimientoordentrabajoRepository = $movimientoordentrabajorepository;
		$this->articulo_costoRepository = $articulo_costorepository;
        $this->operacionRepository = $operacionrepository;
		$this->pedido_combinacionRepository = $pedido_combinacionrepository;
		$this->clienteRepository = $clienterepository;
    }

	public function estadoEnum()
	{
		return $this->movimientoordentrabajoRepository->estadoEnum(); 
	}

	public function leeMovimientoOrdenTrabajo($id = null)
	{
		if ($id)
      		$movimientoOrdentrabajo = $this->movimientoordentrabajoRepository->find($id);
		else
			$movimientoOrdentrabajo = $this->movimientoordentrabajoRepository->allFiltrado();

		return $movimientoOrdentrabajo;
	}

	public function leeMovimientoOrdenTrabajoPorOt($id)
	{
		$movimientoOrdentrabajo = $this->movimientoordentrabajoRepository->allSinFiltrar($id);

		return $movimientoOrdentrabajo;
	}

	public function guardaMovimientoOrdenTrabajo($data, $funcion, $id = null)
	{
		$usuario_id = Auth::user()->id;
		$tipooperacionEnum = $this->operacionRepository->tipooperacionEnum(); 
		$estadoEnum = self::estadoEnum(); 

		// Valida que no se cargue TERMINADA_STOCK 
		if ($data['operacion_id'] == config('consprod.TAREA_TERMINADA_STOCK'))
		{
			throw new ModelNotFoundException("No puede cargar tarea terminada stock");
			return 0;
		}

		$codigos_ot = explode(',', $data['ordenestrabajo']);
		// Recorre cada id de ordenes de trabajo
		for ($i = 0; $i < count($codigos_ot); $i++)
		{
			// Lee Orden de trabajo
			try
			{
				$ordentrabajo = $this->ordentrabajoRepository->findPorCodigo($codigos_ot[$i]);
			} catch (\Exception $e) 
			{
				return $e->getMessage();
			}
			if ($ordentrabajo)
			{
				// Busca la tarea si existe
				$ordentrabajo_tarea = $this->ordentrabajo_tareaRepository->findPorOrdentrabajoId($ordentrabajo->id);

				// Lee la cantidad y articulo de la orden de trabajo
				$ordentrabajo_combinacion_talle = $this->ordentrabajo_combinacion_talleRepository->findPorOrdenTrabajoId($ordentrabajo->id);

				$sku = '';
				$articulo_id = 0;
				$cantidad = 0;
				$flBoletaJunta = false;
				if (count($ordentrabajo_combinacion_talle) > 0)
				{
					$sku = $ordentrabajo_combinacion_talle[0]->pedido_combinacion_talles
									->pedidos_combinacion->articulos->sku;
					$articulo_id = $ordentrabajo_combinacion_talle[0]
									->pedido_combinacion_talles->pedidos_combinacion->articulos->articulo_id;
					// Cuenta las OT para ver si son boletas juntas 
					$arrayOt = [];
					foreach($ordentrabajo_combinacion_talle as $otitem)
					{
						if (!in_array($otitem->ordentrabajo_id, $arrayOt))
							$arrayOt[] = $otitem->ordentrabajo_id;
					}
					if (count($arrayOt) > 1)
						$flBoletaJunta = true;
					$pedido_combinacion_id = $ordentrabajo_combinacion_talle[0]
											->pedido_combinacion_talles->pedidos_combinacion->id;

					foreach ($ordentrabajo_combinacion_talle as $item)
						$cantidad += $item->pedido_combinacion_talles->cantidad;
				}

				$operacion = $this->operacionRepository->find($data['operacion_id']);

				// Busca el costo
				$articulo_costo = $this->articulo_costoRepository->findPorArticuloTarea($articulo_id, $data['tarea_id']);
				$costo = 0;
				if (count($articulo_costo) > 0)
					$costo = $articulo_costo->costo;

				DB::beginTransaction();
				try 
				{
					if (!$operacion)
						throw new ModelNotFoundException("No existe operacion");

					if (count($ordentrabajo_tarea) > 0 && $ordentrabajo_tarea->contains('tarea_id',
						config('consprod.TAREA_TERMINADA')))
						throw new ModelNotFoundException("No puede grabar con OT ya terminada");

					if (count($ordentrabajo_tarea) > 0 && $ordentrabajo_tarea->contains('tarea_id',
						config('consprod.TAREA_TERMINADA_STOCK')))
						throw new Exception("No puede grabar con OT ya terminada de Stock");

					// Filtra tarea_id
					$ordentrabajo_tarea_filtrada = $this->ordentrabajo_tareaRepository
												->findPorOrdentrabajoId($ordentrabajo->id, $data['tarea_id']);
					$accion = '';
					if ($funcion == 'create')
					{
						if ($tipooperacionEnum[$operacion->tipooperacion] == 'Inicio')
						{
							if (count($ordentrabajo_tarea_filtrada) == 0)
								// Crea la tarea
								$accion = 'create';
							else
							{
								// Verifica si tiene desde fecha
								if ($ordentrabajo_tarea_filtrada[0]->desdefecha != null)
									throw new ModelNotFoundException("La tarea ya existe");
								else
									$accion = 'update';
							}
						}
						
						if ($tipooperacionEnum[$operacion->tipooperacion] == 'Fin')
						{
							if (count($ordentrabajo_tarea_filtrada) == 0 && $data['tarea_id'] != config('consprod.TAREA_CORTADO_DE_FORRO'))
								throw new ModelNotFoundException("La tarea no fue iniciada");

							if ($ordentrabajo_tarea_filtrada[0]->empleado_id != $data['empleado_id'])
								throw new ModelNotFoundException("No puede grabar tarea iniciada por otro empleado");
							
							// Actualiza la tarea si es cortado de forro y no existe la crea cargando fin
							if (count($ordentrabajo_tarea_filtrada) == 0 && $data['tarea_id'] == config('consprod.TAREA_CORTADO_DE_FORRO'))
								$accion = 'create';
							else
								$accion = 'update';
						}
					}
					else
						$accion = 'update';

					if ($accion != '')
					{
						if ($flBoletaJunta)
							$pci = null;
						else
							$pci = $pedido_combinacion_id;

						$dataTarea = array(
							'ordentrabajo_id' => $ordentrabajo->id,
							'tarea_id' => $data['tarea_id'],
							'operacion_id' => $data['operacion_id'],
							'empleado_id' => $data['empleado_id'],
							'pedido_combinacion_id' => $pci,
							'fecha' => $data['fecha'],
							'estado' => $estadoEnum['A'],
							'usuario_id' => $usuario_id,
							// Campos para Anita
							'nro_orden' => $codigos_ot[$i],
							'codigo' => $codigos_ot[$i],
							'articulo' => str_pad($sku, 13, "0", STR_PAD_LEFT),
							'cantidad' => $cantidad,
							'costo' => $costo
							);

						if ($funcion == 'create')
						{
							// Crea la tarea
							if ($accion == 'create')
							{
								$dataTarea['desdefecha'] = $dataTarea['fecha'];
								$dataTarea['hastafecha'] = null;
								
								if ($data['tarea_id'] === Config::get("consprod.TAREA_TERMINADA") ||
									$data['tarea_id'] === Config::get("consprod.TAREA_TERMINADA_STOCK") ||
									$data['tarea_id'] === Config::get("consprod.TAREA_CORTADO_DE_FORRO"))
									$dataTarea['hastafecha'] = $dataTarea['fecha'];

								$item_tarea = $this->ordentrabajo_tareaRepository->create($dataTarea);

								if ($item_tarea)
									$dataTarea['ordentrabajo_tarea_id'] = $item_tarea->id;

								// Si carga aparado busca aparado anotado y pone el fin
								if ($data['tarea_id'] === Config::get("consprod.TAREA_APARADO"))
								{
									$ordentrabajo_tarea_anotado = $this->ordentrabajo_tareaRepository
																	->findPorOrdentrabajoId($ordentrabajo->id, 
																	Config::get("consprod.TAREA_APARADO_ANOTADO"));
									if ($ordentrabajo_tarea_anotado)
									{
										$item_tarea = $this->ordentrabajo_tareaRepository->update([
																					'hastafecha' => $dataTarea['desdefecha']
																								], 
																					$ordentrabajo_tarea_anotado[0]->id);

										// Crea el movimiento de OT de cierre de aparado anotado
										$dataTarea['tarea_id'] = Config::get("consprod.TAREA_APARADO_ANOTADO");

										$movimientoordentrabajo = $this->movimientoordentrabajoRepository
																		->create($dataTarea);

										$dataTarea['tarea_id'] = Config::get("consprod.TAREA_APARADO");
									}
								}
							}
							else // Actualiza la tarea
							{
								if ($ordentrabajo_tarea_filtrada[0]->hastafecha != null)
								{
									$dataTarea['desdefecha'] = $dataTarea['fecha'];
									$dataTarea['hastafecha'] = $ordentrabajo_tarea_filtrada[0]->hastafecha;
								}								
								else
								{
									$dataTarea['desdefecha'] = $ordentrabajo_tarea_filtrada[0]->desdefecha;
									$dataTarea['hastafecha'] = $dataTarea['fecha'];
								}
								if ($data['tarea_id'] == Config::get("consprod.TAREA_TERMINADA") ||
									$data['tarea_id'] == Config::get("consprod.TAREA_TERMINADA_STOCK") ||
									$data['tarea_id'] == Config::get("consprod.TAREA_CORTADO_DE_FORRO"))
									$dataTarea['hastafecha'] = $dataTarea['desdefecha'];

								foreach($ordentrabajo_tarea_filtrada as $otTarea)
								{
									$item_tarea = $this->ordentrabajo_tareaRepository->update(['desdefecha' => $dataTarea['desdefecha'],
																								'hastafecha' => $dataTarea['hastafecha']], 
																								$otTarea->id);
								}

								if ($item_tarea)
									$dataTarea['ordentrabajo_tarea_id'] = $ordentrabajo_tarea_filtrada[0]->id;
							}

							// Crea el movimiento de OT
							$movimientoordentrabajo = $this->movimientoordentrabajoRepository->create($dataTarea);
						}
						else
						{								
							$dataTarea['ordentrabajo_tarea_id'] = $ordentrabajo_tarea_filtrada[0]->id;

							// Crea la tarea
							if ($accion == 'create')
							{
								$dataTarea['desdefecha'] = $dataTarea['fecha'];
							}
							else // Actualiza la tarea
							{
								if ($data['operacion_id'] == Config::get("consprod.OPERACION_INICIO"))
									$dataTarea['desdefecha'] = $dataTarea['fecha'];
								else
									$dataTarea['hastafecha'] = $dataTarea['fecha'];
							}

							if ($data['tarea_id'] == Config::get("consprod.TAREA_TERMINADA") ||
								$data['tarea_id'] == Config::get("consprod.TAREA_TERMINADA_STOCK"))
								$dataTarea['hastafecha'] = $dataTarea['desdefecha'];

							$item_tarea = $this->ordentrabajo_tareaRepository->update($dataTarea, $ordentrabajo_tarea_filtrada[0]->id);

							// Actualiza el movimiento de OT
							$movimientoordentrabajo = $this->movimientoordentrabajoRepository->update($dataTarea, $id);
						}
					}
					else
					{
						throw new ModelNotFoundException("No puede grabar movimiento tarea ya existente en OT");
					}
					DB::commit();
				} catch (\Exception $e) {
					DB::rollback();
					return ['errores' => $e->getMessage()];
				}
			}
			else
			{
				throw new ModelNotFoundException("OT Inexistente");
			}
		}

		return ['ordenes'=>$codigos_ot];
	}

	public function borraMovimientoOrdenTrabajo($id)
	{
		$fl_borro = false;

		try
		{
			// Agregar validaciones de anulacion
			$movimientoordentrabajo = self::leeMovimientoOrdenTrabajo($id);

			if ($movimientoordentrabajo)
			{
				// Lee la tarea
				$ordentrabajo_tarea = $this->ordentrabajo_tareaRepository->find($movimientoordentrabajo->ordentrabajo_tarea_id);

				if ($ordentrabajo_tarea)
				{
					// Filtra tarea_id
					$ordentrabajo_tarea_filtrada = $this->ordentrabajo_tareaRepository
												->findPorOrdentrabajoId($ordentrabajo_tarea->ordentrabajo_id, 
												$ordentrabajo_tarea->tarea_id);
					foreach($ordentrabajo_tarea_filtrada as $otTarea)
					{
						// Si el movimiento es de finalizacion borra la fecha en la tarea si no borra la tarea
						if ($movimientoordentrabajo->operaciones->tipooperacion == 'F')
							$ordentrabajo_tarea = $this->ordentrabajo_tareaRepository
														->update(['hastafecha' => null], $otTarea->id);
						else
						{
							// Si no tiene fin borra la tarea, si tiene fecha de fin actualiza desde fecha
							if ($ordentrabajo_tarea->hastafecha == null || $ordentrabajo_tarea->tarea_id == config('consprod.TAREA_CORTADO_DE_FORRO'))
								$ordentrabajo_tarea = $this->ordentrabajo_tareaRepository->delete($otTarea->id);
							else
								$ordentrabajo_tarea = $this->ordentrabajo_tareaRepository
														->update(['desdefecha' => null], $otTarea->id);
						}

						// Lee la tarea, si no tienen fechas la borra
						$ordentrabajo_tarea = $this->ordentrabajo_tareaRepository->find($otTarea->id);

						if ($ordentrabajo_tarea && $ordentrabajo_tarea->desdefecha == null && $ordentrabajo_tarea->hastafecha == null)
							$ordentrabajo_tarea = $this->ordentrabajo_tareaRepository->delete($otTarea->id);
					}
				}

				$this->movimientoordentrabajoRepository->delete($id);
				$fl_borro = true;
			}
			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();
			return $e->getMessage();
		}
		return ($fl_borro);
	}

	// Empaca tarea en produccion

	public function empacaTarea($request)
	{
		$estadoEnum = self::estadoEnum();
		DB::beginTransaction();
		try
		{
			// Graba fin de armado
			$tareaArmado = Config::get("consprod.TAREA_ARMADO");
			$armado = $this->ordentrabajo_tareaRepository
								->findPorOrdentrabajoId($request['ordentrabajo_id'], $tareaArmado);

			if ($armado && count($armado) > 0)
			{
				$this->ordentrabajo_tareaRepository->update(
														['hastafecha'=>Carbon::now()], 
														$armado[0]->id);

				// Guarda movimiento de orden de trabajo
				$dataTarea = array(
					'ordentrabajo_id' => $request['ordentrabajo_id'],
					'ordentrabajo_tarea_id' => $armado[0]->id,
					'tarea_id' => Config::get("consprod.TAREA_ARMADO"),
					'operacion_id' => Config::get("consprod.OPERACION_FIN"),
					'empleado_id' => $armado[0]->empleado_id,
					'pedido_combinacion_id' => $request['pedido_combinacion_id'],
					'fecha' => Carbon::now(),
					'estado' => $estadoEnum['A'], // activa
					'usuario_id' => Auth::user()->id,
					'costo' => 0
					);
				$movimientoordentrabajo = $this->movimientoordentrabajoRepository->create($dataTarea);														
			}
			// Graba tarea 
			$data['ordentrabajo_id'] = $request['ordentrabajo_id'];
			$data['tarea_id'] = Config::get("consprod.TAREA_EMPAQUE"); // Tarea Empaque
			$data['desdefecha'] = Carbon::now();
			$data['hastafecha'] = null;
			$data['empleado_id'] = Config::get("consprod.EMPLEADO_FABRICA");
			$data['pedido_combinacion_id'] = $request['pedido_combinacion_id'];
			$data['estado'] = Config::get("consprod.TAREA_ESTADO_PRODUCCION");
			$data['costo'] = 0;
			$data['usuario_id'] = Auth::user()->id;

			$ordentrabajo = $this->ordentrabajo_tareaRepository->create($data);

			// Guarda movimiento de orden de trabajo
			$dataTarea = array(
				'ordentrabajo_id' => $request['ordentrabajo_id'],
				'ordentrabajo_tarea_id' => $ordentrabajo->id,
				'tarea_id' => Config::get("consprod.TAREA_EMPAQUE"),
				'operacion_id' => Config::get("consprod.OPERACION_INICIO"),
				'empleado_id' => Config::get("consprod.EMPLEADO_FABRICA"),
				'pedido_combinacion_id' => $request['pedido_combinacion_id'],
				'fecha' => Carbon::now(),
				'estado' => $estadoEnum['A'],
				'usuario_id' => Auth::user()->id,
				'costo' => 0
				);
			$movimientoordentrabajo = $this->movimientoordentrabajoRepository->create($dataTarea);

			DB::commit();
		} catch (\Exception $e) 
		{
			DB::rollback();
			dd($e->getMessage());
			return $e->getMessage();
		}
		// Imprime tarea de armado
		// Arma nombre de archivo
		$nombreReporte = "tmp/empaqueOT-" . $data['ordentrabajo_id'] . '.txt';
		$reporte = chr(27).chr(33).chr(2);
		$reporte .= "Empaque de ORDEN DE TRABAJO NRO. ".$request['codigoordentrabajo']."\n\n";
		
		if (isset($request['pedido']))
			$reporte .= "PEDIDO NRO: ".$request['pedido']."\n";

		$reporte .= chr(27).chr(33).chr(32).$request['cliente']."\n\n";
		$reporte .= $request['nombretiposuspensioncliente']."\n";

		$reporte .= "Articulo: \n";
		$reporte .= chr(27).chr(33).chr(32).$request['articulo']."\n";

		$reporte .= chr(27).chr(33).chr(2)."SKU: ".$request['sku']."\n\n";
		$reporte .= "Combinacion: ".$request['combinacion']."\n\n";

		$reporte .= "MEDIDAS\n";
		$medidas = json_decode($request['medidas']);
		
		foreach($medidas as $medida)
		{
			$reporte .= "Talle: ".$medida->nombretalle." Cantidad: ".$medida->cantidad."\n";
		}

		$pedido_combinacion = $this->pedido_combinacionRepository->find($request['pedido_combinacion_id']);
		if ($pedido_combinacion)
		{
			$reporte .= $pedido_combinacion->observacion."\n";
		}		

		$reporte .= chr(27).chr(33).chr(32);
		$reporte .= "\nTotal pares: ".$request['pares']."\n\n\n\n\n\n\n\n\n\n\n\n\n";
		$reporte .= chr(27).chr(33).chr(2)."\n";
		
		Storage::disk('local')->put($nombreReporte, $reporte);
		$path = Storage::path($nombreReporte);
		system("lp -dcalidad ".$path);

		Storage::disk('local')->delete($nombreReporte);
		// Agrega listado de OT asociadas por lote de stock
		if ($request['cliente'] == Config::get("consprod.NOMBRE_CLIENTE_STOCK"))
		{
			// Lee la OT
			$ot = $this->ordentrabajoRepository->find($data['ordentrabajo_id']);

			// Trae las OT
			if ($ot)
			{
				$otStock = $this->ordentrabajo_combinacion_talleRepository->findPorOrdentrabajoStockId($ot->codigo);

				$anterOrdenTrabajo_id = 0;
				$totalPares = 0;
				foreach($otStock as $ot)
				{
					// Si encuentra la misma ot no la imprime
					if ($ot->ordentrabajo_id != $data['ordentrabajo_id'])
					{
						if ($ot->ordentrabajo_id != $anterOrdenTrabajo_id)
						{
							if ($anterOrdenTrabajo_id != 0)
							{
								$reporte .= chr(27).chr(33).chr(32);
								$reporte .= "\nTotal pares: ".$totalPares."\n\n\n\n\n\n\n\n\n\n\n\n\n";
								$reporte .= chr(27).chr(33).chr(2)."\n";
								
								Storage::disk('local')->put($nombreReporte, $reporte);
								$path = Storage::path($nombreReporte);
								system("lp -dcalidad ".$path);
						
								Storage::disk('local')->delete($nombreReporte);
								//dd($reporte);
							}
							$reporte = "";
							$nombreReporte = "tmp/OTstock-" . $ot->ordentrabajo_id . '.txt';
							$reporte = chr(27).chr(33).chr(2);
							$reporte .= "ORDEN DE TRABAJO NRO. ".$ot->ordentrabajo_id."\n";
							$reporte .= "ASOCIADA A LA OT DE STOCK NRO. ".$data['ordentrabajo_id']."\n";
							
							$reporte .= "PEDIDO NRO: ".$ot->pedido_combinacion_talles->pedidos_combinacion->pedido_id."\n";
				
							// Lee el cliente
							$cliente = $this->clienteRepository->find($ot->cliente_id);

							if ($cliente)
							{
								$reporte .= chr(27).chr(33).chr(32).$cliente->nombre."\n\n";
							}
				
							$reporte .= "Articulo: \n";
							$reporte .= chr(27).chr(33).chr(32).$request['articulo']."\n";
					
							$reporte .= chr(27).chr(33).chr(2)."SKU: ".$request['sku']."\n\n";
							$reporte .= "Combinacion: ".$request['combinacion']."\n\n";
				
							$reporte .= "MEDIDAS\n";

							$anterOrdenTrabajo_id = $ot->ordentrabajo_id;
							$totalPares = 0;
						}

						$reporte .= "Talle: ".$ot->pedido_combinacion_talles->talles->nombre.
									" Cantidad: ".$ot->pedido_combinacion_talles->cantidad."\n";
						$totalPares += $ot->pedido_combinacion_talles->cantidad;
					}
				}

				if ($totalPares > 0)
				{
					$reporte .= chr(27).chr(33).chr(32);
					$reporte .= "\nTotal pares: ".$totalPares."\n\n\n\n\n\n\n\n\n\n\n\n\n";
					$reporte .= chr(27).chr(33).chr(2)."\n";
				
					Storage::disk('local')->put($nombreReporte, $reporte);
					$path = Storage::path($nombreReporte);
					system("lp -dcalidad ".$path);
				}

				Storage::disk('local')->delete($nombreReporte);
			}
		}
	}

	// Control de secuencia de fabricacion

	public function controlSecuencia($ordenestrabajo, $operacion_id, $tarea_id, $pedido_combinacion_id, $movimiento_id = null)
	{
		$arrayOrdenesTrabajo = explode(',', $ordenestrabajo);
		$secuenciaTareas = Config::get("consprod.SECUENCIA_TAREAS");
		$otConProblema = [];
		$flExiste = false;
		foreach($arrayOrdenesTrabajo as $ordentrabajo)
		{
			$ot = $this->ordentrabajoRepository->findPorCodigo($ordentrabajo);
			$tareas = self::leeTareas($ot->id);
			$flExiste = false;
			$flExisteEnSecuencia = false;
			// Verifica si tiene validacion de secuencia cargada
			if (array_key_exists($tarea_id, $secuenciaTareas))		
			{
				$flExisteEnSecuencia = true;
				// Busca la tarea en la secuencia
				foreach($secuenciaTareas[$tarea_id] as $secuencia)
				{
					foreach ($tareas as $tarea)
					{
						if ($secuencia == $tarea->tarea_id)
						{
							// Si no termino la tarea es error igual salvo que sea empaque
							if ($tarea->hastafecha != null || $tarea_id == Config::get("consprod.TAREA_EMPAQUE"))
								$flExiste = true;

							// Si tiene mas tareas sin terminar da error salvo en empaque
							if ($flExiste && $tarea->hastafecha == null && 
								$tarea_id != Config::get("consprod.TAREA_EMPAQUE"))
								$flExiste = false;
						}
					}
				}
				// Si la tarea ya fue cargada la toma con problemas
				if (!$flExiste && !in_array($ordentrabajo, $otConProblema))
					$otConProblema[] = $ordentrabajo;
			}
			$flTareaYaCargada = false;
			if ($movimiento_id == null)
			{
				foreach ($tareas as $tarea)
				{
					// Si la tarea ya esta cargada verifica si le falta el fin
					if ($tarea_id == $tarea->tarea_id && 
						($pedido_combinacion_id != 0 ? $tarea->pedido_combinacion_id == $pedido_combinacion_id : true))
					{
						if ($tarea->hastafecha != null)
							$flTareaYaCargada = true;
					}
				}
				// Si la tarea ya fue cargada la toma con problemas
				if ($flTareaYaCargada && !in_array($ordentrabajo, $otConProblema))
					$otConProblema[] = $ordentrabajo;
			}
		}
		$resultado = count($otConProblema) > 0 ? 0 : 1;
		return(['resultado' => $resultado, 'ordenestrabajo' => $otConProblema]);
	}

	// Lee tareas de una OT

	public function leeTareas($ot_id)
	{
		return($this->ordentrabajo_tareaRepository->findPorOrdentrabajoId($ot_id));
	}

}
