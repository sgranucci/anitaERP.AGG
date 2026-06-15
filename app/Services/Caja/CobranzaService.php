<?php
namespace App\Services\Caja;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Repositories\Configuracion\SeteosalidaRepositoryInterface;
use App\Repositories\Caja\Caja_MovimientoRepositoryInterface;
use App\Repositories\Caja\Caja_Movimiento_CuentacajaRepositoryInterface;
use App\Repositories\Caja\Caja_Movimiento_EstadoRepositoryInterface;
use App\Repositories\Caja\Caja_Movimiento_ArchivoRepositoryInterface;
use App\Repositories\Caja\CobranzaRepositoryInterface;
use App\Repositories\Caja\Cobranza_ComprobanteRepositoryInterface;
use App\Repositories\Caja\Cobranza_RetencionRepositoryInterface;
use App\Repositories\Caja\Cobranza_EstadoRepositoryInterface;
use App\Repositories\Caja\Cobranza_ArchivoRepositoryInterface;
use App\Repositories\Caja\ChequeRepositoryInterface;
use App\Repositories\Caja\Tipotransaccion_CajaRepositoryInterface;
use App\Repositories\Caja\ConceptogastoRepositoryInterface;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use App\Repositories\Ventas\Cliente_CuentacorrienteRepositoryInterface;
use App\Repositories\Ventas\Cliente_Cuentacorriente_AplicacionRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Caja\MediopagoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Configuracion\Retencion_CobranzaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\CobranzaNumeracionTransaccion;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Services\Caja\CobranzaDescuentoNotaCreditoService;
use App\Services\Ordenventa\OrdenventaService;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Moneda;
use App\Models\Caja\Cobranza;
use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Caja_Movimiento_Estado;
use App\Models\Caja\Cobranza_Estado;
use App\Models\Ventas\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LynX39\LaraPdfMerger\Facades\PdfMerger;
use Carbon\Carbon;
use App;
use Auth;
use DB;
use Exception;
use PDF;
use App\ApiAnita;

class CobranzaService 
{
	private $caja_movimientoRepository;
    private $caja_movimiento_cuentacajaRepository;
    private $caja_movimiento_estadoRepository;
    private $caja_movimiento_archivoRepository;
	private $cobranzaRepository;
	private $cobranza_comprobanteRepository;
	private $cobranza_retencionRepository;
	private $cobranza_estadoRepository;
	private $cobranza_archivoRepository;
	private $chequeRepository;
	private $tipoasientoRepository;
	private $cuentacontableRepository;
    private $centrocostoRepository;
	private $asientoRepository;
	private $asiento_movimientoRepository;
	private $cuentacajaRepository;
	private $tipotransaccion_cajaRepository;
	private $conceptogastoRepository;
	private $retencion_cobranzaRepository;
	private $clienteRepository;
	private $cliente_cuentacorrienteRepository;
	private $cliente_cuentacorriente_aplicacionRepository;
	private $monedaRepository;
	private $empresaRepository;
	private $ordenventaService;

    public function __construct(Caja_MovimientoRepositoryInterface $caja_movimientorepository,
                                Caja_Movimiento_CuentacajaRepositoryInterface $caja_movimiento_cuentacajarepository,
                                Caja_Movimiento_EstadoRepositoryInterface $caja_movimiento_estadorepository,
                                Caja_Movimiento_ArchivoRepositoryInterface $caja_movimiento_archivorepository,
								CobranzaRepositoryInterface $cobranzaRepository,
								Cobranza_ComprobanteRepositoryInterface $cobranza_comprobanteRepository,
								Cobranza_RetencionRepositoryInterface $cobranza_retencionRepository,
								Cobranza_EstadoRepositoryInterface $cobranza_estadoRepository,
								Cobranza_ArchivoRepositoryInterface $cobranza_archivoRepository,
								ChequeRepositoryInterface $chequeRepository,
								ConceptogastoRepositoryInterface $conceptogastorepository,
								TipoasientoRepositoryInterface $tipoasientorepository,
								CuentacajaRepositoryInterface $cuentacajarepository,
								MediopagoRepositoryInterface $mediopagorepository,
								CuentacontableRepositoryInterface $cuentacontablerepository,
                                CentroCostoRepositoryInterface $centrocostorepository,
								AsientoRepositoryInterface $asientorepository,
								Asiento_MovimientoRepositoryInterface $asiento_movimientorepository,
								SeteosalidaRepositoryInterface $seteosalidarepository,
								TipoTransaccion_CajaRepositoryInterface $tipotransaccion_cajarepository,
								Retencion_CobranzaRepositoryInterface $retencion_cobranzaRepository,
								ClienteRepositoryInterface $clienterepository,
								Cliente_CuentacorrienteRepositoryInterface $cliente_cuentacorrienterepository,
								Cliente_Cuentacorriente_AplicacionRepositoryInterface $cliente_cuentacorriente_aplicacionrepository,
								MonedaRepositoryInterface $monedaRepository,
								EmpresaRepositoryInterface $empresarepository,
								OrdenventaService $ordenventaService,
								)
    {
		$this->caja_movimientoRepository = $caja_movimientorepository;
        $this->caja_movimiento_cuentacajaRepository = $caja_movimiento_cuentacajarepository;
        $this->caja_movimiento_estadoRepository = $caja_movimiento_estadorepository;
        $this->caja_movimiento_archivoRepository = $caja_movimiento_archivorepository;
		$this->cobranzaRepository = $cobranzaRepository;
		$this->cobranza_comprobanteRepository = $cobranza_comprobanteRepository;
		$this->cobranza_retencionRepository = $cobranza_retencionRepository;
		$this->cobranza_estadoRepository = $cobranza_estadoRepository;
		$this->cobranza_archivoRepository = $cobranza_archivoRepository;
		$this->chequeRepository = $chequeRepository;
		$this->conceptogastoRepository = $conceptogastorepository;
		$this->tipoasientoRepository = $tipoasientorepository;
		$this->asientoRepository= $asientorepository;
		$this->asiento_movimientoRepository= $asiento_movimientorepository;
		$this->seteoSalidaRepository = $seteosalidarepository;
		$this->cuentacajaRepository = $cuentacajarepository;
		$this->cuentacontableRepository = $cuentacontablerepository;
        $this->centrocostoRepository = $centrocostorepository;
		$this->tipotransaccion_cajaRepository = $tipotransaccion_cajarepository;
		$this->retencion_cobranzaRepository = $retencion_cobranzaRepository;
		$this->clienteRepository = $clienterepository;
		$this->cliente_cuentacorrienteRepository = $cliente_cuentacorrienterepository;
		$this->cliente_cuentacorriente_aplicacionRepository = $cliente_cuentacorriente_aplicacionrepository;
		$this->mediopagoRepository = $mediopagorepository;
		$this->monedaRepository = $monedaRepository;
		$this->empresaRepository = $empresarepository;
		$this->ordenventaService = $ordenventaService;
    }

