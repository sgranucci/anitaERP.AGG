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
use App\Support\Caja\ChequePropioInstrumentoSupport;
use App\Support\Caja\AnitaSync\CobranzaAnitaCheBanEsquemaSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Models\Contable\Cuentacontable;
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
use App\Models\Ventas\Cliente;
use App\Support\Ventas\ClientePoliticaComercialSupport;
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
		try {
			session(['empresa_id' => $request->empresa_id]);
			$data = $request->all();

			PeriodoContableCierreSupport::assertOperacionPermitida(
				(int) ($data['empresa_id'] ?? 0),
				(string) ($data['fecha'] ?? date('Y-m-d')),
				PeriodoContableCierreSupport::ALCANCE_COBRANZA
			);

			// Crea estado
			$data['fechas'][] = Carbon::now();
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
						$avisoPolitica = $this->aplicarPoliticaTrasCobranzaConfirmada($data);

						return $this->respuestaExitoGrabacionCobranza($cobranza, $request, $avisoPolitica);
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

						$avisoPolitica = $this->aplicarPoliticaTrasCobranzaConfirmada($data);

						return $this->respuestaExitoGrabacionCobranza($cobranza, $request, $avisoPolitica);
					}
				},
			);
		} catch (\Throwable $e) {
			Log::error('cobranza.guarda.fallo', [
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'empresa_id' => $request->empresa_id ?? null,
				'tipotransaccion_caja_id' => $request->tipotransaccion_caja_id ?? null,
			]);

			return ['errores' => $e->getMessage()];
		}
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
			$data = $this->asignarIdsAsientoDesdeFormulario($data);
			$data['cobranza_id'] = $cobranza->id;

			$data['observacion'] = $data['detalle'];

			if (! is_array($data['observaciones'] ?? null)) {
				$data['observaciones'] = [];
			}
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

		$this->persistirAnticiposPagoDeMas($data, $cobranza->id);
	}

    public function actualizaCobranza($request, $id, $origen = null)
    {
        session(['empresa_id' => $request->empresa_id]);
		$data = $request->all();

		try {
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

			if ($origen) {
				Self::actualiza($data, $id, $request);
			} else {
				DB::beginTransaction();
				try {
					Self::actualiza($data, $id, $request);

					if (($data['ordenventa_id'] ?? 0) > 0) {
						$this->ordenventaService->marcaOrdenVentaCobrada($data['ordenventa_id']);
					}

					DB::commit();
				} catch (\Exception $e) {
					DB::rollback();
					Log::error('cobranza.actualiza.fallo', [
						'message' => $e->getMessage(),
						'file' => $e->getFile(),
						'line' => $e->getLine(),
						'cobranza_id' => $id,
						'estado' => $data['estado'] ?? null,
					]);

					return ['errores' => $e->getMessage()];
				}
			}

			$ok = ['mensaje' => 'ok'];
			$avisoPolitica = $this->aplicarPoliticaTrasCobranzaConfirmada($data);
			if ($avisoPolitica) {
				$ok['aviso_politica'] = $avisoPolitica;
			}

			return $ok;
		} catch (\Throwable $e) {
			Log::error('cobranza.actualiza.fallo', [
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'cobranza_id' => $id,
				'estado' => $data['estado'] ?? null,
			]);

			return ['errores' => $e->getMessage()];
		}
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
			$asientoLista = is_countable($asiento) ? $asiento : [];

			if (count($asientoLista) > 0)
			{
				$asiento_id = $asientoLista[0]->id;
				$data['tipoasiento_id'] = $asientoLista[0]->tipoasiento_id;
				$data['numeroasiento'] = $asientoLista[0]->numeroasiento;
			}

			// Arma el asiento contable
			$data = $this->asignarIdsAsientoDesdeFormulario($data);
			$data['cobranza_id'] = $id;
			$data['observacion'] = $data['detalle'];

			for ($i = 0; $i < count($data['observaciones']); $i++)
			{
				if ($data['observaciones'][$i] == null)
					$data['observaciones'][$i] = $data['detalle'];
			}

			if (count($asientoLista) > 0)
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
		// Recrea anticipos si cobraron de más (crédito sin venta)
		$cliente_cuentacorriente = $this->cliente_cuentacorrienteRepository->buscaPorVentaCobranza(null, $data['cobranza_id']);

		foreach ($cliente_cuentacorriente ?? [] as $anticipoCc)
		{
			$this->cliente_cuentacorrienteRepository->find($anticipoCc->id)->delete();
		}

		$this->persistirAnticiposPagoDeMas($data, $id);

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
		$datosCaja = $this->decodeListaAsiento($data['datoscaja'] ?? []);
		$datosContables = $this->decodeListaAsiento($data['datoscontables'] ?? []);
		$datosCheque = $this->decodeListaAsiento($data['datoscheques'] ?? []);
		$datosRetencion = $this->decodeListaAsiento($data['datosretenciones'] ?? []);
		$datosComprobantes = $this->decodeListaAsiento($data['datoscomprobantes'] ?? []);
		$tipotransaccion_caja_id = is_numeric($data['tipotransaccion_caja_id'] ?? null)
			? (int) $data['tipotransaccion_caja_id']
			: (int) json_decode($data['tipotransaccion_caja_id'] ?? '0');
		$empresa_id = is_numeric($data['empresa_id'] ?? null)
			? (int) $data['empresa_id']
			: (int) json_decode($data['empresa_id'] ?? '0');

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
				$montoCheque = (float) ($cheque->montos ?? 0);
				if (abs($montoCheque) < 0.000001) {
					continue;
				}

				$cuentacontable = $this->resolverCuentaValoresADepositar((int) $empresa_id);

				if ($cuentacontable)
				{
					if ($montoCheque * $signo > 0)
						$d_h = 'D';
					else
						$d_h = 'H';

					Self::agregaCuenta($asiento, $cuentacontable->id, $cheque->moneda_ids, $cheque->cotizaciones, $d_h, abs($montoCheque));				
				}
			}

			// Agrega retenciones
			foreach($datosRetencion as $retencion)
			{
				// Lee la retencion de la tabla para sacar la cuenta contable
				$retencion_cobranza = $this->retencion_cobranzaRepository->find($retencion->cuenta_retencion_ids);

				if ($retencion_cobranza)
				{
					$cuentacontable_id = null;
					// Busca en funcion de la empresa
					foreach ($retencion_cobranza->retencion_cobranza_cuentacontables as $cuenta)
					{
						if ($cuenta->empresa_id == $empresa_id)
							$cuentacontable_id = $cuenta->cuentacontable_id;
					}
					if (!$cuentacontable_id) {
						continue;
					}
					if ((float) $retencion->montos * $signo > 0)
						$d_h = 'D';
					else
						$d_h = 'H';

					Self::agregaCuenta($asiento, $cuentacontable_id, $retencion->moneda_ids, $retencion->cotizaciones, $d_h, $retencion->montos);				
				}				
			}
		}

		// Contrapartida: una línea de deudores por moneda (no una por factura)
		if (count($datosContables) == 0)
		{
			$totalesDeudores = [];
			foreach ($datosComprobantes as $comprobante) {
				$montoComp = (float) ($comprobante->montos ?? 0);
				if (abs($montoComp) < 0.000001) {
					continue;
				}
				$monedaId = (int) ($comprobante->moneda_ids ?? 1) ?: 1;
				$cotizacion = (float) ($comprobante->cotizaciones ?? 1);
				if ($cotizacion <= 0) {
					$cotizacion = 1.0;
				}
				if (! isset($totalesDeudores[$monedaId])) {
					$totalesDeudores[$monedaId] = [
						'monto' => 0.0,
						'cotizacion' => $cotizacion,
					];
				}
				$totalesDeudores[$monedaId]['monto'] += $montoComp;
			}

			$codigoDeudores = (int) config('cliente.DEUDORES_POR_VENTAS');
			$cuentacontableDeudores = $codigoDeudores > 0
				? $this->resolverCuentacontablePorCodigo((int) $empresa_id, $codigoDeudores)
				: null;

			foreach ($totalesDeudores as $monedaId => $total) {
				if (! $cuentacontableDeudores) {
					break;
				}
				$montoDeudores = (float) ($total['monto'] ?? 0);
				if (abs($montoDeudores) < 0.000001) {
					continue;
				}
				$d_h = ($montoDeudores * $signo > 0) ? 'H' : 'D';
				Self::agregaCuenta(
					$asiento,
					$cuentacontableDeudores->id,
					$monedaId,
					$total['cotizacion'],
					$d_h,
					abs($montoDeudores)
				);
			}

			if ($asiento === []) {
				return [
					'mensaje' => 'ok',
					'asiento' => [],
					'errores' => 'No se pudo armar el asiento: faltan cuentas contables (valores a depositar / deudores) o no hay montos de medios/comprobantes.',
				];
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
				$codigoAnticipo = (int) config('cliente.ANTICIPO_DE_CLIENTES');
				$cuentacontable = $codigoAnticipo > 0
					? $this->resolverCuentacontablePorCodigo((int) $empresa_id, $codigoAnticipo)
					: null;

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

	/**
	 * @param  mixed  $raw
	 * @return list<object>
	 */
	private function decodeListaAsiento($raw): array
	{
		if (is_array($raw)) {
			return array_values($raw);
		}
		if ($raw === null || $raw === '') {
			return [];
		}
		$decoded = is_string($raw) ? json_decode($raw) : $raw;
		if (! is_array($decoded)) {
			return [];
		}

		return array_values($decoded);
	}

	private function agregaCuenta(&$asiento, $cuentacontable_id, $moneda_id, $cotizacion, $d_h, $monto)
	{
		$monto = abs((float) $monto);
		if ($monto < 0.000001 || ! $cuentacontable_id) {
			return;
		}

		$moneda_id = (int) $moneda_id ?: 1;
		$cotizacion = (float) $cotizacion;
		if ($cotizacion <= 0) {
			$cotizacion = 1.0;
		}

		if ($d_h == 'D')
		{
			$debe = $monto; $haber = 0;
		}
		else
		{
			$debe = 0; $haber = $monto;
		}

		$indiceExistente = null;
		foreach ($asiento as $i => $linea) {
			if ((int) $linea['cuentacontable_id'] === (int) $cuentacontable_id &&
				(int) $linea['moneda_id'] === $moneda_id &&
				abs((float) $linea['cotizacion'] - $cotizacion) < 0.000001 &&
				$linea['d_h'] === $d_h) {
				$indiceExistente = $i;
				break;
			}
		}

		if ($indiceExistente === null)
		{
			$cuentacontable = $this->cuentacontableRepository->find($cuentacontable_id);

			if ($cuentacontable)
				$asiento[] = [ 'cuentacontable_id' => $cuentacontable_id,
								'codigo' => $cuentacontable->codigo,
								'nombre' => $cuentacontable->nombre,
								'moneda_id' => $moneda_id,
								'cotizacion' => $cotizacion,
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
			$asiento[$indiceExistente]['debe'] += $debe;
			$asiento[$indiceExistente]['haber'] += $haber;
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

		$ctxBase = [
			'codigoCliente' => $codigoCliente,
			'fecha' => $fecha,
			'tipo' => $tipo,
			'numeroRecibo' => $numeroRecibo,
			'totalRecibo' => $totalRecibo,
			'cotizacion' => $cotizacion,
			'leyenda' => $leyenda,
			'letra' => $letra,
			'puntoVenta' => $puntoVenta,
			'empresa' => $empresa,
		];

		$apiAnita = new ApiAnita();
		$pagoPayload = CobranzaAnitaCheBanEsquemaSupport::payloadPago($ctxBase);
		$apiAnita->apiCallEscritura([
			'tabla' => 'pago',
			'acc' => 'insert',
			'sistema' => 'che_ban',
			'campos' => $pagoPayload['campos'],
			'valores' => $pagoPayload['valores'],
		]);

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
				$monto = (float) ($montos[$i] ?? 0);
				if (($cuentacaja_ids[$i] ?? null) === null || $cuentacaja_ids[$i] === '' || abs($monto) < 0.000001) {
					continue;
				}

				$cuentacaja = $this->cuentacajaRepository->find($cuentacaja_ids[$i]);
				$codigoCuenta = $cuentacaja ? $cuentacaja->codigo : '';

				$auxCtx = array_merge($ctxBase, [
					'nro' => '0',
					'tipoAp' => 'ATE',
					'monto' => $montos[$i],
					'monedaId' => $moneda_ids[$i],
					'banco' => $codigoCuenta,
					'letraComp' => ' ',
					'sucursal' => '0',
					'letraCob' => $letra,
					'sucursalCob' => $puntoVenta,
					'vendedor' => '0',
					'nroInterno' => '0',
					'concepto' => '0',
					'cbu' => ' ',
				]);
				$auxPayload = CobranzaAnitaCheBanEsquemaSupport::payloadAuxpag($auxCtx);
				$apiAnita->apiCallEscritura([
					'tabla' => 'auxpag',
					'acc' => 'insert',
					'sistema' => 'che_ban',
					'campos' => $auxPayload['campos'],
					'valores' => $auxPayload['valores'],
				]);

				$tesCtx = array_merge($ctxBase, [
					'codigoCuenta' => $codigoCuenta,
					'monto' => $montos[$i],
					'cotizacion' => $cotizaciones[$i],
					'detalle' => $data['detalle'] ?? '',
					'monedaId' => $moneda_ids[$i],
				]);
				$tesPayload = CobranzaAnitaCheBanEsquemaSupport::payloadTesmov($tesCtx);
				$apiAnita->apiCallEscritura([
					'tabla' => 'tesmov',
					'acc' => 'insert',
					'sistema' => 'che_ban',
					'campos' => $tesPayload['campos'],
					'valores' => $tesPayload['valores'],
				]);
			}
		}

		// Graba comprobantes
		if (isset($data['idcuentacorrientes']))
		{
			$cliente_cuentacorriente_ids = $data['idcuentacorrientes'];
			$moneda_ids = $data['monedacomprobante_ids'];
			$montos = $data['montoaplicadocomprobantes'];
			$cotizaciones = $data['cotizacioncomprobantes'];
			$codigoComprobantes = $data['codigocomprobantes'];
			$saldoComprobantes = $data['saldocomprobantes'];

			for ($i = 0; $i < count($cliente_cuentacorriente_ids); $i++)
			{
				$monto = (float) ($montos[$i] ?? 0);
				if (($cliente_cuentacorriente_ids[$i] ?? null) === null || $cliente_cuentacorriente_ids[$i] === '' || abs($monto) < 0.000001) {
					continue;
				}

				$codigo = $codigoComprobantes[$i];
				$tipoComprobante = substr($codigo, 0, 3);
				$letraComprobante = substr($codigo, 4, 1);
				$sucursalComprobante = substr($codigo, 6, 5);
				$nroComprobante = substr($codigo, 12, 8);

				$cliCtx = array_merge($ctxBase, [
					'tipoComprobante' => $tipoComprobante,
					'letraComprobante' => $letraComprobante,
					'sucursalComprobante' => $sucursalComprobante,
					'nroComprobante' => $nroComprobante,
					'monto' => $montos[$i],
					'monedaId' => $moneda_ids[$i],
					'cotizacion' => $cotizaciones[$i],
				]);
				$cliPayload = CobranzaAnitaCheBanEsquemaSupport::payloadClimov($cliCtx);
				$apiAnita->apiCallEscritura([
					'tabla' => 'climov',
					'acc' => 'insert',
					'sistema' => 'ventas',
					'campos' => $cliPayload['campos'],
					'valores' => $cliPayload['valores'],
				]);

				$apiAnita->apiCallEscritura([
					'acc' => 'update',
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
									cliv_nro      = '".$nroComprobante."' ",
				]);

				$apiAnita->apiCallEscritura([
					'tabla' => 'aplmov',
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
										'1',
										'".substr($tipo, 0, 3)."',
										'".$letra."',
										'".$puntoVenta."',
										'".$numeroRecibo."',
										'".date('Ymd', strtotime($fecha))."',
										'".$montos[$i]."',
										'".\App\Support\Configuracion\MonedaAnitaCodigoSupport::normalizar($moneda_ids[$i])."',
										'".$cotizaciones[$i]."',
										'".substr($tipo, 0, 3)."',
										'".$letra."',
										'".$puntoVenta."',
										'".$numeroRecibo."',
										'".date('Ymd', strtotime($fecha))."'",
				]);

				$auxCtx = array_merge($ctxBase, [
					'nro' => $nroComprobante,
					'tipoAp' => $tipoComprobante,
					'monto' => $montos[$i],
					'monedaId' => $moneda_ids[$i],
					'banco' => '000001',
					'letraComp' => $letraComprobante,
					'sucursal' => $sucursalComprobante,
					'letraCob' => $letra,
					'sucursalCob' => $puntoVenta,
					'vendedor' => '0',
					'nroInterno' => '0',
					'concepto' => '0',
					'cbu' => ' ',
				]);
				$auxPayload = CobranzaAnitaCheBanEsquemaSupport::payloadAuxpag($auxCtx);
				$apiAnita->apiCallEscritura([
					'tabla' => 'auxpag',
					'acc' => 'insert',
					'sistema' => 'che_ban',
					'campos' => $auxPayload['campos'],
					'valores' => $auxPayload['valores'],
				]);
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
			$negociables = $data['negociables'] ?? [];
			$cotizacioncheques = $data['cotizacioncheques'];
			$sucursalpagos = $data['sucursalpagos'];
			$cuentalibradoras = $data['cuentalibradoras'];
			$monedacheque_ids = $data['monedacheque_ids'];
			$montocheques = $data['montocheques'];

			$numeroInternoBase = self::traeUltimoChequeDeTercero();
			if ($numeroInternoBase === 'error' || $numeroInternoBase === null || $numeroInternoBase === '') {
				throw new Exception('No se pudo obtener el próximo número interno de cheque Anita (ctermae).');
			}
			$numeroInternoSecuencia = (int) $numeroInternoBase;

			for ($i = 0; $i < count($cheque_ids); $i++)
			{
				$monto = (float) ($montocheques[$i] ?? 0);
				if (abs($monto) < 0.000001) {
					continue;
				}

				$numeroInterno = $numeroInternoSecuencia;
				$numeroInternoSecuencia++;

				$fechaCheque = $fechapagos[$i] ?? ($data['fechapago'] ?? $fecha);
				$camaraFecha = ((string) $fechaCheque > (string) $fecha) ? '2' : '1';
				$negociable = \App\Support\Caja\ChequePropioInstrumentoSupport::negociable(
					(string) ($negociables[$i] ?? ''),
					'N'
				);
				$camara = \App\Support\Caja\ChequeTerceroCtermaeAnitaMapper::interiorDesdeNegociable(
					$negociable,
					$camaraFecha
				);

				$cterCtx = [
					'numeroInterno' => $numeroInterno,
					'fechaCheque' => $fechaCheque,
					'fechaIngreso' => $fecha,
					'numeroCheque' => $numerocheques[$i],
					'importe' => $montocheques[$i],
					'codigoCliente' => $codigoCliente,
					'numeroRecibo' => $numeroRecibo,
					'nombreBanco' => $nombrebancos[$i] ?? '',
					'entregadoPor' => $data['nombrecliente'] ?? '',
					'camara' => $camara,
					'nroEcheq' => \App\Support\Caja\ChequePropioInstrumentoSupport::nroEcheq(
						$negociable,
						(string) ($numerocheques[$i] ?? '')
					),
					'monedaId' => $monedacheque_ids[$i],
					'cotizacion' => $cotizacioncheques[$i],
					'sucursalBanco' => $sucursalpagos[$i] ?? '0',
					'cuentaLibradora' => $cuentalibradoras[$i] ?? '0',
					'codigoBanco' => $codigobancos[$i] ?? '0',
					'cuit' => $numerodocumento,
					'empresa' => $empresa,
				];
				$cterPayload = CobranzaAnitaCheBanEsquemaSupport::payloadCtermae($cterCtx);
				$apiAnita->apiCallEscritura([
					'tabla' => 'ctermae',
					'acc' => 'insert',
					'sistema' => 'che_ban',
					'campos' => $cterPayload['campos'],
					'valores' => $cterPayload['valores'],
				], 'ctermae insert cobranza');

				$chequeErp = \App\Models\Caja\Cheque::query()
					->where('origen', 'R')
					->whereNull('nro_interno_anita')
					->where('cliente_id', $data['cliente_id'] ?? null)
					->where('numerocheque', (string) ($numerocheques[$i] ?? ''))
					->orderByDesc('id')
					->first();
				if ($chequeErp) {
					$this->chequeRepository->vincularNroInternoAnita((int) $chequeErp->id, (int) $numeroInterno);
				}

				$auxCtx = array_merge($ctxBase, [
					'nro' => $numeroInterno,
					'tipoAp' => 'CHT',
					'monto' => $montocheques[$i],
					'monedaId' => $monedacheque_ids[$i],
					'banco' => '000001',
					'letraComp' => ' ',
					'sucursal' => '0',
					'letraCob' => $letra,
					'sucursalCob' => $puntoVenta,
					'vendedor' => '0',
					'nroInterno' => '0',
					'concepto' => '0',
					'cbu' => ' ',
				]);
				$auxPayload = CobranzaAnitaCheBanEsquemaSupport::payloadAuxpag($auxCtx);
				$apiAnita->apiCallEscritura([
					'tabla' => 'auxpag',
					'acc' => 'insert',
					'sistema' => 'che_ban',
					'campos' => $auxPayload['campos'],
					'valores' => $auxPayload['valores'],
				], 'auxpag CHT cobranza');
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
				$monto = (float) ($montos[$i] ?? 0);
				if (($retencion_cobranza_ids[$i] ?? null) === null || $retencion_cobranza_ids[$i] === '' || abs($monto) < 0.000001) {
					continue;
				}

				$retencion_cobranza = $this->retencion_cobranzaRepository->find($retencion_cobranza_ids[$i]);
				// Ganancias / IVA / SUSS no tienen provincia; IIBB sí. Fallback 902 (misma lógica previa).
				$jurisdiccion = $retencion_cobranza?->provincias?->jurisdiccion ?? '902';
				$tipoComprobante = 'R'.substr((string) $jurisdiccion, 1, 2);

				$auxCtx = array_merge($ctxBase, [
					'nro' => '0',
					'tipoAp' => $tipoComprobante,
					'monto' => $montos[$i],
					'monedaId' => $moneda_ids[$i],
					'banco' => '0',
					'letraComp' => ' ',
					'sucursal' => '0',
					'letraCob' => $letra,
					'sucursalCob' => $puntoVenta,
					'vendedor' => '0',
					'nroInterno' => '0',
					'concepto' => '0',
					'cbu' => ' ',
				]);
				$auxPayload = CobranzaAnitaCheBanEsquemaSupport::payloadAuxpag($auxCtx);
				$apiAnita->apiCallEscritura([
					'tabla' => 'auxpag',
					'acc' => 'insert',
					'sistema' => 'che_ban',
					'campos' => $auxPayload['campos'],
					'valores' => $auxPayload['valores'],
				]);
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
		$nro = \App\Support\Caja\ChequeTerceroCtermaeInsertAnitaSupport::siguienteNroInterno();
		if ($nro === null || $nro <= 0) {
			return 'error';
		}

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
		$cobranza->loadMissing([
			'usuarios',
			'monedas',
			'clientes',
			'empresas',
			'tipotransaccioncajas',
			'asientos.asiento_movimientos.cuentacontables',
			'caja_movimientos.caja_movimiento_cuentacajas.cuentacajas',
			'caja_movimientos.caja_movimiento_cuentacajas.monedas',
			'cheques.bancos',
			'cheques.monedas',
		]);

		$letra = 'X';

		$nombreEmpresa = preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string) ($cobranza->empresas->nombre ?? 'empresa'));
		$nombreCliente = preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string) ($cobranza->clientes->nombre ?? 'cliente'));
		$nombre_pdf = 'cobranza-'.$cobranza->numerotransaccion.'-empresa-'.$nombreEmpresa.'-'.$nombreCliente;

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
		$cajaMovimiento = $cobranza->caja_movimientos->first();
		if ($cajaMovimiento) {
			foreach ($cajaMovimiento->caja_movimiento_cuentacajas as $cuenta) {
				$tblCuenta[] = [
					'nombre' => $cuenta->cuentacajas->nombre ?? '',
					'moneda' => $cuenta->monedas->abreviatura ?? '',
					'moneda_id' => $cuenta->moneda_id,
					'monto' => $cuenta->monto,
					'cotizacion' => $cuenta->cotizacion,
				];
			}
		}

		// Lee Cheques
		$tblCheques = [];
		foreach($cobranza->cheques as $cheque)
		{
			$nroInterno = (int) ($cheque->nro_interno_anita ?? 0);
			$tblCheques[] = [
				'fechapago' => $cheque->fechapago,
				'numerocheque' => $cheque->numerocheque,
				'nro_interno_anita' => $nroInterno > 0 ? $nroInterno : null,
				'tipo_instrumento' => ChequePropioInstrumentoSupport::etiquetaNegociable($cheque->negociable ?? null),
				'moneda' => $cheque->monedas->abreviatura ?? '',
				'moneda_id' => $cheque->moneda_id,
				'monto' => $cheque->monto,
				'cotizacion' => $cheque->cotizacion,
				'banco' => $cheque->bancos->nombre ?? '',
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
		if (! is_dir($path)) {
			mkdir($path, 0755, true);
		}

		$pdf = App::make('dompdf.wrapper');
		$pdf->loadHTML($view)->setPaper('a4');
		$archivo = $path.'/'.$nombre_pdf.'.pdf';
		$pdf->save($archivo);

		return response()->file($archivo, [
			'Content-Type' => 'application/pdf',
			'Content-Disposition' => 'inline; filename="'.$nombre_pdf.'.pdf"',
		]);
	}

	/**
	 * Respuesta AJAX post-alta: abre PDF del recibo (mismo patrón que IE / OP).
	 *
	 * @return array{mensaje: string, cobranza_id: int, url_comprobante_pdf: string, redirect_url?: string, aviso_politica?: string}
	 */
	private function respuestaExitoGrabacionCobranza($cobranza, $request, ?string $avisoPolitica = null): array
	{
		$id = (int) ($cobranza->id ?? 0);
		$respuesta = [
			'mensaje' => 'ok',
			'cobranza_id' => $id,
			'url_comprobante_pdf' => route('listar_una_cobranza', ['id' => $id]),
		];
		if ($avisoPolitica) {
			$respuesta['aviso_politica'] = $avisoPolitica;
		}

		$origenUi = (string) ($request->input('origen') ?? '');
		if ($origenUi === 'movimientocaja') {
			$respuesta['redirect_url'] = url('caja/movimientocaja');
		} elseif ($origenUi === 'ordenventa') {
			$respuesta['redirect_url'] = (string) ($request->input('referer') ?: url('caja/cobranza'));
		} else {
			$respuesta['redirect_url'] = route('cobranza');
		}

		return $respuesta;
	}

	private function aplicarPoliticaTrasCobranzaConfirmada(array $data): ?string
	{
		if (($data['estado'] ?? '') !== Cobranza_Estado::$enumEstado[0]['nombre']) {
			return null;
		}

		$clienteId = (int) ($data['cliente_id'] ?? 0);
		if ($clienteId <= 0) {
			return null;
		}

		try {
			$cliente = Cliente::query()->find($clienteId);

			return ClientePoliticaComercialSupport::pasarMorosoAProformaPorCobranza($cliente);
		} catch (\Throwable $e) {
			Log::error('cobranza.politica.moroso_proforma', [
				'message' => $e->getMessage(),
				'cliente_id' => $clienteId,
			]);

			return null;
		}
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

			$data = $this->asignarIdsAsientoDesdeFormulario($data);
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

	private function resolverCuentaValoresADepositar(int $empresaId): ?Cuentacontable
	{
		$id = \App\Support\Caja\ChequePropioImputacionSupport::resolverCuentacontableIdValoresADepositar(
			$empresaId,
			$this->cuentacontableRepository
		);
		if ($id !== null && $id > 0) {
			return $this->cuentacontableRepository->find($id);
		}

		$codigo = (int) config('caja.valores_a_depositar_cuenta_codigo');

		return $this->resolverCuentacontablePorCodigo($empresaId, $codigo);
	}

	private function resolverCuentacontablePorCodigo(int $empresaId, int $codigo): ?Cuentacontable
	{
		if ($codigo <= 0) {
			return null;
		}

		$cuenta = $this->cuentacontableRepository->findPorCodigo($empresaId, $codigo);
		if ($cuenta) {
			return $cuenta;
		}

		// Ferli: un solo plan en empresa 1; la cobranza puede ser de emp 2/3.
		if (EntornoEmpresaSupport::esFerli()) {
			return Cuentacontable::query()
				->where('codigo', $codigo)
				->orderBy('empresa_id')
				->first();
		}

		return null;
	}

	/**
	 * El select de CC del asiento no siempre viaja en el POST (cuenta de efectivo
	 * sin centros, o el combo todavía vacío al grabar). En edición ya se toleraba.
	 *
	 * @param  array<string, mixed>  $data
	 * @return array<string, mixed>
	 */
	private function asignarIdsAsientoDesdeFormulario(array $data): array
	{
		$monedaIds = $data['monedaasiento_ids'] ?? [];
		if (! is_array($monedaIds)) {
			$monedaIds = ($monedaIds !== null && $monedaIds !== '') ? [$monedaIds] : [];
		}
		$data['moneda_ids'] = $monedaIds;

		if (! isset($data['centrocostoasiento_ids']) || ! is_array($data['centrocostoasiento_ids'])) {
			$data['centrocosto_ids'] = [];
			foreach (array_keys($monedaIds) as $i) {
				$data['centrocosto_ids'][$i] = null;
			}
		} else {
			$data['centrocosto_ids'] = $data['centrocostoasiento_ids'];
		}

		$data['debes'] = is_array($data['debeasientos'] ?? null) ? $data['debeasientos'] : [];
		$data['haberes'] = is_array($data['haberasientos'] ?? null) ? $data['haberasientos'] : [];
		$data['cotizaciones'] = is_array($data['cotizacionasientos'] ?? null) ? $data['cotizacionasientos'] : [];
		$data['observaciones'] = is_array($data['observacionasientos'] ?? null) ? $data['observacionasientos'] : [];

		return $data;
	}

	/**
	 * Si los medios superan lo aplicado (pago de más), deja un crédito en CC
	 * (sin venta) para la próxima cobranza. totalcobranzas = aplicado − medios.
	 *
	 * @param  array<string, mixed>  $data
	 */
	private function persistirAnticiposPagoDeMas(array $data, $cobranzaId): void
	{
		$totalCobranzas = $data['totalcobranzas'] ?? [];
		if (! is_array($totalCobranzas)) {
			$totalCobranzas = ($totalCobranzas !== null && $totalCobranzas !== '') ? [$totalCobranzas] : [];
		}
		$monedaCobranzaIds = $data['moneda_cobranza_ids'] ?? [];
		if (! is_array($monedaCobranzaIds)) {
			$monedaCobranzaIds = ($monedaCobranzaIds !== null && $monedaCobranzaIds !== '') ? [$monedaCobranzaIds] : [];
		}

		foreach ($totalCobranzas as $i => $saldoMoneda) {
			$excedente = (float) $saldoMoneda;
			if ($excedente > -0.009) {
				continue;
			}

			$this->cliente_cuentacorrienteRepository->create([
				'fecha' => $data['fecha'],
				'fechavencimiento' => $data['fecha'],
				'cliente_id' => $data['cliente_id'],
				'total' => $excedente,
				'moneda_id' => $monedaCobranzaIds[$i] ?? ($data['moneda_id'] ?? 1),
				'cotizacion' => $data['cotizacion_cobranza'] ?? 1,
				'cobranza_id' => $cobranzaId,
				'empresa_id' => $data['empresa_id'],
			]);
		}
	}
}