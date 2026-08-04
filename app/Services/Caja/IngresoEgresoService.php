<?php
namespace App\Services\Caja;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Repositories\Configuracion\SeteosalidaRepositoryInterface;
use App\Repositories\Caja\Caja_MovimientoRepositoryInterface;
use App\Repositories\Caja\Caja_Movimiento_CuentacajaRepositoryInterface;
use App\Repositories\Caja\Caja_Movimiento_EstadoRepositoryInterface;
use App\Repositories\Caja\Caja_Movimiento_ArchivoRepositoryInterface;
use App\Repositories\Caja\Tipotransaccion_CajaRepositoryInterface;
use App\Repositories\Caja\ConceptogastoRepositoryInterface;
use App\Repositories\Caja\ChequeRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Localidad;
use App\Models\Caja\Caja_Movimiento_Estado;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Caja\IngresoEgresoChequeAsientoSupport;
use App\Support\Caja\IngresoEgresoComprobanteIvaAsientoSupport;
use App\Support\Caja\IngresoEgresoTransferenciaSupport;
use InvalidArgumentException;
use App;
use Auth;
use DB;
use Exception;

class IngresoEgresoService 
{
	private $caja_movimientoRepository;
    private $caja_movimiento_cuentacajaRepository;
    private $caja_movimiento_estadoRepository;
    private $caja_movimiento_archivoRepository;
	private $tipoasientoRepository;
	private $cuentacontableRepository;
    private $centrocostoRepository;
	private $asientoRepository;
	private $asiento_movimientoRepository;
	private $cuentacajaRepository;
	private $tipotransaccion_cajaRepository;
	private $conceptogastoRepository;
	private $chequeRepository;
	private $comprobanteIvaService;

    public function __construct(Caja_MovimientoRepositoryInterface $caja_movimientorepository,
                                Caja_Movimiento_CuentacajaRepositoryInterface $caja_movimiento_cuentacajarepository,
                                Caja_Movimiento_EstadoRepositoryInterface $caja_movimiento_estadorepository,
                                Caja_Movimiento_ArchivoRepositoryInterface $caja_movimiento_archivorepository,
								ConceptogastoRepositoryInterface $conceptogastorepository,
								TipoasientoRepositoryInterface $tipoasientorepository,
								CuentacajaRepositoryInterface $cuentacajarepository,
								CuentacontableRepositoryInterface $cuentacontablerepository,
                                CentroCostoRepositoryInterface $centrocostorepository,
								AsientoRepositoryInterface $asientorepository,
								Asiento_MovimientoRepositoryInterface $asiento_movimientorepository,
								SeteosalidaRepositoryInterface $seteosalidarepository,
								TipoTransaccion_CajaRepositoryInterface $tipotransaccion_cajarepository,
								ChequeRepositoryInterface $chequeRepository,
								IngresoEgresoComprobanteIvaService $comprobanteIvaService,
								)
    {
		$this->caja_movimientoRepository = $caja_movimientorepository;
        $this->caja_movimiento_cuentacajaRepository = $caja_movimiento_cuentacajarepository;
        $this->caja_movimiento_estadoRepository = $caja_movimiento_estadorepository;
        $this->caja_movimiento_archivoRepository = $caja_movimiento_archivorepository;
		$this->conceptogastoRepository = $conceptogastorepository;
		$this->tipoasientoRepository = $tipoasientorepository;
		$this->asientoRepository= $asientorepository;
		$this->asiento_movimientoRepository= $asiento_movimientorepository;
		$this->seteoSalidaRepository = $seteosalidarepository;
		$this->cuentacajaRepository = $cuentacajarepository;
		$this->cuentacontableRepository = $cuentacontablerepository;
        $this->centrocostoRepository = $centrocostorepository;
		$this->tipotransaccion_cajaRepository = $tipotransaccion_cajarepository;
		$this->chequeRepository = $chequeRepository;
		$this->comprobanteIvaService = $comprobanteIvaService;
    }

