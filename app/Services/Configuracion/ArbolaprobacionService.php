<?php
namespace App\Services\Configuracion;

use App\Repositories\Configuracion\ArbolaprobacionRepositoryInterface;
use App\Repositories\Configuracion\Arbolaprobacion_NivelRepositoryInterface;
use App\Repositories\Configuracion\Arbolaprobacion_MovimientoRepositoryInterface;
use App\Repositories\Ordenventa\OrdenventaRepositoryInterface;
use App\Repositories\Ordenventa\Ordenventa_EstadoRepositoryInterface;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Services\Configuracion\CotizacionService;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Ordenventa\Ordenventa_Estado;
use App\Mail\Configuracion\MailArbolAprobacion;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Mail;
use Auth;
use DB;

class ArbolaprobacionService 
{
	private $arbolaprobacionRepository;
	private $arbolaprobacion_movimientoRepository;
	private $ordenventaRepository;
	private $ordenventa_estadoRepository;
	private $usuarioRepository;
	private $cotizacionService;

	public function __construct(ArbolaprobacionRepositoryInterface $arbolaprobacionrepository,
								Arbolaprobacion_MovimientoRepositoryInterface $arbolaprobacion_movimientorepository,
								OrdenventaRepositoryInterface $ordenventarepository,
								Ordenventa_EstadoRepositoryInterface $ordenventa_estadorepository,
								UsuarioRepositoryInterface $usuariorepository,
								CotizacionService $cotizacionservice)
	{
		$this->arbolaprobacionRepository = $arbolaprobacionrepository;
		$this->arbolaprobacion_movimientoRepository = $arbolaprobacion_movimientorepository;
		$this->ordenventaRepository = $ordenventarepository;
		$this->ordenventa_estadoRepository = $ordenventa_estadorepository;
		$this->usuarioRepository = $usuariorepository;
		$this->cotizacionService = $cotizacionservice;
	}

	public function procesaArbolaprobacion($tipocomprobante, $comprobante_id, $operacion)
	{
		// Define reemplazos para envio de hash via URLs
		$arrayReplace = ['/', '%'];
		$proximoNivel['proximonivel'] = 0;

		// Busca arbol
		$tipoarbol = Arbolaprobacion::$enumTipoArbol[array_search($tipocomprobante, array_column(Arbolaprobacion::$enumTipoArbol, 'valor'))]['nombre'];
		$arbolaprobacion = $this->arbolaprobacionRepository->findPorTipoArbol($tipoarbol);

		if ($arbolaprobacion)
		{
			switch($tipocomprobante)
			{
				case 'OV':
					// Lee la orden de venta
					$ordenventa = $this->ordenventaRepository->find($comprobante_id);

					if ($ordenventa)
					{
						// Lee la aprobacion del comprobante sacando nivel actual
						$estadoAprobacionActual = Self::leeAprobacionComprobante($tipoarbol, $comprobante_id);

						// Verifica siguiente nivel
						$proximoNivel = Self::buscaProximoNivel($arbolaprobacion, $ordenventa->centrocosto_id, 
																$estadoAprobacionActual['nivelactual'],
																$ordenventa->fecha, $ordenventa->monto, $ordenventa->moneda_id);
						
						if ($proximoNivel['proximonivel'] > 0)
						{
							$hashAprobacion = Hash::make($tipocomprobante.'A'.$comprobante_id.$ordenventa->fecha.$ordenventa->numeroordenventa.'N'.
																$estadoAprobacionActual['nivelactual'].'U'.$proximoNivel['proximousuario']);
							$hashRechazo = Hash::make($tipocomprobante.'R'.$comprobante_id.$ordenventa->fecha.$ordenventa->numeroordenventa.'N'.
																$estadoAprobacionActual['nivelactual'].'U'.$proximoNivel['proximousuario']);
																
							$hashAprobacion = str_replace($arrayReplace, "+", $hashAprobacion);	
							$ip = config('arbolaprobacion.ip_link');
							$linkAprobacion = $ip."/anitaERP/public/arbolaprobacion/aprobar/".$tipocomprobante."/".$comprobante_id."/".$hashAprobacion;
							$hashRechazo = str_replace($arrayReplace, "+", $hashRechazo);
							$linkRechazo = $ip."/anitaERP/public/arbolaprobacion/buscarechazo/".$tipocomprobante."/".$comprobante_id."/".$hashRechazo;

							$hashVisualizar = Hash::make('VIS'.$comprobante_id.$ordenventa->fecha.$ordenventa->numeroordenventa);
							$hashVisualizar = str_replace($arrayReplace, "+", $hashVisualizar);
							$linkVisualizar = $ip."/anitaERP/public/ordenventa/visualizar/".$comprobante_id."/".$hashVisualizar;

							// Envia mail al usuario del siguiente nivel
							Self::enviaCorreo($proximoNivel['proximousuario'], $tipoarbol, $ordenventa, $linkAprobacion, $linkRechazo, 
												$linkVisualizar);

							// Graba tabla de aprobacion pendiente
							$aprobacion = [
									'arbolaprobacion_id' => $arbolaprobacion[0]->id,
									'fechaenvio' => Carbon::now(),
									'enviousuario_id' => Auth::user()->id,
									'requisicion_id' => null,
									'ordencompra_id' => null,
									'solicitudpago_id' => null,
									'ordenventa_id' => $comprobante_id,
									'hashaprobacion' => $hashAprobacion,
									'hashrechazo' => $hashRechazo,
									'hashvisualizar' => $hashVisualizar,
									'nivel' => $proximoNivel['proximonivel'],
									'destinatariousuario_id' => $proximoNivel['proximousuario'],
									'fechaproceso' => null,
									'estado' => Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'],
									'observacion' => ''
							];
							$this->arbolaprobacion_movimientoRepository->create($aprobacion);
						}
					}
					break;
			}
		}
		return $proximoNivel['proximonivel'];
	}