	public function guardaCobranza($request, $origen = null)
	{
		session(['empresa_id' => $request->empresa_id]);
		$data = $request->all();

		PeriodoContableCierreSupport::assertOperacionPermitida(
			(int) ($data['empresa_id'] ?? 0),
			(string) ($data['fecha'] ?? date('Y-m-d')),
			PeriodoContableCierreSupport::ALCANCE_COBRANZA
		);

   		// Crea estado
	   	$data['fechas'][] = Carbon::now();
//dd($data);
		if (config('cobranza.GRABACION') == "CON_PRECARGA")
		{
	   		$data['estados'][] = Cobranza_Estado::$enumEstado[1]['nombre'];
	   		$data['observacionestados'][] = "Alta de Pre Carga";
			$data['estado'] = Cobranza_Estado::$enumEstado[1]['nombre'];
		}
		else
		{
	   		$data['estados'][] = Cobranza_Estado::$enumEstado[0]['nombre'];
	   		$data['observacionestados'][] = "Alta de Cobranza";
			$data['estado'] = Cobranza_Estado::$enumEstado[0]['nombre'];
		}
		$data['usuario_ids'][] = Auth::user()->id;

		return CobranzaNumeracionTransaccion::conExclusividad(
			(int) $data['empresa_id'],
			(int) $data['tipotransaccion_caja_id'],
			function () use ($data, $request, $origen) {
				$data['numerotransaccion'] = CobranzaNumeracionTransaccion::calcularSiguienteNumeroSecuencialBd(
					(int) $data['empresa_id'],
					(int) $data['tipotransaccion_caja_id'],
				);
				$data['usuario_id'] = Auth::user()->id;

				if (! isset($data['detalle'])) {
					$data['detalle'] = 'Cobranza Nro. '.$data['numerotransaccion'];
				}

				if (isset($data['ordenventa_id']) && $data['ordenventa_id'] > 0) {
					$ordenventa = $this->ordenventaService->leeOrdenVenta($data['ordenventa_id']);

					if ($ordenventa) {
						$data['detalle'] .= ' Orden de Venta Nro. '.$ordenventa->numeroordenventa;
					}
				}

				if ($origen) {
					$this->procesarDescuentosAntesDeGrabar($data);

					$cobranza = $this->cobranzaRepository->create($data);

					if (! $cobranza) {
						throw new Exception('Error en grabacion');
					}

					Self::agrega($data, $cobranza, $request);
					$this->persistirDescuentosCobranza($cobranza->id, $data);
				} else {
					DB::beginTransaction();
					try {
						$this->procesarDescuentosAntesDeGrabar($data);

						$cobranza = $this->cobranzaRepository->create($data);

						if ($cobranza == 'Error') {
							throw new Exception('Error en grabacion');
						}

						if ($cobranza) {
							Self::agrega($data, $cobranza, $request);
							$this->persistirDescuentosCobranza($cobranza->id, $data);
						}

						$anita = self::grabaAnita(
							$data['fecha'],
							'COB',
							'X',
							0,
							$data['numerotransaccion'],
							$data['totalfinalcobranza'],
							$data['cotizacion_cobranza'],
							$data['detalle'],
							$data['empresa_id'],
							$data,
						);

						if (isset($anita['error'])) {
							throw new Exception('Error en grabacion anita. '.$anita['mensaje']);
						}

						if ($data['ordenventa_id'] > 0) {
							$this->ordenventaService->marcaOrdenVentaCobrada($data['ordenventa_id']);
						}

						DB::commit();
					} catch (\Exception $e) {
						DB::rollback();

						return ['errores' => $e->getMessage()];
					}
				}

				return ['mensaje' => 'ok'];
			},
		);
	}

	private function agrega($data, $cobranza, $request)
	{
		$data['cobranza_id'] = $cobranza->id;

		$cobranza_comprobante = $this->cobranza_comprobanteRepository->create($data, $cobranza->id);
		
		$cobranza_retencion = $this->cobranza_retencionRepository->create($data, $cobranza->id);
		$cobranza_estado = $this->cobranza_estadoRepository->create($data, $cobranza->id);
		$cobranza_archivo = $this->cobranza_archivoRepository->create($request, $cobranza->id);

		$caja_movimiento = $this->caja_movimientoRepository->create($data);
		$caja_movimiento_cuentacaja = $this->caja_movimiento_cuentacajaRepository->create($data, $caja_movimiento->id);

   		// Crea estado
		$data['fechas'] = []; $data['estados'] = []; $data['observacionestados'] = [];

	   	$data['fechas'][] = Carbon::now();
	   	$data['estados'][] = Caja_Movimiento_Estado::$enumEstado[0]['valor'];
	   	$data['observacionestados'][] = "Alta de Movimiento de Caja";

		$caja_movimiento_estado = $this->caja_movimiento_estadoRepository->create($data, $caja_movimiento->id);

		// Graba cheques
		$cheque = $this->chequeRepository->guardarChequeCobranza($data, 'create', $cobranza->id);

		// Graba asiento contable
		if (isset($data['cuentacontable_ids']) && $data['estado'] != Cobranza_Estado::$enumEstado[1]['nombre'])
		{
			// Busca tipo de asiento de tesoreria
			$tipoasiento = $this->tipoasientoRepository->findPorAbreviatura('TES');

			if ($tipoasiento)
				$data['tipoasiento_id'] = $tipoasiento->id;
			else
				throw new Exception('Error en grabacion, no existe tipo de asiento de tesoreria');

			// Arma el asiento contable
			$data['moneda_ids'] = $data['monedaasiento_ids'];
			$data['centrocosto_ids'] = $data['centrocostoasiento_ids'];
			$data['debes'] = $data['debeasientos'];
			$data['haberes'] = $data['haberasientos'];
			$data['cotizaciones'] = $data['cotizacionasientos'];
			$data['observaciones'] = $data['observacionasientos'];
			$data['cobranza_id'] = $cobranza->id;

			$data['observacion'] = $data['detalle'];

			for ($i = 0; $i < count($data['observaciones']); $i++)
			{
				if ($data['observaciones'][$i] == null)
					$data['observaciones'][$i] = $data['detalle'];
			}

			$asiento = $this->asientoRepository->create($data);

			if ($asiento == 'Error')
				throw new Exception('Error en grabacion anita.');

			if ($asiento)
				$asiento_movimiento = $this->asiento_movimientoRepository->create($data, $asiento->id);
		}

		// Verifica si tiene que crear un anticipo de cuenta corriente
		$totalCobranzas = $data['totalcobranzas'];
		$monedaCobranza_ids = $data['moneda_cobranza_ids'];

		for ($i = 0; $i < count($totalCobranzas); $i++)
		{
			if ($totalCobranzas[$i] > 0)
			{
				$cliente_cuentacorriente = $this->cliente_cuentacorrienteRepository->create([
						'fecha' => $data['fecha'],
						'fechavencimiento' => $data['fecha'],
						'cliente_id' => $data['cliente_id'],
						'total' => -$totalCobranzas[$i],
						'moneda_id' => $monedaCobranza_ids[$i],
						'cotizacion' => $data['cotizacion_cobranza'],
						'cobranza_id' => $data['cobranza_id'],
						'empresa_id' => $data['empresa_id']
				]);		
			}
		}
	}

    public function actualizaCobranza($request, $id, $origen = null)
    {
        session(['empresa_id' => $request->empresa_id]);
		$data = $request->all();

		PeriodoContableCierreSupport::assertOperacionPermitida(
			(int) ($data['empresa_id'] ?? 0),
			(string) ($data['fecha'] ?? date('Y-m-d')),
			PeriodoContableCierreSupport::ALCANCE_COBRANZA
		);

		// Crea estado
		$data['fechas'][] = Carbon::now();
		$data['estados'][] = $data['estado'];
		$data['observacionestados'][] = "Actualización de Cobranza";
		$data['usuario_ids'][] = Auth::user()->id;

		if ($origen)
			Self::actualiza($data, $id, $request);
		else
		{
			DB::beginTransaction();
			try
			{
				Self::actualiza($data, $id, $request);

				if ($data['ordenventa_id'] > 0)
					$ordenventa = $this->ordenventaService->marcaOrdenVentaCobrada($data['ordenventa_id']);

				// Graba anita por cobranza
				//$anita = self::grabaAnita($data['fecha'], 'COB', 'X', 0, $data['numerotransaccion'], $data['totalfinalcobranza'], $data['cotizacion_cobranza'],
				//				$data['detalle'], $data['empresa_id'], $data);

				//if (isset($anita['error']))
				//{
				//	if ($anita['error'] == 'Error')
				//		throw new Exception('Error en grabacion anita. '.$anita['mensaje']);
				//}

				DB::commit();
			} catch (\Exception $e) {
				DB::rollback();

				return ['errores' => $e->getMessage()];
			}
		}
        return ['mensaje' => 'ok'];
    }