	public function guardaIngresoEgreso($request, $origen = null)
	{
		session(['empresa_id' => $request->empresa_id]);

		PeriodoContableCierreSupport::assertOperacionPermitida(
			(int) $request->input('empresa_id'),
			(string) ($request->input('fecha') ?? date('Y-m-d')),
			PeriodoContableCierreSupport::ALCANCE_CAJA
		);

		$this->validarComprobantesIvaContraCaja($request);

		$data = $request->all();

		try {
			IngresoEgresoTransferenciaSupport::assertBalanceado($data);
		} catch (InvalidArgumentException $e) {
			return ['errores' => $e->getMessage()];
		}

   		// Crea estado
	   	$data['fechas'][] = Carbon::now();
	   	$data['estados'][] = Caja_Movimiento_Estado::$enumEstado[0]['valor'];
	   	$data['observacionestados'][] = "Alta de Movimiento de Caja";

		if ($origen)
		{
			$caja_movimiento = $this->caja_movimientoRepository->create($request->all());

			if (!$caja_movimiento)
				throw new Exception('Error en grabacion');

			Self::agrega($data, $caja_movimiento, $request);
		}
		else
		{
			DB::beginTransaction();
			try
			{
				$caja_movimiento = $this->caja_movimientoRepository->create($request->all());

				if ($caja_movimiento == 'Error')
					throw new Exception('Error en grabacion');

				// Guarda tablas asociadas
				if ($caja_movimiento)
					Self::agrega($data, $caja_movimiento, $request);

				$this->sincronizarComprobantesIva($request, (int) $caja_movimiento->id, (int) $request->input('empresa_id'));

				DB::commit();

				if ($caja_movimiento) {
					app(\App\Services\Solicitudpago\SolicitudpagoPagoDesdeCajaService::class)
						->sincronizarDesdeMovimiento($caja_movimiento->fresh());
				}
			} catch (\Exception $e) {
				DB::rollback();

				// Borra el asiento creado

				return ['errores' => $e->getMessage()];
			}
		}
        return ['mensaje' => 'ok'];
	}

