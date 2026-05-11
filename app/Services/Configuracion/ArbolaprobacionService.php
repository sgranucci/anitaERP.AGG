<?php
namespace App\Services\Configuracion;

use App\Repositories\Configuracion\ArbolaprobacionRepositoryInterface;
use App\Repositories\Configuracion\Arbolaprobacion_NivelRepositoryInterface;
use App\Repositories\Configuracion\Arbolaprobacion_MovimientoRepositoryInterface;
use App\Repositories\Ordenventa\OrdenventaRepositoryInterface;
use App\Repositories\Ordenventa\Ordenventa_EstadoRepositoryInterface;
use App\Repositories\Compras\RequisicionRepositoryInterface;
use App\Repositories\Compras\Requisicion_EstadoRepositoryInterface;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Services\Configuracion\CotizacionService;
use App\Support\Compras\RequisicionTotalesCabecera;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Nivel;
use App\Models\Ordenventa\Ordenventa_Estado;
use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Estado;
use App\Mail\Configuracion\MailArbolAprobacion;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Mail;
use Auth;
use DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ArbolaprobacionService 
{
	private $arbolaprobacionRepository;
	private $arbolaprobacion_movimientoRepository;
	private $ordenventaRepository;
	private $ordenventa_estadoRepository;
	private $requisicionRepository;
	private $requisicion_estadoRepository;
	private $usuarioRepository;
	private $cotizacionService;
	private CotizacionQueryInterface $cotizacionQuery;

	public function __construct(ArbolaprobacionRepositoryInterface $arbolaprobacionrepository,
								Arbolaprobacion_MovimientoRepositoryInterface $arbolaprobacion_movimientorepository,
								OrdenventaRepositoryInterface $ordenventarepository,
								Ordenventa_EstadoRepositoryInterface $ordenventa_estadorepository,
								RequisicionRepositoryInterface $requisicionrepository,
								Requisicion_EstadoRepositoryInterface $requisicion_estadorepository,
								UsuarioRepositoryInterface $usuariorepository,
								CotizacionService $cotizacionservice,
								CotizacionQueryInterface $cotizacionquery)
	{
		$this->arbolaprobacionRepository = $arbolaprobacionrepository;
		$this->arbolaprobacion_movimientoRepository = $arbolaprobacion_movimientorepository;
		$this->ordenventaRepository = $ordenventarepository;
		$this->ordenventa_estadoRepository = $ordenventa_estadorepository;
		$this->requisicionRepository = $requisicionrepository;
		$this->requisicion_estadoRepository = $requisicion_estadorepository;
		$this->usuarioRepository = $usuariorepository;
		$this->cotizacionService = $cotizacionservice;
		$this->cotizacionQuery = $cotizacionquery;
	}

	public function procesaArbolaprobacion($tipocomprobante, $comprobante_id, $operacion)
	{
		$arrayReplace = ['/', '%'];
		$tipoarbol = Arbolaprobacion::$enumTipoArbol[array_search($tipocomprobante, array_column(Arbolaprobacion::$enumTipoArbol, 'valor'))]['nombre'];
		if ($tipocomprobante === 'RE') {
			$requisicionPre = $this->requisicionRepository->find($comprobante_id);
			if (! $requisicionPre) {
				return 0;
			}
			$arbolaprobacion = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($tipoarbol, (int) $requisicionPre->empresa_id);
		} else {
			$arbolaprobacion = $this->arbolaprobacionRepository->findPorTipoArbol($tipoarbol);
		}
		if (! $arbolaprobacion || ! $arbolaprobacion->count()) {
			return 0;
		}

		switch ($tipocomprobante) {
			case 'OV':
				return $this->procesaArbolOrdenVenta($arbolaprobacion, $tipoarbol, $comprobante_id, $arrayReplace);
			case 'RE':
				return $this->procesaArbolRequisicion($arbolaprobacion, $tipoarbol, $comprobante_id, $arrayReplace);
			default:
				return 0;
		}
	}

	private function procesaArbolOrdenVenta($arbolaprobacion, $tipoarbol, $comprobante_id, array $arrayReplace): int
	{
		$ordenventa = $this->ordenventaRepository->find($comprobante_id);
		if (! $ordenventa) {
			return 0;
		}

		$arbol = $arbolaprobacion->first();
		if (! $arbol) {
			return 0;
		}

		while (true) {
			$estadoAprobacionActual = $this->leeAprobacionComprobante($tipoarbol, $comprobante_id);
			$proximoNivel = $this->buscaProximoNivel($arbol, $ordenventa->centrocosto_id,
				$estadoAprobacionActual['nivelactual'],
				$ordenventa->fecha, $ordenventa->monto, $ordenventa->moneda_id);

			if ($proximoNivel['proximonivel'] === -1) {
				$uid = Auth::check() ? Auth::user()->id : $ordenventa->creousuario_id;
				$this->finalizaOrdenVentaTrasArbolCompleto($comprobante_id, $uid);

				return -1;
			}

			if ($proximoNivel['proximonivel'] <= 0) {
				return 0;
			}

			if (empty($proximoNivel['proximousuario'])) {
				$this->grabaMovimientoArbolAutomatico($arbol->id, 'OV', $comprobante_id,
					$proximoNivel['proximonivel'], $arrayReplace);

				continue;
			}

			$ip = config('arbolaprobacion.ip_link');
			$hashVisualizar = Hash::make('VIS'.$comprobante_id.$ordenventa->fecha.$ordenventa->numeroordenventa);
			$hashVisualizar = str_replace($arrayReplace, '+', $hashVisualizar);
			$linkVisualizar = $ip.'/anitaERP/public/ordenventa/visualizar/'.$comprobante_id.'/'.$hashVisualizar;

			$nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
			$uids = $proximoNivel['proximousuarios'] ?? [];
			if (! is_array($uids) || count($uids) === 0) {
				$uids = [$proximoNivel['proximousuario']];
			}
			$uids = array_values(array_unique(array_filter($uids)));

			$ya = Arbolaprobacion_Movimiento::where('ordenventa_id', $comprobante_id)
				->where('nivel', $proximoNivel['proximonivel'])
				->where('estado', $nombrePendiente)
				->pluck('destinatariousuario_id')
				->map(fn ($x) => (int) $x)
				->all();

			foreach ($uids as $uid) {
				$uid = (int) $uid;
				if ($uid <= 0 || in_array($uid, $ya, true)) {
					continue;
				}

				$hashAprobacion = Hash::make('OV'.'A'.$comprobante_id.$ordenventa->fecha.$ordenventa->numeroordenventa.'N'.
					$estadoAprobacionActual['nivelactual'].'U'.$uid);
				$hashRechazo = Hash::make('OV'.'R'.$comprobante_id.$ordenventa->fecha.$ordenventa->numeroordenventa.'N'.
					$estadoAprobacionActual['nivelactual'].'U'.$uid);
				$hashAprobacion = str_replace($arrayReplace, '+', $hashAprobacion);
				$hashRechazo = str_replace($arrayReplace, '+', $hashRechazo);
				$linkAprobacion = $ip.'/anitaERP/public/arbolaprobacion/aprobar/OV/'.$comprobante_id.'/'.$hashAprobacion;
				$linkRechazo = $ip.'/anitaERP/public/arbolaprobacion/buscarechazo/OV/'.$comprobante_id.'/'.$hashRechazo;

				$this->enviaCorreo($uid, $tipoarbol, $ordenventa, $linkAprobacion, $linkRechazo, $linkVisualizar, null);

				$this->arbolaprobacion_movimientoRepository->create([
					'arbolaprobacion_id' => $arbol->id,
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
					'destinatariousuario_id' => $uid,
					'fechaproceso' => null,
					'estado' => $nombrePendiente,
					'observacion' => '',
				]);
			}

			return $proximoNivel['proximonivel'];
		}
	}

	private function procesaArbolRequisicion($arbolaprobacion, $tipoarbol, $comprobante_id, array $arrayReplace): int
	{
		$requisicion = $this->requisicionRepository->find($comprobante_id);
		if (! $requisicion) {
			return 0;
		}

		if ($arbolaprobacion->count() > 1) {
			throw new \RuntimeException('Hay más de un árbol de aprobación activo de requisiciones para la empresa; debe quedar uno solo.');
		}

		$arbol = $arbolaprobacion->first();
		if (! $arbol) {
			return 0;
		}

		$centrocostoArbol = $this->centroCostoParaArbolAprobacionDesdeModelo($requisicion);

		while (true) {
			$requisicion = $this->requisicionRepository->find($comprobante_id);
			$totalesReq = RequisicionTotalesCabecera::desdeModelo($requisicion, $this->cotizacionQuery);

			$estadoAprobacionActual = $this->leeAprobacionComprobante($tipoarbol, $comprobante_id);
			$proximoNivel = $this->buscaProximoNivel($arbol, $centrocostoArbol,
				$estadoAprobacionActual['nivelactual'],
				$requisicion->fecha, $totalesReq['monto'], $totalesReq['moneda_id']);

			if ($proximoNivel['proximonivel'] === -1) {
				$uid = Auth::check() ? Auth::user()->id : $requisicion->creousuario_id;
				$this->finalizaRequisicionTrasArbolCompleto($comprobante_id, $uid);

				return -1;
			}

			if ($proximoNivel['proximonivel'] <= 0) {
				return 0;
			}

			if (empty($proximoNivel['proximousuario'])) {
				$this->aplicaEstadoRequisicionPorNombre($comprobante_id, $proximoNivel['requisicion_estado_al_aprobar'],
					'Árbol de aprobación: nivel '.$proximoNivel['proximonivel'].' sin usuario (automático)',
					$this->usuarioHistoriaRequisicion($requisicion));

				$this->grabaMovimientoArbolAutomatico($arbol->id, 'RE', $comprobante_id,
					$proximoNivel['proximonivel'], $arrayReplace);

				$reqTrasAuto = $this->requisicionRepository->find($comprobante_id);
				$nombreEnCompras = Requisicion_Estado::$enumEstado[array_search('K', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
				if ($reqTrasAuto && $reqTrasAuto->estado === $nombreEnCompras) {
					return 0;
				}

				continue;
			}

			$ip = config('arbolaprobacion.ip_link');
			$hashVisualizar = Hash::make('VIS'.$comprobante_id.$requisicion->fecha.$requisicion->numerorequisicion);
			$hashVisualizar = str_replace($arrayReplace, '+', $hashVisualizar);
			$linkVisualizar = $ip.'/anitaERP/public/compras/requisicion/visualizar/'.$comprobante_id.'/'.$hashVisualizar;

			$mailExtras = $this->armaExtrasMailRequisicion($requisicion, $proximoNivel['requisicion_estado_al_aprobar']);
			$envioUid = Auth::check() ? Auth::user()->id : $requisicion->creousuario_id;
			$nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];

			$uids = $proximoNivel['proximousuarios'] ?? [];
			if (! is_array($uids) || count($uids) === 0) {
				$uids = [$proximoNivel['proximousuario']];
			}
			$uids = array_values(array_unique(array_filter($uids)));

			$ya = Arbolaprobacion_Movimiento::where('requisicion_id', $comprobante_id)
				->where('nivel', $proximoNivel['proximonivel'])
				->where('estado', $nombrePendiente)
				->pluck('destinatariousuario_id')
				->map(fn ($x) => (int) $x)
				->all();

			foreach ($uids as $uid) {
				$uid = (int) $uid;
				if ($uid <= 0 || in_array($uid, $ya, true)) {
					continue;
				}

				$hashAprobacion = Hash::make('RE'.'A'.$comprobante_id.$requisicion->fecha.$requisicion->numerorequisicion.'N'.
					$estadoAprobacionActual['nivelactual'].'U'.$uid);
				$hashRechazo = Hash::make('RE'.'R'.$comprobante_id.$requisicion->fecha.$requisicion->numerorequisicion.'N'.
					$estadoAprobacionActual['nivelactual'].'U'.$uid);
				$hashAprobacion = str_replace($arrayReplace, '+', $hashAprobacion);
				$hashRechazo = str_replace($arrayReplace, '+', $hashRechazo);
				$linkAprobacion = $ip.'/anitaERP/public/arbolaprobacion/aprobar/RE/'.$comprobante_id.'/'.$hashAprobacion;
				$linkRechazo = $ip.'/anitaERP/public/arbolaprobacion/buscarechazo/RE/'.$comprobante_id.'/'.$hashRechazo;

				$this->enviaCorreo($uid, $tipoarbol, $requisicion, $linkAprobacion, $linkRechazo, $linkVisualizar, $mailExtras);

				$this->arbolaprobacion_movimientoRepository->create([
					'arbolaprobacion_id' => $arbol->id,
					'fechaenvio' => Carbon::now(),
					'enviousuario_id' => $envioUid,
					'requisicion_id' => $comprobante_id,
					'ordencompra_id' => null,
					'solicitudpago_id' => null,
					'ordenventa_id' => null,
					'hashaprobacion' => $hashAprobacion,
					'hashrechazo' => $hashRechazo,
					'hashvisualizar' => $hashVisualizar,
					'nivel' => $proximoNivel['proximonivel'],
					'destinatariousuario_id' => $uid,
					'fechaproceso' => null,
					'estado' => $nombrePendiente,
					'observacion' => '',
				]);
			}

			return $proximoNivel['proximonivel'];
		}
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
			case 'Requisiciones':
				$arbolaprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->findPorRequisicion($comprobante_id);
				break;
		}
		if ($arbolaprobacion_movimiento)
		{
			$nombreAprobado = Arbolaprobacion_Movimiento::$enumEstado[array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
			foreach ($arbolaprobacion_movimiento as $aprobacion) {
				$estadoActual = $aprobacion->estado;
				if ($aprobacion->estado === $nombreAprobado && $aprobacion->nivel >= $nivelActual) {
					$nivelActual = $aprobacion->nivel;
					$usuarioActual_id = $aprobacion->destinatariousuario_id;
				}
			}
		}

		return ['nivelactual' => $nivelActual, 'estado' => $estadoActual, 'usuario_id' => $usuarioActual_id];
	}

	public function buscaProximoNivel(Arbolaprobacion $arbol, $centrocosto_id, $nivelactual, $fecha, $monto, $moneda_id)
	{
		$candidatos = [];
		foreach ($arbol->arbolaprobacion_niveles as $nivel) {
			if ($nivel->centrocosto_id != $centrocosto_id) {
				continue;
			}

			$coeficienteConversion = 1.;
			if ($nivel->moneda_id != $moneda_id) {
				$cotizacion = $this->cotizacionService->leeCotizacionDiaria($fecha, $moneda_id);
				$coeficienteConversion = calculaCoeficienteMoneda($nivel->moneda_id, $moneda_id, $cotizacion);
				if ($coeficienteConversion == 0) {
					$coeficienteConversion = 1.;
				}
			}

			$montoEnMonedaNivel = $monto * $coeficienteConversion;

			$enRango = ($nivel->desdemonto != 0 || $nivel->hastamonto != 0)
				? ($nivel->desdemonto <= $montoEnMonedaNivel && $nivel->hastamonto >= $montoEnMonedaNivel)
				: true;

			if ($nivelactual < $nivel->nivel && $enRango) {
				$candidatos[] = [
					'nivel' => $nivel->nivel,
					'usuario_id' => $nivel->usuario_id ?: null,
					'requisicion_estado_al_aprobar' => $nivel->requisicion_estado_al_aprobar,
				];
			}
		}

		if (count($candidatos) === 0) {
			$proximoNivel = 0;
			$proximoUsuario = null;
			$proximoUsuarios = [];
			$estadoReq = null;
		} else {
			usort($candidatos, fn ($a, $b) => $a['nivel'] <=> $b['nivel']);
			$proximoNivel = (int) $candidatos[0]['nivel'];
			$enNivel = array_values(array_filter($candidatos, fn ($c) => (int) $c['nivel'] === $proximoNivel));

			$uids = [];
			$estadoReq = null;
			foreach ($enNivel as $c) {
				if (! empty($c['usuario_id'])) {
					$uids[] = (int) $c['usuario_id'];
				}
				if ($estadoReq === null && filled($c['requisicion_estado_al_aprobar'])) {
					$estadoReq = $c['requisicion_estado_al_aprobar'];
				}
			}
			$uids = array_values(array_unique($uids));
			$proximoUsuarios = $uids;
			$proximoUsuario = $uids[0] ?? null; // compat
		}

		if ($nivelactual > 0 && $proximoNivel === 0) {
			$proximoNivel = -1;
		}

		return [
			'proximonivel' => $proximoNivel,
			'proximousuario' => $proximoUsuario,
			'proximousuarios' => $proximoUsuarios,
			'requisicion_estado_al_aprobar' => $estadoReq,
		];
	}

	public function enviaCorreo($usuario_id, $tipoarbol, $ptrcomprobante, $linkaprobacion, $linkrechazo, $linkvisualizar, $mailExtras = null)
	{
		// Lee el usuario
		$usuario = $this->usuarioRepository->find($usuario_id);

		if ($usuario)
		{
        	$receivers = $usuario->email;

        	Mail::to($receivers)->send(new MailArbolAprobacion($ptrcomprobante, $tipoarbol, $linkaprobacion, $linkrechazo, $linkvisualizar, $mailExtras));
		}
		else
			throw new ModelNotFoundException("Usuario en arbol de aprobación no encontrado");
	}

	/**
	 * Texto y montos adicionales para el correo de aprobación de requisiciones.
	 *
	 * @return array<string, mixed>
	 */
	private function armaExtrasMailRequisicion(Requisicion $requisicion, ?string $estadoAlAprobarEsteNivel): array
	{
		$totales = RequisicionTotalesCabecera::desdeModelo($requisicion, $this->cotizacionQuery);

		return [
			'estado_tras_aprobar' => $estadoAlAprobarEsteNivel !== null && $estadoAlAprobarEsteNivel !== ''
				? $estadoAlAprobarEsteNivel
				: null,
			'monto_items' => $totales['monto'],
			'moneda_abrev_items' => $totales['monedacabecera_abreviatura'],
		];
	}

	public function aprobar($tipocomprobante, $comprobante_id, $aprobacion_id, $usuario_id)
	{
		DB::beginTransaction();
		try {
			$movimientoPre = Arbolaprobacion_Movimiento::findOrFail($aprobacion_id);

			$nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
			$nombreAprobado = Arbolaprobacion_Movimiento::$enumEstado[array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
			$nombreSinEfecto = Arbolaprobacion_Movimiento::$enumEstado[array_search('X', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];

			// First-wins: si otro ya aprobó/gestionó, no avanzamos.
			$rows = Arbolaprobacion_Movimiento::where('id', $aprobacion_id)
				->where('estado', $nombrePendiente)
				->update([
					'fechaproceso' => Carbon::now(),
					'estado' => $nombreAprobado,
				]);
			if ($rows === 0) {
				DB::commit();
				return;
			}

			// Invalida el resto de los usuarios del mismo nivel/comprobante.
			$q = Arbolaprobacion_Movimiento::where('arbolaprobacion_id', $movimientoPre->arbolaprobacion_id)
				->where('nivel', $movimientoPre->nivel)
				->where('estado', $nombrePendiente)
				->where('id', '!=', $aprobacion_id);
			if ($movimientoPre->requisicion_id) {
				$q->where('requisicion_id', $movimientoPre->requisicion_id);
			} elseif ($movimientoPre->ordenventa_id) {
				$q->where('ordenventa_id', $movimientoPre->ordenventa_id);
			}
			$q->update([
				'fechaproceso' => Carbon::now(),
				'estado' => $nombreSinEfecto,
				'observacion' => 'Sin efecto (otro usuario aprobó el nivel)',
			]);

			if ($tipocomprobante === 'RE') {
				$arbol = $this->arbolaprobacionRepository->find($movimientoPre->arbolaprobacion_id);
				$requisicion = $this->requisicionRepository->find($comprobante_id);
				$centrocostoArbol = $this->centroCostoParaArbolAprobacionDesdeModelo($requisicion);
				$totalesReq = RequisicionTotalesCabecera::desdeModelo($requisicion, $this->cotizacionQuery);
				$nivelCfg = $this->encuentraNivelCoincidente(
					$arbol,
					$centrocostoArbol,
					$movimientoPre->nivel,
					$requisicion->fecha,
					$totalesReq['monto'],
					$totalesReq['moneda_id']
				);
				if ($nivelCfg !== null && filled($nivelCfg->requisicion_estado_al_aprobar)) {
					$this->aplicaEstadoRequisicionPorNombre(
						$comprobante_id,
						trim($nivelCfg->requisicion_estado_al_aprobar),
						'Árbol de aprobación: nivel '.$movimientoPre->nivel.' aprobado',
						$usuario_id
					);
				}
			}

			if ($tipocomprobante === 'RE') {
				$reqActual = $this->requisicionRepository->find($comprobante_id);
				$nombreEnCompras = Requisicion_Estado::$enumEstado[array_search('K', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
				if ($reqActual && $reqActual->estado === $nombreEnCompras) {
					DB::commit();

					return;
				}
			}

			$this->procesaArbolaprobacion($tipocomprobante, $comprobante_id, 'self');

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();

			return ['mensaje' => 'error', 'errores' => $e->getMessage()];
		}
	}

	private function grabaMovimientoArbolAutomatico(
		int $arbolaprobacion_id,
		string $tipoComprobante,
		int $comprobante_id,
		int $numeroNivel,
		array $arrayReplace
	): void {
		$token = $tipoComprobante.'AUTO'.$comprobante_id.'N'.$numeroNivel.str_replace([' ', ':'], '', microtime(false));
		$hashAprobacion = str_replace($arrayReplace, '+', Hash::make($token.'A'));
		$hashRechazo = str_replace($arrayReplace, '+', Hash::make($token.'R'));
		$hashVisualizar = str_replace($arrayReplace, '+', Hash::make($token.'V'));
		$nombreAprobado = Arbolaprobacion_Movimiento::$enumEstado[array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];

		if ($tipoComprobante === 'OV') {
			$ordenventa = $this->ordenventaRepository->find($comprobante_id);
			$envioUid = Auth::check() ? Auth::user()->id : $ordenventa->creousuario_id;
			$this->arbolaprobacion_movimientoRepository->create([
				'arbolaprobacion_id' => $arbolaprobacion_id,
				'fechaenvio' => Carbon::now(),
				'enviousuario_id' => $envioUid,
				'requisicion_id' => null,
				'ordencompra_id' => null,
				'solicitudpago_id' => null,
				'ordenventa_id' => $comprobante_id,
				'hashaprobacion' => $hashAprobacion,
				'hashrechazo' => $hashRechazo,
				'hashvisualizar' => $hashVisualizar,
				'nivel' => $numeroNivel,
				'destinatariousuario_id' => null,
				'fechaproceso' => Carbon::now(),
				'estado' => $nombreAprobado,
				'observacion' => 'Nivel sin usuario (automático)',
			]);

			return;
		}

		$requisicion = $this->requisicionRepository->find($comprobante_id);
		$envioUid = Auth::check() ? Auth::user()->id : $requisicion->creousuario_id;
		$this->arbolaprobacion_movimientoRepository->create([
			'arbolaprobacion_id' => $arbolaprobacion_id,
			'fechaenvio' => Carbon::now(),
			'enviousuario_id' => $envioUid,
			'requisicion_id' => $comprobante_id,
			'ordencompra_id' => null,
			'solicitudpago_id' => null,
			'ordenventa_id' => null,
			'hashaprobacion' => $hashAprobacion,
			'hashrechazo' => $hashRechazo,
			'hashvisualizar' => $hashVisualizar,
			'nivel' => $numeroNivel,
			'destinatariousuario_id' => null,
			'fechaproceso' => Carbon::now(),
			'estado' => $nombreAprobado,
			'observacion' => 'Nivel sin usuario (automático)',
		]);
	}

	private function finalizaOrdenVentaTrasArbolCompleto(int $ordenventa_id, $usuarioHistoriaId): void
	{
		$estado = Ordenventa_Estado::$enumEstado[array_search('P', array_column(Ordenventa_Estado::$enumEstado, 'valor'))]['nombre'];
		$data = [];
		$data['fechas'][] = Carbon::now();
		$data['estados'][] = $estado;
		$data['usuario_ids'][] = $usuarioHistoriaId;
		$data['observacionestados'][] = 'Orden de Venta Aprobada';

		$this->ordenventa_estadoRepository->create($data, $ordenventa_id);
		$this->ordenventaRepository->update(['estado' => $estado], $ordenventa_id);
	}

	private function finalizaRequisicionTrasArbolCompleto(int $requisicion_id, $usuarioHistoriaId): void
	{
		$aprobada = Requisicion_Estado::$enumEstado[array_search('A', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
		$req = $this->requisicionRepository->find($requisicion_id);
		if ($req->estado === $aprobada) {
			return;
		}

		$this->requisicion_estadoRepository->creaEstado(
			$requisicion_id,
			Carbon::now()->format('Y-m-d'),
			$aprobada,
			$usuarioHistoriaId,
			'Requisición aprobada (árbol completo)'
		);
		$this->requisicionRepository->update(['estado' => $aprobada], $requisicion_id);
	}

	private function aplicaEstadoRequisicionPorNombre(int $requisicion_id, ?string $estadoNombre, string $observacion, $usuarioHistoriaId): void
	{
		if ($estadoNombre === null || $estadoNombre === '') {
			return;
		}
		if (! Requisicion_Estado::esNombreEstadoValido($estadoNombre)) {
			return;
		}

		$this->requisicion_estadoRepository->creaEstado(
			$requisicion_id,
			Carbon::now()->format('Y-m-d'),
			$estadoNombre,
			$usuarioHistoriaId,
			$observacion
		);
		$this->requisicionRepository->update(['estado' => $estadoNombre], $requisicion_id);
	}

	private function usuarioHistoriaRequisicion($requisicion): int
	{
		if (Auth::check()) {
			return Auth::user()->id;
		}

		return $requisicion->creousuario_id;
	}

	private function encuentraNivelCoincidente(
		Arbolaprobacion $arbol,
		$centrocosto_id,
		$numeroNivel,
		$fecha,
		$montoOriginal,
		$moneda_id
	): ?Arbolaprobacion_Nivel {
		$candidatosMismoNivel = [];
		foreach ($arbol->arbolaprobacion_niveles as $nivel) {
			if ($nivel->centrocosto_id != $centrocosto_id || (int) $nivel->nivel !== (int) $numeroNivel) {
				continue;
			}

			$coeficienteConversion = 1.;
			if ($nivel->moneda_id != $moneda_id && $moneda_id !== null && $moneda_id !== '') {
				$cotizacion = $this->cotizacionService->leeCotizacionDiaria($fecha, $moneda_id);
				$coeficienteConversion = calculaCoeficienteMoneda($nivel->moneda_id, $moneda_id, $cotizacion);
				if ($coeficienteConversion == 0) {
					$coeficienteConversion = 1.;
				}
			}

			$montoEnMonedaNivel = (float) $montoOriginal * $coeficienteConversion;
			$enRango = ($nivel->desdemonto != 0 || $nivel->hastamonto != 0)
				? ($nivel->desdemonto <= $montoEnMonedaNivel && $nivel->hastamonto >= $montoEnMonedaNivel)
				: true;
			if ($enRango) {
				return $nivel;
			}
			$candidatosMismoNivel[] = $nivel;
		}

		// El movimiento ya existió para este nivel; si el monto de cabecera cambió o no calzaba el rango,
		// usar la fila de configuración del mismo centro y número de nivel para aplicar requisicion_estado_al_aprobar.
		return $candidatosMismoNivel[0] ?? null;
	}

	public function rechazar($tipocomprobante, $comprobante_id, $aprobacion_id, $usuario_id, $observacion)
	{
		DB::beginTransaction();
		try
		{
			$movimientoPre = Arbolaprobacion_Movimiento::findOrFail($aprobacion_id);

			$nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
			$nombreRechazado = Arbolaprobacion_Movimiento::$enumEstado[array_search('R', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
			$nombreSinEfecto = Arbolaprobacion_Movimiento::$enumEstado[array_search('X', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];

			// First-wins: si otro ya gestionó, no avanzamos.
			$rows = Arbolaprobacion_Movimiento::where('id', $aprobacion_id)
				->where('estado', $nombrePendiente)
				->update([
					'fechaproceso' => Carbon::now(),
					'estado' => $nombreRechazado,
					'observacion' => (string) ($observacion ?? ''),
				]);
			if ($rows === 0) {
				DB::commit();
				return;
			}

			// Invalida el resto de los usuarios del mismo nivel/comprobante.
			$q = Arbolaprobacion_Movimiento::where('arbolaprobacion_id', $movimientoPre->arbolaprobacion_id)
				->where('nivel', $movimientoPre->nivel)
				->where('estado', $nombrePendiente)
				->where('id', '!=', $aprobacion_id);
			if ($movimientoPre->requisicion_id) {
				$q->where('requisicion_id', $movimientoPre->requisicion_id);
			} elseif ($movimientoPre->ordenventa_id) {
				$q->where('ordenventa_id', $movimientoPre->ordenventa_id);
			}
			$q->update([
				'fechaproceso' => Carbon::now(),
				'estado' => $nombreSinEfecto,
				'observacion' => 'Sin efecto (otro usuario rechazó el nivel)',
			]);

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
				case 'RE':
					$estado = Requisicion_Estado::$enumEstado[array_search('S', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
					$this->requisicion_estadoRepository->creaEstado(
						$comprobante_id,
						Carbon::now()->format('Y-m-d'),
						$estado,
						$usuario_id,
						'Requisición suspendida / rechazada en árbol: '.$observacion
					);
					$this->requisicionRepository->update(['estado' => $estado], $comprobante_id);
					break;
			}

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();

			return ['mensaje' => 'error', 'errores' => $e->getMessage()];
		}
	}

	public function nombreTipoArbolRequisiciones(): string
	{
		return Arbolaprobacion::$enumTipoArbol[array_search('RE', array_column(Arbolaprobacion::$enumTipoArbol, 'valor'))]['nombre'];
	}

	/**
	 * Centro de costo usado para niveles del árbol: CC destino de los ítems (debe ser único); si no hay ítems válidos, cabecera.
	 *
	 * @throws \RuntimeException
	 */
	public function centroCostoParaArbolAprobacionDesdeModelo(Requisicion $requisicion): int
	{
		$requisicion->loadMissing('requisicion_articulos');
		$ids = [];
		foreach ($requisicion->requisicion_articulos as $linea) {
			if (empty($linea->articulo_id) || (float) $linea->cantidad <= 0) {
				continue;
			}
			$cid = $linea->centrocostodestino_id ?? $requisicion->centrocosto_id;
			if ($cid !== null && $cid !== '') {
				$ids[] = (int) $cid;
			}
		}
		$unique = array_unique($ids);
		if (count($unique) > 1) {
			throw new \RuntimeException('Todos los renglones deben tener el mismo centro de costo de destino para el árbol de aprobación.');
		}
		if (count($unique) === 1) {
			return (int) reset($unique);
		}

		return (int) $requisicion->centrocosto_id;
	}

	/**
	 * @throws \RuntimeException
	 */
	public function validaRequisicionRequestContraArbol(array $data): void
	{
		$empresaId = (int) ($data['empresa_id'] ?? 0);
		if ($empresaId <= 0) {
			return;
		}
		$nombreTipo = $this->nombreTipoArbolRequisiciones();
		$trees = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($nombreTipo, $empresaId);
		if ($trees->isEmpty()) {
			throw new \RuntimeException('No hay un árbol de aprobación activo de requisiciones para la empresa seleccionada.');
		}
		if ($trees->count() > 1) {
			throw new \RuntimeException('Hay más de un árbol de aprobación activo de requisiciones para esa empresa; debe quedar uno solo.');
		}
		$cc = $this->centroCostoParaArbolDesdeRequest($data);
		[$monto, $monedaId] = $this->montoYMonedaDesdeLineasRequisicionRequest($data);
		$fecha = $data['fecha'] ?? date('Y-m-d');
		$rid = (int) ($data['requisicion_id'] ?? 0);
		$nivelActual = $rid > 0 ? $this->leeAprobacionComprobante($nombreTipo, $rid)['nivelactual'] : 0;
		$arbol = $trees->first();
		$prox = $this->buscaProximoNivel($arbol, $cc, $nivelActual, $fecha, $monto, $monedaId);
		if ($prox['proximonivel'] === 0) {
			throw new \RuntimeException('El árbol de aprobación no tiene un nivel aplicable para el centro de costo de destino, el monto total y la moneda de la requisición.');
		}
	}

	/**
	 * @throws \RuntimeException
	 */
	public function validaRequisicionModeloContraArbol(Requisicion $req): void
	{
		$nombreTipo = $this->nombreTipoArbolRequisiciones();
		$trees = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($nombreTipo, (int) $req->empresa_id);
		if ($trees->isEmpty()) {
			throw new \RuntimeException('No hay un árbol de aprobación activo de requisiciones para la empresa de la requisición.');
		}
		if ($trees->count() > 1) {
			throw new \RuntimeException('Hay más de un árbol de aprobación activo de requisiciones para esa empresa; debe quedar uno solo.');
		}
		$cc = $this->centroCostoParaArbolAprobacionDesdeModelo($req);
		$nivelActual = $this->leeAprobacionComprobante($nombreTipo, $req->id)['nivelactual'];
		$arbol = $trees->first();
		$totalesReq = RequisicionTotalesCabecera::desdeModelo($req, $this->cotizacionQuery);
		$prox = $this->buscaProximoNivel($arbol, $cc, $nivelActual, $req->fecha, $totalesReq['monto'], $totalesReq['moneda_id']);
		if ($prox['proximonivel'] === 0) {
			throw new \RuntimeException('El árbol de aprobación no tiene un nivel aplicable para el centro de costo de destino, el monto total y la moneda de la requisición.');
		}
	}

	/**
	 * Mensaje si la empresa no tiene exactamente un árbol activo de requisiciones (solo cabecera empresa).
	 */
	public function mensajeEmpresaSinArbolRequisicionActivoUnico(int $empresaId): ?string
	{
		if ($empresaId <= 0) {
			return null;
		}
		$nombreTipo = $this->nombreTipoArbolRequisiciones();
		$trees = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($nombreTipo, $empresaId);
		if ($trees->isEmpty()) {
			return 'No hay un árbol de aprobación activo de requisiciones para la empresa seleccionada.';
		}
		if ($trees->count() > 1) {
			return 'Hay más de un árbol de aprobación activo de requisiciones para esa empresa; debe quedar uno solo.';
		}

		return null;
	}

	/**
	 * @return array{movimientos: array<int, array<string, mixed>>, aviso_grabacion_pendiente: string|null}
	 */
	public function movimientosRequisicionConAvisoGrabacion(int $requisicionId): array
	{
		$movs = $this->arbolaprobacion_movimientoRepository->findPorRequisicion($requisicionId);
		$enriquecidos = $this->adjuntaIndicacionEstadoRequisicionMovimientos($movs, $requisicionId);
		$req = $this->requisicionRepository->find($requisicionId);
		$aviso = null;
		if ($req) {
			$pendiente = Requisicion_Estado::$enumEstado[array_search('P', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
			if ($req->estado === $pendiente) {
				try {
					$this->validaRequisicionModeloContraArbol($req);
				} catch (\RuntimeException $e) {
					$aviso = $e->getMessage();
				}
			}
		}

		return [
			'movimientos' => $enriquecidos->values()->all(),
			'aviso_grabacion_pendiente' => $aviso,
		];
	}

	/**
	 * @param  int  $empresaId  empresa elegida en el formulario (alta)
	 * @param  int  $requisicionId  id de requisición en edición, o 0 en alta
	 */
	public function avisoGrabacionRequisicionAjax(int $empresaId, int $requisicionId): ?string
	{
		if ($requisicionId > 0) {
			$req = $this->requisicionRepository->find($requisicionId);
			if (! $req) {
				return null;
			}
			$pendiente = Requisicion_Estado::$enumEstado[array_search('P', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
			if ($req->estado !== $pendiente) {
				return null;
			}
			try {
				$this->validaRequisicionModeloContraArbol($req);
			} catch (\RuntimeException $e) {
				return $e->getMessage();
			}

			return null;
		}

		return $this->mensajeEmpresaSinArbolRequisicionActivoUnico($empresaId);
	}

	/**
	 * @param \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection $movimientos
	 * @return \Illuminate\Support\Collection
	 */
	public function adjuntaIndicacionEstadoRequisicionMovimientos($movimientos, int $requisicion_id)
	{
		$req = $this->requisicionRepository->find($requisicion_id);
		$nombreTipo = $this->nombreTipoArbolRequisiciones();
		$trees = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($nombreTipo, (int) $req->empresa_id);
		$arbol = $trees->first();
		$cc = null;
		$ccIndicacionError = null;
		try {
			$cc = $this->centroCostoParaArbolAprobacionDesdeModelo($req);
		} catch (\RuntimeException $e) {
			$ccIndicacionError = $e->getMessage();
			$cc = (int) $req->centrocosto_id;
		}

		$totalesReq = RequisicionTotalesCabecera::desdeModelo($req, $this->cotizacionQuery);

		return $movimientos->map(function ($m) use ($arbol, $cc, $req, $ccIndicacionError, $totalesReq) {
			$row = $m->toArray();
			if ($ccIndicacionError !== null) {
				$row['indicacion_estado_requisicion'] = $ccIndicacionError;

				return $row;
			}
			if ($arbol) {
				$nivelCfg = $this->encuentraNivelCoincidente(
					$arbol,
					$cc,
					(int) $m->nivel,
					$req->fecha,
					$totalesReq['monto'],
					$totalesReq['moneda_id']
				);
				$est = $nivelCfg && filled($nivelCfg->requisicion_estado_al_aprobar)
					? trim((string) $nivelCfg->requisicion_estado_al_aprobar)
					: null;
				$row['indicacion_estado_requisicion'] = $est !== null && $est !== ''
					? 'Tras aprobar este nivel, la requisición quedaría en estado: '.$est.'.'
					: 'Sin estado configurado al aprobar este nivel (continúa el circuito del árbol).';
			} else {
				$row['indicacion_estado_requisicion'] = 'No hay árbol de aprobación activo para la empresa de esta requisición.';
			}

			return $row;
		})->values();
	}

	private function centroCostoParaArbolDesdeRequest(array $data): int
	{
		$articulo_ids = $data['articulo_ids'] ?? [];
		if (! is_array($articulo_ids)) {
			return (int) ($data['centrocosto_id'] ?? 0);
		}
		$headerCc = (int) ($data['centrocosto_id'] ?? 0);
		$n = count($articulo_ids);
		$ids = [];
		for ($i = 0; $i < $n; $i++) {
			$aid = $articulo_ids[$i] ?? null;
			if ($aid === null || $aid === '') {
				continue;
			}
			$cant = (float) ($data['cantidades'][$i] ?? 0);
			if ($cant <= 0) {
				continue;
			}
			$dest = isset($data['centrocostodestino_ids'][$i]) && $data['centrocostodestino_ids'][$i] !== ''
				? (int) $data['centrocostodestino_ids'][$i]
				: $headerCc;
			$ids[] = $dest;
		}
		$unique = array_unique($ids);
		if (count($unique) > 1) {
			throw new \RuntimeException('Todos los renglones deben tener el mismo centro de costo de destino para el árbol de aprobación.');
		}
		if (count($unique) === 1) {
			return (int) reset($unique);
		}

		return $headerCc;
	}

	/**
	 * @return array{0: float, 1: int|null}
	 */
	private function montoYMonedaDesdeLineasRequisicionRequest(array $data): array
	{
		return RequisicionTotalesCabecera::montoYMonedaDesdeRequest($data, $this->cotizacionQuery);
	}
}

