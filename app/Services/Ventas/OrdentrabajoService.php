<?php
namespace App\Services\Ventas;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Queries\Ventas\OrdentrabajoQueryInterface;
use App\Queries\Ventas\ClienteQueryInterface;
use App\Queries\Ventas\Cliente_ComisionQueryInterface;
use App\Queries\Ventas\PedidoQueryInterface;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Services\Stock\Articulo_MovimientoService;
use App\Repositories\Ventas\Pedido_CombinacionRepositoryInterface;
use App\Repositories\Ventas\Pedido_Combinacion_TalleRepositoryInterface;
use App\Repositories\Ventas\OrdentrabajoRepositoryInterface;
use App\Repositories\Ventas\Ordentrabajo_Combinacion_TalleRepositoryInterface;
use App\Repositories\Ventas\Ordentrabajo_TareaRepositoryInterface;
use App\Repositories\Ventas\VentaRepositoryInterface;
use App\Repositories\Produccion\TareaRepositoryInterface;
use App\Repositories\Configuracion\SeteosalidaRepositoryInterface;
use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use App\Support\Configuracion\SalidaImpresionFallbackSupport;
use App\Support\Ventas\QrCodePngSupport;
use App\Support\Ventas\ClientePoliticaComercialSupport;
use App\Support\Ventas\OrdentrabajoEmisionCopiaSupport;
use App\Support\Ventas\OrdentrabajoEmisionPreimpresoLayout;
use App\Models\Configuracion\Salida;
use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Categoria;
use App\Models\Stock\Linea;
use App\Models\Stock\Color;
use App\Models\Stock\Forro;
use App\Models\Stock\Fondo;
use App\Models\Stock\Talle;
use App\Models\Stock\Tipocorte;
use App\Models\Stock\Material;
use App\Models\Stock\Materialcapellada;
use App\Models\Stock\Materialavio;
use App\Models\Stock\Plvista;
use App\Models\Stock\Plarmado;
use App\Models\Stock\Serigrafia;
use App\Models\Stock\Capeart;
use App\Models\Stock\Avioart;
use App\Models\Stock\Puntera;
use App\Models\Stock\Contrafuerte;
use App\Models\Stock\Articulo_Caja;
use App\Models\Stock\Caja;
use App\Models\Ventas\Ordentrabajo;
use App\Models\Ventas\Copiaot;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Localidad;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Carbon\Carbon;
use QrCode;
use App;
use Auth;
use DB;
use Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrdentrabajoService 
{
	protected $ordentrabajoQuery;
	protected $ordentrabajoRepository;
	protected $ordentrabajo_combinacion_talleRepository;
	protected $ordentrabajo_tareaRepository;
	protected $tareaRepository;
	protected $pedido_combinacionRepository;
	protected $pedido_combinacion_talleRepository;
	protected $ventaRepository;
	protected $pedidoQuery;
	protected $clienteQuery;
	protected $cliente_comisionQuery;
	protected $articuloQuery;
	protected $articulo_movimientoService;
	protected $seteoSalidaRepository;
	protected $tot_pares1, $tot_pares2, $tot_pares3, $tot_pares4;

    public function __construct(
								OrdentrabajoQueryInterface $ordentrabajoquery,
								OrdentrabajoRepositoryInterface $ordentrabajorepository,
								Ordentrabajo_Combinacion_TalleRepositoryInterface $ordentrabajocombinaciontallerepository,
								Ordentrabajo_TareaRepositoryInterface $ordentrabajotarearepository,
								TareaRepositoryInterface $tarearepository,
								VentaRepositoryInterface $ventarepository,
								PedidoQueryInterface $pedidoquery,
								ClienteQueryInterface $clientequery,
								Cliente_ComisionQueryInterface $clientecomisionquery,
								ArticuloQueryInterface $articuloquery,
								Articulo_MovimientoService $articulo_movimientoservice,
    							Pedido_CombinacionRepositoryInterface $pedidocombinacionrepository,
    							Pedido_Combinacion_TalleRepositoryInterface $pedidocombinaciontallerepository,
								SeteosalidaRepositoryInterface $seteosalidarepository
								)
    {
        $this->ordentrabajoQuery = $ordentrabajoquery;
        $this->ordentrabajoRepository = $ordentrabajorepository;
        $this->ordentrabajo_combinacion_talleRepository = $ordentrabajocombinaciontallerepository;
        $this->ordentrabajo_tareaRepository = $ordentrabajotarearepository;
		$this->tareaRepository = $tarearepository;
		$this->ventaRepository = $ventarepository;
        $this->pedidoQuery = $pedidoquery;
        $this->clienteQuery = $clientequery;
        $this->cliente_comisionQuery = $clientecomisionquery;
        $this->articuloQuery = $articuloquery;
		$this->articulo_movimientoService = $articulo_movimientoservice;
        $this->pedido_combinacionRepository = $pedidocombinacionrepository;
        $this->pedido_combinacion_talleRepository = $pedidocombinaciontallerepository;
		$this->seteoSalidaRepository = $seteosalidarepository;
    }

	public function leeOrdenestrabajoPendientesAnita()
	{
		return $this->ordentrabajoQuery->allOrdentrabajo('P');
	}

	public function leeOrdenestrabajoPendientes()
	{
        //$hay_ordentrabajo = $this->ordentrabajoQuery->first();

		//if (!$hay_ordentrabajo)
		//{
		//	$this->ordentrabajoRepository->sincronizarConAnita();
		//	$this->ordentrabajo_combinacion_talleRepository->sincronizarConAnita();
		//	$this->ordentrabajo_tareaRepository->sincronizarConAnita();
		//}

		return $this->ordentrabajoQuery->all();
	}

	public function leeOrdenestrabajoPaginando($filtros, $flPaginar)
	{
		return $this->ordentrabajoQuery->allPaginando($filtros, $flPaginar);
	}

	public function guardaOrdenTrabajo($id_items, $checkOtStock, $ordentrabajo_stock_codigo, $deposito_id,
									$leyenda, $funcion, $id = null)
	{
		$usuario_id = Auth::user()->id;

		if (!is_array($id_items))
			$ids = explode(',', $id_items);
		else 
			$ids = $id_items;

		if ($ids !== []) {
			$primer = $this->pedido_combinacionRepository->find($ids[0]);
			if ($primer) {
				$pedidoPre = $this->pedidoQuery->leePedidoporId($primer->pedido_id)->first();
				if ($pedidoPre) {
					$clientePre = $this->clienteQuery->traeClienteporId($pedidoPre->cliente_id);
					$errorPolitica = ClientePoliticaComercialSupport::errorSiNoPermite(
						$clientePre,
						ClientePoliticaComercialSupport::OP_BOLETA
					);
					if ($errorPolitica !== null) {
						return $errorPolitica;
					}
				}
			}
		}

		$flBoletasJuntas = false;
		if (count($ids) > 1)
			$flBoletasJuntas = true; 

		$ordentrabajo_stock_id = null;
		$lote_id = null;
		if ($ordentrabajo_stock_codigo > 0 && $checkOtStock == 'on')
		{
			$ot = $this->ordentrabajoQuery->leeOrdenTrabajoPorCodigo($ordentrabajo_stock_codigo);
			if ($ot)
			{
				$ordentrabajo_stock_id = $ot->codigo; // Guarda el codigo ingresado de la ot

				// Lee el lote de la OT de stock
				$ot_stock = $this->ordentrabajoQuery->leeOrdenTrabajoPorCodigo($ot->codigo);
				if ($ot_stock)
				{
					$lote_id = $ot_stock->pedidoCombinacionVigente()?->lote_id;
				}
			}
			else // Asigna el codigo de lote ingresado
				$ordentrabajo_stock_id = $ordentrabajo_stock_codigo;
		}

		// Si asigna un lote de stock pero no es una OT de stock, la asigna al lote cargado
		if ($ordentrabajo_stock_codigo > 0 && $checkOtStock != 'on')
			$ordentrabajo_stock_id = $ordentrabajo_stock_codigo;

		// Recorre cada id de linea de pedido
		DB::beginTransaction();
	
		try 
		{
			for ($i = 0; $i < count($ids); $i++)
			{
				// Lee el articulo para sacar todos los datos para Anita
				$pedido_combinacion = $this->pedido_combinacionRepository->find($ids[$i]);
				if ($pedido_combinacion)
				{
					// Agrega info para Anita
					$nro_orden = 0;
					$nro_item = $pedido_combinacion->numeroitem;
					if ($funcion == 'create')
					{
						if ($checkOtStock == 'on') 
							$estado = array_search('Terminada', OrdenTrabajo::$enumEstado);
						else
							$estado = array_search('Pendiente', OrdenTrabajo::$enumEstado);
					}
					else
					{
						$ordentrabajo = $this->ordentrabajoRepository->find($id);

						if ($ordentrabajo)
						{
							$nro_orden = $ordentrabajo->codigo;
							$estado = $ordentrabajo->estado;
						}
					}

					// Lee el pedido para sacar el codigo
					$pedido = $this->pedidoQuery->leePedidoporId($pedido_combinacion->pedido_id)->first();

					// Lee articulo y combinacion
					$articulo = Articulo::find($pedido_combinacion->articulo_id);

					$categoria_codigo = ' ';
					if ($articulo)
					{
						$categoria = Categoria::where('id' , $articulo->categoria_id)->first();
						if ($categoria)
							$categoria_codigo = $categoria->codigo;
					}

					$combinacion = Combinacion::find($pedido_combinacion->combinacion_id);

					// Lee el cliente
					$cliente = $this->clienteQuery->traeClienteporId($pedido->cliente_id);

					// Lee la linea
					$linea = Linea::find($articulo->linea_id);

					// Lee el fondo
					$fondo_codigo = ' ';
					if ($combinacion->fondo_id != NULL)
					{
						$fondo = Fondo::where('id' , $combinacion->fondo_id)->first();
						if ($fondo)
							$fondo_codigo = $fondo->codigo;
					}

					$colorfondo_codigo = NULL;
					$color = Color::select('id', 'codigo')->where('id' , $combinacion->colorfondo_id)->first();
					if ($color)
						$colorfondo_codigo = $color->codigo;

					$colorforro_codigo = NULL;
					$color = Color::select('id', 'codigo')->where('id' , $combinacion->colorforro_id)->first();
					if ($color)
						$colorforro_codigo = $color->codigo;

					// Arma datos para ERP y Anita 
					$data = array(
									'cliente' => str_pad($cliente->codigo, 6, "0", STR_PAD_LEFT),
									'nro_orden' => $nro_orden,
									'tipo' => substr($pedido->codigo, 0, 3),
									'letra' => substr($pedido->codigo, 4, 1),
									'sucursal' => substr($pedido->codigo, 6, 5),
									'nro' => substr($pedido->codigo, 12, 8),
									'nro_renglon' => $pedido_combinacion->numeroitem,
									'fecha' => Carbon::now(),
									'estado' => $estado,
									'observacion' => $leyenda,
									'alfa_cliente' => $cliente->nombre,
									'articulo' => str_pad($articulo->sku, 13, "0", STR_PAD_LEFT),
									'agrupacion' => str_pad($categoria_codigo, 4, "0", STR_PAD_LEFT),
									'color' => $combinacion->codigo,
									'forro' => ' ',
									'alfa_art' => substr($articulo->descripcion, 0, 30),
									'linea' => str_pad($linea->codigo, 6, "0", STR_PAD_LEFT),
									'fondo' => $fondo_codigo,
									'color_fondo' => $colorfondo_codigo,
									'capellada' => $combinacion->codigo,
									'color_cap' => 0,
									'color_forro' => $colorforro_codigo,
									'tipo_fact' => ' ',
									'letra_fact' => ' ',
									'suc_fact' => 0,
									'nro_fact' => 0,
									'aplique' =>  0,
									'fl_impresa' => ' ',
									'fl_stock' => ($checkOtStock == 'on' ? 'S' : 'N'),
									'tipoot' => ($checkOtStock == 'on' ? 'S' : ' '),
									'usuario_id' => $usuario_id 
										);
				}

				// Lee las medidas del item del pedido x id de pedido_combinacion
				$pedido_combinacion_talle = $this->pedido_combinacion_talleRepository->findporpedido_combinacion($ids[$i]);

				$ordentrabajo_id = '';
				$fl_graba_ot = false;
				if ($pedido_combinacion_talle)
				{
					if ($i == 0) 
					{
						if ($funcion == 'create')
						{
							// Guarda maestro de orden de trabajo 
							$ordentrabajo = $this->ordentrabajoRepository->create($data);

							// Actualiza el codigo con el id
							$id_ot = $ordentrabajo->id;
							$ordentrabajo = $this->ordentrabajoRepository->update(['codigo' => $id_ot], $id_ot);
						}
						else
						{
							$ordentrabajo = $this->ordentrabajoRepository->update($data, $id);

							$id_ot = $id;
						}
						$ordentrabajo = $this->ordentrabajoRepository->find($id_ot);

						if($ordentrabajo)
							$nro_orden = $ordentrabajo->codigo;
						
						$fl_graba_ot = true;
					}
					else
						$fl_graba_ot = true;
					
					// Guarda medidas
					if ($fl_graba_ot)
					{
						if ($data['articulo'] == '0000000000000')
						{
							throw new Exception('Articulo en cero.');
						}

						$ordentrabajo_id = ($funcion == 'update' ? $id : $id_ot);
						$cliente_id = $ordentrabajo->cliente_id;
			
						// Borra los registros de movimientos antes de grabar nuevamente
						if ($funcion == 'update')
						{
							$this->ordentrabajo_combinacion_talleRepository->deleteporordentrabajo($ordentrabajo_id);
						}

						foreach($pedido_combinacion_talle as $item)
						{
							if ($item)
							{
								$talle = Talle::find($item->talle_id);
								if ($talle)
									$medida = $talle->nombre;
								else
									$medida = '';

								$data['medida'] = $medida;
								$data['cantidad'] = $item->cantidad;
								$data['cantfact'] = 0;
								$data['cliente_id'] = $cliente->id;
								$data['ordentrabajo_id'] = $ordentrabajo_id;
								$data['pedido_combinacion_talle_id'] = $item->id;
								$data['ordentrabajo_stock_id'] = $ordentrabajo_stock_id;

								// Guarda item
								$ordentrabajo_combinacion_talle = $this->ordentrabajo_combinacion_talleRepository->create($data);
							}
						}
						// Actualiza el nro. de ot en el pedido
						if ($funcion == 'create')
						{
							if ($lote_id > 0)
								$this->pedido_combinacionRepository->find($ids[$i])->update([
													'ot_id'=>$ordentrabajo_id,
													'lote_id'=>$lote_id
												]);
							else
								$this->pedido_combinacionRepository->find($ids[$i])->update([
													'ot_id'=>$ordentrabajo_id,
												]);
						}

						// Graba stock si el cliente es el correspondiente
						if ($cliente->id == config("consprod.CLIENTE_STOCK") || $ordentrabajo_stock_codigo > 0)
						{
							// Valida deposito del alta de produccion
							if ($checkOtStock == 'on')
								Log::notice('OT Stock '.$ordentrabajo_id.' Lote '.$ordentrabajo_stock_codigo);

							if ($ordentrabajo_stock_codigo > 0 && $checkOtStock == 'on' &&
								$cliente->id != config("consprod.CLIENTE_STOCK"))
							{
								$stock = Self::controlaOtStock($ordentrabajo_stock_codigo, $articulo->id, $combinacion->id);

								$deposito_id = $stock['deposito_id'];

								Log::notice('OT Stock '.$ordentrabajo_id.' Lote '.$ordentrabajo_stock_codigo.' Deposito '.$deposito_id);
							}

							if ($deposito_id == null)
								$deposito_id = 1;

							$dataArticuloMovimiento = [
									'fecha' => Carbon::now(),
									'fechajornada' => Carbon::now(),
									'tipotransaccion_id' => $ordentrabajo_stock_codigo > 0 && $checkOtStock == 'on' &&
														$cliente->id != config("consprod.CLIENTE_STOCK") ? 
														config("consprod.TIPOTRANSACCION_CONSUME_OT") :
														config("consprod.TIPOTRANSACCION_ALTA_PRODUCCION"),
									'pedido_combinacion_id' => $ids[$i],
									'ordentrabajo_id' => $ordentrabajo_id,
									'lote' => $ordentrabajo_stock_codigo > 0 ? $ordentrabajo_stock_codigo : $nro_orden,
									'articulo_id' => $articulo->id,
									'combinacion_id' => $combinacion->id,
									'modulo_id' => $pedido_combinacion->modulo_id,
									'concepto' => $ordentrabajo_stock_codigo > 0 ? 'Consumo de OT' : 'Alta de produccion',
									'cantidad' => $pedido_combinacion->cantidad,
									'precio' => $pedido_combinacion->precio,
									'costo' => 0,
									'descuento' => $pedido_combinacion->descuento,
									'descuentointegrado' => $pedido_combinacion->descuentointegrado,
									'moneda_id' => $pedido_combinacion->moneda_id,
									'incluyeimpuesto' => $pedido_combinacion->incluyeimpuesto,
									'listaprecio_id' => $pedido_combinacion->listaprecio_id,
									'deposito_id' => $deposito_id
							];
							$articulo_movimiento = $this->articulo_movimientoService->
													guardaArticuloMovimiento($funcion, 
													$dataArticuloMovimiento, $pedido_combinacion_talle);
						}

						if ($i == 0)
						{
							if ($funcion == 'create')
							{
								$pedido_combinacion = $this->pedido_combinacionRepository->find($ids[$i]);

								// Graba tarea inicial
								$data['ordentrabajo_id'] = $ordentrabajo_id;
								$data['tarea_id'] = config("consprod.TAREA_PENDIENTE_FABRICACION"); 
								$data['desdefecha'] = Carbon::now();
								$data['hastafecha'] = Carbon::now();
								$data['empleado_id'] = null;
								$data['pedido_combinacion_id'] = ($flBoletasJuntas ? null : $pedido_combinacion->id);
								$data['estado'] = config("consprod.TAREA_ESTADO_TERMINADA");
								$data['costo'] = 0;
								$ordentrabajo = $this->ordentrabajo_tareaRepository->create($data);
							
								// Crea tarea de OT terminada
								if ($checkOtStock == 'on')
								{
									$data['tarea_id'] = config("consprod.TAREA_TERMINADA_STOCK"); // Ot terminada
									$data['desdefecha'] = Carbon::now();
									$data['hastafecha'] = Carbon::now();
									$data['empleado_id'] = null;
									$data['pedido_combinacion_id'] = ($flBoletasJuntas ? null : $pedido_combinacion->id);
									$data['estado'] = config("consprod.TAREA_ESTADO_TERMINADA");
									$data['costo'] = 0;
		
									if ($funcion == 'create') 
										$ordentrabajo = $this->ordentrabajo_tareaRepository->create($data);
									else
									{
										$tarea_32 = $this->ordentrabajo_tareaRepository->findPorOrdentrabajoId($ordentrabajo_id, 
											config("consprod.TAREA_TERMINADA"));
		
										if ($tarea_32 && count($tarea_32) > 0)
											$ordentrabajo = $this->ordentrabajo_tareaRepository->update($data, $tarea_32->id);

											
										$tarea_32 = $this->ordentrabajo_tareaRepository->findPorOrdentrabajoId($ordentrabajo_id, 
											config("consprod.TAREA_TERMINADA_STOCK"));
	
										if ($tarea_32 && count($tarea_32) > 0)
											$ordentrabajo = $this->ordentrabajo_tareaRepository->update($data, $tarea_32->id);
									}
								}
							}
							else
							{
								// Borra la tarea TERMINADA en caso que cambie de stock a no stock
								if ($funcion == 'update')
								{
									$tarea_32 = $this->ordentrabajo_tareaRepository->findPorOrdentrabajoId($ordentrabajo_id, 
										config("consprod.TAREA_TERMINADA"));

									if ($tarea_32 && count($tarea_32) > 0)
										$this->ordentrabajo_tareaRepository->delete($tarea_32->id, $nro_orden);

									$tarea_32 = $this->ordentrabajo_tareaRepository->findPorOrdentrabajoId($ordentrabajo_id, 
										config("consprod.TAREA_TERMINADA_STOCK"));

									if ($tarea_32 && count($tarea_32) > 0)
										$this->ordentrabajo_tareaRepository->delete($tarea_32->id, $nro_orden);
								}
							}
						}
					}
				}
			}
			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();
			Log::error('guardaOrdenTrabajo: '.$e->getMessage(), [
				'exception' => $e,
			]);

			return ['id' => 0, 'nro_orden' => 0, 'error' => $e->getMessage()];
		}
		
		return ['id'=>$ordentrabajo_id, 'nro_orden'=>$ordentrabajo_id];
	}

	public function borraOrdenTrabajo($id)
	{
		$pedido_combinacion_id = 0;
		$ordentrabajo_combinacion_talle = $this->ordentrabajo_combinacion_talleRepository
												->findPorOrdenTrabajoId($id);
		if ($ordentrabajo_combinacion_talle)
		{
			// Busca pedido_combinacion_talle para traer el item del pedido
			$pedido_combinacion_talle = $this->pedido_combinacion_talleRepository
											->find($ordentrabajo_combinacion_talle[0]->pedido_combinacion_talle_id);

			if ($pedido_combinacion_talle)										
			{
				// Lee pedido_combinacion
				$pedido_combinacion = $this->pedido_combinacionRepository
											->find($pedido_combinacion_talle->pedido_combinacion_id);

				$pedido_combinacion_id = $pedido_combinacion->id;
			}
		}
		// Recorre cada id de linea de pedido
		if ($pedido_combinacion_id > 0)
		{
			DB::beginTransaction();
			try 
			{
				$this->pedido_combinacionRepository
						->updatePorOtId($pedido_combinacion_talle->pedido_combinacion_id);
				//$this->ordentrabajo_combinacion_talleRepository->deleteporordentrabajo($id);
				//$this->ordentrabajo_tareaRepository->deleteporordentrabajo($id, 0);
				$this->ordentrabajoRepository->delete($id);

				// Borra stock
				$stock = $this->articulo_movimientoService
								->deletePorOrdentrabajoId($id);
			
				DB::commit();
			} catch (\Exception $e) {
				DB::rollback();
				dd($e->getMessage());
				return $e->getMessage();
			}
		}
		return true;
	}

	public function listaOrdenTrabajoLaser($id)
	{
    	$ot = $this->ordentrabajoQuery->leeOrdenTrabajo($id);

		if ($ot->tipoot == 'S')
		{
			$codigo_copia = 14;
			$copia = 1;
		}
		else
		{
			$codigo_copia = 11;
			$copia = 12;
		}

		if (auth()->user()->usuario == 'diego')
			$salida = 'ot-laser';
		else
			$salida = 'ot-laser-gaby';

		//$qr = QrCode::size(200)->generate($ot->codigo);

		$ret = shell_exec('ssh -i /etc/id_rsa -o BatchMode=yes -o StrictHostKeyChecking=no sergio@server1 "cd /usr2/ferli/ventas; ./l-ordtmael -b '.$ot->codigo.' '.$codigo_copia.' '.$copia.' '.$salida.' 2>&1"');
		return($ret);
	}

	public function listaEtiquetaCuit(array $data)
	{
		// Arma nombre de archivo
		$nombreEtiqueta = "tmp/etiCUIT-" . Str::random(10) . '.txt';

		$ordenes = explode(',', $data['ordenestrabajo']);

		$etiqueta = "";
		//$pos = [44,66,88,110,132,154];
		$pos = [25,46,68,90,112,134];

		foreach($ordenes as $id)
		{
			// Verifica origen
			if ($data['origen'] == 'ANITA')
	    		$ot = $this->ordentrabajoQuery->traeOrdentrabajoPorId($id);
			else
				$ot = $this->ordentrabajoQuery->traeOrdentrabajoPorIdERP($id);

			if (isset($ot[0]))
			{
				$buff = [];
				$buff[] = " ";
				$buff[] = " ";
				$buff[] = "Nro. O.T.: ".$ot[0]->ordtm_nro_orden;

				if ($etiqueta == "")
					$etiqueta = "\nN\n";

				for ($i = 0; $i < count($buff); $i++)
				{
					$salida = sprintf("%-43.43s%-43.43s", $buff[$i], " ");
					$etiqueta .= "A30,".$pos[$i].",0,1,1,2,N,\"".$salida."\"\n";
				}
		
				$etiqueta .= "P1\n";

				$buffpar = [];
				$buffimpar = [];
				$fl_par = true;
				foreach($ot as $item)
				{
					// Gira por cada unidad en funcion de la cantidad del item
					$fl_imprimio = false;
					for ($unidad = 0; $unidad < $item->ordtv_cantidad; $unidad++)
					{
						// Lee articulo
						$articulo = Articulo::where('sku', ltrim($item->ordtv_articulo, '0'))->first();

						$buff = [];
						if ($articulo)
						{
							$combinacion = Combinacion::where('articulo_id', $articulo->id)
													->where('codigo', $item->ordtm_capellada)
													->first();
		
							if ($combinacion)
							{
								$empresa = Empresa::where('codigo',1)->first();
		
								if ($empresa)
								{
									$buff[] = $empresa->nombre;
									$buff[] = "C.U.I.T. ".$empresa->nroinscripcion;
								}
								else
								{
									$buff[] = "EMPRESA";
									$buff[] = "CUIT";
								}

								if ($articulo->material_id)
								{
									$material = Material::findorFail($articulo->material_id);
									if ($material)
									{
										$buff[] = "CAPELLADA ".$material->nombre;
									}
								}
								
								$linea = Linea::find($articulo->linea_id);
								$forro = Forro::find($articulo->forro_id);
								if ($linea && $forro)
									$buff[] = "FONDO ".$linea->nombre." FORRO ".substr($forro->nombre,0,6);
		
								$buff[] = "ARTICULO ".$articulo->sku;
								$buff[] = "FERLI (MR)-MADE IN ARGENTINA";
							}
						}

						$fl_imprimo = false;
						if ($fl_par)
						{
							for ($i = 0; $i < count($buff); $i++)
								$buffpar[] = $buff[$i];

							$fl_par = false;
						}
						else
						{
							for ($i = 0; $i < count($buff); $i++)
								$buffimpar[] = $buff[$i];

							if ($etiqueta == "")
								$etiqueta = "\nN\n";
		
							for ($i = 0; $i < count($buffpar); $i++)
							{
								$salida = sprintf("%-43.43s%-43.43s", $buffpar[$i], $buffimpar[$i]);
								$etiqueta .= "A30,".$pos[$i].",0,1,1,2,N,\"".$salida."\"\n";
							}
		
							$etiqueta .= "P1\n";
							$fl_imprimo = true;
							$fl_par = true;
							$buffpar = [];
							$buffimpar = [];
						}
						
					}
				}
				if (!$fl_imprimio)
				{
					for ($i = 0; $i < count($buffpar); $i++)
					{
						$salida = sprintf("%-43.43s%-43.43s", $buffpar[$i], " ");
						$etiqueta .= "A30,".$pos[$i].",0,1,1,2,N,\"".$salida."\"\n";
					}
					$etiqueta .= "P1\n";

					$buffpar = [];
					$buffimpar = [];
				}
			}
		}
		Storage::disk('local')->put($nombreEtiqueta, $etiqueta);
		$path = Storage::path($nombreEtiqueta);

		// Busca configuracion
		$usuario_id = Auth::user()->id;

		system("lp -dzebra2 ".$path);

		Storage::disk('local')->delete($nombreEtiqueta);

        return redirect()->back()->with('status','Las ordenes seleccionadas no existen');
    }

	public function listaEtiquetaCaja(array $data)
	{
		// Arma nombre de archivo
		$nombreEtiqueta = "tmp/etiCAJA-" . Str::random(10) . '.txt';

		$ordenes = explode(',', $data['ordenestrabajo']);

		$etiqueta = "";
		foreach($ordenes as $id)
		{
			// Verifica origen
			if ($data['origen'] == 'ANITA')
				$ot = $this->ordentrabajoQuery->traeOrdentrabajoPorId($id);
			else
				$ot = $this->ordentrabajoQuery->traeOrdentrabajoPorIdERP($id);
			foreach($ot as $item)
			{
				// Gira por cada unidad en funcion de la cantidad del item
				for ($unidad = 0; $unidad < $item->ordtv_cantidad; $unidad++)
				{
					// Lee articulo
			 		$articulo = Articulo::where('sku', ltrim($item->ordtv_articulo, '0'))->first();

					$buff = [];
					if ($articulo)
					{
				  		$combinacion = Combinacion::where('articulo_id', $articulo->id)
												->where('codigo', $item->ordtm_capellada)
												->first();
	
						if ($combinacion)
						{
							// Lee foto
							//$file_ori = "/var/www/html/anitaERP/public/storage/imagenes/fotos_articulos/$combinacion->foto";
							//$file = str_replace("jpg", "pcx", $file_ori);
							$file = "/var/www/html/anitaERP/public/storage/imagenes/fotos_articulos/11000703-1.pcx";
							$fp = fopen($file, "r");
							$contents = fread($fp, filesize($file));

							$cod_art = "";
							$cod_art_red = "";
							$item->ordtv_articulo = str_pad($articulo->sku, 13, "0", STR_PAD_LEFT);
    						if (substr($item->ordtv_articulo, 5, 1) == '0')
    						{
        						$cod_art = substr($item->ordtv_articulo,6,2).'-'.'0'.substr($item->ordtv_articulo,8,3).'-'.substr($item->ordtv_articulo,11,2).'-'.$item->ordtv_medida.'-'.$combinacion->codigo;
						
        						$cod_art_red = substr($item->ordtv_articulo,6,2).'-'.'0'.substr($item->ordtv_articulo,8,3).'-'.substr($item->ordtv_articulo,11,2);
    						}
    						else
    						{
        						$cod_art = substr($item->ordtv_articulo,5,2).'-'.substr($item->ordtv_articulo,7,4).'-'.substr($item->ordtv_articulo,11,2).'-'.$item->ordtv_medida.'-'.$combinacion->codigo;

        						$cod_art_red = substr($item->ordtv_articulo,5,2).'-'.substr($item->ordtv_articulo,7,4).'-'.substr($item->ordtv_articulo,11,2);
    						}

					  		$empresa = Empresa::where('codigo',1)->first();

							$linea = Linea::find($articulo->linea_id);
							$nombrelinea = '';
							if ($linea)
								$nombrelinea = $linea->nombre;
	
							//$buff[] = "GK".chr(34)."IMAGEN".chr(34).chr(13).chr(10);
							//$buff[] = "GK".chr(34)."IMAGEN".chr(34).chr(13).chr(10);
							//$buff[] = "GM".chr(34)."IMAGEN".chr(34).filesize($file).chr(13).chr(10);
							//$buff[] = chr(34).$contents.chr(34);

							$buff[] = chr(13).chr(10);
							$buff[] = chr(13).chr(10);
							$buff[] = "Q406,019".chr(13).chr(10);
							$buff[] = "q831".chr(13).chr(10);
							$buff[] = "rN".chr(13).chr(10);
							$buff[] = "S4".chr(13).chr(10);
							$buff[] = "D7".chr(13).chr(10);
							$buff[] = "ZT".chr(13).chr(10);
							$buff[] = "JB".chr(13).chr(10);
							$buff[] = "OD".chr(13).chr(10);
							$buff[] = "R9,0".chr(13).chr(10);
							$buff[] = "N".chr(13).chr(10);
        					$buff[] = "A100,5,0,3,2,2,N,".chr(34)."ART:".chr(34).chr(13).chr(10);
        					$buff[] = "A100,69,0,1,2,2,N,".chr(34).$combinacion->nombre.chr(34).chr(13).chr(10);
        					$buff[] = "A100,104,0,1,2,2,N,".chr(34)."Linea: ".$nombrelinea.chr(34).chr(13).chr(10);
        					$buff[] = "A100,185,0,3,2,2,N,".chr(34)."NRO.:".chr(34).chr(13).chr(10);
        					$buff[] = "LO40,10,8,238".chr(13).chr(10);

							//$buff[] = "GG50,5,".chr(34)."IMAGEN".chr(34).chr(13).chr(10);
							//$buff[] = "GW50,30,35,200".chr(13).chr(10);
							//$buff[] = $contents;
							//$buff[] = chr(13).chr(10);

        					$buff[] = "B116,235,0,3,2,6,83,B,".chr(34).$cod_art.chr(34).chr(13).chr(10);
        					$buff[] = "A280,0,0,3,2,3,N,".chr(34).$cod_art_red.chr(34).chr(13).chr(10);

        					/* MEDIDA */
        					$buff[] = "A355,135,0,4,4,4,N,".chr(34).$item->ordtv_medida.chr(34).chr(13).chr(10);
        					$buff[] = "A527,83,0,4,1,1,N,".chr(34)." ".chr(34).chr(13).chr(10);
						}
					}

					for ($i = 0; $i < count($buff); $i++)
					{
           				$etiqueta .= $buff[$i];
			  		}
	
					$etiqueta .= "P1\n";
					//break;
			  	}
			}
		}
		Storage::disk('local')->put($nombreEtiqueta, $etiqueta);
		$path = Storage::path($nombreEtiqueta);

		system("lp -dzebra1 ".$path);

		Storage::disk('local')->delete($nombreEtiqueta);

        return redirect()->back()->with('status','Las ordenes seleccionadas no existen');
    }

	public function listaEtiquetaCajaZPL(array $data)
	{
		// Arma nombre de archivo
		$nombreEtiqueta = "tmp/etiCAJA-" . Str::random(10) . '.txt';

		$ordenes = explode(',', $data['ordenestrabajo']);

		$etiqueta = "";
		foreach($ordenes as $id)
		{
			// Verifica origen
			if ($data['origen'] == 'ANITA')
				$ot = $this->ordentrabajoQuery->traeOrdentrabajoPorId($id);
			else
				$ot = $this->ordentrabajoQuery->traeOrdentrabajoPorIdERP($id);

			foreach($ot as $item)
			{
				// Gira por cada unidad en funcion de la cantidad del item
				for ($unidad = 0; $unidad < $item->ordtv_cantidad; $unidad++)
				{
					// Lee articulo
			 		$articulo = Articulo::where('sku', ltrim($item->ordtv_articulo, '0'))->first();
					$buff = [];
					if ($articulo)
					{
				  		$combinacion = Combinacion::where('articulo_id', $articulo->id)
												->where('codigo', $item->ordtm_capellada)
												->first();

						$item->ordtv_articulo = str_pad($articulo->sku, 13, "0", STR_PAD_LEFT);
						if ($combinacion)
						{
							$file = "/var/www/html/anitaERP/public/storage/imagenes/fotos_articulos/".$articulo->sku.".zpl";
							
							if (!file_exists($file))
								return redirect()->back()->with('mensaje','Articulo '.$articulo->sku.' SIN FOTO');
								
							$fp = fopen($file, "r");

							$contents = fread($fp, filesize($file));

							$cod_art = "";
							$cod_art_red = "";
    						if (substr($item->ordtv_articulo, 5, 1) == '0')
    						{
        						$cod_art = substr($item->ordtv_articulo,6,2).'-'.'0'.substr($item->ordtv_articulo,8,3).'-'.substr($item->ordtv_articulo,11,2).'-'.$item->ordtv_medida.'-'.$combinacion->codigo;
						
        						$cod_art_red = substr($item->ordtv_articulo,6,2).'-'.'0'.substr($item->ordtv_articulo,8,3).'-'.substr($item->ordtv_articulo,11,2);
    						}
    						else
    						{
        						$cod_art = substr($item->ordtv_articulo,5,2).'-'.substr($item->ordtv_articulo,7,4).'-'.substr($item->ordtv_articulo,11,2).'-'.$item->ordtv_medida.'-'.$combinacion->codigo;

        						$cod_art_red = substr($item->ordtv_articulo,5,2).'-'.substr($item->ordtv_articulo,7,4).'-'.substr($item->ordtv_articulo,11,2);
    						}
					  		$empresa = Empresa::where('codigo',1)->first();

							$linea = Linea::find($articulo->linea_id);
	
							$buff[] = "^XA".chr(13).chr(10);
							$buff[] = "^SZ2".chr(13).chr(10);
							$buff[] = "^JMA".chr(13).chr(10);
							$buff[] = "^MCY".chr(13).chr(10);
							$buff[] = "^PMN".chr(13).chr(10);
							$buff[] = "^PW792".chr(13).chr(10);
							$buff[] = "~JSN".chr(13).chr(10);
							$buff[] = "^JZY".chr(13).chr(10);
							$buff[] = "^LH0,0".chr(13).chr(10);
							$buff[] = "^XZ".chr(13).chr(10);

							$buff[] = "^XA".chr(13).chr(10);
							$buff[] = $contents;
							$buff[] = chr(13).chr(10);

							$buff[] = "^CF0,50".chr(13).chr(10);
        					$buff[] = "^FO40,30^FDART: ".$cod_art_red."^FS".chr(13).chr(10);
							$buff[] = "^CF0,30".chr(13).chr(10);
        					$buff[] = "^FO40,85^FD".$combinacion->nombre."^FS".chr(13).chr(10);
        					$buff[] = "^FO40,125^FDLinea: ".$linea->nombre."^FS".chr(13).chr(10);
        					$buff[] = "^FO40,175^FDNRO.: ^FS".chr(13).chr(10);

        					$buff[] = "^FO15,240^GB700,3,3^FS".chr(13).chr(10);

							// Codigo de barras
        					$buff[] = "^BY3^B3N,N,70,Y,N".chr(13).chr(10);
        					$buff[] = "^FO40,250^BC^FD".$cod_art."^FS".chr(13).chr(10);

        					$buff[] = "^FO25,10^GB3,250,3^FS".chr(13).chr(10);

        					/* MEDIDA */
							$buff[] = "^CF0,100".chr(13).chr(10);
        					$buff[] = "^FO200,160^FD".$item->ordtv_medida."^FS".chr(13).chr(10);
							$buff[] = "^CF0,30".chr(13).chr(10);
						}
					}

					for ($i = 0; $i < count($buff); $i++)
					{
           				$etiqueta .= $buff[$i];
			  		}
					$etiqueta .= "^XZ\n";
			  	}
			}
		}
		Storage::disk('local')->put($nombreEtiqueta, $etiqueta);
		$path = Storage::path($nombreEtiqueta);

		system("lp -dzebra1 ".$path);

		Storage::disk('local')->delete($nombreEtiqueta);

        return redirect()->back()->with('status','Las ordenes seleccionadas no existen');
    }

	public function listaEtiquetaPruebaCajaZPL(array $data)
	{
		// Arma nombre de archivo
		$nombreEtiqueta = "tmp/etiCAJA-" . Str::random(10) . '.txt';
		$articulo = Articulo::where('id', $data['articulo_id'])->first();
		$medida = 38;

		$etiqueta = "";
		// Gira por cada unidad en funcion de la cantidad del item
		for ($unidad = 0; $unidad < 2; $unidad++)
		{
			// Lee articulo
			$buff = [];
			if ($articulo)
			{
				$combinacion = Combinacion::where('articulo_id', $articulo->id)
										->first();

				$articuloCompleto = str_pad($articulo->sku, 13, "0", STR_PAD_LEFT);
				if ($combinacion)
				{
					$file = "/var/www/html/anitaERP/public/storage/imagenes/fotos_articulos/".$articulo->sku.".zpl";
					
					if (!file_exists($file))
						return redirect()->back()->with('mensaje','Articulo '.$articulo->sku.' SIN FOTO');
						
					$fp = fopen($file, "r");

					$contents = fread($fp, filesize($file));

					$cod_art = "";
					$cod_art_red = "";
					if (substr($articuloCompleto, 5, 1) == '0')
					{
						$cod_art = substr($articuloCompleto,6,2).'-'.'0'.substr($articuloCompleto,8,3).'-'.substr($articuloCompleto,11,2).'-'.$medida.'-'.$combinacion->codigo;
				
						$cod_art_red = substr($item->articuloCompleto,6,2).'-'.'0'.substr($articuloCompleto,8,3).'-'.substr($articuloCompleto,11,2);
					}
					else
					{
						$cod_art = substr($articuloCompleto,5,2).'-'.substr($articuloCompleto,7,4).'-'.substr($articuloCompleto,11,2).'-'.$medida.'-'.$combinacion->codigo;

						$cod_art_red = substr($articuloCompleto,5,2).'-'.substr($articuloCompleto,7,4).'-'.substr($articuloCompleto,11,2);
					}
					$empresa = Empresa::where('codigo',1)->first();

					$linea = Linea::find($articulo->linea_id);

					$buff[] = "^XA".chr(13).chr(10);
					$buff[] = "^SZ2".chr(13).chr(10);
					$buff[] = "^JMA".chr(13).chr(10);
					$buff[] = "^MCY".chr(13).chr(10);
					$buff[] = "^PMN".chr(13).chr(10);
					$buff[] = "^PW792".chr(13).chr(10);
					$buff[] = "~JSN".chr(13).chr(10);
					$buff[] = "^JZY".chr(13).chr(10);
					$buff[] = "^LH0,0".chr(13).chr(10);
					$buff[] = "^XZ".chr(13).chr(10);

					$buff[] = "^XA".chr(13).chr(10);
					$buff[] = $contents;
					$buff[] = chr(13).chr(10);

					$buff[] = "^CF0,50".chr(13).chr(10);
					$buff[] = "^FO40,30^FDART: ".$cod_art_red."^FS".chr(13).chr(10);
					$buff[] = "^CF0,30".chr(13).chr(10);
					$buff[] = "^FO40,85^FD".$combinacion->nombre."^FS".chr(13).chr(10);
					$buff[] = "^FO40,125^FDLinea: ".$linea->nombre."^FS".chr(13).chr(10);
					$buff[] = "^FO40,175^FDNRO.: ^FS".chr(13).chr(10);

					$buff[] = "^FO15,240^GB700,3,3^FS".chr(13).chr(10);

					// Codigo de barras
					$buff[] = "^BY3^B3N,N,70,Y,N".chr(13).chr(10);
					$buff[] = "^FO40,250^BC^FD".$cod_art."^FS".chr(13).chr(10);

					$buff[] = "^FO25,10^GB3,250,3^FS".chr(13).chr(10);

					/* MEDIDA */
					$buff[] = "^CF0,100".chr(13).chr(10);
					$buff[] = "^FO200,160^FD".$medida."^FS".chr(13).chr(10);
					$buff[] = "^CF0,30".chr(13).chr(10);
				}
			}

			for ($i = 0; $i < count($buff); $i++)
			{
				$etiqueta .= $buff[$i];
			}
			$etiqueta .= "^XZ\n";
		}
		Storage::disk('local')->put($nombreEtiqueta, $etiqueta);
		$path = Storage::path($nombreEtiqueta);
		$usuario_id = Auth::user()->id;
        $seteosalida = $this->seteoSalidaRepository->buscaSeteo($usuario_id, SeteoSalidaProgramaSupport::VENTAS_REPETIQUETAOT);

		$comando = sprintf($seteosalida->salidas->comando, $path);
		system($comando);

		Storage::disk('local')->delete($nombreEtiqueta);

        return redirect()->back()->with('status','El articulo seleccionado no existen');
    }

	/**
	 * Emisión OT vía DomPDF (impresora o descarga), sin IFPU/servidores externos.
	 *
	 * @return array{ok: bool, mensaje: string}
	 */
	public function imprimirEmisionOt(array $data): array
	{
		ini_set('memory_limit', '512M');

		$usuarioId = Auth::user()->id;
		$programa = SeteoSalidaProgramaSupport::VENTAS_REPEMISIONOT;
		$seteosalida = $this->seteoSalidaRepository->buscaSeteo($usuarioId, $programa);

		if (! $seteosalida || ! $seteosalida->salidas) {
			return [
				'ok' => false,
				'mensaje' => 'No hay impresora configurada para emisión de OT. Use «Configura salida».',
			];
		}

		$salidaPrincipal = $seteosalida->salidas;
		$plantillaComando = $this->plantillaComandoEmisionOtPdf($salidaPrincipal);
		if ($plantillaComando === null) {
			return [
				'ok' => false,
				'mensaje' => 'La salida de OT debe imprimir un PDF por JetDirect (un %s = ruta). '
					.'Ej.: '.config('ordentrabajo.imprimir_script', base_path('bin/imprimir-pdf-laser.sh')).' "%s" IP. '
					.'Diego 160.132.0.203 · Gaby 160.132.0.183 · P1 160.132.0.201.',
			];
		}

		$rutaPdf = null;

		try {
			$rutaPdf = $this->generarPdfEmisionOtArchivo($data);
			if ($rutaPdf === '') {
				return [
					'ok' => false,
					'mensaje' => 'No se encontraron las órdenes de trabajo indicadas.',
				];
			}

			$salidasIntentar = collect([$salidaPrincipal]);
			if (config('pedido.imprimir_fallback_habilitado', true)) {
				$salidasIntentar = $salidasIntentar->merge(
					SalidaImpresionFallbackSupport::alternativasPorMismoUso($salidaPrincipal, $programa)
				)->unique('id')->values();
			}

			$errores = [];
			$nombrePrincipal = (string) $salidaPrincipal->nombre;

			foreach ($salidasIntentar as $salida) {
				$plantilla = $this->plantillaComandoEmisionOtPdf($salida);
				if ($plantilla === null) {
					continue;
				}
				$errorImpresion = $this->ejecutarImpresionEmisionOtEnSalida($plantilla, $rutaPdf);
				if ($errorImpresion === null) {
					$mensaje = 'Impresión exitosa.';
					if ((int) $salida->id !== (int) $salidaPrincipal->id) {
						$mensaje = sprintf(
							'Impresión exitosa en %s (impresora alternativa; falló %s).',
							$salida->nombre,
							$nombrePrincipal
						);
					}

					return [
						'ok' => true,
						'mensaje' => $mensaje,
					];
				}
				$errores[] = $salida->nombre.': '.$errorImpresion;
			}

			return [
				'ok' => false,
				'mensaje' => 'No se pudo imprimir la OT. '.implode(' · ', $errores),
			];
		} catch (Exception $e) {
			return [
				'ok' => false,
				'mensaje' => 'No se pudo imprimir la OT: '.$e->getMessage(),
			];
		} finally {
			if ($rutaPdf !== null && is_file($rutaPdf)) {
				@unlink($rutaPdf);
			}
		}
	}

	public function descargarPdfEmisionOt(array $data): BinaryFileResponse|\Illuminate\Http\RedirectResponse
	{
		ini_set('memory_limit', '512M');

		try {
			$ruta = $this->generarPdfEmisionOtArchivo($data);
			if ($ruta === '') {
				return redirect()->back()->with('errores', ['No se encontraron las órdenes de trabajo indicadas.']);
			}

			$nombre = 'emision-ot-'.date('Ymd-His').'.pdf';

			return response()->download($ruta, $nombre)->deleteFileAfterSend(true);
		} catch (Exception $e) {
			return redirect()->back()->with('errores', ['No se pudo generar el PDF: '.$e->getMessage()]);
		}
	}

	public function generarPdfEmisionOtArchivo(array $data): string
	{
		$documentos = $this->armarDocumentosEmisionOt($data);
		if ($documentos === []) {
			return '';
		}

		$view = View::make('ventas.ordentrabajo.emision.pdf', compact('documentos'))->render();
		$path = storage_path('pdf/ordentrabajo');
		if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
			throw new Exception('No se pudo crear el directorio de PDF de OT.');
		}

		$codigos = implode('-', array_map(static fn (array $d) => $d['codigo'], $documentos));
		$codigos = preg_replace('/[^\w\-]+/', '_', (string) $codigos);
		$nombrePdf = 'emision-ot-'.$codigos.'-'.Str::random(6).'.pdf';

		$pdf = App::make('dompdf.wrapper');
		$pdf->setPaper('a4', 'portrait');
		$pdf->loadHTML($view)->save($path.'/'.$nombrePdf);

		return $path.'/'.$nombrePdf;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function armarDocumentosEmisionOt(array $data): array
	{
		$ordenes = array_values(array_filter(array_map('trim', explode(',', (string) ($data['ordenestrabajo'] ?? '')))));
		$tipoemision = strtoupper(trim((string) ($data['tipoemision'] ?? 'COMPLETA')));
		if ($tipoemision === '') {
			$tipoemision = 'COMPLETA';
		}

		$documentos = [];
		foreach ($ordenes as $codigo) {
			$doc = $this->armarDatosEmisionOtUna($codigo, $tipoemision);
			if ($doc !== null) {
				$documentos[] = $doc;
			}
		}

		return $documentos;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function armarDatosEmisionOtUna(string $codigo, string $tipoemision): ?array
	{
		$ot = $this->ordentrabajoQuery->leeOrdenTrabajoPorCodigo($codigo);
		$lineas = $ot?->ordentrabajoCombinacionTallesVigentes();
		$pedidoCombinacion = $ot?->pedidoCombinacionVigente();
		if (! $ot || ! $lineas || $lineas->isEmpty() || ! $pedidoCombinacion) {
			return null;
		}

		$mventa = 0;
		$observacion = '';
		$leyendaPedido = '';
		$articulo = $this->articuloQuery->traeArticuloPorId($pedidoCombinacion->articulo_id);
		if ($articulo) {
			$mventa = (int) $articulo->mventa_id;
		}

		$this->tot_pares1 = $this->tot_pares2 = $this->tot_pares3 = $this->tot_pares4 = 0;
		$totPares = 0;
		$medidas = [];
		$pedidos = [];

		foreach ($lineas as $item) {
			$pct = $item->pedido_combinacion_talles;
			$talle = Talle::find($pct->talle_id);
			if ($talle) {
				$medidas[] = ['medida' => $talle->nombre, 'cantidad' => $pct->cantidad];

				if ($talle->nombre >= config('consprod.DESDE_INTERVALO1') && $talle->nombre <= config('consprod.HASTA_INTERVALO1')) {
					$this->tot_pares1 += $pct->cantidad;
				}
				if ($talle->nombre >= config('consprod.DESDE_INTERVALO2') && $talle->nombre <= config('consprod.HASTA_INTERVALO2')) {
					$this->tot_pares2 += $pct->cantidad;
				}
				if ($talle->nombre >= config('consprod.DESDE_INTERVALO3') && $talle->nombre <= config('consprod.HASTA_INTERVALO3')) {
					$this->tot_pares3 += $pct->cantidad;
				}
				if ($talle->nombre >= config('consprod.DESDE_INTERVALO4') && $talle->nombre <= config('consprod.HASTA_INTERVALO4')) {
					$this->tot_pares4 += $pct->cantidad;
				}
			}
			$totPares += $pct->cantidad;

			$pc = $pct->pedidos_combinacion;
			if ($pc && ! in_array($pc->pedido_id, $pedidos, true)) {
				$pedidos[] = $pc->pedido_id;
			}
			$observacion = $pc?->observacion ?? $observacion;
			$leyendaPedido = $pc?->pedidos?->leyenda ?? $leyendaPedido;
		}

		$combinacion = Combinacion::find($pedidoCombinacion->combinacion_id);

		$nombreFondo = '';
		$colorFondo = '';
		$nombreForro = '';
		$colorForro = '';
		$nombreSerigrafia = '';
		$codigoCombinacion = '';
		$descripcionCombinacion = '';
		$nombrePlvista = '';
		$plvistaConConsumo = '';
		$nombrePlarmado = ' ';
		$materialCapellada = '';
		$materialCapelladaConConsumo = '';
		$forradoFondoConConsumo = '';
		$forradoBaseConConsumo = '';
		$aplique = '';
		$empaque = '';

		if ($combinacion) {
			$codigoCombinacion = $combinacion->codigo;
			$descripcionCombinacion = $combinacion->nombre;

			$fondo = Fondo::find($combinacion->fondo_id);
			if ($fondo) {
				$nombreFondo = $fondo->nombre;
			}
			$color = Color::find($combinacion->colorfondo_id);
			if ($color) {
				$colorFondo = $color->nombre;
			}
			$plvista = Plvista::find($combinacion->plvista_id);
			if ($plvista) {
				$nombrePlvista = $plvista->nombre;
			}
			$plvistaConConsumo = $this->calculaPlvista(
				$nombrePlvista,
				$combinacion->plvista_16_26,
				$combinacion->plvista_17_33,
				$combinacion->plvista_34_40,
				$combinacion->plvista_41_45
			);
			$forro = Forro::find($combinacion->forro_id);
			if ($forro) {
				$nombreForro = $forro->nombre;
			}
			$color = Color::find($combinacion->colorforro_id);
			if ($color) {
				$colorForro = $color->nombre;
			}
			$serigrafia = Serigrafia::find($combinacion->serigrafia_id);
			if ($serigrafia) {
				$nombreSerigrafia = $serigrafia->nombre;
			}
			$plarmado = Plarmado::find($combinacion->plarmado_id);
			if ($plarmado) {
				$nombrePlarmado = $plarmado->nombre;
			}

			$materialCapellada = $this->armaCapellada($combinacion->id, $combinacion->articulo_id, 'C', false);
			$materialCapelladaConConsumo = $this->armaCapellada($combinacion->id, $combinacion->articulo_id, 'C', true);
			$forradoFondoConConsumo = $this->armaCapellada($combinacion->id, $combinacion->articulo_id, 'F', true);
			$forradoBaseConConsumo = $this->armaCapellada($combinacion->id, $combinacion->articulo_id, 'B', true);
			$aplique = $this->armaAvio($combinacion->id, $combinacion->articulo_id, 'A');
			$empaque = $this->armaAvio($combinacion->id, $combinacion->articulo_id, 'E');
		}

		$medidasAcumuladas = [];
		$med = [];
		foreach ($medidas as $parte) {
			$med[] = $parte['medida'];
		}
		foreach (array_unique($med) as $un) {
			$suma = 0;
			foreach ($medidas as $original) {
				if ($un == $original['medida']) {
					$suma += $original['cantidad'];
				}
			}
			$medidasAcumuladas[] = ['medida' => $un, 'cantidad' => $suma];
		}
		$medidas = $medidasAcumuladas;

		$numeroPedidos = '';
		foreach ($pedidos as $pedido) {
			$numeroPedidos .= $pedido.' ';
		}

		$nombreTipoCorte = '';
		$abreviaturaTipoCorte = '';
		$nombreTipoCorteForro = '';
		$nombrePuntera = '';
		$nombreContrafuerte = '';
		$numeracion = '';
		$codigoArticulo = '';
		$codigoArticuloReducido = '';
		$cajas = [];

		if ($articulo) {
			$tipocorte = Tipocorte::find($articulo->tipocorte_id);
			if ($tipocorte) {
				$nombreTipoCorte = $tipocorte->nombre;
				$abreviaturaTipoCorte = $tipocorte->abreviatura;
			}
			$tipocorte = Tipocorte::find($articulo->tipocorteforro_id);
			if ($tipocorte) {
				$nombreTipoCorteForro = $tipocorte->nombre;
			}
			$puntera = Puntera::find($articulo->puntera_id);
			if ($puntera) {
				$nombrePuntera = $puntera->nombre;
			}
			$contrafuerte = Contrafuerte::find($articulo->contrafuerte_id);
			if ($contrafuerte) {
				$nombreContrafuerte = $contrafuerte->nombre;
			}
			$cajas = $this->armaCaja($articulo->id, $ot);

			$sku = str_pad((string) $articulo->sku, 13, '0', STR_PAD_LEFT);
			$codigoArticulo = substr($sku, 7, 4).'-'.substr($sku, 11, 2);
			$codigoArticuloReducido = substr($sku, 5, 2);

			$linea = Linea::select('nombre', 'codigo', 'tiponumeracion_id')
				->with('tiponumeraciones')
				->where('id', $articulo->linea_id)
				->first();
			if ($linea) {
				$numeracion = $linea->tiponumeraciones->nombre;
			}
		}

		$clientes = [];
		$localidadId = 0;
		$nombreVendedor = '';
		foreach ($lineas as $item) {
			$cliente = $item->clientes;
			if (! $cliente) {
				continue;
			}
			$suspension = $cliente->tipossuspensioncliente;
			if ((int) ($suspension?->id ?? 0) > 0) {
				$descCliente = substr($cliente->nombre, 0, 20).' '.substr((string) $suspension->nombre, 0, 8);
			} else {
				$descCliente = $cliente->nombre;
			}

			if (! in_array($descCliente, $clientes, true)) {
				$clientes[] = $descCliente;
				$localidadId = $cliente->localidad_id;
				$clicomi = $this->cliente_comisionQuery->traeVendedor($cliente->codigo, $mventa);
				if ($clicomi) {
					$nombreVendedor = $clicomi[0]->vend_nombre;
				}
			}
		}

		$nombreLocalidad = '';
		$localidad = Localidad::find($localidadId);
		if ($localidad) {
			$nombreLocalidad = $localidad->nombre;
		}

		$leyenda = trim(implode(' ', array_filter([
			$this->textoOVacioPreimpresoOt($ot->leyenda ?? null),
			$this->textoOVacioPreimpresoOt($observacion),
			$this->textoOVacioPreimpresoOt($leyendaPedido),
		], static fn (string $parte): bool => $parte !== '')));

		$copias = OrdentrabajoEmisionCopiaSupport::cantidadCopias($tipoemision);
		$titulosCopia = OrdentrabajoEmisionCopiaSupport::titulos($tipoemision);
		$qrDataUri = QrCodePngSupport::svgDataUri((string) $ot->codigo, 4);

		$doc = [
			'codigo' => (string) $ot->codigo,
			'fecha_fmt' => date('d-m-Y', strtotime((string) $ot->fecha)),
			'tipoemision' => $tipoemision,
			'mventa' => $mventa,
			'clientes' => $clientes,
			'localidad' => $nombreLocalidad,
			'tot_pares' => $totPares,
			'tot_pares1' => $this->tot_pares1,
			'tot_pares2' => $this->tot_pares2,
			'tot_pares3' => $this->tot_pares3,
			'tot_pares4' => $this->tot_pares4,
			'tipo_corte' => $nombreTipoCorte,
			'abrev_tipo_corte' => $abreviaturaTipoCorte,
			'tipo_corte_forro' => $nombreTipoCorteForro,
			'vendedor' => is_array($nombreVendedor) ? '' : (string) $nombreVendedor,
			'leyenda' => $leyenda,
			'codigo_articulo' => $codigoArticulo,
			'codigo_articulo_reducido' => $codigoArticuloReducido,
			'color_fondo' => $colorFondo,
			'combinacion' => trim($codigoCombinacion.' '.$descripcionCombinacion),
			'fondo' => $nombreFondo,
			'material_capellada' => $materialCapellada,
			'material_capellada_consumo' => $materialCapelladaConConsumo,
			'forrado_fondo_consumo' => $forradoFondoConConsumo,
			'forrado_base_consumo' => $forradoBaseConConsumo,
			'aplique' => $aplique,
			'empaque' => $empaque,
			'plvista' => $plvistaConConsumo,
			'serigrafia' => $nombreSerigrafia,
			'plarmado' => $nombrePlarmado,
			'puntera' => $nombrePuntera,
			'contrafuerte' => $nombreContrafuerte,
			'forro' => trim($nombreForro.'/'.$colorForro, '/'),
			'pedidos' => trim($numeroPedidos),
			'medidas' => $medidas,
			'cajas' => $cajas,
			'numeracion' => $numeracion,
			'copias' => $copias,
			'titulos_copia' => $titulosCopia,
			'qr_data_uri' => $qrDataUri,
		];
		$doc['paginas_preimpreso'] = $this->armarPaginasPreimpresoOt($doc);

		return $doc;
	}

	/**
	 * Páginas A4 portrait con campos en coordenadas del PostScript (preimpreso).
	 *
	 * @param  array<string, mixed>  $doc
	 * @return list<array{campos: list<array{y:float,x:float,k:string,v:string,max_w:float}>, qrs: list<array{x:float,y:float,s:float,uri:string}>}>
	 */
	private function armarPaginasPreimpresoOt(array $doc): array
	{
		$valores = $this->valoresCamposPreimpresoOt($doc);
		$tipoemision = strtoupper((string) ($doc['tipoemision'] ?? 'COMPLETA'));
		$titulos = $doc['titulos_copia'] ?? OrdentrabajoEmisionCopiaSupport::titulos($tipoemision);
		$qrUri = (string) ($doc['qr_data_uri'] ?? '');
		$mventa = (int) ($doc['mventa'] ?? 0);
		$numeracion = (string) ($doc['numeracion'] ?? '');

		$cantidadPaginas = ($tipoemision === 'COMPLETA') ? 2 : 1;
		$paginas = [];

		for ($pagina = 1; $pagina <= $cantidadPaginas; $pagina++) {
			$layout = OrdentrabajoEmisionPreimpresoLayout::pagina($pagina, $mventa, $numeracion);

			$offsetTitulo = ($pagina - 1) * 4;
			$valoresPagina = $valores;
			for ($i = 0; $i < 4; $i++) {
				$titulo = trim((string) ($titulos[$offsetTitulo + $i] ?? ''));
				if ($tipoemision !== 'COMPLETA' && $i > 0) {
					$titulo = '';
				}
				$valoresPagina['titulo_'.$i] = $titulo;
			}

			$campos = [];
			foreach ($layout as $pos) {
				$k = (string) ($pos['k'] ?? '');
				$v = $this->textoOVacioPreimpresoOt((string) ($valoresPagina[$k] ?? ''));
				if ($v === '') {
					continue;
				}
				$campos[] = [
					'y' => OrdentrabajoEmisionPreimpresoLayout::topCssMm((float) $pos['y']),
					'x' => (float) $pos['x'],
					'k' => $k,
					'v' => $v,
					'max_w' => $this->anchoMaxCampoPreimpresoOt($k),
				];
			}

			$qrs = [];
			foreach (OrdentrabajoEmisionPreimpresoLayout::qrPaneles() as $idx => $qrPos) {
				if ($tipoemision !== 'COMPLETA' && $idx > 0) {
					break;
				}
				if ($qrUri === '') {
					continue;
				}
				$qrs[] = [
					'x' => (float) $qrPos['x'],
					'y' => (float) $qrPos['y'],
					's' => (float) $qrPos['s'],
					'uri' => $qrUri,
				];
			}

			$paginas[] = [
				'campos' => $campos,
				'qrs' => $qrs,
			];
		}

		return $paginas;
	}

	/**
	 * @param  array<string, mixed>  $doc
	 * @return array<string, string>
	 */
	private function valoresCamposPreimpresoOt(array $doc): array
	{
		$clientes = array_values(array_map('strval', $doc['clientes'] ?? []));
		$lineasCortas = $this->partirLineasClientesOt($clientes, 60, 3);
		$lineasLargas = $this->partirLineasClientesOt($clientes, 80, 5);

		$leyenda = (string) ($doc['leyenda'] ?? '');
		$mat = (string) ($doc['material_capellada_consumo'] ?? '');
		$aplique = (string) ($doc['aplique'] ?? '');
		$forradoFondo = (string) ($doc['forrado_fondo_consumo'] ?? '');
		$forradoBase = (string) ($doc['forrado_base_consumo'] ?? '');
		$codArtRed = (string) ($doc['codigo_articulo_reducido'] ?? '');
		$pedidos = trim((string) ($doc['pedidos'] ?? ''));
		$localidad = (string) ($doc['localidad'] ?? '');

		$valores = [
			'codigo' => (string) ($doc['codigo'] ?? ''),
			'fecha_fmt' => (string) ($doc['fecha_fmt'] ?? ''),
			'tot_pares' => (string) (int) ($doc['tot_pares'] ?? 0),
			'tipo_corte' => (string) ($doc['tipo_corte'] ?? ''),
			'tipo_corte_forro' => (string) ($doc['tipo_corte_forro'] ?? ''),
			'vendedor' => (string) ($doc['vendedor'] ?? ''),
			'codigo_articulo' => (string) ($doc['codigo_articulo'] ?? ''),
			'codigo_articulo_reducido' => $codArtRed,
			'cod_art_fmt' => $codArtRed !== '' ? 'Cod.Art.:'.$codArtRed : '',
			'combinacion' => (string) ($doc['combinacion'] ?? ''),
			'fondo' => (string) ($doc['fondo'] ?? ''),
			'fondo_color' => trim((string) ($doc['fondo'] ?? '').'/'.(string) ($doc['color_fondo'] ?? ''), '/'),
			'localidad_fmt' => $localidad !== '' ? ' Loc.:'.$localidad : '',
			'pedidos_fmt' => $pedidos !== '' ? 'PEDIDOS: '.$pedidos : '',
			'plvista' => $this->prefijoCampoOt('PLANTILLA: ', substr((string) ($doc['plvista'] ?? ''), 0, 30)),
			'serigrafia' => $this->prefijoCampoOt('SERIGRAFIA: ', substr((string) ($doc['serigrafia'] ?? ''), 0, 30)),
			'plarmado' => $this->prefijoCampoOt('PL.ARMADO: ', substr((string) ($doc['plarmado'] ?? ''), 0, 30)),
			'puntera' => $this->prefijoCampoOt('PUNTERA: ', substr((string) ($doc['puntera'] ?? ''), 0, 30)),
			'contrafuerte' => $this->prefijoCampoOt('CONTRAFUERTE: ', substr((string) ($doc['contrafuerte'] ?? ''), 0, 30)),
			'empaque' => substr((string) ($doc['empaque'] ?? ''), 0, 60),
			'empaque_fmt' => $this->prefijoCampoOt('AVIOS DE EMPAQUE: ', substr((string) ($doc['empaque'] ?? ''), 0, 60)),
			'forrado_base_fmt' => $this->prefijoCampoOt('FORRADO BASE: ', substr($forradoBase, 0, 60)),
			'forrado_fondo1' => $this->prefijoCampoOt('FORRO: ', substr($forradoFondo, 0, 80)),
			'forrado_fondo2' => substr($forradoFondo, 80, 80),
			'material2' => substr($mat, 0, 90),
			'material3' => substr($mat, 90, 90),
			'material4' => substr($mat, 180, 90),
			'aplique0' => $this->prefijoCampoOt('APLIQUES: ', substr($aplique, 0, 55)),
			'aplique1' => substr($aplique, 55, 55),
			'aplique2' => substr($aplique, 110, 55),
			'obs1' => substr($leyenda, 0, 30),
			'obs2' => substr($leyenda, 30, 30),
			'obs3' => substr($leyenda, 60, 30),
			'obs4' => substr($leyenda, 90, 30),
			'cliente0' => $lineasCortas[0] ?? '',
			'cliente1' => $lineasCortas[1] ?? '',
			'cliente2' => $this->prefijoCampoOt('CLIENTES: ', $lineasLargas[0] ?? ($lineasCortas[2] ?? '')),
			'cliente3' => $lineasLargas[1] ?? '',
			'cliente4' => $lineasLargas[2] ?? '',
			'cliente5' => $lineasLargas[3] ?? '',
			'cliente6' => $lineasLargas[4] ?? '',
		];

		foreach ($doc['medidas'] ?? [] as $medida) {
			$talle = (int) ($medida['medida'] ?? 0);
			$cant = (int) ($medida['cantidad'] ?? 0);
			if ($talle > 0 && $cant !== 0) {
				$valores['medida_'.$talle] = (string) $cant;
			}
		}

		return $valores;
	}

	private function prefijoCampoOt(string $prefijo, string $valor): string
	{
		$valor = $this->textoOVacioPreimpresoOt($valor);
		if ($valor === '') {
			return '';
		}

		return $prefijo.$valor;
	}

	/**
	 * Informix/Anita a veces graba el literal "NULL" en leyendas vacías.
	 */
	private function textoOVacioPreimpresoOt(?string $valor): string
	{
		$valor = trim((string) $valor);
		if ($valor === '' || strcasecmp($valor, 'NULL') === 0) {
			return '';
		}

		return $valor;
	}

	/**
	 * @param  list<string>  $clientes
	 * @return list<string>
	 */
	private function partirLineasClientesOt(array $clientes, int $maxLen, int $maxLineas): array
	{
		$lineas = [];
		$actual = '';
		foreach ($clientes as $i => $cli) {
			$pieza = count($clientes) > 1 ? substr($cli, 0, 15) : $cli;
			$candidato = $actual === '' ? $pieza : $actual.'/'.$pieza;
			if (strlen($candidato) > $maxLen && $actual !== '' && count($lineas) < $maxLineas) {
				$lineas[] = $actual;
				$actual = $pieza;
			} else {
				$actual = $candidato;
			}
		}
		if ($actual !== '' && count($lineas) < $maxLineas) {
			$lineas[] = $actual;
		}
		while (count($lineas) < $maxLineas) {
			$lineas[] = '';
		}

		return $lineas;
	}

	private function anchoMaxCampoPreimpresoOt(string $k): float
	{
		return match (true) {
			str_starts_with($k, 'medida_') => 10.0,
			$k === 'codigo', $k === 'tot_pares' => 25.0,
			str_starts_with($k, 'titulo_') => 55.0,
			str_starts_with($k, 'cliente'), str_starts_with($k, 'material'), str_starts_with($k, 'aplique') => 160.0,
			default => 120.0,
		};
	}

	/**
	 * @return list<string>
	 */
	private function titulosCopiaEmisionOt(string $tipoemision): array
	{
		return OrdentrabajoEmisionCopiaSupport::titulos($tipoemision);
	}

	/**
	 * Plantilla de impresión OT: PDF vía JetDirect (imprimir-pdf-laser.sh "%s" IP).
	 * No requiere colas CUPS en el L12.
	 */
	private function plantillaComandoEmisionOtPdf(?Salida $salida): ?string
	{
		if (! $salida instanceof Salida) {
			return null;
		}

		$comando = trim((string) $salida->comando);
		if ($comando === '') {
			return null;
		}

		$scriptLaser = config('ordentrabajo.imprimir_script', base_path('bin/imprimir-pdf-laser.sh'));

		if (SalidaImpresionFallbackSupport::comandoPdfCompatible($salida)) {
			// Si apunta a cola CUPS local por nombre histórico, convertir a IP JetDirect.
			$scriptCups = base_path('bin/imprimir-pedido.sh');
			if (str_starts_with($comando, $scriptCups) && preg_match('/"?%s"?\s+(\S+)/', $comando, $m) === 1) {
				$ip = $this->resolverIpJetDirectOt($m[1]);

				return $scriptLaser.' "%s" '.$ip;
			}

			return $comando;
		}

		if (preg_match('/imp_otrS?\s+%s\s+%s\s+(\S+)/i', $comando, $m) === 1) {
			$ip = $this->resolverIpJetDirectOt($m[1]);

			return $scriptLaser.' "%s" '.$ip;
		}

		return null;
	}

	/**
	 * Colas históricas Ferli → IP JetDirect (puerto 9100), según printers del host 160.132.0.254.
	 */
	private function resolverIpJetDirectOt(string $destino): string
	{
		$destino = trim($destino);

		return match (strtolower($destino)) {
			'hp-diego', 'diego' => '160.132.0.203',
			'hp4250gaby', 'gaby', 'gabriela' => '160.132.0.183',
			'p1', 'pserver', 'monica' => '160.132.0.201',
			'hp1300', 'laura' => '160.132.0.200',
			'laserjet4050' => '160.132.0.202',
			default => $destino,
		};
	}

	private function ejecutarImpresionEmisionOtEnSalida(string $plantillaComando, string $rutaPdf): ?string
	{
		$comando = sprintf(trim($plantillaComando), $rutaPdf);
		if (trim($comando) === '') {
			return 'comando de impresora vacío';
		}

		$process = Process::fromShellCommandline($comando);
		$process->setTimeout((int) config('pedido.imprimir_timeout_segundos', 90));
		$process->run();

		if ($process->isSuccessful()) {
			return null;
		}

		$detalle = trim($process->getErrorOutput());
		if ($detalle === '') {
			$detalle = trim($process->getOutput());
		}

		return $detalle !== '' ? $detalle : 'el comando de impresión falló';
	}

	/**
	 * Legacy IFPU + PostScript en host Ferli. Preferir imprimirEmisionOt / descargarPdfEmisionOt.
	 */
	public function EmisionOt(array $data)
	{
		// Arma nombre de archivo
		$nombreReporte = "tmp/emisionOT-" . Str::random(10) . '.txt';

		$ordenes = explode(',', $data['ordenestrabajo']);

		// Define cantidad de copias
		$copias = 0;
		switch($data['tipoemision'])
		{
		case 'COMPLETA': // codigo_copia 11
			$copias = 8;
			break;
		case 'STOCK':    // codigo_copia 14
			$copias = 1;
			break;
		case 'CAJA':     // codigo_copia 12
			$copias = 1;
			break;
		}

		$flImpOtAsociadas = false;
		//if (array_key_exists('otasociadas', $data))
		//{
		//	if (data['otasociadas'] == 'on')
		//		$flImpOtAsociadas = true;
		//}

		$reporte = "";
		$nroPosicionOt = 0;
		$nombreQR = '';
		foreach($ordenes as $codigo)
		{
    		$ot = $this->ordentrabajoQuery->leeOrdenTrabajoPorCodigo($codigo);

			$mventa = 0;
			$observacion = '';
			$leyendaPedido = '';
			$leyenda = '';
			$lineas = $ot?->ordentrabajoCombinacionTallesVigentes();
			$pedidoCombinacion = $ot?->pedidoCombinacionVigente();
			if ($ot && $lineas && $lineas->isNotEmpty() && $pedidoCombinacion)
			{
				// Lee articulo
			 	$articulo = $this->articuloQuery->traeArticuloPorId($pedidoCombinacion->articulo_id);

				if ($articulo)
					$mventa = $articulo->mventa_id;

				// Arma numeracion y totales
				$this->tot_pares1 = $this->tot_pares2 = $this->tot_pares3 = $this->tot_pares4 = 0;
				$totPares = 0;
				$medidas = [];
				$pedidos = [];
				foreach($lineas as $item)
				{
					$pct = $item->pedido_combinacion_talles;
					$talle = Talle::find($pct->talle_id);

					if ($talle)
					{
						$medidas[] = ['medida' => $talle->nombre, 'cantidad' => $pct->cantidad];
						
						if ($talle->nombre >= config('consprod.DESDE_INTERVALO1') && $talle->nombre <= config('consprod.HASTA_INTERVALO1'))
							$this->tot_pares1 += $pct->cantidad;

						if ($talle->nombre >= config('consprod.DESDE_INTERVALO2') && $talle->nombre <= config('consprod.HASTA_INTERVALO2'))
							$this->tot_pares2 += $pct->cantidad;

						if ($talle->nombre >= config('consprod.DESDE_INTERVALO3') && $talle->nombre <= config('consprod.HASTA_INTERVALO3'))
							$this->tot_pares3 += $pct->cantidad;

						if ($talle->nombre >= config('consprod.DESDE_INTERVALO4') && $talle->nombre <= config('consprod.HASTA_INTERVALO4'))
							$this->tot_pares4 += $pct->cantidad;
					}
					$totPares += $pct->cantidad;

					$pc = $pct->pedidos_combinacion;
					if ($pc && !in_array($pc->pedido_id, $pedidos))
						$pedidos[] = $pc->pedido_id;
					$observacion = $pc?->observacion ?? $observacion;
					$leyendaPedido = $pc?->pedidos?->leyenda ?? $leyendaPedido;
				}
				// Lee combinacion 
				$combinacion = Combinacion::find($pedidoCombinacion->combinacion_id);

				$nombreFondo = '';
				$colorFondo = '';
				$nombreForro = '';
				$colorForro = '';
				$nombreSerigrafia = '';
				$codigoCombinacion = '';
				$descripcionCombinacion = '';
				$nombrePlvista = '';
				$plvistaConConsumo = '';
				if ($combinacion)
				{
					$codigoCombinacion = $combinacion->codigo;
					$descripcionCombinacion = $combinacion->nombre;
					
					$fondo = Fondo::find($combinacion->fondo_id);	
					if ($fondo)
						$nombreFondo = $fondo->nombre;

					$color = Color::find($combinacion->colorfondo_id);	
					if ($color)
						$colorFondo = $color->nombre;

					$plvista = Plvista::find($combinacion->plvista_id);
					if ($plvista)
						$nombrePlvista = $plvista->nombre;
					$plvistaConConsumo = $this->calculaPlvista($nombrePlvista,  $combinacion->plvista_16_26, 
											$combinacion->plvista_17_33, $combinacion->plvista_34_40, 
											$combinacion->plvista_41_45);

					$forro = Forro::find($combinacion->forro_id);
					if ($forro)
						$nombreForro = $forro->nombre;

					$color = Color::find($combinacion->colorforro_id);	
					if ($color)
						$colorForro = $color->nombre;

					$serigrafia = Serigrafia::find($combinacion->serigrafia_id);
					if ($serigrafia)
						$nombreSerigrafia = $serigrafia->nombre;

					$plarmado = Plarmado::find($combinacion->plarmado_id);
					$nombrePlarmado = ' ';
					if ($plarmado)
						$nombrePlarmado = $plarmado->nombre;

					// Arma materiales de capellada
					$materialCapellada = $this->armaCapellada($combinacion->id, 
														  	$combinacion->articulo_id, 'C', false);
					$materialCapelladaConConsumo = $this->armaCapellada($combinacion->id, 
														  	$combinacion->articulo_id, 'C', true);
					$forradoFondoConConsumo = $this->armaCapellada($combinacion->id, 
														  	$combinacion->articulo_id, 'F', true);
					$forradoBaseConConsumo = $this->armaCapellada($combinacion->id, 
														  	$combinacion->articulo_id, 'B', true);
	
					// Arma avios
					$aplique = $this->armaAvio($combinacion->id, $combinacion->articulo_id, 'A');
					$empaque = $this->armaAvio($combinacion->id, $combinacion->articulo_id, 'E');
				}

				// Acumula medidas
				$medidasAcumuladas = [];
				$med = [];
				foreach($medidas as $parte)
					$med[] = $parte['medida'];
				$medUnico = array_unique($med);
				foreach($medUnico as $un)
				{
					$suma = 0;
					foreach($medidas as $original)
					{
						if ($un == $original['medida'])
							$suma += $original['cantidad'];
					}
					$medidasAcumuladas[] = ['medida' => $un, 'cantidad' => $suma];
				}
				$medidas = $medidasAcumuladas;
				
				// Arma pedidos
				$numeroPedidos = '';
				foreach($pedidos as $pedido)
					$numeroPedidos .= $pedido.' ';

				$nombreTipoCorte = '';
				$abreviaturaTipoCorte = '';
				$nombreTipoCorteForro = '';
				$nombrePuntera = '';
				$nombreContrafuerte = '';
				$numeracion = '';

				// Carga datos del articulo
				if ($articulo)
				{
					$tipocorte = Tipocorte::find($articulo->tipocorte_id);
					if ($tipocorte)
					{
						$nombreTipoCorte = $tipocorte->nombre;
						$abreviaturaTipoCorte = $tipocorte->abreviatura;
					}
	
					$tipocorte = Tipocorte::find($articulo->tipocorteforro_id);
					if ($tipocorte)
						$nombreTipoCorteForro = $tipocorte->nombre;

					$puntera = Puntera::find($articulo->puntera_id);
					if ($puntera)
						$nombrePuntera = $puntera->nombre;

					$contrafuerte = Contrafuerte::find($articulo->contrafuerte_id);
					if ($contrafuerte)
						$nombreContrafuerte = $contrafuerte->nombre;

					// Arma cajas
					$cajas = $this->armaCaja($articulo->id, $ot);

					// Arma codigo de articulo
					$sku = str_pad($articulo->sku, 13, "0", STR_PAD_LEFT);
					$codigoArticulo = substr($sku, 7, 4).'-'.substr($sku, 11, 2);
					$codigoArticuloReducido = substr($sku, 5, 2);

					$linea = Linea::select('nombre', 'codigo', 'tiponumeracion_id')->with('tiponumeraciones')->where('id',$articulo->linea_id)->first();
					if ($linea)
						$numeracion = $linea->tiponumeraciones->nombre;
				}

				// Lee items
				$clientes = [];
				$localidad_id = 0;
				$nombreVendedor = [];
				foreach ($lineas as $item)
				{
					$cliente = $item->clientes;
					if (! $cliente) {
						continue;
					}
					$suspension = $cliente->tipossuspensioncliente;
					if ((int) ($suspension?->id ?? 0) > 0)
					{
						$descCliente = substr($cliente->nombre,0,20).' '.substr((string) $suspension->nombre, 0, 8);
					}
					else
						$descCliente = $cliente->nombre;

					if (!in_array($descCliente, $clientes))
					{
						$clientes[] = $descCliente;
						$localidad_id = $cliente->localidad_id;

						// Lee el vendedor
						$clicomi = $this->cliente_comisionQuery->traeVendedor($cliente->codigo, $mventa);

						if ($clicomi)
						{
							$codigoVendedor = $clicomi[0]->clico_vendedor;
							$nombreVendedor = $clicomi[0]->vend_nombre;
						}
					}
				}

				// Lee localidad
				$localidad = Localidad::find($localidad_id);
				$nombreLocalidad = "";
				if ($localidad)
					$nombreLocalidad = $localidad->nombre;

				$leyenda = $ot->leyenda;
			}
			
			$leyenda .= " ".$observacion." ".$leyendaPedido;

			// Genera QR
			if ($data['tipoemision'] != 'COMPLETA')
			{
				if ($nombreQR != '')
					$nombreQR .= ';';

				$nombreQR .= 'ot-'.$codigo.'.svg';
				QrCode::size(400)->generate((string) $codigo, $nombreQR);
			}
			else
			{
				$nombreQR = 'ot-'.$codigo.'.svg';
				QrCode::size(400)->generate((string) $codigo, $nombreQR);
			}

			$nroPosicionOt++;
			for ($copia = 1; $copia <= $copias; $copia++)
			{
				self::DefineFormulario($data['tipoemision'], $copia, $flImpOtAsociadas, $mventa, $formulario, $numeracion);

				if ($formulario != '')
				{
					if ($copia > 1)
					{
						$reporte .= "printform\n";
						$this->listaOt($reporte, $nombreQR, $data['tipoemision']);
						$reporte = '';
					}

        			$reporte .= "#ifpu2.0"."\n";
        			$reporte .= "set formpath ../spool/forms"."\n";
        			$reporte .= "set form ".$formulario."\n";
				}

				// Arma variables de impresion
				$fin = "---------------@";
				$posicion = 0;

				if ($mventa == 4 && $data['tipoemision'] != 'STOCK')
				{
					if ($copia > 4 && $copia <= 8)
						$posicion = $copia - 4;
					else
						if ($copia > 8 && $copia <= 12)
							$posicion = $copia - 8;
						else
							if ($copia > 12)
								$posicion = $copia - 12;
							else
								$posicion = $copia;
				}
				else
					$posicion = $copia;

				if ($data['tipoemision'] != 'COMPLETA')
					$d_copia = "tag @titulo_copia".'11'.$nroPosicionOt."---------------@";
				else
					$d_copia = "tag @titulo_copia".sprintf("%02d", $posicion).'-'.$fin;

				// Trae el titulo de la copia
				$copiaot = Copiaot::traeCopia($data['tipoemision']);
				$tituloCopia = '';

				if ($copiaot && $copia <= count($copiaot))
					$tituloCopia = $copiaot[$copia-1];

        		$reporte .= $d_copia.' {'.$tituloCopia.'}'."\n";

				// Imprime clientes
				if ($data['tipoemision'] != 'COMPLETA')
				{
					$d_cli[0] = "tag @cliente".$nroPosicionOt."----------------------------------------------------@ ";
					$d_cli[1] = "tag @cliente1".$nroPosicionOt."---------------------------------------------------@ ";
				}
				else
				{
					$d_cli[0] = "tag @cliente-----------------------------------------------------@ ";
					$d_cli[1] = "tag @cliente1----------------------------------------------------@ ";
				}
				$d_cli[2] = "tag @CLIENTES:-cliente2------------------------------------------------------------------------@ ";

				// Arma impresion de clientes
				$cliStr = "";
				$pos = 0;

				if (isset($clientes))
				{
					for ($i = 0; $i < count($clientes); $i++)
					{
						if (strlen($cliStr.$clientes[$i]) > 60 && $pos < 3)
						{
							$reporte .= $d_cli[$pos].'{'.$cliStr.'}'."\n";
							$cliStr = "";
							$pos++;
						}

						if ($i > 0 && $cliStr != "")
							$cliStr .= '/';

						if (count($clientes) > 1)
							$cliStr .= substr($clientes[$i], 0, 15);
						else
							$cliStr .= $clientes[$i];
					}
				}

				// Imprime linea faltante
				if ($cliStr != "" && $pos < 3)
				{
					$reporte .= $d_cli[$pos].'{'.$cliStr.'}'."\n";
					$pos++;
				}
				// Completa clientes
				while ($pos < 3)
				{
					$reporte .= $d_cli[$pos].'{'.' '.'}'."\n";
					$pos++;
				}

				// Imprime clientes formato grande
				if ($data['tipoemision'] != 'COMPLETA')
				{
					$d_cli[0] =  "tag @cliente2".$nroPosicionOt."-------------------------------------------------------------------------@ ";
					$d_cli[1] =  "tag @cliente3".$nroPosicionOt."-------------------------------------------------------------------------@ ";
					$d_cli[2] =  "tag @cliente4".$nroPosicionOt."-------------------------------------------------------------------------@ ";
					$d_cli[3] =  "tag @cliente5".$nroPosicionOt."-------------------------------------------------------------------------@ ";
					$d_cli[4] =  "tag @cliente6".$nroPosicionOt."-------------------------------------------------------------------------@ ";
				}
				else
				{
					$d_cli[0] =  "tag @cliente2------------------------------------------------------------------------@ ";
					$d_cli[1] =  "tag @cliente3------------------------------------------------------------------------@ ";
					$d_cli[2] =  "tag @cliente4------------------------------------------------------------------------@ ";
					$d_cli[3] =  "tag @cliente5------------------------------------------------------------------------@ ";
					$d_cli[4] =  "tag @cliente6------------------------------------------------------------------------@ ";
				}

				// Arma impresion de clientes
				$cliStr = "";
				$pos = 0;

				if (isset($clientes))
				{
					for ($i = 0; $i < count($clientes); $i++)
					{
						if (strlen($cliStr.$clientes[$i]) > 80 && $pos < 5)
						{
							$reporte .= $d_cli[$pos].'{'.$cliStr.'}'."\n";
							$cliStr = "";
							$pos++;
						}

						if ($i > 0 && $cliStr != "")
							$cliStr .= '/';

						if (count($clientes) > 1)
							$cliStr .= substr($clientes[$i], 0, 15);
						else
							$cliStr .= $clientes[$i];
					}
				}

				// Imprime linea faltante
				if ($cliStr != "" && $pos < 5)
				{
					$reporte .= $d_cli[$pos].'{'.$cliStr.'}'."\n";
					$pos++;
				}
				// Completa clientes
				while ($pos < 5)
				{
					$reporte .= $d_cli[$pos].'{'.' '.'}'."\n";
					$pos++;
				}

				// Imprime localidad
				if ($data['tipoemision'] != 'COMPLETA')
					$d_loc = "tag @localidad".$nroPosicionOt."----------@ ";
				else
					$d_loc = "tag @localidad-----------@ ";
				$reporte .= $d_loc.'{ Loc.:'.$nombreLocalidad.'}'."\n";

				// Imprime pares
				if ($data['tipoemision'] != 'COMPLETA')
					$d_pares = "tag @pares".$nroPosicionOt."@ ";
				else
					$d_pares = "tag @pares-@ ";

				$reporte .= $d_pares.'{'.$totPares.'}'."\n";

				// Tipo corte
				$d_abrevtipocorte = "tag @abrevc@";
				$d_tipocorte = "tag @tipo_corte----------@ ";
				$reporte .= $d_tipocorte.'{'.$nombreTipoCorte.'}'."\n";
				$reporte .= $d_abrevtipocorte.'{'.$abreviaturaTipoCorte.'}'."\n";

				// Tipo corte forro
				$d_tipocorteforro = "tag @tipo_corte_forro----@ ";
				$reporte .= $d_tipocorteforro.'{'.$nombreTipoCorteForro.'}'."\n";

				if ($data['tipoemision'] != 'COMPLETA')
					$d_vendedor = "tag @vendedor".$nroPosicionOt."---------------------@ ";
				else
					$d_vendedor = "tag @vendedor----------------------@ ";
				if ($nombreVendedor)
					$reporte .= $d_vendedor.'{'.$nombreVendedor.'}'."\n";
				else
					$reporte .= $d_vendedor.'{'.' '.'}'."\n";
				
				if ($data['tipoemision'] != 'COMPLETA')
				{
					$d_obs[0] = "tag @obs1".$nroPosicionOt."-------------------------@ ";
					$d_obs[1] = "tag @obs2".$nroPosicionOt."-------------------------@ ";
					$d_obs[2] = "tag @obs3".$nroPosicionOt."-------------------------@ ";
					$d_obs[3] = "tag @obs4".$nroPosicionOt."-------------------------@ ";
				}
				else
				{
					$d_obs[0] = "tag @obs1--------------------------@ ";
					$d_obs[1] = "tag @obs2--------------------------@ ";
					$d_obs[2] = "tag @obs3--------------------------@ ";
					$d_obs[3] = "tag @obs4--------------------------@ ";
				}

				$reporte .= $d_obs[0].'{'.substr($leyenda, 0, 30).'}'."\n";
				$reporte .= $d_obs[1].'{'.substr($leyenda, 30, 30).'}'."\n";
				$reporte .= $d_obs[2].'{'.substr($leyenda, 60, 30).'}'."\n";
				$reporte .= $d_obs[3].'{'.substr($leyenda, 90, 30).'}'."\n";

				$d_articulo = "tag @articulo@ ";
				$reporte .= $d_articulo.'{'.$codigoArticulo.'}'."\n";

				if ($data['tipoemision'] != 'COMPLETA')
					$d_cod_art = "tag @art".$nroPosicionOt."----@ ";
				else
					$d_cod_art = "tag @art".sprintf("%02d", $posicion)."---@ ";
				$reporte .= $d_cod_art.'{'.$codigoArticulo.'}'."\n";

				$d_cod_art = "tag @cod_art@ ";
				$reporte .= $d_cod_art.'{'.$codigoArticuloReducido.'}'."\n";

				if ($data['tipoemision'] != 'COMPLETA')
					$d_cod_art = "tag @Cod.Art.:-cod_art".$nroPosicionOt."@ ";
				else
					$d_cod_art = "tag @Cod.Art.:-cod_art".sprintf("%01d", $posicion)."-@ ";
				$reporte .= $d_cod_art.'{'.'Cod.Art.:'.$codigoArticuloReducido.'}'."\n";

				$d_color_fondo = "tag @color_fondo--------------------@ ";
				$reporte .= $d_color_fondo.'{'.$colorFondo.'}'."\n";
				
				if ($data['tipoemision'] != 'COMPLETA')
					$d_combinacion = "tag @combinacion".$nroPosicionOt."------------------------------------------------@ ";
				else
					$d_combinacion = "tag @combinacion-------------------------------------------------@ ";
				$reporte .= $d_combinacion.'{'.$codigoCombinacion.' '.$descripcionCombinacion.'}'."\n";

				if ($data['tipoemision'] != 'COMPLETA')
					$d_fondo = "tag @fondo".$nroPosicionOt."------------------------------------------------------@ ";
				else
					$d_fondo = "tag @fondo-------------------------------------------------------@ ";
				$reporte .= $d_fondo.'{'.$nombreFondo.'}'."\n";

				$d_color_fondo = "tag @fondo_color-------------------------------------------------@ ";
				$reporte .= $d_color_fondo.'{'.$nombreFondo.'/'.$colorFondo.'}'."\n";

				$d_material[0] = "tag @material----------------------------------------------------@ ";
				$reporte .= $d_material[0].'{'.substr($materialCapellada,0,60).'}'."\n";

				$d_material[1] = "tag @material1---------------------------------------------------@ ";
				$reporte .= $d_material[1].'{'.substr($materialCapellada,60,60).'}'."\n";

				$d_material[2] = "tag @material2---------------------------------------------------------------------------------@ ";
				$reporte .= $d_material[2].'{'.substr($materialCapelladaConConsumo,0,90).'}'."\n";

				$d_material[3] = "tag @material3---------------------------------------------------------------------------------@ ";
				$reporte .= $d_material[3].'{'.substr($materialCapelladaConConsumo,90,90).'}'."\n";

				$d_material[4] = "tag @material4---------------------------------------------------------------------------------@ ";
				$reporte .= $d_material[4].'{'.substr($materialCapelladaConConsumo,180,90).'}'."\n";

				$d_forradofondo[0] = "tag @forrado_fondo1------------------------------------------------------------------@ ";
				$reporte .= $d_forradofondo[0].'{'.substr($forradoFondoConConsumo,0,80).'}'."\n";

				$d_forradofondo[1] = "tag @forrado_fondo2------------------------------------------------------------------@ ";
				$reporte .= $d_forradofondo[1].'{'.substr($forradoFondoConConsumo,80,80).'}'."\n";

				if ($data['tipoemision'] != 'COMPLETA')
					$d_forradobase = "tag @forrado_base".$nroPosicionOt."-----------------------------------------------@ ";
				else
					$d_forradobase = "tag @forrado_base------------------------------------------------@ ";
				$reporte .= $d_forradobase.'{'.'FORRADO BASE: '.substr($forradoBaseConConsumo,0,60).'}'."\n";
				$d_aplique[0] = "tag @aplique----------------------------------------------------------@ ";
				$reporte .= $d_aplique[0].'{'.'APLIQUES: '.substr($aplique,0,55).'}'."\n";

				$d_aplique[1] = "tag @aplique2---------------------------------------------------------@ ";
				$reporte .= $d_aplique[1].'{'.substr($aplique,55,55).'}'."\n";

				$d_aplique[2] = "tag @aplique3---------------------------------------------------------@ ";
				$reporte .= $d_aplique[2].'{'.substr($aplique,110,55).'}'."\n";

				$d_avio = "tag @avio_empaque------------------------------------------------@ ";
				$reporte .= $d_avio.'{'.substr($empaque,0,60).'}'."\n";

				$d_plvista = "tag @plantilla---------------------@ ";
				$reporte .= $d_plvista.'{'.substr($plvistaConConsumo,0,30).'}'."\n";

				//$d_forro = "tag @forro-------------------------@ ";
				//$reporte .= $d_forro.'{'.rtrim(substr($nombreForro,0,15),' ').'/'.rtrim(substr($colorForro,0,15),' ').'}'."\n";

				$d_serigrafia = "tag @serigrafia--------------------@ ";
				$reporte .= $d_serigrafia.'{'.substr($nombreSerigrafia,0,30).'}'."\n";

				$d_plarmado = "tag @plantilla_armado--------------@ ";
				$reporte .= $d_plarmado.'{'.substr($nombrePlarmado,0,30).'}'."\n";

				$d_puntera = "tag @puntera-----------------------@ ";
				$reporte .= $d_puntera.'{'.substr($nombrePuntera,0,30).'}'."\n";

				$d_contrafuerte = "tag @contrafuerte------------------@ ";
				$reporte .= $d_contrafuerte.'{'.substr($nombreContrafuerte,0,30).'}'."\n";

				if ($data['tipoemision'] != 'COMPLETA')
					$d_pedido = "tag @pedido".$nroPosicionOt."---------------------------------@ ";
				else
					$d_pedido = "tag @pedido----------------------------------@ ";
				$reporte .= $d_pedido.'{'.'PEDIDOS: '.$numeroPedidos.'}'."\n";

				if ($data['tipoemision'] != 'COMPLETA')
					$d_nro_ot = "tag @nro_ot".$nroPosicionOt."-@ ";
				else
					$d_nro_ot = "tag @nro_ot--@ ";
				$reporte .= $d_nro_ot.'{'.$ot->codigo.'}'."\n";

				if ($data['tipoemision'] != 'COMPLETA')
					$d_fecha = "tag @fecha".$nroPosicionOt."--@ ";
				else
					$d_fecha = "tag @fecha---@ ";
				$reporte .= $d_fecha.'{'.date('d-m-Y', strtotime($ot->fecha)).'}'."\n";

				// Imprime cajas
				if ($copia == 9)
				{
					$d_CA = "tag @CA------@ ";
					$reporte .= $d_CA.'{'.'CANTIDAD'.'}'."\n";

					$d_CO = "tag @CO------@ ";
					$reporte .= $d_CO.'{'.'CODIGO'.'}'."\n";

					$pos = 1;
					for($caja = 0; $caja < count($cajas); $caja++)
					{
						$d_ca = "tag @ca".$pos."--@ ";
						$reporte .= $d_ca.'{'.$cajas[$caja]['cantidad'].'}'."\n";

						$d_co = "tag @co".$pos."--@ ";
						$reporte .= $d_co.'{'.$cajas[$caja]['descripcion'].'}'."\n";
						$pos++;
					}
					// Completa posiciones
					for ($ii = $pos; $ii <= 3; $ii++)
					{
						$d_ca = "tag @ca".$pos."--@ ";
						$reporte .= $d_ca.'{'.' '.'}'."\n";

						$d_co = "tag @co".$pos."--@ ";
						$reporte .= $d_co.'{'.' '.'}'."\n";
						$pos++;
					}
				}

				// Imprime medidas
				if ($copia == 1 || $copia == 5 || $copia == 9 ||
					$data['tipoemision'] == 'STOCK' || $data['tipoemision'] == 'CAJA')	
				{
					foreach ($medidas as $key => $valor)
					{
						//dd($numeracion.' '.$data['tipoemision']);
						if ($numeracion == 'CHICO')
						{
							if ($data['tipoemision'] != 'COMPLETA')
							{
								$d_med = "tag @".chr(97+$valor['medida']-config('consprod.DESDE_INTERVALO1')-2).$nroPosicionOt."@ ";
								$reporte .= $d_med.'{'.number_format($valor['cantidad'],0).'}'."\n";

								$d_med = "tag @".sprintf("%01d", $valor['medida'])."@ ";
								$reporte .= $d_med.'{'.number_format($valor['cantidad'],0).'}'."\n";
							}
							else
							{
								$d_med = "tag @".chr(97+$valor['medida']-config('consprod.HASTA_INTERVALO1')).sprintf("%01d", $posicion)."@ ";
								$reporte .= $d_med.'{'.number_format($valor['cantidad'],0).'}'."\n";
								$d_med = "tag @".sprintf("%02d", $key).chr(97+$posicion-1)."@ ";
								$reporte .= $d_med.'{'.number_format($valor['cantidad'],0).'}'."\n";
								$d_med = "tag @".sprintf("%01d", $valor['medida'])."@ ";
								$reporte .= $d_med.'{'.number_format($valor['cantidad'],0).'}'."\n";
							}
						}
						else 
						{
							//dd($mventa.' '.$numeracion.' '.$valor['medida']);
							$d_med = "tag @".chr(97+$valor['medida']-config('consprod.HASTA_MEDIDA_CHICO')).sprintf("%01d", $valor['medida'])."@ ";
							$reporte .= $d_med.'{'.number_format($valor['cantidad'],0).'}'."\n";
							$d_med = "tag @".sprintf("%02d", $valor['medida']).chr(97+$posicion-1)."@ ";
							$reporte .= $d_med.'{'.number_format($valor['cantidad'],0).'}'."\n";
							if ($mventa == 5 && $numeracion != 'DAMA')
								$d_med = "tag @".sprintf("%02d", $valor['medida']+1-
									config('consprod.DESDE_MEDIDA_TOMAHAWK'))."@ ";
							else
								$d_med = "tag @".sprintf("%01d", $valor['medida'])."@ ";
							$reporte .= $d_med.'{'.number_format($valor['cantidad'],0).'}'."\n";

							if ($data['tipoemision'] != 'COMPLETA')
							{
								if ($mventa == 5 && $numeracion != 'DAMA')
									$d_med = "tag @".chr(97+$valor['medida']-39).$nroPosicionOt."@ ";
								else
									$d_med = "tag @".chr(97+$valor['medida']-config('consprod.HASTA_MEDIDA_CHICO')).$nroPosicionOt."@ ";
								$reporte .= $d_med.'{'.number_format($valor['cantidad'],0).'}'."\n";
							}
						}
					}

					/* Completa medidas */
					for ($ii = config('consprod.DESDE_INTERVALO1'); $ii <= config('consprod.HASTA_INTERVALO4'); $ii++)
					{
						/* Busca si existe medida */
						for ($jj = 0, $_flEncontro = false; $jj < count($medidas); $jj++)
						{
							if ($ii == $medidas[$jj]['medida'])
							{
								$_flEncontro = true;
								break;
							}
						}
						if (!$_flEncontro)
						{
							if ($numeracion == 'CHICO')
							{
								if ($data['tipoemision'] != 'COMPLETA')
								{
									$d_med = "tag @".chr(97+$ii-config('consprod.DESDE_INTERVALO1')).$nroPosicionOt."@ ";
									$reporte .= $d_med.'{'.' '.'}'."\n";
									$d_med = "tag @".sprintf("%01d", $ii)."@ ";
									$reporte .= $d_med.'{'.' '.'}'."\n";
								}
								else
								{
									$d_med = "tag @".chr(97+$ii-config('consprod.HASTA_INTERVALO1')).sprintf("%01d", $posicion)."@ ";
									$reporte .= $d_med.'{'.' '.'}'."\n";
									$d_med = "tag @".sprintf("%02d", $ii).chr(97+$posicion-1)."@ ";
									$reporte .= $d_med.'{'.' '.'}'."\n";
									$d_med = "tag @".sprintf("%01d", $ii)."@ ";
									$reporte .= $d_med.'{'.' '.'}'."\n";
								}
							}
							else
							{
								$d_med = "tag @".chr(97+$ii-config('consprod.HASTA_MEDIDA_CHICO')).sprintf("%01d", $ii)."@ ";
								$reporte .= $d_med.'{'.' '.'}'."\n";
								$d_med = "tag @".sprintf("%02d", $ii).chr(97+$posicion-1)."@ ";
								$reporte .= $d_med.'{'.' '.'}'."\n";
								if ($mventa == 5 && $numeracion != 'DAMA')
									$d_med = "tag @".sprintf("%01d", $ii+1-config('consprod.DESDE_MEDIDA_TOMAHAWK'))."@ ";
								else
									$d_med = "tag @".sprintf("%01d", $ii)."@ ";
								$reporte .= $d_med.'{'.' '.'}'."\n";

								if ($data['tipoemision'] != 'COMPLETA')
								{
									if ($mventa == 5 && $numeracion != 'DAMA')
										$d_med = "tag @".chr(97+$ii-39).$nroPosicionOt."@ ";
									else
										$d_med = "tag @".chr(97+$ii-config('consprod.HASTA_MEDIDA_CHICO')).$nroPosicionOt."@ ";
									$reporte .= $d_med.'{'.' '.'}'."\n";									
								}
							}
						}
					}
				}
			}
			if ($data['tipoemision'] != 'COMPLETA')
				$this->imprimeOtEnBlanco($reporte, $numeracion, $nroPosicionOt);

			$reporte .= "printform\n";
			if ($data['tipoemision'] != 'COMPLETA')
			{
				// $nombreQR .= $nombreQR . ';STOCK';
			}
			//dd($reporte);
			$this->listaOt($reporte, $nombreQR, $data['tipoemision']);
		}

        return redirect()->back()->with('status','Las ordenes seleccionadas no existen');
    }

	private function imprimeOtEnBlanco(&$reporte, $numeracion, $nroPosicionOt)
	{
		for ($copia = $nroPosicionOt+1; $copia <= 4; $copia++)
		{
			$d_copia = "tag @titulo_copia".'11'.$copia."---------------@";
			$reporte .= $d_copia.' {'.' '.'}'."\n";

			// Imprime clientes
			$d_cli[0] = "tag @cliente".$copia."----------------------------------------------------@ ";
			$d_cli[1] = "tag @cliente1".$copia."---------------------------------------------------@ ";
			$d_cli[2] = "tag @cliente2".$copia."-------------------------------------------------------------------------@ ";
			
			$reporte .= $d_cli[0].'{'.' '.'}'."\n";
			$reporte .= $d_cli[1].'{'.' '.'}'."\n";
			$reporte .= $d_cli[2].'{'.' '.'}'."\n";

			// Imprime localidad
			$d_loc = "tag @localidad".$copia."----------@ ";
			$reporte .= $d_loc.'{  }'."\n";

			// Imprime pares
			$d_pares = "tag @pares".$copia."@ ";
			$reporte .= $d_pares.'{ }'."\n";

			$d_vendedor = "tag @vendedor".$copia."---------------------@ ";
			$reporte .= $d_vendedor.'{'.' '.'}'."\n";
				
			$d_obs[0] = "tag @obs1".$copia."-------------------------@ ";
			$d_obs[1] = "tag @obs2".$copia."-------------------------@ ";
			$d_obs[2] = "tag @obs3".$copia."-------------------------@ ";
			$d_obs[3] = "tag @obs4".$copia."-------------------------@ ";

			$reporte .= $d_obs[0].'{'.' '.'}'."\n";
			$reporte .= $d_obs[1].'{'.' '.'}'."\n";
			$reporte .= $d_obs[2].'{'.' '.'}'."\n";
			$reporte .= $d_obs[3].'{'.' '.'}'."\n";

			$d_cod_art = "tag @art".$copia."----@ ";
			$reporte .= $d_cod_art.'{ }'."\n";

			$d_cod_art = "tag @Cod.Art.:-cod_art".$copia."@ ";
			$reporte .= $d_cod_art.'{ }'."\n";
			$d_combinacion = "tag @combinacion".$copia."------------------------------------------------@ ";
			$reporte .= $d_combinacion.'{ }'."\n";
			$d_fondo = "tag @fondo".$copia."------------------------------------------------------@ ";
			$reporte .= $d_fondo.'{ }'."\n";

			$d_forradobase = "tag @forrado_base".$copia."-----------------------------------------------@ ";
			$reporte .= $d_forradobase.'{'.' '.'}'."\n";			
			
			$d_pedido = "tag @pedido".$copia."---------------------------------@ ";
			$reporte .= $d_pedido.'{'.' '.'}'."\n";
			$d_nro_ot = "tag @nro_ot".$copia."-@ ";
			$reporte .= $d_nro_ot.'{'.' '.'}'."\n";
			$d_fecha = "tag @fecha".$copia."--@ ";
			$reporte .= $d_fecha.'{'.' '.'}'."\n";

			/* Busca si existe medida */
			for ($ii = config('consprod.DESDE_INTERVALO1'); $ii <= config('consprod.HASTA_INTERVALO4'); $ii++)
			{
				if ($numeracion == 'CHICO')
				{
					$d_med = "tag @".chr(97+$ii-config('consprod.DESDE_INTERVALO1')).$copia."@ ";
					$reporte .= $d_med.'{'.' '.'}'."\n";
				}
				else
				{
					$d_med = "tag @".chr(97+$ii-config('consprod.HASTA_MEDIDA_CHICO')).$copia."@ ";
					$reporte .= $d_med.'{'.' '.'}'."\n";									
				}
			}
		}
	}

	private function DefineFormulario($tipoemision, $copia, $flImpOtAsociadas, $marca, &$formulario, $numeracion)
	{
		$formulario = '';
		if ($flImpOtAsociadas)
		{
			switch($marca)
			{
			case 1:
				$formulario = 'otferliasoc.ps';
				break;
			case 4:
				$formulario = 'otboaondaasoc.ps';
				break;
			default:
				$formulario = 'otfragolaasoc.ps';
				break;
			}
		}
		else
		{
			switch($tipoemision)
			{
			case 'COMPLETA': // codigo_copia 11
				switch($copia)
				{
				case 1:
					switch($marca)
					{
					case 1:
						$formulario = 'otferli.ps';
						break;
					case 4:
						$formulario = 'otboaonda.ps';
						break;
					default:
						if ($numeracion === "CHICO")
							$formulario = 'otferli.ps';
						else
							$formulario = 'otfragola.ps';
						break;
					}
					break;
				case 5:
					switch($marca)
					{
					case 1:
						$formulario = 'otferli2.ps';
						break;
					case 4:
						$formulario = 'otboaonda.ps';
						break;
					default:
						if ($numeracion === "CHICO")
							$formulario = 'otferli2.ps';
						else
							$formulario = 'otfragola2.ps';
						break;
					}
					break;
				}
				break;
			case 'STOCK':    // codigo_copia 14
				switch($marca)
				{
				case 1:
					$formulario = 'otferli11.ps';
					break;
				case 4:
					$formulario = 'otboaonda11.ps';
					break;
				default:
					if ($numeracion === "CHICO")
						$formulario = 'otferli11.ps';
					else
						$formulario = 'otfragola11.ps';
					break;
				}
				break;
			case 'CAJA':     // codigo_copia 12
				switch($marca)
				{
				case 1:
					$formulario = 'otferli9.ps';
					break;
				case 4:
					$formulario = 'otboaonda.ps';
					break;
				default:
					$formulario = 'otfragola9.ps';
					break;
				}
				break;
			}
		}
	}

	private function armaCapellada($combinacion_id, $articulo_id, $tipoMaterial, $flConsumo)
	{
    	$capeart = Capeart::where('combinacion_id', $combinacion_id)
        					->where('articulo_id', $articulo_id)->get();
		$strSalida = '';
				
		foreach($capeart as $itemMaterial)
		{
			if ($itemMaterial->tipo == $tipoMaterial)
			{
				// Lee el material
				$descripcionMaterial = '';
			 	$material = MaterialCapellada::find($itemMaterial->material_id);
				if ($material)
					$descripcionMaterial = rtrim(substr($material->nombre,0,15),' ');
			 	//$material = Articulo::find($itemMaterial->material_id);
				//if ($material)
				//	$descripcionMaterial = rtrim(substr($material->descripcion,0,15),' ');

				// Lee el color
				$descripcionColor = '';
				$color = Color::find($itemMaterial->color_id);
				if ($color)
					$descripcionColor = rtrim(substr($color->nombre,0,15),' ');

				$consumo = ($itemMaterial->consumo1*$this->tot_pares1) +
							($itemMaterial->consumo2*$this->tot_pares2) +
							($itemMaterial->consumo3*$this->tot_pares3) +
							($itemMaterial->consumo4*$this->tot_pares4);
						
				$piezas = rtrim(substr($itemMaterial->piezas,0,15),' ');

				if ($strSalida)
					$strSalida .= '/';
				if ($flConsumo)
					$_str = $piezas.' '.$descripcionMaterial.' '.$descripcionColor.' -'.
							number_format($consumo,2).'- '.$itemMaterial->tipocalculo;
				else
					$_str = $piezas.' '.$descripcionMaterial.' '.$descripcionColor.' '.
							$itemMaterial->tipocalculo;
				$strSalida .= $_str;
			}
		}
		return $strSalida;
	}

	private function armaAvio($combinacion_id, $articulo_id, $tipoMaterial)
	{
    	$avioart = Avioart::where('combinacion_id', $combinacion_id)
        					->where('articulo_id', $articulo_id)->get();
		$strSalida = '';
		foreach($avioart as $itemMaterial)
		{
			if ($itemMaterial->tipo == $tipoMaterial)
			{
				// Lee el articulo
				$descripcionMaterial = '';
				$material = MaterialAvio::find($itemMaterial->material_id);
				if ($material)
					$descripcionMaterial = rtrim(substr($material->nombre,0,15),' ');
			 	//$material = Articulo::find($itemMaterial->material_id);
				//if ($material)
				//	$descripcionMaterial = rtrim(substr($material->descripcion,0,15),' ');

				// Lee el color
				$descripcionColor = '';
				$color = Color::find($itemMaterial->color_id);
				if ($color)
					$descripcionColor = rtrim(substr($color->nombre,0,15),' ');

				$consumo = ($itemMaterial->consumo1*$this->tot_pares1) +
							($itemMaterial->consumo2*$this->tot_pares2) +
							($itemMaterial->consumo3*$this->tot_pares3) +
							($itemMaterial->consumo4*$this->tot_pares4);

				if ($strSalida)
					$strSalida .= '/';
				$_str = $descripcionMaterial.' '.$descripcionColor.' '.number_format($consumo,2);
				$strSalida .= $_str;
			}
		}
		return $strSalida;
	}

	private function armaCaja($articulo_id, $ot)
	{
    	$articulocaja = Articulo_Caja::where('articulo_id', $articulo_id)->get();
		$arrayCajas = [];
		foreach($articulocaja as $itemCaja)
		{
			// Lee la caja
			$caja = Caja::find($itemCaja->caja_id);
			$cantidad = 0;

			if ($caja)
			{
				$descripcionArticulo = '';
				$articulo = $this->articuloQuery->traeArticuloPorId($caja->articulo_id);
				if ($articulo)
					$descripcionArticulo = $articulo->descripcion;

				foreach ($ot->ordentrabajoCombinacionTallesVigentes() as $item)
				{
					$pct = $item->pedido_combinacion_talles;
					$talle = Talle::find($pct->talle_id);

					if ($talle)
					{
						if ($caja->desdenro <= $talle->nombre && $caja->hastanro >= $talle->nombre)
							$cantidad += $pct->cantidad;
					}
				}
				$arrayCajas[] = ['descripcion' => $descripcionArticulo, 'cantidad' => $cantidad];
			}
		}
		return $arrayCajas;
	}

	private function calculaPlvista($nombrePlvista, $consumo1, $consumo2, $consumo3, $consumo4)
	{
		$consumo = ($consumo1*$this->tot_pares1) +
					($consumo2*$this->tot_pares2) +
					($consumo3*$this->tot_pares3) +
					($consumo4*$this->tot_pares4);

		$strSalida = $nombrePlvista.'-'.number_format($consumo,2).'-';

		return $strSalida;
	}

	private function listaOt($reporte, $nombreQR, $tipoEmision)
	{
		// Arma nombre de archivo
		$nombreReporte = "tmp/emisionOT-" . Str::random(10) . '.txt';

		Storage::disk('local')->put($nombreReporte, $reporte);
		$path = Storage::path($nombreReporte);

		//$cmd = "./bin/imp_otr ".$path." ".$nombreQR." hp-diego";

		$usuario_id = Auth::user()->id;
        $seteosalida = $this->seteoSalidaRepository->buscaSeteo($usuario_id, SeteoSalidaProgramaSupport::VENTAS_REPEMISIONOT);

		$cmd = sprintf($seteosalida->salidas->comando, $path, $nombreQR);
		//system($comando);

		if ($tipoEmision == 'STOCK')
			$cmd = $cmd.' STOCK';

		$process = new Process($cmd);
		$process->run();
		if (!$process->isSuccessful()) {
	   		throw new ProcessFailedException($process);
		}
    	//echo $process->getOutput();

		Storage::disk('local')->delete($nombreReporte);
	}

	// Genera datos para reporte de estado de OT

	public function generaDatosRepEstadoOt($desdefecha, $hastafecha, $ordenestrabajo)
	{
		//$data = $this->ordentrabajo_tareaRepository->findPorRangoFecha($desdefecha, $hastafecha, $ordenestrabajo);
		$data = $this->ordentrabajo_tareaRepository->findReporteEstadoOt($desdefecha, $hastafecha, $ordenestrabajo);
		$tareas = $this->tareaRepository->all();

		return(['data' => $data, 'tareas' => $tareas]);
	}

	// Genera datos para reporte de total de pares

	public function generaDatosRepTotalPares($desdefecha, $hastafecha, $ordenestrabajo, $apertura)
	{
		$data = $this->ordentrabajo_tareaRepository->agrupaPorFechaTarea($desdefecha, $hastafecha, $apertura, $ordenestrabajo);
		$tareas = $this->tareaRepository->all();
		return(['data' => $data, 'tareas' => $tareas]);
	}

	// Genera datos para reporte liquidacion de tareas

	public function generaDatosRepLiquidacionTarea($estadoot, 
													$desdefecha, $hastafecha, 
													$desdetarea_id, $hastatarea_id,
													$desdecliente_id, $hastacliente_id,
													$desdearticulo, $hastaarticulo,
													$desdeempleado_id, $hastaempleado_id)
	{
		$data = $this->ordentrabajo_tareaRepository->findTareaPorRangos($estadoot, $desdefecha, $hastafecha,
																	$desdetarea_id, $hastatarea_id,
																	$desdecliente_id, $hastacliente_id,
																	$desdearticulo, $hastaarticulo,
																	$desdeempleado_id, $hastaempleado_id);

		return ($data);
	}		

	// Genera datos para reporte consumo de OT

	public function generaDatosRepConsumoOt($desdefecha, $hastafecha, $ordenestrabajo)
	{
		ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '2400');
		
		$data = $this->ordentrabajoQuery->findConsumoOt($desdefecha, $hastafecha, $ordenestrabajo);

		$dataCapellada = [];
		$dataAvio = [];
		foreach($data['datacapellada'] as $material)
		{
			// Calcula el consumo capellada
			$consumoCapellada = 0;
			calculaConsumo($consumoCapellada, $material['nombretalle'], $material['cantidadportalle'], 
							$material['consumocapellada1'], $material['consumocapellada2'], 
							$material['consumocapellada3'], $material['consumocapellada4']);

			if ($consumoCapellada > 0)
			{
				$tipoMaterial = 'Capellada';
				switch($material['tipomaterial'])
				{
				case 'C':
					$tipoMaterial = 'Capellada';
					break;
				case 'B':
           			$tipoMaterial = 'Base';
					break;
				case 'F':
           			$tipoMaterial = 'Forro';
					break;
				}
				
				$dataCapellada[] = ['nombrematerial' => $tipoMaterial.' '.$material['nombrematerialcapellada'].' '.$material['nombrecolorcapellada'],
									'consumo' => $consumoCapellada];
			}
		}

		$dataAvio = [];
		foreach($data['dataavio'] as $material)
		{
			// Calcula el consumo capellada
			$consumoAvio = 0;
			calculaConsumo($consumoAvio, $material['nombretalle'], $material['cantidadportalle'], 
							$material['consumoavio1'], $material['consumoavio2'], 
							$material['consumoavio3'], $material['consumoavio4']);

			if ($consumoAvio > 0)
				$dataAvio[] = ['nombrematerial' => $material['nombrematerialavio'].' '.$material['nombrecoloravio'],
						'consumo' => $consumoAvio];
		}
	
		return(['datacapellada' => $this->agrupaMaterial($dataCapellada, 'consumo', 'nombrematerial'), 
				'dataavio' => $this->agrupaMaterial($dataAvio, 'consumo', 'nombrematerial')]);
	}
	

	// Genera datos para reporte consumo de OT

	public function generaDatosRepConsumoCaja($desdefecha, $hastafecha, $ordenestrabajo)
	{
		$data = $this->ordentrabajoQuery->findConsumoCaja($desdefecha, $hastafecha, $ordenestrabajo);

		// Agrupa por nombre de caja
		$retorno = [];
		$retornoEspecial = [];
		foreach($data as $item)
		{
			if ($item['nombretalle'] >= $item['desdenumero'] &&
				$item['nombretalle'] <= $item['hastanumero'])
			{
				if ($item['cajaespecial'] == 'S')
				{
					Self::armaTablaRepConsumoCaja($retornoEspecial, $item);
				}
				else
				{
					Self::armaTablaRepConsumoCaja($retorno, $item);
				}
			}
		}

		return ['cajas' => $retorno, 'cajasespeciales' => $retornoEspecial];
	}
	
	// Arma tabla de reporte de cajas
	private function armaTablaRepConsumoCaja(&$retorno, $item)
	{
		for ($i = 0, $flEncontro = false; $i < count($retorno); $i++)
		{
			if ($retorno[$i]['caja_id'] == $item['caja_id'])
			{
				$flEncontro = true;
				break;
			}
		}
		if (!$flEncontro)
			$retorno[] = ['nombrecaja' => $item['nombrecaja'], 
						'caja_id' => $item['caja_id'],
						'consumo' => $item['cantidadportalle'],
						'nombrearticulocaja' => $item['nombrearticulocaja'],
						'desdenumero' => $item['desdenumero'],
						'hastanumero' => $item['hastanumero']
						];
		else
			$retorno[$i]['consumo'] += $item['cantidadportalle'];
	}

	// Genera datos para reporte consumo de OT

	public function generaDatosRepProgArmado($ordenestrabajo, $tipoprogramacion)
	{
		$data = $this->ordentrabajoQuery->findProgArmado($ordenestrabajo);
	
		$retorno = [];
		$arrayOt = explode(',', $ordenestrabajo);
		// Gira por cada ot ingresada para conservar el orden
		foreach ($arrayOt as $codigoOt)
		{
			foreach ($data as $ot)
			{
				if ($codigoOt == $ot['codigoot'])
				{
					for ($i = 0, $flEncontro = false; $i < count($retorno); $i++)
					{
						if ($retorno[$i]['ordentrabajo_id'] == $ot['ordentrabajo_id'])
						{
							$flEncontro = true;
							break;
						}
					}
					if (!$flEncontro)
					{
						$retorno[] = ['orden' => $i+1, 'ordentrabajo_id' => $ot['ordentrabajo_id'],
									'numeroot' => $ot['codigoot'], 'linea' => $ot['nombrelinea'],
									'sku' => $ot['sku'], 'material' => $ot['nombrecombinacion'], 
									'pares' => $ot['cantidad'],
									'fecha' => $ot['fecha'],
									'nombrearticulo' => $ot['nombrearticulo'],
									'nombrecliente' => [$ot['nombrecliente']]];
					}
					else
					{
						$retorno[$i]['pares'] += $ot['cantidad'];

						for ($j = 0, $flEncontro = false; $j < count($retorno[$i]['nombrecliente']); $j++)
						{
							if ($retorno[$i]['nombrecliente'][$j] == $ot['nombrecliente'])
								$flEncontro = true;
						}
						if (!$flEncontro)
							$retorno[$i]['nombrecliente'][] = $ot['nombrecliente'];
					}
				}
			}
		}
		// Si es programacion definitiva genera reporte de cajas y envia correo avisando a administracion
		if ($tipoprogramacion == "DEFINITIVA")
		{
			foreach ($retorno as $ot)
			{
				$this->listaTicketCaja($ot['numeroot'], $ot['fecha'], $ot['nombrecliente'],
									$ot['nombrearticulo'], $ot['material'], $ot['pares']);
			}
		}
		return $retorno;
	}

	// Lista cajas

	public function listaTicketCaja($codigoOt, $fecha, $nombrecliente, $nombrearticulo, $nombrecombinacion, 
									$totalpares)
	{
		// Arma nombre de archivo
		$nombreReporte = "tmp/cajaOT-" . $codigoOt . '.txt';

		$reporte = "";
		$reporte .= "\n\n\n\n\n\nCajas de ORDEN DE TRABAJO NRO. ".$codigoOt."\n\n";
		$reporte .= "Cliente: ".implode(",", $nombrecliente)."\n\n";
		$reporte .= "Articulo: ".$nombrearticulo."\n\n";
		$reporte .= "Combinacion: ".$nombrecombinacion."\n\n";

		//$reporte .= "MEDIDAS\n";
		//$medidas = json_decode($request['medidas']);
		
		//foreach($medidas as $medida)
		//{
		//	$reporte .= "Talle: ".$medida->talle." Cantidad: ".$medida->cantidad."\n";
		//}

		$reporte .= "\nTotal pares: ".$totalpares."\n";

		// Total de cajas
		$dataCajas = $this->generaDatosRepConsumoCaja($fecha, $fecha, $codigoOt);
		$reporte .= "CAJAS\n";
		foreach($dataCajas as $caja)
		{
			for ($i = 0; $i < count($caja); $i++)
			{
				$reporte .= "Caja: ". $caja[$i]['nombrecaja']." ".$caja[$i]['nombrearticulocaja']."\n";
				$reporte .= "Consumo: ".$caja[$i]['consumo']." Medidas: ".$caja[$i]['desdenumero']." ".$caja[$i]['hastanumero']."\n\n\n\n\n\n\n\n\n\n\n\n\n\n";
			}
		}
		$reporte .= "- - - - - - - - - - - - - - - - - - - - - - - -\n";

		Storage::disk('local')->put($nombreReporte, $reporte);
		$path = Storage::path($nombreReporte);
		system("lp -darmado ".$path." 1>&2 2>/dev/null");

		Storage::disk('local')->delete($nombreReporte);
	}
	
	// Trae el estado de la orden de trabajo segun el item del pedido y id de ot

	public function traeEstadoOt($ordentrabajo_id, $pedido_combinacion_id, &$nombretarea)
	{
		$ordentrabajo_tarea = $this->ordentrabajo_tareaRepository->findPorOrdentrabajoId($ordentrabajo_id);

		$nombretarea = ''; $idTarea = 0;
		foreach ($ordentrabajo_tarea as $tarea)
		{
			if ($tarea->pedido_combinacion_id == $pedido_combinacion_id || $tarea->pedido_combinacion_id == 0)
			{
				$nombretarea = $tarea->tareas->nombre;
				$idTarea = $tarea->tareas->id;
			}
		}
		// Busca si es boletas juntas para verificar tareas globales o individuales
		if ($idTarea != config("consprod.TAREA_EMPAQUE") &&
		    $idTarea != config("consprod.TAREA_FACTURADA"))
		{
			$pedido_combinacion = $this->pedido_combinacionRepository->findPorOrdenTrabajoId($ordentrabajo_id);
			if (count($pedido_combinacion) > 1) // Si es boleta junta
			{
				$nombretarea = ''; $idTarea = 0;
				foreach ($ordentrabajo_tarea as $tarea)
				{
					if ($tarea->tareas->id != config("consprod.TAREA_EMPAQUE") &&
						$tarea->tareas->id != config("consprod.TAREA_FACTURADA"))
					{
						$nombretarea = $tarea->tareas->nombre;
						$idTarea = $tarea->tareas->id;
					}
				}
			}
		}
	}

	// Agrupa por material

	private function agrupaMaterial($data, $keyconsumo, $keynombre)
	{
		$retorno = [];
		foreach($data as $item)
		{
			for ($i = 0, $flEncontro = false; $i < count($retorno); $i++)
			{
				if ($retorno[$i]['nombrematerial'] == $item[$keynombre])
				{
					$flEncontro = true;
					break;
				}
			}
			if (!$flEncontro)
				$retorno[] = ['nombrematerial' => $item[$keynombre], 'consumo' => $item[$keyconsumo]];
			else
				$retorno[$i]['consumo'] += $item[$keyconsumo];
		}
		return $retorno;
	}

	// Controla estado de la orden de trabajo
	public function buscaTareaOt($id, $tarea_id)
	{
		$ret = 0;
		$tarea = $this->ordentrabajo_tareaRepository->findPorOrdentrabajoId($id, $tarea_id);

		if (count($tarea) > 0)
			$ret = 1;

		return $ret;
	}

	public function otFacturada($codigoOt, $id, $pedido_combinacion_id = null)
	{
		if ($id != null)
			$ordentrabajo = $this->ordentrabajoQuery->leeOrdenTrabajo($id);
		else
			$ordentrabajo = $this->ordentrabajoQuery->leeOrdenTrabajoPorCodigo($codigoOt);

		$secuenciaTareas = config("consprod.SECUENCIA_TAREAS");

		$numeroFactura = '-1';
		$flTareaTerminada = false;
		$flExisteSecuencia = false;
		if ($ordentrabajo)
		{
			foreach ($ordentrabajo->ordentrabajo_tareas as $tareaOt)
			{
				if ($pedido_combinacion_id ? $tareaOt->pedido_combinacion_id == $pedido_combinacion_id || $tareaOt->pedido_combinacion_id == null : true)
				{
					if ($tareaOt->tarea_id == config("consprod.TAREA_TERMINADA") ||
						$tareaOt->tarea_id == config("consprod.TAREA_TERMINADA_STOCK") ||
						$tareaOt->tarea_id == config("consprod.TAREA_EMPAQUE"))
						$flTareaTerminada = true;

					if ($tareaOt->tarea_id == config("consprod.TAREA_FACTURADA"))
					{
						if ($tareaOt->venta_id != null)
						{
							$venta = $this->ventaRepository->find($tareaOt->venta_id);

							if ($venta)
								$numeroFactura = $venta->codigo;
						}
					}

					// Predecesoras de FACTURADA (34/32/39): alcanza con que exista alguna con hastafecha
					foreach ($secuenciaTareas[config("consprod.TAREA_FACTURADA")] as $secuencia)
					{
						if ($secuencia == $tareaOt->tarea_id && $tareaOt->hastafecha != null)
							$flExisteSecuencia = true;
					}
				}
			}
			if ($numeroFactura == -1 && !$flExisteSecuencia)
				$numeroFactura = -2;
		}
		if (!$flTareaTerminada)
			return ['numerofactura' => -3, 'terminada' => 'no'];

		return [
			'numerofactura' => $numeroFactura,
			'terminada' => 'si',
		];
	}

	// Trae articulo de la ot por codigo se usa cuando hay boletas juntas en la OT

	public function traeArticuloOtPorId($id)
	{
		$ot = $this->ordentrabajoQuery->leeOrdenTrabajo($id);

		$sku = $nombreLinea = $pares = '';
		if ($ot)
		{
			// Lee articulo
			$articulo = false;
			$pedidoCombinacion = $ot->pedidoCombinacionVigente();
			if ($pedidoCombinacion)
				$articulo = $this->articuloQuery->traeArticuloPorId($pedidoCombinacion->articulo_id);

			if ($articulo)
			{
				$sku = $articulo->sku;
				$nombreLinea = $articulo->lineas->nombre;

				$pares = 0;
				foreach($ot->ordentrabajoCombinacionTallesVigentes() as $item)
				{
					$pares += $item->pedido_combinacion_talles->cantidad;
				}
			}
		}
		return ['sku' => $sku, 'nombrelinea' => $nombreLinea, 'pares' => $pares];
	}

	// Controla el saldo de OT de stock
	public function controlaOtStock($codigoOt, $articulo_id, $combinacion_id)
	{
		$stock = $this->articulo_movimientoService->leeStockPorLote($codigoOt, $articulo_id, $combinacion_id);
		$estado = '-1';
		$saldo = 0;
		$deposito_id = 0;
		$tipoAlta = (int) config('consprod.TIPOTRANSACCION_ALTA_PRODUCCION', 3);
		foreach ($stock as $movimiento) {
			if ($movimiento->ordentrabajo_id > 0) {
				$estado = 0;
			} else {
				$estado = 1;
			}
			$saldo += $movimiento->cantidad;
			$depMov = (int) ($movimiento->deposito_id ?? 0);
			if ($depMov <= 0) {
				continue;
			}
			// Preferir depósito de la alta de producción (tipo legacy en articulo_movimiento).
			if ((int) ($movimiento->tipotransaccion_id ?? 0) === $tipoAlta) {
				$deposito_id = $depMov;
			} elseif ($deposito_id <= 0) {
				// Ferli / mov. stock: el tipo quedó en tipotransaccion_stock_id y tipotransaccion_id en null.
				$deposito_id = $depMov;
			}
		}

		return ['estado' => $estado, 'saldo' => $saldo, 'deposito_id' => $deposito_id];
	}
}