	private function actualiza($data, $id, $request)
	{
		$this->procesarDescuentosAntesDeGrabar($data);

		// Graba cobranza 
		$cobranza = $this->cobranzaRepository->update($data, $id);

		$cobranza_comprobante = $this->cobranza_comprobanteRepository->update($data, $id);
		$cobranza_retencion = $this->cobranza_retencionRepository->update($data, $id);
		$cobranza_estado = $this->cobranza_estadoRepository->update($data, $id);
		$cobranza_archivo = $this->cobranza_archivoRepository->update($request, $id);

		// Graba movimiento de caja
		$caja_movimiento_id = $data['caja_movimiento_id'];

		$data['cobranza_id'] = $id;

		$caja_movimiento = $this->caja_movimientoRepository->update($data, $caja_movimiento_id);

		if ($caja_movimiento === 'Error')
			throw new Exception('Error en grabacion anita.');

		// Graba movimientos de cuentas de caja
		$this->caja_movimiento_cuentacajaRepository->update($data, $caja_movimiento_id);

		// Graba movimientos de estados del movimiento de caja
		$this->caja_movimiento_estadoRepository->update($data, $caja_movimiento_id);

		// Graba cheques 
		$cheque = $this->chequeRepository->guardarChequeCobranza($data, 'update', $id);

		// Graba asiento
		if (isset($data['cuentacontable_ids']) && $data['estado'] != Cobranza_Estado::$enumEstado[1]['nombre'])
		{
			// Busca el asiento correspondiente a la cobranza
			$asiento = $this->asientoRepository->leeAsientoPorClave($id, 'cobranza_id');

			if (count($asiento) > 0)
			{
				$asiento_id = $asiento[0]->id;

				if (!isset($data['numeroasiento']))
					$data['numeroasiento'] = $asiento[0]->numeroasiento;
			}

			// Arma el asiento contable
			$data['moneda_ids'] = $data['monedaasiento_ids'];

			if (!isset($data['centrocostoasiento_ids']))
			{
				for ($i = 0; $i < count($data['moneda_ids']); $i++)
					$data['centrocosto_ids'][$i] = null;
			}
			else
				$data['centrocosto_ids'] = $data['centrocostoasiento_ids'];

			$data['debes'] = $data['debeasientos'];
			$data['haberes'] = $data['haberasientos'];
			$data['cotizaciones'] = $data['cotizacionasientos'];
			$data['observaciones'] = $data['observacionasientos'];
			$data['cobranza_id'] = $id;
			$data['observacion'] = $data['detalle'];

			for ($i = 0; $i < count($data['observaciones']); $i++)
			{
				if ($data['observaciones'][$i] == null)
					$data['observaciones'][$i] = $data['detalle'];
			}

			if (count($asiento) > 0)
			{
				// Busca tipo de asiento de tesoreria
				$tipoasiento = $this->tipoasientoRepository->findPorAbreviatura('TES');

				if ($tipoasiento)
					$data['tipoasiento_id'] = $tipoasiento->id;

				$data['tipo'] = 'COB';
				$data['letra'] = 'X';
				$data['sucursal'] = 0;
				$data['nro'] = $data['numerotransaccion'];

				$asiento = $this->asientoRepository->update($data, $asiento_id);

				if ($asiento === 'Error')
					throw new Exception('Error en grabacion anita.');

				// Graba movimientos del asiento
				$this->asiento_movimientoRepository->update($data, $asiento_id);
			}
			else
			{
				// Busca tipo de asiento de tesoreria
				$tipoasiento = $this->tipoasientoRepository->findPorAbreviatura('TES');

				if ($tipoasiento)
					$data['tipoasiento_id'] = $tipoasiento->id;
				else
					throw new Exception('Error en grabacion, no existe tipo de asiento de tesoreria');

				$data['tipo'] = 'COB';
				$data['letra'] = 'X';
				$data['sucursal'] = 0;
				$data['nro'] = $data['numerotransaccion'];

				// El asiento es con fecha de hoy
				$data['fecha'] = Carbon::now()->format("Y-m-d");

				$asiento = $this->asientoRepository->create($data);

				if ($asiento == 'Error')
					throw new Exception('Error en grabacion anita.');

				if ($asiento)
					$asiento_movimiento = $this->asiento_movimientoRepository->create($data, $asiento->id);
			}
		}
		// Verifica si tiene que crear un anticipo de cuenta corriente con cobranza != 0 y venta en null
		$cliente_cuentacorriente = $this->cliente_cuentacorrienteRepository->buscaPorVentaCobranza(null, $data['cobranza_id']);

		foreach($cliente_cuentacorriente as $cobranza)
		{
			// Borra el anticipo de la cuenta corriente
			$this->cliente_cuentacorrienteRepository->find($cobranza->id)->delete();
		}

		$totalCobranzas = $data['totalcobranzas'];
		$monedaCobranza_ids = $data['moneda_cobranza_ids'];
		for ($i = 0; $i < count($totalCobranzas); $i++)
		{
			if ($totalCobranzas[$i] > 0)
			{
				$cliente_cuentacorriente = $this->cliente_cuentacorrienteRepository->create([
						'fecha' => $data['fecha'],
						'fechavencimiento' => $data['fecha'],
						'cliente_id' => $data['cliente_id'],
						'total' => -$totalCobranzas[$i],
						'moneda_id' => $monedaCobranza_ids[$i],
						'cotizacion' => $data['cotizacion_cobranza'],
						'cobranza_id' => $data['cobranza_id'],
						'empresa_id' => $data['empresa_id']
				]);		
			}
		}

		$this->persistirDescuentosCobranza($id, $data);
	}

	/**
	 * @param  array<string, mixed>  $data
	 */
	private function procesarDescuentosAntesDeGrabar(array &$data): void
	{
		/** @var CobranzaDescuentoNotaCreditoService $descuentoService */
		$descuentoService = app(CobranzaDescuentoNotaCreditoService::class);
		$descuentos = $descuentoService->parseDescuentosDesdeRequest($data);

		if ($descuentos === []) {
			$data['_cobranza_descuentos'] = [];
			$data['_cobranza_descuentos_emitidos'] = [];

			return;
		}

		if (! can('generar-nota-de-credito', false)) {
			throw new Exception('No tiene permiso para generar notas de crédito por descuento en cobranza.');
		}

		$data['_cobranza_descuentos'] = $descuentos;
		$data['_cobranza_descuentos_emitidos'] = [];

		if (CobranzaDescuentoNotaCreditoService::debeEmitirNotasCredito((string) ($data['estado'] ?? ''))) {
			$data['_cobranza_descuentos_emitidos'] = $descuentoService->emitirDescuentosPendientes($data, $descuentos);
		}
	}

	/**
	 * @param  array<string, mixed>  $data
	 */
	private function persistirDescuentosCobranza(int $cobranzaId, array $data): void
	{
		$descuentos = $data['_cobranza_descuentos'] ?? [];
		if ($descuentos === []) {
			return;
		}

		/** @var CobranzaDescuentoNotaCreditoService $descuentoService */
		$descuentoService = app(CobranzaDescuentoNotaCreditoService::class);
		$emitidos = $data['_cobranza_descuentos_emitidos'] ?? [];
		$descuentoService->persistirDescuentos($cobranzaId, $descuentos, $emitidos);
	}