	private function agrega($data, $caja_movimiento, $request)
	{
		$caja_movimiento_cuentacaja = $this->caja_movimiento_cuentacajaRepository->create($data, $caja_movimiento->id);
		$caja_movimiento_estado = $this->caja_movimiento_estadoRepository->create($data, $caja_movimiento->id);
		$caja_movimiento_archivo = $this->caja_movimiento_archivoRepository->create($request, $caja_movimiento->id);

		$this->chequeRepository->guardarChequeIngresoEgreso($data, 'create', (int) $caja_movimiento->id);

		// Graba asiento contable
		if (isset($data['cuentacontable_ids']))
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
			$data['caja_movimiento_id'] = $caja_movimiento->id;

			$data['observacion'] = $data['detalle'];

			$asiento = $this->asientoRepository->create($data);

			if ($asiento == 'Error')
				throw new Exception('Error en grabacion anita.');

			if ($asiento)
				$asiento_movimiento = $this->asiento_movimientoRepository->create($data, $asiento->id);
		}
	}

    public function actualizaIngresoEgreso($request, $id, $origen = null)
    {
        session(['empresa_id' => $request->empresa_id]);

		PeriodoContableCierreSupport::assertOperacionPermitida(
			(int) $request->input('empresa_id'),
			(string) ($request->input('fecha') ?? date('Y-m-d')),
			PeriodoContableCierreSupport::ALCANCE_CAJA
		);

		$this->validarComprobantesIvaContraCaja($request);

		$data = $request->all();

		try {
			IngresoEgresoTransferenciaSupport::assertBalanceado($data);
		} catch (InvalidArgumentException $e) {
			return ['errores' => $e->getMessage()];
		}

		// Crea estado
		$data['fechas'][] = Carbon::now();
		$data['estados'][] = Caja_Movimiento_Estado::$enumEstado[0]['valor'];
		$data['observacionestados'][] = "Alta de Movimiento de Caja";

		if ($origen)
			Self::actualiza($data, $id, $request);
		else
		{
			DB::beginTransaction();
			try
			{
				Self::actualiza($data, $id, $request);

				$this->sincronizarComprobantesIva($request, $id, (int) $request->input('empresa_id'));

				DB::commit();

				$mov = $this->caja_movimientoRepository->find($id);
				if ($mov) {
					app(\App\Services\Solicitudpago\SolicitudpagoPagoDesdeCajaService::class)
						->sincronizarDesdeMovimiento($mov);
				}
			} catch (\Exception $e) {
				DB::rollback();

				return ['errores' => $e->getMessage()];
			}
		}
        return ['mensaje' => 'ok'];
    }

	private function actualiza($data, $id, $request)
	{
		// Graba movimiento de caja
		$caja_movimiento = $this->caja_movimientoRepository->update($data, $id);

		if ($caja_movimiento === 'Error')
			throw new Exception('Error en grabacion anita.');

		// Graba movimientos de cuentas de caja
		$this->caja_movimiento_cuentacajaRepository->update($data, $id);

		// Graba movimientos de estados del movimiento de caja
		$this->caja_movimiento_estadoRepository->update($data, $id);

		// Graba archivos del ingreso egreso
		$this->caja_movimiento_archivoRepository->update($request, $id);

		$this->chequeRepository->guardarChequeIngresoEgreso($data, 'update', (int) $id);

		// Graba asiento
		if (isset($data['cuentacontable_ids']))
		{
			// Busca el asiento correspondiente al movimiento de caja
			$asiento = $this->asientoRepository->leeAsientoPorClave($id, 'caja_movimiento_id');

			if (count($asiento) > 0)
				$asiento_id = $asiento[0]->id;

			if (!isset($data['numeroasiento']))
			{
				$data['tipoasiento_id'] = $asiento[0]->tipoasiento_id;
				$data['numeroasiento'] = $asiento[0]->numeroasiento;
			}

			// Arma el asiento contable
			$data['moneda_ids'] = $data['monedaasiento_ids'];
			$data['centrocosto_ids'] = $data['centrocostoasiento_ids'];
			$data['debes'] = $data['debeasientos'];
			$data['haberes'] = $data['haberasientos'];
			$data['cotizaciones'] = $data['cotizacionasientos'];
			$data['observaciones'] = $data['observacionasientos'];
			$data['caja_movimiento_id'] = $id;
			$data['observacion'] = $data['detalle'];

			if (count($asiento) > 0)
			{
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

				$asiento = $this->asientoRepository->create($data);

				if ($asiento == 'Error')
					throw new Exception('Error en grabacion anita.');

				if ($asiento)
					$asiento_movimiento = $this->asiento_movimientoRepository->create($data, $asiento->id);
			}
		}
	}

	public function copiarIngresoEgreso(Request $request)
    {
		$id = $request->id;
		$fechacopia = $request->fechacopia;
		$flRevierte = false;

		if (isset($request->revierte))
			$flRevierte = true;

		$data = $this->caja_movimientoRepository->find($id)->toArray();

		$cuentacaja_ids = [];
		$montos = [];
		$moneda_ids = [];
		$cotizaciones = [];
		foreach ($data['caja_movimiento_cuentacaja'] as $movimiento)
		{
			$cuentacaja_ids[] = $movimiento['cuentacaja_id'];

			if ($flRevierte)
			{
				if ($movimiento['monto'] >= 0)
					$montos[] = $movimiento['monto'] * -1.;
				else
					$montos[] = abs($movimiento['monto']);
			}
			else
				$montos[] = $movimiento['monto'];

			$cuentacaja_ids[] = $movimiento['cuentacaja_id'];
			$moneda_ids[] = $movimiento['moneda_id'];
			$cotizaciones[] = $movimiento['cotizacion'];
		}
		$nombrearchivos = [];
		foreach ($data['caja_movimiento_archivos'] as $archivo) 
			$nombrearchivos[] = $archivo['nombrearchivo'];

		$datas = ['cuentacaja_ids' => $cuentacaja_ids,
					'moneda_ids' => $moneda_ids,
					'cotizaciones' => $cotizaciones,
					'montos' => $montos,
					];

		// Modifica la observacion
		$data['detalle'] = ($flRevierte ? 'Revierte movimiento ' : 'Copiado de ').$data['numeroasiento'].' '.$data['detalle'];

		// Graba el ingreso y egreso
		DB::beginTransaction();
		try
		{
			$caja_movimiento = $this->caja_movimientoRepository->create($data);

			if ($caja_movimiento == 'Error')
				throw new Exception('Error en grabacion anita.');

			// Guarda tablas asociadas
			if ($caja_movimiento)
			{
				$this->caja_movimiento_cuentacajaRepository->create($datas, $caja_movimiento->id);
				$this->caja_movimiento_estadoRepository->create($datas, $caja_movimiento->id);
				
				foreach($nombrearchivos as $archivo)
					$caja_movimiento_archivo = $this->caja_movimiento_archivoRepository->copiaArchivo($id, $archivo, $caja_movimiento->id);
			}

			DB::commit();

			return ['caja_movimiento_id' => $caja_movimiento->id, 'numerotransaccion' => $caja_movimiento->numerotransaccion];

		} catch (\Exception $e) {
			DB::rollback();

			// Borra el asiento creado

			return ['errores' => $e->getMessage()];
		}
	}
	
	public function borraIngresoEgreso($id)
	{
	}

	public function generaAsientoContable(array $data)
	{
		$datosCaja = json_decode($data['datoscaja'] ?? '[]') ?: [];
		$datosContables = json_decode($data['datoscontables'] ?? '[]') ?: [];
		$datosChequesEmitidos = json_decode($data['datoscheques_emitidos'] ?? '[]') ?: [];
		$datosChequesRecibidos = json_decode($data['datoscheques_recibidos'] ?? '[]') ?: [];
		$datosChequesReemplazo = json_decode($data['datoscheques_reemplazo'] ?? '[]') ?: [];
		$tipotransaccion_caja_id = json_decode($data['tipotransaccion_caja_id'] ?? '0');
		$conceptogasto_id = json_decode($data['conceptogasto_id'] ?? '0');
		$empresa_id = (int) json_decode($data['empresa_id'] ?? '0');
		$fechaOperacion = (string) ($data['fecha'] ?? date('Y-m-d'));

		$tipotransaccion_caja = $this->tipotransaccion_cajaRepository->find($tipotransaccion_caja_id);
		$signo = 1;
		if ($tipotransaccion_caja)
		{
			if ($tipotransaccion_caja->signo == 'I')
				$signo = 1;
			else
				$signo = -1;
		}

		// Transferencia (TRA): los montos ya vienen firmados (+ entrada / − salida).
		if (IngresoEgresoTransferenciaSupport::esTransferencia($tipotransaccion_caja)) {
			$signo = 1;
		}

		// Arma cuentas contables de cada imputacion de caja
		$asiento = [];
		if (count($datosContables) > 0)
		{
			foreach($datosContables as $imputacionContable)
			{
				$cuentacontable = $this->cuentacontableRepository->find($imputacionContable->cuentacontable_ids);

				if ($cuentacontable)
					$asiento[] = [ 'cuentacontable_id' => $imputacionContable->cuentacontable_ids,
							'codigo' => $cuentacontable->codigo,
							'nombre' => $cuentacontable->nombre,
							'moneda_id' => $imputacionContable->monedaasiento_ids,
							'cotizacion' => $imputacionContable->cotizacionasientos,
							'centrocosto_id' => $imputacionContable->centrocostoasiento_ids,
							'debe' => $imputacionContable->debeasientos,
							'haber' => $imputacionContable->haberasientos,
							'observacion' => $imputacionContable->observacionasientos,
							'carga_cuentacontable_manual' => $imputacionContable->carga_cuentacontable_manuales
							];
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
					$importeFirmado = (float) $movimiento->montos * $signo;
					if ($importeFirmado > 0)
					{
						$debe = round(abs($importeFirmado), 2);
						$haber = '';
					}
					else
					{
						$debe = '';
						$haber = round(abs($importeFirmado), 2);
					}

					for ($i = 0, $flExiste = false; $i < count($asiento) && !$flExiste; $i++)
					{
						if ($asiento[$i]['cuentacontable_id'] == $cuentacaja->cuentacontable_id &&
							$asiento[$i]['moneda_id'] == $movimiento->moneda_ids &&
							$asiento[$i]['cotizacion'] == $movimiento->cotizaciones)
							$flExiste = true;
					}
					if (!$flExiste)
					{
						$cuentacontable = $this->cuentacontableRepository->find($cuentacaja->cuentacontable_id);

						if ($cuentacontable)
							$asiento[] = [ 'cuentacontable_id' => $cuentacaja->cuentacontable_id,
											'codigo' => $cuentacontable->codigo,
											'nombre' => $cuentacontable->nombre,
											'moneda_id' => $movimiento->moneda_ids,
											'cotizacion' => $movimiento->cotizaciones,
											'centrocosto_id' => 0,
											'debe' => $debe,
											'haber' => $haber,
											'observacion' => '',
											'carga_cuentacontable_manual' => 'N'
									];
					}
					else
					{
						$asiento[$i - 1]['debe'] = (float) ($asiento[$i - 1]['debe'] ?: 0) + (float) ($debe ?: 0);
						$asiento[$i - 1]['haber'] = (float) ($asiento[$i - 1]['haber'] ?: 0) + (float) ($haber ?: 0);
					}
				}
			}

			IngresoEgresoChequeAsientoSupport::agregarLineasCheques(
				$asiento,
				$datosChequesEmitidos,
				$datosChequesRecibidos,
				$datosChequesReemplazo,
				$signo,
				$empresa_id,
				$fechaOperacion,
				$this->cuentacajaRepository,
				$this->cuentacontableRepository
			);
		}

		// Agrega contrapartida de gastos (concepto de caja) o comprobantes IVA compra
		$comprobantesIva = $this->decodificarComprobantesIvaJson($data['comprobantes_ivacompra_json'] ?? null);

		if ($comprobantesIva !== []) {
			$this->agregarLineasDebeComprobantesIva($asiento, $comprobantesIva, $signo);
		} elseif ($conceptogasto_id > 0 && count($datosContables) == 0)
		{
			$conceptogasto = $this->conceptogastoRepository->find($conceptogasto_id);

			if ($conceptogasto)
			{
				// Asume como moneda del asiento la moneda del 1er. movimiento
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
				foreach($conceptogasto->conceptogasto_cuentacontables as $cuenta)
				{
					if ($cuenta->empresa_id == $empresa_id)
					{
						$cuentacontable = $this->cuentacontableRepository->find($cuenta->cuentacontable_id);

						if ($cuentacontable)
						{
							if ($totalHaber != 0)
							{
								$debe = abs($totalHaber);
								$haber = '';
							}
							else
							{
								$debe = '';
								$haber = $totalDebe;
							}
							$asiento[] = [ 'cuentacontable_id' => $cuentacontable->id,
											'codigo' => $cuentacontable->codigo,
											'nombre' => $cuentacontable->nombre,
											'moneda_id' => $monedaAsiento_id,
											'cotizacion' => $cotizacion,
											'centrocosto_id' => 0,
											'debe' => $debe,
											'haber' => $haber,
											'observacion' => '',
											'carga_cuentacontable_manual' => 'N'
									];
						}
					}
				}
			}
		}
		return ['mensaje' => 'ok', 'asiento' => $asiento];
	}

	/** @param  list<array<string, mixed>>  $comprobantes */
	private function agregarLineasDebeComprobantesIva(array &$asiento, array $comprobantes, int $signo): void
	{
		if ($asiento === []) {
			return;
		}

		$monedaAsientoId = $asiento[0]['moneda_id'];
		$cotizacion = $asiento[0]['cotizacion'];

		try {
			$lineasDebe = IngresoEgresoComprobanteIvaAsientoSupport::lineasDebeDesdeComprobantes($comprobantes);
		} catch (\Throwable $e) {
			throw new Exception($e->getMessage());
		}

		foreach ($lineasDebe as $linea) {
			$cuentacontable = $this->cuentacontableRepository->find($linea['cuentacontable_id']);
			if (! $cuentacontable) {
				continue;
			}

			$importe = round((float) $linea['importe'], 2);
			$asiento[] = [
				'cuentacontable_id' => $linea['cuentacontable_id'],
				'codigo' => $cuentacontable->codigo,
				'nombre' => $cuentacontable->nombre,
				'moneda_id' => $monedaAsientoId,
				'cotizacion' => $cotizacion,
				'centrocosto_id' => $linea['centrocosto_id'] ?? 0,
				'debe' => $signo >= 0 ? $importe : '',
				'haber' => $signo < 0 ? $importe : '',
				'observacion' => $linea['observacion'] ?? '',
				'carga_cuentacontable_manual' => 'N',
			];
		}
	}

	private function sincronizarComprobantesIva($request, int $cajaMovimientoId, int $empresaId): void
	{
		$json = $request->input('comprobantes_ivacompra_json');
		if ($json === null || $json === '') {
			return;
		}

		$comprobantes = $this->decodificarComprobantesIvaJson($json);
		$this->comprobanteIvaService->sincronizarDesdeJson($cajaMovimientoId, $empresaId, $comprobantes);
	}

	/** @return list<array<string, mixed>> */
	private function decodificarComprobantesIvaJson(mixed $json): array
	{
		if (is_array($json)) {
			return $json;
		}

		if (! is_string($json) || trim($json) === '') {
			return [];
		}

		$decoded = json_decode($json, true);

		return is_array($decoded) ? $decoded : [];
	}

	private function validarComprobantesIvaContraCaja($request): void
	{
		$comprobantes = $this->decodificarComprobantesIvaJson($request->input('comprobantes_ivacompra_json'));
		if ($comprobantes === []) {
			return;
		}

		$lineasCaja = [];
		$montos = $request->input('montos', []);
		$monedaIds = $request->input('moneda_ids', []);
		$cotizaciones = $request->input('cotizaciones', []);

		if (is_array($montos)) {
			for ($i = 0; $i < count($montos); $i++) {
				$lineasCaja[] = [
					'montos' => $montos[$i] ?? 0,
					'moneda_ids' => $monedaIds[$i] ?? 1,
					'cotizaciones' => $cotizaciones[$i] ?? 1,
				];
			}
		}

		$monedaRef = (int) ($monedaIds[0] ?? 1);
		$this->comprobanteIvaService->validarTotalesContraCaja($comprobantes, $lineasCaja, $monedaRef);
	}

}