<?php
namespace App\Services\Produccion;

use App\Models\Produccion\MovimientoOrdentrabajo;
use App\Models\Ventas\Ordentrabajo_Tarea;
use App\Repositories\Stock\Articulo_CostoRepositoryInterface;
use App\Repositories\Ventas\OrdentrabajoRepositoryInterface;
use App\Repositories\Ventas\Ordentrabajo_Combinacion_TalleRepositoryInterface;
use App\Repositories\Ventas\Ordentrabajo_TareaRepositoryInterface;
use App\Repositories\Ventas\Pedido_CombinacionRepositoryInterface;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use App\Repositories\Produccion\MovimientoOrdentrabajoRepositoryInterface;
use App\Repositories\Produccion\OperacionRepositoryInterface;
use App\Support\Configuracion\CupsRemotoImpresionSupport;
use App\Support\Produccion\OrdentrabajoTareaFechaSupport;
use App\Support\Stock\OtTerminadaAltaStockFerliSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
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
	protected $otTerminadaAltaStockFerliSupport;

    public function __construct(
								OrdentrabajoRepositoryInterface $ordentrabajorepository,
								Ordentrabajo_Combinacion_TalleRepositoryInterface $ordentrabajocombinaciontallerepository,
								Ordentrabajo_TareaRepositoryInterface $ordentrabajotarearepository,
								MovimientoOrdentrabajoRepositoryInterface $movimientoordentrabajorepository,
								Articulo_CostoRepositoryInterface $articulo_costorepository,
								OperacionRepositoryInterface $operacionrepository,
								Pedido_CombinacionRepositoryInterface $pedido_combinacionrepository,
								ClienteRepositoryInterface $clienterepository,
								OtTerminadaAltaStockFerliSupport $otterminadaaltastockferlisupport
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
		$this->otTerminadaAltaStockFerliSupport = $otterminadaaltastockferlisupport;
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
				$pedido_combinacion_id = null;
				if (count($ordentrabajo_combinacion_talle) > 0)
				{
					// Puede haber renglones huérfanos (pedido_combinacion_talle borrado / sync L8).
					$primeroConTalle = null;
					foreach ($ordentrabajo_combinacion_talle as $otitem) {
						$pct = $otitem->pedido_combinacion_talles;
						if ($pct && $pct->pedidos_combinacion && $pct->pedidos_combinacion->articulos) {
							$primeroConTalle = $otitem;
							break;
						}
					}

					if (! $primeroConTalle) {
						throw new ModelNotFoundException(
							'La OT '.$codigos_ot[$i].' no tiene talles de pedido asociados'
						);
					}

					$sku = $primeroConTalle->pedido_combinacion_talles
									->pedidos_combinacion->articulos->sku;
					$articulo_id = $primeroConTalle
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
					$pedido_combinacion_id = $primeroConTalle
											->pedido_combinacion_talles->pedidos_combinacion->id;

					foreach ($ordentrabajo_combinacion_talle as $item) {
						if ($item->pedido_combinacion_talles) {
							$cantidad += (float) $item->pedido_combinacion_talles->cantidad;
						}
					}
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
								// Verifica si tiene desde fecha (0000-00-00 = sin fecha)
								if (OrdentrabajoTareaFechaSupport::tieneValor($ordentrabajo_tarea_filtrada[0]->desdefecha))
									throw new ModelNotFoundException("La tarea ya existe");
								else
									$accion = 'update';
							}
						}
						
						if ($tipooperacionEnum[$operacion->tipooperacion] == 'Fin')
						{
							$esCortadoForro = (int) $data['tarea_id'] === (int) config('consprod.TAREA_CORTADO_DE_FORRO');

							if (count($ordentrabajo_tarea_filtrada) == 0) {
								if (! $esCortadoForro) {
									throw new ModelNotFoundException("La tarea no fue iniciada");
								}
								// Cortado de forro: permite alta directa con fin
								$accion = 'create';
							} else {
								if ((int) $ordentrabajo_tarea_filtrada[0]->empleado_id !== (int) $data['empleado_id']) {
									throw new ModelNotFoundException("No puede grabar tarea iniciada por otro empleado");
								}
								if (OrdentrabajoTareaFechaSupport::tieneValor($ordentrabajo_tarea_filtrada[0]->hastafecha)) {
									throw new ModelNotFoundException("La tarea ya fue finalizada");
								}
								$accion = 'update';
							}
						}
					}
					else
						$accion = 'update';

					$esInicio = ($tipooperacionEnum[$operacion->tipooperacion] ?? '') === 'Inicio';
					$esAparado = (int) ($data['tarea_id'] ?? 0) === (int) config('consprod.TAREA_APARADO');
					if ($accion !== '' && $esInicio && $esAparado
						&& ! $this->tieneAparadoAnotado((int) $ordentrabajo->id)) {
						throw new ModelNotFoundException(
							'No puede iniciar APARADO: la OT '.$codigos_ot[$i].' no tiene APARADO ANOTADO'
						);
					}

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
							}
							else // Actualiza la tarea
							{
								if ($esInicio)
								{
									// Inicio sobre una tarea sin fecha de inicio (0000-00-00).
									// Si no hay movimiento de Fin, el hastafecha es huérfano
									// (el alta anterior copió el mismo día) y no debe quedar cerrada.
									$dataTarea['desdefecha'] = $dataTarea['fecha'];
									$tieneFin = $this->tieneMovimientoFin(
										(int) $ordentrabajo->id,
										(int) $data['tarea_id']
									);
									if ($tieneFin && OrdentrabajoTareaFechaSupport::tieneValor($ordentrabajo_tarea_filtrada[0]->hastafecha))
										$dataTarea['hastafecha'] = $ordentrabajo_tarea_filtrada[0]->hastafecha;
									else
										$dataTarea['hastafecha'] = null;
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
									$camposTarea = [
										'desdefecha' => $dataTarea['desdefecha'],
										'hastafecha' => $dataTarea['hastafecha'],
									];
									if ($esInicio)
										$camposTarea['empleado_id'] = $dataTarea['empleado_id'];

									$item_tarea = $this->ordentrabajo_tareaRepository->update($camposTarea, $otTarea->id);
								}

								if ($item_tarea)
									$dataTarea['ordentrabajo_tarea_id'] = $ordentrabajo_tarea_filtrada[0]->id;
							}

							// El inicio de APARADO cierra el APARADO ANOTADO.
							if ($esInicio && $esAparado) {
								$this->cierraAparadoAnotado((int) $ordentrabajo->id, $dataTarea);
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

					if ((int) ($data['tarea_id'] ?? 0) === (int) config('consprod.TAREA_TERMINADA')) {
						$this->otTerminadaAltaStockFerliSupport->alTerminar($ordentrabajo);
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
				// Lee la tarea. Si no existe o es de otra OT (copia cruzada de L8),
				// se borra solo este movimiento: no hay que tocar la tarea ajena.
				$ordentrabajo_tarea = Ordentrabajo_Tarea::query()->find($movimientoordentrabajo->ordentrabajo_tarea_id);
				$tareaDeEstaOt = $ordentrabajo_tarea
					&& (int) $ordentrabajo_tarea->ordentrabajo_id === (int) $movimientoordentrabajo->ordentrabajo_id;

				if ($tareaDeEstaOt)
				{
					// Filtra tarea_id
					$ordentrabajo_tarea_filtrada = $this->ordentrabajo_tareaRepository
												->findPorOrdentrabajoId($ordentrabajo_tarea->ordentrabajo_id, 
												$ordentrabajo_tarea->tarea_id);
					foreach($ordentrabajo_tarea_filtrada as $otTarea)
					{
						// Si el movimiento es de finalizacion borra la fecha en la tarea si no borra la tarea
						if ($movimientoordentrabajo->operaciones->tipooperacion == 'F')
							$this->ordentrabajo_tareaRepository
								->update(['hastafecha' => null], $otTarea->id);
						else
						{
							// Hastafecha sin movimiento de Fin es un cierre huérfano (inicio y fin
							// el mismo día). Borrar el Inicio tiene que llevarse la tarea, como
							// cuando nunca tuvo fin. Si hay Fin, solo se limpia el inicio.
							$tieneFin = $this->tieneMovimientoFin(
								(int) $otTarea->ordentrabajo_id,
								(int) $otTarea->tarea_id,
								(int) $movimientoordentrabajo->id
							);
							if (! $tieneFin
								|| (int) $otTarea->tarea_id === (int) config('consprod.TAREA_CORTADO_DE_FORRO'))
								$this->ordentrabajo_tareaRepository->delete($otTarea->id);
							else
								$this->ordentrabajo_tareaRepository
									->update(['desdefecha' => null], $otTarea->id);
						}

						$restante = Ordentrabajo_Tarea::query()->find($otTarea->id);

						if ($restante
							&& ! OrdentrabajoTareaFechaSupport::tieneValor($restante->desdefecha)
							&& ! OrdentrabajoTareaFechaSupport::tieneValor($restante->hastafecha))
							$this->ordentrabajo_tareaRepository->delete($otTarea->id);
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

	/**
	 * Empaca tarea en producción (fin de armado) e imprime etiqueta térmica.
	 *
	 * @return array{ok: bool, mensaje: string}
	 */
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
			Log::error('empacaTarea: '.$e->getMessage(), ['exception' => $e]);

			return [
				'ok' => false,
				'mensaje' => 'No se pudo empacar la OT: '.$e->getMessage(),
			];
		}

		$impresion = $this->imprimirEtiquetaEmpaque($request, (int) $data['ordentrabajo_id']);

		if ($impresion['ok']) {
			return [
				'ok' => true,
				'mensaje' => 'OT empacada con éxito. '.$impresion['mensaje'],
			];
		}

		return [
			'ok' => true,
			'mensaje' => 'OT empacada con éxito, pero la etiqueta no se imprimió: '.$impresion['mensaje'],
		];
	}

	/**
	 * Genera e imprime el ticket térmico de empaque (cola CUPS "calidad").
	 *
	 * @param  array<string, mixed>  $request
	 * @return array{ok: bool, mensaje: string}
	 */
	private function imprimirEtiquetaEmpaque(array $request, int $ordentrabajoId): array
	{
		$nombreReporte = 'tmp/empaqueOT-'.$ordentrabajoId.'.txt';
		$reporte = chr(27).chr(33).chr(2);
		$reporte .= 'Empaque de ORDEN DE TRABAJO NRO. '.$request['codigoordentrabajo']."\n\n";

		if (isset($request['pedido'])) {
			$reporte .= 'PEDIDO NRO: '.$request['pedido']."\n";
		}

		$reporte .= chr(27).chr(33).chr(32).$request['cliente']."\n\n";
		$reporte .= ($request['nombretiposuspensioncliente'] ?? '')."\n";

		$reporte .= "Articulo: \n";
		$reporte .= chr(27).chr(33).chr(32).$request['articulo']."\n";

		$reporte .= chr(27).chr(33).chr(2).'SKU: '.$request['sku']."\n\n";
		$reporte .= 'Combinacion: '.$request['combinacion']."\n\n";

		$reporte .= "MEDIDAS\n";
		$medidas = json_decode($request['medidas'] ?? '[]');
		if (is_array($medidas) || $medidas instanceof \Traversable) {
			foreach ($medidas as $medida) {
				$talle = is_object($medida)
					? ($medida->nombretalle ?? $medida->talle ?? '')
					: '';
				$cantidad = is_object($medida) ? ($medida->cantidad ?? '') : '';
				$reporte .= 'Talle: '.$talle.' Cantidad: '.$cantidad."\n";
			}
		}

		$pedido_combinacion = $this->pedido_combinacionRepository->find($request['pedido_combinacion_id']);
		if ($pedido_combinacion) {
			$reporte .= $pedido_combinacion->observacion."\n";
		}

		$reporte .= chr(27).chr(33).chr(32);
		$reporte .= "\nTotal pares: ".$request['pares']."\n\n\n\n\n\n\n\n\n\n\n\n\n";
		$reporte .= chr(27).chr(33).chr(2)."\n";

		Storage::disk('local')->put($nombreReporte, $reporte);
		$path = Storage::path($nombreReporte);

		try {
			$resultado = CupsRemotoImpresionSupport::imprimirArchivo(
				$path,
				(string) config('impresion_termica.cola_empaque', 'calidad')
			);
		} finally {
			Storage::disk('local')->delete($nombreReporte);
		}

		// OT de stock: listado de OT asociadas (misma cola)
		if (($request['cliente'] ?? '') == Config::get('consprod.NOMBRE_CLIENTE_STOCK')) {
			$this->imprimirOtStockAsociadas($request, $ordentrabajoId);
		}

		return $resultado;
	}

	/**
	 * @param  array<string, mixed>  $request
	 */
	private function imprimirOtStockAsociadas(array $request, int $ordentrabajoId): void
	{
		$ot = $this->ordentrabajoRepository->find($ordentrabajoId);
		if (! $ot) {
			return;
		}

		$otStock = $this->ordentrabajo_combinacion_talleRepository->findPorOrdentrabajoStockId($ot->codigo);
		$anterOrdenTrabajo_id = 0;
		$totalPares = 0;
		$reporte = '';
		$nombreReporte = '';

		foreach ($otStock as $otItem) {
			if ($otItem->ordentrabajo_id == $ordentrabajoId) {
				continue;
			}

			$pct = $otItem->pedido_combinacion_talles;
			if (! $pct || ! $pct->pedidos_combinacion) {
				continue;
			}

			if ($otItem->ordentrabajo_id != $anterOrdenTrabajo_id) {
				if ($anterOrdenTrabajo_id != 0 && $reporte !== '') {
					$reporte .= chr(27).chr(33).chr(32);
					$reporte .= "\nTotal pares: ".$totalPares."\n\n\n\n\n\n\n\n\n\n\n\n\n";
					$reporte .= chr(27).chr(33).chr(2)."\n";
					$this->enviarTicketTermicoEmpaque($nombreReporte, $reporte);
				}

				$nombreReporte = 'tmp/OTstock-'.$otItem->ordentrabajo_id.'.txt';
				$reporte = chr(27).chr(33).chr(2);
				$reporte .= 'ORDEN DE TRABAJO NRO. '.$otItem->ordentrabajo_id."\n";
				$reporte .= 'ASOCIADA A LA OT DE STOCK NRO. '.$ordentrabajoId."\n";
				$reporte .= 'PEDIDO NRO: '.$pct->pedidos_combinacion->pedido_id."\n";

				$cliente = $this->clienteRepository->find($otItem->cliente_id);
				if ($cliente) {
					$reporte .= chr(27).chr(33).chr(32).$cliente->nombre."\n\n";
				}

				$reporte .= "Articulo: \n";
				$reporte .= chr(27).chr(33).chr(32).$request['articulo']."\n";
				$reporte .= chr(27).chr(33).chr(2).'SKU: '.$request['sku']."\n\n";
				$reporte .= 'Combinacion: '.$request['combinacion']."\n\n";
				$reporte .= "MEDIDAS\n";

				$anterOrdenTrabajo_id = $otItem->ordentrabajo_id;
				$totalPares = 0;
			}

			$nombreTalle = $pct->talles->nombre ?? '';
			$reporte .= 'Talle: '.$nombreTalle.
						' Cantidad: '.$pct->cantidad."\n";
			$totalPares += $pct->cantidad;
		}

		if ($totalPares > 0 && $reporte !== '') {
			$reporte .= chr(27).chr(33).chr(32);
			$reporte .= "\nTotal pares: ".$totalPares."\n\n\n\n\n\n\n\n\n\n\n\n\n";
			$reporte .= chr(27).chr(33).chr(2)."\n";
			$this->enviarTicketTermicoEmpaque($nombreReporte, $reporte);
		}
	}

	private function enviarTicketTermicoEmpaque(string $nombreRelativo, string $contenido): void
	{
		if ($nombreRelativo === '' || $contenido === '') {
			return;
		}

		Storage::disk('local')->put($nombreRelativo, $contenido);
		$path = Storage::path($nombreRelativo);
		try {
			CupsRemotoImpresionSupport::imprimirArchivo(
				$path,
				(string) config('impresion_termica.cola_empaque', 'calidad')
			);
		} finally {
			Storage::disk('local')->delete($nombreRelativo);
		}
	}

	/**
	 * APARADO ANOTADO cargado en la OT (con fecha de inicio real).
	 */
	private function tieneAparadoAnotado(int $ordentrabajoId): bool
	{
		$tareaId = (int) config('consprod.TAREA_APARADO_ANOTADO');
		if ($tareaId <= 0) {
			return false;
		}

		$filas = $this->ordentrabajo_tareaRepository->findPorOrdentrabajoId($ordentrabajoId, $tareaId);
		foreach ($filas as $fila) {
			if (OrdentrabajoTareaFechaSupport::tieneValor($fila->desdefecha)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Al iniciar APARADO, pone fin al APARADO ANOTADO y deja el movimiento de ese cierre.
	 *
	 * @param  array<string, mixed>  $dataTarea
	 */
	private function cierraAparadoAnotado(int $ordentrabajoId, array $dataTarea): void
	{
		$tareaId = (int) config('consprod.TAREA_APARADO_ANOTADO');
		$filas = $this->ordentrabajo_tareaRepository->findPorOrdentrabajoId($ordentrabajoId, $tareaId);
		if (count($filas) === 0) {
			return;
		}

		$this->ordentrabajo_tareaRepository->update(
			['hastafecha' => $dataTarea['desdefecha']],
			$filas[0]->id
		);

		$dataTarea['tarea_id'] = $tareaId;
		$this->movimientoordentrabajoRepository->create($dataTarea);
	}

	/**
	 * Hay un movimiento de Fin de esta tarea en la OT.
	 * Un hastafecha sin este movimiento es un cierre huérfano del alta de Inicio.
	 */
	private function tieneMovimientoFin(int $ordentrabajoId, int $tareaId, ?int $exceptoMovimientoId = null): bool
	{
		$query = MovimientoOrdentrabajo::query()
			->where('ordentrabajo_id', $ordentrabajoId)
			->where('tarea_id', $tareaId)
			->where('operacion_id', (int) config('consprod.OPERACION_FIN'));

		if ($exceptoMovimientoId)
			$query->where('id', '!=', $exceptoMovimientoId);

		return $query->exists();
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
							$tareaTerminada = OrdentrabajoTareaFechaSupport::tieneValor($tarea->hastafecha);
							// Si no termino la tarea es error igual salvo que sea empaque
							if ($tareaTerminada || $tarea_id == Config::get("consprod.TAREA_EMPAQUE"))
								$flExiste = true;

							// Si tiene mas tareas sin terminar da error salvo en empaque
							if ($flExiste && ! $tareaTerminada &&
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
						// Ya cargada = inicio y fin reales. Un hastafecha huérfano
						// (desdefecha 0000-00-00 tras borrar el Inicio) no bloquea volver a cargarlo.
						$inicio = OrdentrabajoTareaFechaSupport::tieneValor($tarea->desdefecha);
						$fin = OrdentrabajoTareaFechaSupport::tieneValor($tarea->hastafecha);
						if ($inicio && $fin)
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