	public function generaAsientoContable(array $data)
	{
		$datosCaja = json_decode($data['datoscaja']);
		$datosContables = json_decode($data['datoscontables']);
		$datosCheque = json_decode($data['datoscheques']);
		$datosRetencion = json_decode($data['datosretenciones']);
		$datosComprobantes = json_decode($data['datoscomprobantes']);
		$tipotransaccion_caja_id = json_decode($data['tipotransaccion_caja_id']);
		$empresa_id = json_decode($data['empresa_id']);
//logger()->error('Error crítico', ['contexto' => $datosCaja]);
		$tipotransaccion_caja = $this->tipotransaccion_cajaRepository->find($tipotransaccion_caja_id);
		$signo = 1;
		if ($tipotransaccion_caja)
		{
			if ($tipotransaccion_caja->signo == 'I')
				$signo = 1;
			else
				$signo = -1;
		}

		// Arma cuentas contables de cada imputacion de caja
		$asiento = [];
		if (count($datosContables) > 0)
		{
			foreach($datosContables as $imputacionContable)
			{
				$cuentacontable = $this->cuentacontableRepository->find($imputacionContable->cuentacontable_ids);

				if ($cuentacontable)
				{
					if ($imputacionContable->debeasientos != 0)
						$d_h = 'D';
					else
						$d_h = 'H';

					$asiento[] = [ 'cuentacontable_id' => $imputacionContable->cuentacontable_ids,
							'codigo' => $cuentacontable->codigo,
							'nombre' => $cuentacontable->nombre,
							'moneda_id' => $imputacionContable->monedaasiento_ids,
							'cotizacion' => $imputacionContable->cotizacionasientos,
							'centrocosto_id' => $imputacionContable->centrocostoasiento_ids,
							'debe' => $imputacionContable->debeasientos,
							'haber' => $imputacionContable->haberasientos,
							'd_h' => $d_h,
							'observacion' => $imputacionContable->observacionasientos,
							'carga_cuentacontable_manual' => $imputacionContable->carga_cuentacontable_manuales
							];
				}
			}
		}
		else
		{
			foreach($datosCaja as $movimiento)
			{
				// Busca la cuenta contable de la cuenta de caja 
				$cuentacaja = $this->cuentacajaRepository->find($movimiento->cuentacaja_ids);

				// Busca si la imputacion ya existe
				if ($cuentacaja)
				{
					if ((float) $movimiento->montos * $signo > 0)
						$d_h = 'D';
					else
						$d_h = 'H';

					Self::agregaCuenta($asiento, $cuentacaja->cuentacontable_id, $movimiento->moneda_ids, $movimiento->cotizaciones, $d_h, $movimiento->montos);
				}
			}

			// Agrega cuentas de cheques
			foreach($datosCheque as $cheque)
			{
				// Busca codigo de cuenta en funcion de la empresa
				$codigocuentacontable = config('cobranza.VALORES_A_DEPOSITAR.'.$empresa_id);

				$cuentacontable = $this->cuentacontableRepository->findPorCodigo($empresa_id, $codigocuentacontable);

				if ($cuentacontable)
				{
					if ((float) $cheque->montos * $signo > 0)
						$d_h = 'D';
					else
						$d_h = 'H';

					Self::agregaCuenta($asiento, $cuentacontable->id, $cheque->moneda_ids, $cheque->cotizaciones, $d_h, $cheque->montos);				
				}
			}

			// Agrega retenciones
			foreach($datosRetencion as $retencion)
			{
				// Lee la retencion de la tabla para sacar la cuenta contable
				$retencion_cobranza = $this->retencion_cobranzaRepository->find($retencion->cuenta_retencion_ids);

				if ($retencion_cobranza)
				{
					// Busca en funcion de la empresa
					foreach ($retencion_cobranza->retencion_cobranza_cuentacontables as $cuenta)
					{
						if ($cuenta->empresa_id == $empresa_id)
							$cuentacontable_id = $cuenta->cuentacontable_id;
					}
					if ((float) $retencion->montos * $signo > 0)
						$d_h = 'D';
					else
						$d_h = 'H';

					Self::agregaCuenta($asiento, $cuentacontable_id, $retencion->moneda_ids, $retencion->cotizaciones, $d_h, $retencion->montos);				
				}				
			}
		}

		// Agrega la contrapartida sumando los comprobantes
		if (count($datosContables) == 0)
		{
			foreach($datosComprobantes as $comprobante)
			{
				$cuentacontable = $this->cuentacontableRepository->findPorCodigo($empresa_id, config('cliente.DEUDORES_POR_VENTAS'));

				if ($cuentacontable)
				{
					// Invierte signos
					if ((float) $comprobante->montos * $signo > 0)
						$d_h = 'H';
					else
						$d_h = 'D';

					Self::agregaCuenta($asiento, $cuentacontable->id, $comprobante->moneda_ids, $comprobante->cotizaciones, $d_h, $comprobante->montos);				
				}				
			}

			// Agrega si se paga de mas va contra anticipo de clientes
			$monedaAsiento_id = $asiento[0]['moneda_id'];
			$cotizacion = $asiento[0]['cotizacion'];

			// Suma monto del asiento
			$totalDebe = $totalHaber = 0.;
			foreach($asiento as $movimiento)
			{
				$coef = calculaCoeficienteMoneda($monedaAsiento_id, $movimiento['moneda_id'], $movimiento['cotizacion']);

				if ($movimiento['debe'])
					$totalDebe += $movimiento['debe'] * $coef;

				if ($movimiento['haber'])
					$totalHaber += $movimiento['haber'] * $coef;
			}

			if (abs($totalDebe-$totalHaber) > 0.009)
			{
				$cuentacontable = $this->cuentacontableRepository->findPorCodigo($empresa_id, config('cliente.ANTICIPO_DE_CLIENTES'));

				if ($cuentacontable)
				{
					$diferencia = $totalDebe - $totalHaber;

					// Invierte signos
					if ($diferencia > 0)
						$d_h = 'H';
					else
						$d_h = 'D';

					Self::agregaCuenta($asiento, $cuentacontable->id, $monedaAsiento_id, $cotizacion, $d_h, abs($diferencia));
				}
			}
		}
		//logger()->error('Error crítico', ['contexto' => $asiento]);
		return ['mensaje' => 'ok', 'asiento' => $asiento];
	}

	private function agregaCuenta(&$asiento, $cuentacontable_id, $moneda_id, $cotizacion, $d_h, $monto)
	{
		if ($d_h == 'D')
		{
			$debe = $monto; $haber = 0;
		}
		else
		{
			$debe = 0; $haber = $monto;
		}

		for ($i = 0, $flExiste = false; $i < count($asiento) && !$flExiste; $i++)
		{
			if ($asiento[$i]['cuentacontable_id'] == $cuentacontable_id &&
				$asiento[$i]['moneda_id'] == $moneda_id &&
				$asiento[$i]['cotizacion'] == $cotizacion &&
				$asiento[$i]['d_h'] == $d_h)
				$flExiste = true;
		}
		if (!$flExiste)
		{
			$cuentacontable = $this->cuentacontableRepository->find($cuentacontable_id);

			if ($cuentacontable)
				$asiento[] = [ 'cuentacontable_id' => $cuentacontable_id,
								'codigo' => $cuentacontable->codigo,
								'nombre' => $cuentacontable->nombre,
								'moneda_id' => $moneda_id,
								'cotizacion' => (float) $cotizacion,
								'centrocosto_id' => 0,
								'debe' => (float) $debe,
								'haber' => (float) $haber,
								'd_h' => $d_h,
								'observacion' => '',
								'carga_cuentacontable_manual' => 'N'
						];
		}
		else
		{
			$asiento[$i]['debe'] += $debe;
			$asiento[$i]['haber'] += $haber;
		}	
	}