	public function leeAprobacionComprobante($tipoarbol, $comprobante_id)
	{
		$nivelActual = 0;
		$estadoActual = '';
		$usuarioActual_id = null;
		switch($tipoarbol)
		{
			case 'Ordenes de venta':
				// Trae las aprobaciones por orden de venta
				$arbolaprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->findPorOrdenVenta($comprobante_id);
				break;
		}
		if ($arbolaprobacion_movimiento)
		{
			// Busca la ultima aprobacion
			foreach ($arbolaprobacion_movimiento as $aprobacion)
			{
				$estadoActual = $aprobacion->estado;

				// Si esta aprobado guarda ultimo nivel
				if ($aprobacion->estado == 
					Arbolaprobacion_Movimiento::$enumEstado[array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'])
				{
					$nivelActual = $aprobacion->nivel;
					$usuarioActual_id = $aprobacion->destinatariousuario_id;
				}
			}
		}
		return ['nivelactual' => $nivelActual, 'estado' => $estadoActual, 'usuario_id' => $usuarioActual_id];
	}

	public function buscaProximoNivel($arbolaprobacion, $centrocosto_id, $nivelactual, $fecha, $monto, $moneda_id)
	{
		// Lee el arbol
		$proximoNivel = $proximoUsuario = 0;
		foreach ($arbolaprobacion[0]->arbolaprobacion_niveles as $nivel)
		{
			if ($nivel->centrocosto_id == $centrocosto_id)
			{
				// Convierte monto a moneda del nivel para controlar
				if ($nivel->moneda_id != $moneda_id)
				{
					$cotizacion = $this->cotizacionService->leeCotizacionDiaria($fecha, $moneda_id);

					$coeficienteConversion = calculaCoeficienteMoneda($nivel->moneda_id, $moneda_id, $cotizacion);
				}
				else
					$coeficienteConversion = 1.;

				$monto *= $coeficienteConversion;

				if ($nivelactual < $nivel->nivel &&
					($nivel->desdemonto != 0 || $nivel->hastamonto != 0 ?
					$nivel->desdemonto <= $monto &&
					$nivel->hastamonto >= $monto : true))
				{
					$proximoNivel = $nivel->nivel;
					$proximoUsuario = $nivel->usuario_id;
				}
			}
		}
		// Si ya tiene aprobacion y no puede seguir el arbol retorna -1 para avisar que aprueba el comprobante
		if ($nivelactual > 0 && $proximoNivel == 0)
			$proximoNivel = -1;

		return ['proximonivel' => $proximoNivel, 'proximousuario' => $proximoUsuario];
	}

	public function enviaCorreo($usuario_id, $tipoarbol, $ptrcomprobante, $linkaprobacion, $linkrechazo, $linkvisualizar)
	{
		// Lee el usuario
		$usuario = $this->usuarioRepository->find($usuario_id);

		if ($usuario)
		{
        	$receivers = $usuario->email;

        	Mail::to($receivers)->send(new MailArbolAprobacion($ptrcomprobante, $tipoarbol, $linkaprobacion, $linkrechazo, $linkvisualizar));
		}
		else
			throw new ModelNotFoundException("Usuario en arbol de aprobación no encontrado");
	}

	public function aprobar($tipocomprobante, $comprobante_id, $aprobacion_id, $usuario_id)
	{
		DB::beginTransaction();
		try
		{
			// Graba nivel aprobado
			$arbolaprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->update([
															"fechaproceso" => Carbon::now(),
															"estado" => Arbolaprobacion_Movimiento::$enumEstado[array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre']
															], $aprobacion_id);

			// Continua siguiente nivel si no aprueba el comprobante
			$nivelSiguiente = Self::procesaArbolaprobacion($tipocomprobante, $comprobante_id, 'self');

			// Si termino de recorrer el arbol aprueba
			if ($nivelSiguiente == -1)
			{
				switch($tipocomprobante)
				{
					case 'OV':
						$estado = Ordenventa_Estado::$enumEstado[array_search('P', array_column(Ordenventa_Estado::$enumEstado, 'valor'))]['nombre'];

						// Graba estado de aprobacion
						$data = [];
						
						$data['fechas'][] = Carbon::now();
						$data['estados'][] = $estado;
						$data['usuario_ids'][] = $usuario_id;
						$data['observacionestados'][] = "Orden de Venta Aprobada";

						$ordenventa_estado = $this->ordenventa_estadoRepository->create($data, $comprobante_id);

						$this->ordenventaRepository->update(['estado' => $estado], $comprobante_id);
						break;
				}
			}
			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();

			return ['mensaje' => 'error', 'errores' => $e->getMessage()];
		}
	}

	public function rechazar($tipocomprobante, $comprobante_id, $aprobacion_id, $usuario_id, $observacion)
	{
		DB::beginTransaction();
		try
		{
			// Graba nivel rechazado
			$arbolaprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->update([
															"fechaproceso" => Carbon::now(),
															"estado" => Arbolaprobacion_Movimiento::$enumEstado[array_search('R', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre']
															], $aprobacion_id);

			// Actualiza comprobantes
			switch($tipocomprobante)
			{
				case 'OV':
					$estado = Ordenventa_Estado::$enumEstado[array_search('R', array_column(Ordenventa_Estado::$enumEstado, 'valor'))]['nombre'];

					// Graba estado de aprobacion
					$data = [];
					
					$data['fechas'][] = Carbon::now();
					$data['estados'][] = $estado;
					$data['usuario_ids'][] = $usuario_id;
					$data['observacionestados'][] = "Orden de Venta Rechazada";

					$ordenventa_estado = $this->ordenventa_estadoRepository->create($data, $comprobante_id);

					$this->ordenventaRepository->update([
														'estado' => $estado, 
														'observacion' => $observacion
														], $comprobante_id);
					break;
			}

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();

			return ['mensaje' => 'error', 'errores' => $e->getMessage()];
		}
	}	
}