	// Graba cobranza en Anita
	public function grabaAnita($fecha, $tipo, $letra, $puntoVenta, $numeroRecibo, $totalRecibo, $cotizacion,
								$leyenda, $empresa, $data,
								$servidor = null, $ifx_server = null)
	{
		$cliente = $this->clienteRepository->find($data['cliente_id']);
		$codigoCliente = $cliente->codigo;
		$numerodocumento = $cliente->numerodocumento;

		// Graba pago
        $apiAnita = new ApiAnita();

		$grabaAnita = array( 	'tabla' => 'pago', 
						'acc' => 'insert',
						'sistema' => 'che_ban',
            			'campos' => ' 
							pag_pro,
							pag_fecha,
							pag_tipo,
							pag_rec,
							pag_trec,
							pag_cotizacion,
							pag_leyenda,
							pag_entregado_a,
							pag_letra,
							pag_sucursal,
							pag_mov_ext,
							pag_cod_mon_me,
							pag_cobrador,
							pag_sucursal_p,
							pag_recibo_p,
							pag_sin_comision,
							pag_emp_sueldos,
							pag_legajo,
							pag_tipo_vale,
							pag_nro_vale,
							pag_vendedor,
							pag_usuario,
							pag_fecha_ult_act,
							pag_empresa,
							pag_fecha_pago,
							pag_documento_id',
            			'valores' => " 
							'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."', 
							'".date('Ymd', strtotime($fecha))."',
							'".substr($tipo, 0, 3)."',
							'".$numeroRecibo."',
							'".$totalRecibo."',
							'".$cotizacion."',
							'".$leyenda."',
							'".' '."',
							'".$letra."',
							'".$puntoVenta."',
							'".' '."',
							'".'0'."',
							'".'0'."',
							'".'0'."',
							'".'0'."',
							'".'0'."',
							'".'0'."',
							'".'0'."',
							'".' '."',
							'".'0'."',
							'".'0'."',
							'".Auth::user()->nombre."',
							'".date_format(Carbon::now(), 'Ymd')."',
							'".$empresa."',
							'".'0'."',
							'".'0'."'"
					);

        $pago = $apiAnita->apiCallEscritura($grabaAnita);

		// Graba cuentas de caja
		if (isset($data['cuentacaja_ids']))
		{
			$cuentacaja_ids = $data['cuentacaja_ids'];
			$moneda_ids = $data['moneda_ids'];
			$montos = $data['montos'];
			$cotizaciones = $data['cotizaciones'];
			$observaciones = $data['observaciones'];
			$fecha = $data['fecha'];

			for ($i = 0; $i < count($cuentacaja_ids); $i++)
			{
				// Lee la cuenta
				$cuentacaja = $this->cuentacajaRepository->find($cuentacaja_ids[$i]);
				
				$codigoCuenta = '';
				if ($cuentacaja)
					$codigoCuenta = $cuentacaja->codigo;
		
				// Graba auxpag
				$apiAnita = new ApiAnita();

				$grabaAnita = array( 	'tabla' => 'auxpag', 
								'acc' => 'insert',
								'sistema' => 'che_ban',
								'campos' => ' 
									axp_pro,
									axp_fecha,
									axp_rec,
									axp_tipo,
									axp_nro,
									axp_tipo_ap,
									axp_monto_ap,
									axp_cod_mon_co,
									axp_fecha_co,
									axp_banco,
									axp_letra_comp,
									axp_sucursal,
									axp_letra_cob,
									axp_sucursal_cob,
									axp_vendedor,
									axp_nro_interno,
									axp_empresa,
									axp_concepto,
									axp_cbu',
								'valores' => "   
									'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."', 
									'".date('Ymd', strtotime($fecha))."',
									'".$numeroRecibo."',
									'".substr($tipo, 0, 3)."',
									'".'0'."',
									'".'ATE'."',
									'".$montos[$i]."',
									'".$moneda_ids[$i]."',
									'".'0'."',
									'".str_pad($codigoCuenta, 8, "0", STR_PAD_LEFT)."',
									'".' '."',
									'".'0'."',
									'".$letra."',
									'".$puntoVenta."',
									'".'0'."',
									'".'0'."',
									'".$empresa."',
									'".'0'."',
									'".' '."'"
							);  
							
				$auxpag = $apiAnita->apiCallEscritura($grabaAnita);


				// Graba tesmov
				$apiAnita = new ApiAnita();

				$grabaAnita = array( 	'tabla' => 'tesmov', 
								'acc' => 'insert',
								'sistema' => 'che_ban',
								'campos' => ' 			
									tesv_cuenta,
									tesv_fecha_mov,
									tesv_fecha_dev,
									tesv_tipo,
									tesv_letra,
									tesv_sucursal,
									tesv_nro,
									tesv_importe,
									tesv_cotizacion,
									tesv_desc_mov,
									tesv_conciliado,
									tesv_contrapartida,
									tesv_nro_conc,
									tesv_fecha_conc,
									tesv_empresa,
									tesv_cod_mon',
								'valores' => " 
									'".str_pad($codigoCuenta, 8, "0", STR_PAD_LEFT)."',
									'".date('Ymd', strtotime($fecha))."',
									'".date('Ymd', strtotime($fecha))."',
									'".substr($tipo, 0, 3)."',
									'".$letra."',
									'".$puntoVenta."',
									'".$numeroRecibo."',
									'".$montos[$i]."',
									'".$cotizaciones[$i]."',
									'".$data['detalle']."',
									'".' '."',
									'".' '."',
									'".'0'."',
									'".'0'."',
									'".$empresa."',
									'".$moneda_ids[$i]."'"
							);
							
				$tesmov = $apiAnita->apiCallEscritura($grabaAnita);

			}
		}

		// Graba comprobantes
		if (isset($data['idcuentacorrientes']))
		{
			$cliente_cuentacorriente_ids = $data['idcuentacorrientes'];
			$venta_ids = $data['idventas'];
			$moneda_ids = $data['monedacomprobante_ids'];
			$montos = $data['montoaplicadocomprobantes'];
			$cotizaciones = $data['cotizacioncomprobantes'];
			$codigoComprobantes = $data['codigocomprobantes'];
			$saldoComprobantes = $data['saldocomprobantes'];

			for ($i = 0; $i < count($cliente_cuentacorriente_ids); $i++)
			{
				$codigo = $codigoComprobantes[$i];

				$tipoComprobante = substr($codigo, 0, 3);
				$letraComprobante = substr($codigo, 4, 1);
				$sucursalComprobante = substr($codigo, 6, 5);
				$nroComprobante = substr($codigo, 12, 8);
				
				// Graba climov	
				$apiAnita = new ApiAnita();

				$grabaAnita = array( 	'tabla' => 'climov', 
								'acc' => 'insert',
								'sistema' => 'ventas',
								'campos' => ' 
									cliv_cliente,
									cliv_tipo,
									cliv_letra,
									cliv_sucursal,
									cliv_nro,
									cliv_ref_tipo,
									cliv_ref_letra,
									cliv_ref_sucursal,
									cliv_ref_nro,
									cliv_fecha,
									cliv_fecha_vto,
									cliv_monto,
									cliv_cod_mon,
									cliv_cotizacion,
									cliv_nro_cuota,
									cliv_t_cobrado,
									cliv_fecha_cobro,
									cliv_cedio_a,
									cliv_estado,
									cliv_empresa',
								'valores' => " 
									'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."', 
									'".substr($tipo, 0, 3)."',
									'".$letra."',
									'".$puntoVenta."',
									'".$numeroRecibo."',
									'".$tipoComprobante."',
									'".$letraComprobante."',
									'".$sucursalComprobante."',
									'".$nroComprobante."',
									'".date('Ymd', strtotime($fecha))."',
									'".date('Ymd', strtotime($fecha))."',
									'".$montos[$i]."',
									'".$moneda_ids[$i]."',
									'".$cotizaciones[$i]."',
									'".'0'."',
									'".'0'."',
									'".'0'."',
									'".'0'."',
									'".'C'."',
									'".$empresa."'"
							);
				$climov = $apiAnita->apiCallEscritura($grabaAnita);

				
				// Modifica lo aplicado a la factura
				$apiAnita = new ApiAnita();

				$grabaAnita = array( 	'acc' => 'update', 
								'tabla' => 'climov',
								'sistema' => 'ventas',
								'valores' => " 
								cliv_t_cobrado   = cliv_t_cobrado + ".$montos[$i].",
								cliv_fecha_cobro = '".date('Ymd', strtotime($fecha))."',
								cliv_estado 	 = '".($saldoComprobantes[$i] != 0 ? 'I' : 'C')."' ",
								'whereArmado' => " WHERE 
									cliv_tipo     = '".$tipoComprobante."' AND
									cliv_letra    = '".$letraComprobante."' AND
									cliv_sucursal = '".$sucursalComprobante."' AND
									cliv_nro      = '".$nroComprobante."' " );

				$climov = $apiAnita->apiCallEscritura($grabaAnita);		
				
													
				// Graba aplmov
				$apiAnita = new ApiAnita();

				$grabaAnita = array('tabla' => 'aplmov', 
								'acc' => 'insert',
								'sistema' => 'ventas',
								'campos' => ' 
										aplv_tipo,
										aplv_letra,
										aplv_sucursal,
										aplv_nro,
										aplv_nro_cuota,
										aplv_ref_tipo,
										aplv_ref_letra,
										aplv_ref_sucursal,
										aplv_ref_nro,
										aplv_fecha,
										aplv_monto,
										aplv_cod_mon,
										aplv_cotizacion,
										aplv_tipo_cob,
										aplv_letra_cob,
										aplv_sucursal_cob,
										aplv_nro_cob,
										aplv_fecha_aplic',
								'valores' => " 
										'".$tipoComprobante."',
										'".$letraComprobante."',
										'".$sucursalComprobante."',
										'".$nroComprobante."',
										'".'1'."',
										'".substr($tipo, 0, 3)."',
										'".$letra."',
										'".$puntoVenta."',
										'".$numeroRecibo."',
										'".date('Ymd', strtotime($fecha))."',
										'".$montos[$i]."',
										'".$moneda_ids[$i]."',
										'".$cotizaciones[$i]."',
										'".substr($tipo, 0, 3)."',
										'".$letra."',
										'".$puntoVenta."',
										'".$numeroRecibo."',
										'".date('Ymd', strtotime($fecha))."'"
							);
				$aplmov = $apiAnita->apiCallEscritura($grabaAnita);

				
				// Graba auxpag del comprobante
				$apiAnita = new ApiAnita();

				$grabaAnita = array('tabla' => 'auxpag', 
								'acc' => 'insert',
								'sistema' => 'che_ban',
								'campos' => ' 
									axp_pro,
									axp_fecha,
									axp_rec,
									axp_tipo,
									axp_nro,
									axp_tipo_ap,
									axp_monto_ap,
									axp_cod_mon_co,
									axp_fecha_co,
									axp_banco,
									axp_letra_comp,
									axp_sucursal,
									axp_letra_cob,
									axp_sucursal_cob,
									axp_vendedor,
									axp_nro_interno,
									axp_empresa,
									axp_concepto,
									axp_cbu',
								'valores' => "   
									'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."', 
									'".date('Ymd', strtotime($fecha))."',
									'".$numeroRecibo."',
									'".substr($tipo, 0, 3)."',
									'".$nroComprobante."',
									'".$tipoComprobante."',
									'".$montos[$i]."',
									'".$moneda_ids[$i]."',
									'".'0'."',
									'".'000001'."',
									'".$letraComprobante."',
									'".$sucursalComprobante."',
									'".$letra."',
									'".$puntoVenta."',
									'".'0'."',
									'".'0'."',
									'".$empresa."',
									'".'0'."',
									'".' '."'"
							);  
							
				$auxpag = $apiAnita->apiCallEscritura($grabaAnita);

			}
		}

		// Graba cheques
		if (isset($data['cheque_ids']))
		{
		    $cheque_ids = $data['cheque_ids'];
			$fechapagos = $data['fechapagos'];
			$codigobancos = $data['codigobancos'];
			$nombrebancos = $data['nombrebancos'];
			$numerocheques = $data['numerocheques'];
			$cotizacioncheques = $data['cotizacioncheques'];
			$sucursalpagos = $data['sucursalpagos'];
            $cuentalibradoras = $data['cuentalibradoras'];
            $monedacheque_ids = $data['monedacheque_ids'];
			$montocheques = $data['montocheques'];

			for ($i = 0; $i < count($cheque_ids); $i++)
			{
				// Lee ultimo numero de cheque 
				$numeroInterno = Selft::traeUltimoChequeDeTercero();

				if ($data['fechapago'] > $data['fecha'])
					$camara = '2';
				else
					$camara = '1';

				// Graba ctermae del comprobante
				$apiAnita = new ApiAnita();

				$grabaAnita = array( 	'tabla' => 'ctermae', 
								'acc' => 'insert',
								'sistema' => 'che_ban',
								'campos' => ' 
										cter_nro_interno,     
										cter_fecha_cheque,    
										cter_fecha_ingreso,   
										cter_fecha_dep,  
										cter_fecha_acreed,    
										cter_fecha_baja,      
										cter_nro_cheque,   
										cter_importe,      
										cter_cliente,     
										cter_proveedor,    
										cter_entregado_a,  
										cter_nro_recibo,   
										cter_nro_op,       
										cter_banco_emision,
										cter_cuenta,       
										cter_nro_boleta,   
										cter_clearing,     
										cter_entregado_por,
										cter_interior,     
										cter_nro_caucion,  
										cter_cod_mon,      
										cter_cotizacion,   
										cter_estado,       
										cter_cedio_a,     
										cter_nro_cesion,   
										cter_sucursal_bco, 
										cter_cod_pos_bco,  
										cter_cta_libradora,
										cter_cod_banco,    
										cter_cuit_emisor,  
										cter_empresa',
								'valores' => "   
									'".$numeroInterno."',
									'".date('Ymd', strtotime($fechapagos[$i]))."',
									'".date('Ymd', strtotime($fecha))."',
									'".'0'."',
									'".'0'."',
									'".'0'."',
									'".$numerocheques[$i]."',
									'".$montocheques[$i]."',
									'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."',
									'".''."',
									'".''."',
									'".$numeroRecibo."',
									'".'0'."',
									'".$nombrebancos[$i]."',
									'".''."',
									'".'0'."',
									'".'0'."',
									'".$data['nombrecliente']."',
									'".$camara."',
									'".'0'."',
									'".$monedacheque_ids[$i]."',
									'".$cotizacioncheques[$i]."',
									'".' '."',
									'".'0'."',
									'".'0'."',
									'".$sucursalpagos[$i]."',
									'".'0'."',
									'".$cuentalibradoras[$i]."',
									'".$codigobancos[$i]."',
									'".$numerodocumento."',
									'".$empresa."' 
								"
							);

				$ctermae = $apiAnita->apiCallEscritura($grabaAnita);


				// Graba auxpag del comprobante
				$apiAnita = new ApiAnita();

				$grabaAnita = array( 	'tabla' => 'auxpag', 
								'acc' => 'insert',
								'sistema' => 'che_ban',
								'campos' => ' 
									axp_pro,
									axp_fecha,
									axp_rec,
									axp_tipo,
									axp_nro,
									axp_tipo_ap,
									axp_monto_ap,
									axp_cod_mon_co,
									axp_fecha_co,
									axp_banco,
									axp_letra_comp,
									axp_sucursal,
									axp_letra_cob,
									axp_sucursal_cob,
									axp_vendedor,
									axp_nro_interno,
									axp_empresa,
									axp_concepto,
									axp_cbu',
								'valores' => "   
									'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."', 
									'".date('Ymd', strtotime($fecha))."',
									'".$numeroRecibo."',
									'".substr($tipo, 0, 3)."',
									'".$numeroInterno."',
									'".'CHT'."',
									'".$montocheques[$i]."',
									'".$monedacheque_ids[$i]."',
									'".'0'."',
									'".'000001'."',
									'".' '."',
									'".'0'."',
									'".$letra."',
									'".$puntoVenta."',
									'".'0'."',
									'".'0'."',
									'".$empresa."',
									'".'0'."',
									'".' '."'"
							);  
							
				$auxpag = $apiAnita->apiCallEscritura($grabaAnita);

			}
		}

		// Graba retenciones
		if (isset($data['retencion_cobranza_ids']))
		{
			$retencion_cobranza_ids = $data['retencion_cobranza_ids'];
			$moneda_ids = $data['moneda_retencion_ids'];
			$montos = $data['monto_retenciones'];

			for ($i = 0; $i < count($retencion_cobranza_ids); $i++)
			{
				// Lee retencion cobranza
				$retencion_cobranza = $this->retencion_cobranzaRepository->find($retencion_cobranza_ids[$i]);

				$jurisdiccion = "902";
				if ($retencion_cobranza)
					$jurisdiccion = $retencion_cobranza->provincias->jurisdiccion;

				$tipoComprobante = 'R'.substr($jurisdiccion,1,2);

				// Graba auxpag
				$grabaAnita = array( 	'tabla' => 'auxpag', 
								'acc' => 'insert',
								'sistema' => 'che_ban',
								'campos' => ' 
									axp_pro,
									axp_fecha,
									axp_rec,
									axp_tipo,
									axp_nro,
									axp_tipo_ap,
									axp_monto_ap,
									axp_cod_mon_co,
									axp_fecha_co,
									axp_banco,
									axp_letra_comp,
									axp_sucursal,
									axp_letra_cob,
									axp_sucursal_cob,
									axp_vendedor,
									axp_nro_interno,
									axp_empresa,
									axp_concepto,
									axp_cbu',
								'valores' => "   
									'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."', 
									'".date('Ymd', strtotime($fecha))."',
									'".$numeroRecibo."',
									'".substr($tipo, 0, 3)."',
									'".'0'."',
									'".$tipoComprobante."',
									'".$montos[$i]."',
									'".$moneda_ids[$i]."',
									'".'0'."',
									'".' '."',
									'".' '."',
									'".'0'."',
									'".$letra."',
									'".$puntoVenta."',
									'".'0'."',
									'".'0'."',
									'".$empresa."',
									'".'0'."',
									'".' '."'"
							);  
							
				$auxpag = $apiAnita->apiCallEscritura($grabaAnita);

			}
		}
		
		return ['Success'];
	}	

	// Borra cobranza en Anita
	public function borraAnita($tipo, $letra, $puntoventa, $numero, $empresa)
	{
        $apiAnita = new ApiAnita();
        $grabaAnita = array( 'acc' => 'delete', 
						'sistema' => 'che_ban',
						'tabla' => 'pago', 
						'whereArmado' => " WHERE pag_tipo = '".$tipo."' AND
												pag_letra = '".$letra."' AND
												pag_sucursal = '".$puntoventa."' AND
												pag_rec = '".$numero."'
						" );

		$apiAnita->apiCallEscritura($grabaAnita);
	}

    public function traeUltimoChequeDeTercero()
    {
        // Lee numerador desde anita
		$apiAnita = new ApiAnita();
        $grabaAnita = array( 
            'acc' => 'list', 
			'tabla' => 'ctermae', 
            'campos' => '
                max(cter_nro_interno) as numerointerno
			' 
        );
        $dataAnita = json_decode($apiAnita->apiCall($grabaAnita));
        
        if (count($dataAnita) > 0)
            $nro = $dataAnita[0]->numerointerno + 1;

		if (!isset($nro))
            return 'error';
        
        return $nro;
    }

	public function leeHistoriaCobranza($cobranza_id)
	{
		return $this->cobranza_estadoRepository->leeHistoriaCobranza($cobranza_id);
	}

	// Lista cobranza
	public function listarUnaCobranza($id)
	{
	  	ini_set('memory_limit', '512M');

		//$pdfMerger = PDFMerger::init();

		$cobranza = $this->cobranzaRepository->find($id);

		$letra = 'X';

		$nombre_pdf = 'cobranza-'.$cobranza->numerotransaccion.'-empresa-'.$cobranza->empresas->nombre.'-'.$cobranza->clientes->nombre;

		// Arma tablas para calculo de impuestos
		// Lee el cliente
		$cliente = $this->clienteRepository->find($cobranza->cliente_id);

		$tblComprobante = [];
		foreach($cobranza->cobranza_comprobantes as $comprobante)
		{
			$totalAplicado = 0;
			foreach ($comprobante->cliente_cuentacorrientes->cliente_cuentacorriente_aplicaciones as $aplicacion)
			{
				$coeficiente = calculaCoeficienteMoneda($comprobante->cliente_cuentacorrientes->moneda_id, $aplicacion->moneda_id, $aplicacion->cotizacion);
				$totalAplicado += ($aplicacion->total * $coeficiente);
			}

			$tblComprobante[] = [
					"comprobante" => $comprobante->cliente_cuentacorrientes->ventas->codigo,
					"fecha" => $comprobante->cliente_cuentacorrientes->fecha,
					"fechavencimiento" => $comprobante->cliente_cuentacorrientes->fechavencimiento,
					"moneda" => $comprobante->cliente_cuentacorrientes->monedas->abreviatura,
					"cotizacion" => $comprobante->cliente_cuentacorrientes->cotizacion,
					"monto" => $comprobante->cliente_cuentacorrientes->total,
					"aplicado" => $totalAplicado,
					"saldo" => $comprobante->cliente_cuentacorrientes->total + $totalAplicado,
					];
		}

		// Arma datos del cliente
		$datosCliente = [ "nombre" => $cobranza->clientes->nombre,
						  "domicilio" => $cobranza->clientes->domicilio,
						  "condicioniva" => $cobranza->clientes->condicionivas->nombre,
						  "tipodocumento" => $cobranza->clientes->tipodocumentos->abreviatura,
						  "numerodocumento" => $cobranza->clientes->numerodocumento,
						  "retieneiva" => $cobranza->clientes->retieneiva,
						  "condicioniibb" => $cobranza->clientes->condicioniibbs->nombre,
						  "nroiibb" => $cobranza->clientes->nroiibb,
						  "provincia" => $cobranza->clientes->provincias->nombre??'',
						  "localidad" => $cobranza->clientes->localidades->nombre??'',
						  "codigopostal" => $cobranza->clientes->codigopostal,
						  "pais" => $cobranza->clientes->paises->nombre,
						  "telefono" => $cobranza->clientes->telefono,
						  "id" => $cobranza->clientes->id,
						  "codigo" => $cobranza->clientes->codigo
						];

		$datosEmpresa = [
						"nombre" => $cobranza->empresas->nombre,
						"domicilio" => $cobranza->empresas->domicilio,
						"numeroinscripcion" => $cobranza->empresas->nroinscripcion,
						"numeroiibb" => $cobranza->empresas->numeroiibb
		];

		// Lee cuentas 
		$tblCuenta = [];
		foreach($cobranza->caja_movimientos[0]->caja_movimiento_cuentacajas as $cuenta)
		{
			$tblCuenta[] = [
				'nombre' => $cuenta->cuentacajas->nombre,
				'moneda' => $cuenta->monedas->abreviatura,
				'moneda_id' => $cuenta->moneda_id,
				'monto' => $cuenta->monto,
				'cotizacion' => $cuenta->cotizacion
			];
		}

		// Lee Cheques
		$tblCheques = [];
		foreach($cobranza->cheques as $cheque)
		{
			$tblCheques[] = [
				'fechapago' => $cheque->fechapago,
				'numerocheque' => $cheque->numerocheque,
				'moneda' => $cheque->monedas->abreviatura,
				'moneda_id' => $cheque->moneda_id,
				'monto' => $cheque->monto,
				'cotizacion' => $cheque->cotizacion,
				'banco' => $cheque->bancos->nombre,
				'sucursalpago' => $cheque->sucursalpago,
				'cuentalibradora' => $cheque->cuentalibradora
			];
		}

		$tblRetenciones = [];
		foreach($cobranza->cobranza_retenciones as $retencion)
		{
			$tblRetenciones[] = [
				'retencion' => $retencion->retencion_cobranzas->nombre,
				'comprobante' => $retencion->comprobante,
				'tasa' => $retencion->tasa,
				'moneda' => $retencion->monedas->abreviatura,
				'moneda_id' => $retencion->moneda_id,
				'monto' => $retencion->monto,
				'cotizacion' => $retencion->cotizacion,
			];
		}
		$totalCobranza = [ 	'moneda' => $cobranza->monedas->nombre,
							'abreviatura' => $cobranza->monedas->abreviatura,
							'cotizacion' => $cobranza->cotizacion,
							'monto' => $cobranza->monto
						];
		$view =  \View::make('exports.caja.formulariocobranza', compact('cobranza', 'tblComprobante', 
																		'datosCliente', 'datosEmpresa', 'letra',
																		'tblCuenta', 'tblCheques', 'tblRetenciones',
																		'totalCobranza'
																		))
			    ->render();
		$path = storage_path('pdf/caja');

        $pdf = App::make('dompdf.wrapper');
        $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');
        $pdf->download($nombre_pdf.'.pdf');

		return response()->download($path.'/'.$nombre_pdf.'.pdf');
	}

	public function editaUnaCobranza($cobranza_id, $origen = null)
	{
        can('editar-cobranza');

        if (!isset($origen))
            $origen = 'cobranza';

        $data = $this->cobranzaRepository->find($cobranza_id);

        $tipotransaccion_caja_query = $this->tipotransaccion_cajaRepository->all();
        $mediopago_query = $this->mediopagoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $retencion_cobranza_query = $this->retencion_cobranzaRepository->all();
        $caja_id = $data->caja_id;

        $nombreCaja = '';
        if (isset($caja_id))
        {
            $caja = $this->cajaRepository->find($caja_id);

            if ($caja)
                $nombreCaja = $caja->nombre;
        }

        $tipotransaccion_caja_id = session('tipotransaccioncobranza_caja_id');
        $empresa_id = session('empresa_id');

        return view('caja.cobranza.editar', compact('data', 
                                                    'tipotransaccion_caja_query', 'moneda_query',
                                                    'mediopago_query', 'tipotransaccion_caja_id', 'empresa_id',
                                                    'empresa_query',  'retencion_cobranza_query',
                                                    'centrocosto_query', 'caja_id', 'nombreCaja', 'origen'));		
	}

	/**
	 * Cobranza confirmada desde POS gastronomía (sin pantalla de caja ni cuenta corriente).
	 *
	 * @param  array{
	 *   venta:Venta,
	 *   empresa_id:int,
	 *   tipotransaccion_caja_id:int,
	 *   lineas:list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion:float,observacion:string}>,
	 *   totalfinalcobranza:float,
	 *   monedafinalcobranza_id:int,
	 *   cotizacion_cobranza:float,
	 *   genera_contabilidad:bool,
	 *   detalle?:string
	 * }  $payload
	 * @return array{cobranza_id:int,caja_movimiento_id:int}
	 */
	public function guardaCobranzaGastronomia(array $payload): array
	{
		/** @var Venta $venta */
		$venta = $payload['venta'];
		$venta->loadMissing(['clientes', 'puntoventas']);

		$cobranzaExistente = Cobranza::query()
			->where('venta_id', (int) $venta->id)
			->first();
		if ($cobranzaExistente) {
			$cajaMovimientoId = (int) (Caja_Movimiento::query()
				->where('cobranza_id', $cobranzaExistente->id)
				->orderByDesc('id')
				->value('id') ?? 0);

			return [
				'cobranza_id' => (int) $cobranzaExistente->id,
				'caja_movimiento_id' => $cajaMovimientoId,
			];
		}

		session(['empresa_id' => $payload['empresa_id']]);

		$montoAplicado = abs((float) $venta->total);
		$fecha = $venta->fecha ?? Carbon::now()->format('Y-m-d');

		$data = [
			'empresa_id' => $payload['empresa_id'],
			'tipotransaccion_caja_id' => $payload['tipotransaccion_caja_id'],
			'cliente_id' => (int) $venta->cliente_id,
			'venta_id' => (int) $venta->id,
			'fecha' => $fecha,
			'caja_id' => null,
			'ordenventa_id' => 0,
			'totalfinalcobranza' => $payload['totalfinalcobranza'],
			'monedafinalcobranza_id' => $payload['monedafinalcobranza_id'],
			'cotizacion_cobranza' => $payload['cotizacion_cobranza'],
			'cuentacaja_ids' => [],
			'moneda_ids' => [],
			'montos' => [],
			'cotizaciones' => [],
			'observaciones' => [],
		];

		foreach ($payload['lineas'] as $linea) {
			$data['cuentacaja_ids'][] = $linea['cuentacaja_id'];
			$data['moneda_ids'][] = $linea['moneda_id'];
			$data['montos'][] = $linea['monto'];
			$data['cotizaciones'][] = $linea['cotizacion'];
			$data['observaciones'][] = $linea['observacion'];
		}

		$data['usuario_id'] = Auth::id();
		$detalleManual = trim((string) ($payload['detalle'] ?? ''));
		$data['detalle'] = $detalleManual !== ''
			? $detalleManual
			: 'Cobranza gastronomía — '.$venta->codigo;
		$data['estado'] = Cobranza_Estado::$enumEstado[0]['nombre'];
		$data['fechas'] = [Carbon::now()];
		$data['estados'] = [Cobranza_Estado::$enumEstado[0]['nombre']];
		$data['observacionestados'] = ['Alta de Cobranza (gastronomía)'];
		$data['usuario_ids'] = [Auth::id()];

		if ($payload['genera_contabilidad']) {
			$this->aplicarAsientoContableGastronomia($data, $montoAplicado, (int) $venta->moneda_id, (float) ($venta->cotizacion ?: 1.));
		}

		$data['numerotransaccion'] = CobranzaNumeracionTransaccion::numerotransaccionDesdeCodigoVenta(
			(string) ($venta->codigo ?? ''),
		);

		$transaccionExterna = DB::transactionLevel() > 0;
		if (! $transaccionExterna) {
			DB::beginTransaction();
		}
		try {
			$cobranza = $this->cobranzaRepository->create($data);
			if (! $cobranza || $cobranza === 'Error') {
				throw new Exception('No se pudo crear la cobranza.');
			}

			$request = new Request($data);
			self::agregaGastronomia($data, $cobranza, $request);

			$cajaMovimientoId = (int) (Caja_Movimiento::query()
				->where('cobranza_id', $cobranza->id)
				->orderByDesc('id')
				->value('id') ?? 0);

			if (! $transaccionExterna) {
				DB::commit();
			}

			return [
				'cobranza_id' => (int) $cobranza->id,
				'caja_movimiento_id' => $cajaMovimientoId,
			];
		} catch (\Throwable $e) {
			if (! $transaccionExterna) {
				DB::rollBack();
			}
			throw $e;
		}
	}

	/**
	 * Persiste movimiento de caja sin cuenta corriente ni cobranza_comprobante (misma regla que factura gastronomía).
	 *
	 * @param  array<string, mixed>  $data
	 */
	private function agregaGastronomia(array $data, $cobranza, Request $request): void
	{
		$data['cobranza_id'] = $cobranza->id;

		$this->cobranza_retencionRepository->create($data, $cobranza->id);
		$this->cobranza_estadoRepository->create($data, $cobranza->id);
		$this->cobranza_archivoRepository->create($request, $cobranza->id);

		$caja_movimiento = $this->caja_movimientoRepository->create($data);
		$this->caja_movimiento_cuentacajaRepository->create($data, $caja_movimiento->id);

		$data['fechas'] = [];
		$data['estados'] = [];
		$data['observacionestados'] = [];
		$data['fechas'][] = Carbon::now();
		$data['estados'][] = Caja_Movimiento_Estado::$enumEstado[0]['valor'];
		$data['observacionestados'][] = 'Alta de Movimiento de Caja';

		$this->caja_movimiento_estadoRepository->create($data, $caja_movimiento->id);

		$this->chequeRepository->guardarChequeCobranza($data, 'create', $cobranza->id);

		if (isset($data['cuentacontable_ids']) && $data['estado'] != Cobranza_Estado::$enumEstado[1]['nombre']) {
			$tipoasiento = $this->tipoasientoRepository->findPorAbreviatura('TES');

			if ($tipoasiento) {
				$data['tipoasiento_id'] = $tipoasiento->id;
			} else {
				throw new Exception('Error en grabacion, no existe tipo de asiento de tesoreria');
			}

			$data['moneda_ids'] = $data['monedaasiento_ids'];
			$data['centrocosto_ids'] = $data['centrocostoasiento_ids'];
			$data['debes'] = $data['debeasientos'];
			$data['haberes'] = $data['haberasientos'];
			$data['cotizaciones'] = $data['cotizacionasientos'];
			$data['observaciones'] = $data['observacionasientos'];
			$data['cobranza_id'] = $cobranza->id;
			$data['observacion'] = $data['detalle'];

			for ($i = 0; $i < count($data['observaciones']); $i++) {
				if ($data['observaciones'][$i] == null) {
					$data['observaciones'][$i] = $data['detalle'];
				}
			}

			$asiento = $this->asientoRepository->create($data);

			if ($asiento == 'Error') {
				throw new Exception('Error en grabacion anita.');
			}

			if ($asiento) {
				$this->asiento_movimientoRepository->create($data, $asiento->id);
			}
		}
	}

	/**
	 * @param  array<string, mixed>  $data
	 */
	private function aplicarAsientoContableGastronomia(
		array &$data,
		float $montoComprobante,
		int $monedaComprobanteId,
		float $cotizacionComprobante,
	): void {
		$datosCaja = [];
		foreach ($data['cuentacaja_ids'] as $i => $cuentacajaId) {
			$datosCaja[] = (object) [
				'cuentacaja_ids' => $cuentacajaId,
				'moneda_ids' => $data['moneda_ids'][$i],
				'montos' => $data['montos'][$i],
				'cotizaciones' => $data['cotizaciones'][$i],
			];
		}

		$datosComprobantes = [(object) [
			'montos' => $montoComprobante,
			'moneda_ids' => $monedaComprobanteId,
			'cotizaciones' => $cotizacionComprobante,
		]];

		$resultado = $this->generaAsientoContable([
			'datoscaja' => json_encode($datosCaja),
			'datoscontables' => json_encode([]),
			'datoscheques' => json_encode([]),
			'datosretenciones' => json_encode([]),
			'datoscomprobantes' => json_encode($datosComprobantes),
			'tipotransaccion_caja_id' => json_encode($data['tipotransaccion_caja_id']),
			'empresa_id' => json_encode($data['empresa_id']),
		]);

		$lineas = $resultado['asiento'] ?? [];
		if ($lineas === []) {
			return;
		}

		$data['cuentacontable_ids'] = [];
		$data['monedaasiento_ids'] = [];
		$data['centrocostoasiento_ids'] = [];
		$data['debeasientos'] = [];
		$data['haberasientos'] = [];
		$data['cotizacionasientos'] = [];
		$data['observacionasientos'] = [];

		foreach ($lineas as $linea) {
			$data['cuentacontable_ids'][] = $linea['cuentacontable_id'];
			$data['monedaasiento_ids'][] = $linea['moneda_id'];
			$data['centrocostoasiento_ids'][] = $linea['centrocosto_id'] ?? null;
			$data['debeasientos'][] = $linea['debe'] ?? 0;
			$data['haberasientos'][] = $linea['haber'] ?? 0;
			$data['cotizacionasientos'][] = $linea['cotizacion'] ?? 1;
			$data['observacionasientos'][] = $linea['observacion'] ?? $data['detalle'];
		}
	}
}