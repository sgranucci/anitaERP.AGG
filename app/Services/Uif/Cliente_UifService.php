<?php
namespace App\Services\Uif;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Repositories\Uif\Cliente_UifRepositoryInterface;
use App\Repositories\Uif\Cliente_Archivo_UifRepositoryInterface;
use App\Repositories\Uif\Cliente_Premio_UifRepositoryInterface;
use App\Repositories\Uif\Cliente_Riesgo_UifRepositoryInterface;
use App\Repositories\Uif\Cliente_Premio_Archivo_UifRepositoryInterface;
use App\Repositories\Uif\Inusualidad_UifRepositoryInterface;
use App\Repositories\Uif\Monto_UifRepositoryInterface;
use App\Repositories\Uif\Puntaje_UifRepositoryInterface;
use App\Repositories\Uif\Factorriesgo_UifRepositoryInterface;
use App\Repositories\Uif\Frecuencia_UifRepositoryInterface;
use App\Services\Configuracion\CotizacionService;
use App\Services\Configuracion\ModuloAvisoService;
use App\Services\Uif\ClienteUifFotoDocumento;
use App\Services\Uif\ClienteUifSexoAprendizajeService;
use App\Support\Uif\ClienteUifCamposPorDefecto;
use App\Models\Uif\Cliente_Uif;
use App\Models\Uif\Cliente_Premio_Uif;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App;
use Auth;
use DB;
use Exception;

class Cliente_UifService 
{
	private $cliente_uifRepository;
    private $cliente_archivo_uifRepository;
    private $cliente_premio_uifRepository;
	private $cliente_riesgo_uifRepository;
	private $cliente_premio_archivo_uifRepository;
	private $inusualidad_uifRepository;
	private $monto_uifRepository;
	private $puntaje_uifRepository;
	private $factorriesgo_uifRepository;
	private $frecuencia_uifRepository;
	private $cotizacionService;
	private $clienteUifSexoAprendizajeService;
	private ModuloAvisoService $moduloAvisoService;

    public function __construct(Cliente_UifRepositoryInterface $cliente_uifrepository,
                                Cliente_Archivo_UifRepositoryInterface $cliente_archivo_uifrepository,
                                Cliente_Premio_UifRepositoryInterface $cliente_premio_uifrepository,
								Cliente_Riesgo_UifRepositoryInterface $cliente_riesgo_uifrepository,
								Cliente_Premio_Archivo_UifRepositoryInterface $cliente_premio_archivo_uifrepository,
								Inusualidad_UifRepositoryInterface $inusualidad_uifrepository,
								Monto_UifRepositoryInterface $monto_uifrepository,
								Puntaje_UifRepositoryInterface $puntaje_uifrepository,
								Factorriesgo_UifRepositoryInterface $factorriesgo_uifrepository,
								Frecuencia_UifRepositoryInterface $frecuencia_uifrepository,
								CotizacionService $cotizacionservice,
								ClienteUifSexoAprendizajeService $clienteUifSexoAprendizajeService,
								ModuloAvisoService $moduloAvisoService
								)
    {
		$this->cliente_uifRepository = $cliente_uifrepository;
        $this->cliente_archivo_uifRepository = $cliente_archivo_uifrepository;
        $this->cliente_premio_uifRepository = $cliente_premio_uifrepository;
		$this->cliente_riesgo_uifRepository = $cliente_riesgo_uifrepository;
		$this->cliente_premio_archivo_uifRepository = $cliente_premio_archivo_uifrepository;
		$this->inusualidad_uifRepository = $inusualidad_uifrepository;
		$this->monto_uifRepository = $monto_uifrepository;
		$this->puntaje_uifRepository = $puntaje_uifrepository;
		$this->factorriesgo_uifRepository = $factorriesgo_uifrepository;
		$this->frecuencia_uifRepository = $frecuencia_uifrepository;
		$this->cotizacionService = $cotizacionservice;
		$this->clienteUifSexoAprendizajeService = $clienteUifSexoAprendizajeService;
		$this->moduloAvisoService = $moduloAvisoService;
    }

	public function guardaCliente_Uif($request, $origen = null)
	{
		DB::beginTransaction();
		try
		{
			$data = $request->except(['fotodocumento']);
			$estado = Cliente_Uif::$enumEstado[array_search('A', array_column(Cliente_Uif::$enumEstado, 'valor'))]['nombre'];
			$data['estado'] = $estado;
			$fotodocumento = $request->file('fotodocumento');
			if ($fotodocumento) {
				$data['fotodocumento'] = ClienteUifFotoDocumento::storeUploadedFile(
					$fotodocumento,
					trim((string) $request->input('numerodocumento'))
				);
			}

			$data = ClienteUifCamposPorDefecto::aplicarEnAlta($data);

			$cliente_uif = $this->cliente_uifRepository->create($data);

			if ($cliente_uif == 'Error')
				throw new Exception('Error en grabacion');

			// Guarda tablas asociadas
			if ($cliente_uif)
				Self::agrega($data, $cliente_uif, $request);

			$this->clienteUifSexoAprendizajeService->registrarDesdeCliente(
				trim((string) $request->input('nombre')),
				trim((string) $request->input('sexo'))
			);

			DB::commit();

			$this->moduloAvisoService->enviar('uif', 'cliente_alta', (int) $cliente_uif->id);
		} catch (\Exception $e) {
			DB::rollback();

			Log::error('guardaCliente_Uif: '.$e->getMessage());

			return ['errores' => $e->getMessage()];
		}
        return ['mensaje' => 'ok', 'cliente_uif_id' => $cliente_uif->id ?? null];
	}

	// Agrega tablas asociadas
	private function agrega($data, $cliente_uif, $request)
	{
		if (isset($data['riesgo_ids']))
			$cliente_riesgo_uif = $this->cliente_riesgo_uifRepository->create($data, $cliente_uif->id);

		$cliente_archivo_uif = $this->cliente_archivo_uifRepository->create($request, $cliente_uif->id);
	}

    public function actualizaCliente_Uif($request, $id, $origen = null)
    {
		DB::beginTransaction();
		try
		{
			$data = $request->except(['fotodocumento']);
			$existente = $this->cliente_uifRepository->find($id);

			$fotodocumento = $request->file('fotodocumento');
			if ($fotodocumento) {
				$data['fotodocumento'] = ClienteUifFotoDocumento::storeUploadedFile(
					$fotodocumento,
					trim((string) $request->input('numerodocumento')),
					$existente->fotodocumento
				);
			} else {
				$renombrado = ClienteUifFotoDocumento::renameIfDocNumberChanged(
					(string) $existente->numerodocumento,
					trim((string) $request->input('numerodocumento')),
					$existente->fotodocumento
				);
				if ($renombrado !== null) {
					$data['fotodocumento'] = $renombrado;
				}
			}

			$data = ClienteUifCamposPorDefecto::aplicarEnActualizacion($data);

			$actualizarRiesgo = ! esCajeroUifSinSupervisor();

			Self::actualiza($data, $id, $request, $actualizarRiesgo);

			$this->clienteUifSexoAprendizajeService->registrarDesdeCliente(
				trim((string) $request->input('nombre')),
				trim((string) $request->input('sexo'))
			);

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();

			Log::error('actualizaCliente_Uif: '.$e->getMessage());

			return ['errores' => $e->getMessage()];
		}
        return ['mensaje' => 'ok'];
    }

	/**
	 * @param  bool  $actualizarRiesgo  Falso para cajero UIF: la solapa de riesgo la gestiona solo el supervisor.
	 */
	private function actualiza($data, $id, $request, $actualizarRiesgo = true)
	{
		// Graba cliente_uif
		$cliente_uif = $this->cliente_uifRepository->update($data, $id);

		if ($cliente_uif === 'Error')
			throw new Exception('Error en grabacion cliente');

		$this->cliente_archivo_uifRepository->update($request, $id);

		if ($actualizarRiesgo) {
			$this->cliente_riesgo_uifRepository->update($data, $id);
		}
	}

	public function guardaCliente_Premio_Uif($request)
	{
		DB::beginTransaction();
		try
		{
			$data = $this->normalizarDatosPremio($request->all());
			$cliente_premio_uif = $this->cliente_premio_uifRepository->createUnique($data);

			if ($cliente_premio_uif == 'Error' || ! $cliente_premio_uif) {
				throw new Exception('Error en grabacion');
			}

			$this->cliente_premio_archivo_uifRepository->create($request, $cliente_premio_uif->id);

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();
			Log::error('UIF guardaCliente_Premio_Uif: '.$e->getMessage(), [
				'cliente_uif_id' => $request->input('cliente_uif_id'),
				'exception' => $e,
			]);

			return ['errores' => $e->getMessage()];
		}

		return ['mensaje' => 'ok', 'cliente_premio_uif_id' => $cliente_premio_uif->id ?? null];
	}

    public function actualizaCliente_Premio_Uif($request, $id)
    {
		DB::beginTransaction();
		try
		{
			$data = $this->normalizarDatosPremio($request->all());
			$cliente_premio_uif = $this->cliente_premio_uifRepository->updateUnique($data, $id);

			if ($cliente_premio_uif == 'Error') {
				throw new Exception('Error en grabacion');
			}

			$this->cliente_premio_archivo_uifRepository->update($request, $id);

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();
			Log::error('UIF actualizaCliente_Premio_Uif: '.$e->getMessage(), [
				'cliente_premio_uif_id' => $id,
				'exception' => $e,
			]);

			return ['errores' => $e->getMessage()];
		}

		return ['mensaje' => 'ok'];
    }

	/**
	 * Prepara atributos del premio para create/update (fillable + fechas).
	 */
	private function normalizarDatosPremio(array $data): array
	{
		foreach (['fechaentrega', 'fechatito'] as $campo) {
			$valor = $data[$campo] ?? null;
			if ($valor === null || $valor === '') {
				$data[$campo] = null;
				continue;
			}
			try {
				$data[$campo] = Carbon::parse(str_replace('T', ' ', (string) $valor))->format('Y-m-d H:i:s');
			} catch (\Throwable $e) {
				$data[$campo] = null;
			}
		}

		if (empty($data['fechaentrega'])) {
			throw new Exception('La fecha de entrega del premio es obligatoria.');
		}

		$fillable = (new Cliente_Premio_Uif())->getFillable();
		$normalizado = [];
		foreach ($fillable as $campo) {
			if (array_key_exists($campo, $data)) {
				$normalizado[$campo] = $data[$campo];
			}
		}

		return $normalizado;
	}

	public function calculaRiesgo($cliente_uif_id, $periodo, $inusualidad_uif_id)
	{
		if (strlen($periodo) == 5)
			$periodo = '0'.$periodo;

		// Trae el cliente
		$cliente_uif = $this->cliente_uifRepository->find($cliente_uif_id);		

		// En base al periodo arma rango de fechas
		$anio = substr($periodo,2,5);
		$mes = substr($periodo,0,2);
		$dias = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
		$fecha = $anio.'-'.$mes.'-01';
		$desdeFecha = Carbon::createFromFormat('Y-m-d', $fecha); // Pasa a formato fecha
		$fecha = $anio.'-'.$mes.'-'.$dias;
		$hastaFecha = Carbon::createFromFormat('Y-m-d', $fecha); // Pasa a formato fecha

		$fecha2 = Carbon::createFromFormat('Y-m-d', '2025-12-31');

		// Trae los premios del mes
		$montoOperadoMensual = 0;
		$puntajeJuego = 0;
		$cantidadVisita = 0;
		$puntaje = [];
		foreach ($cliente_uif->cliente_premios_uif as $premio)
		{
			if ($desdeFecha < $premio->fechaentrega &&
				$hastaFecha > $premio->fechaentrega)
			{
				// Convierte a pesos
				$cotizacion = $this->cotizacionService->leeCotizacionDiaria($premio->fechaentrega, $premio->moneda_id);
				$coeficienteConversion = calculaCoeficienteMoneda(config('cotizacion.ID_MONEDA_DEFAULT'), $premio->moneda_id, $cotizacion);
				$montoOperadoMensual += ($premio->monto * $coeficienteConversion);

				// Deja el puntaje del ultimo juego del periodo
				$puntaje[7] = $premio->juegos_uif->puntaje;

				$cantidadVisita++;
			}
		}

		// Calcula puntajes
		$puntaje[1] = $cliente_uif->actividades_uif->puntaje;
		$puntaje[2] = $cliente_uif->paises_uif->puntaje;
		$puntaje[3] = $cliente_uif->peps_uif->puntaje;
		$puntaje[4] = $cliente_uif->provincias_uif->puntaje;
		$puntaje[5] = $cliente_uif->sos_uif->puntaje;

		// Calcula puntaje de inusualidad
		$inusualidad_uif = $this->inusualidad_uifRepository->find($inusualidad_uif_id);

		$puntaje[8] = 0;
		if ($inusualidad_uif)
			$puntaje[8] = $inusualidad_uif->puntaje;

		// Calcula puntaje en funcion del monto de juego mensual
		$monto_uif = $this->monto_uifRepository->findPorMonto($montoOperadoMensual);

		$puntaje[9] = 0;
		foreach($monto_uif as $monto)
		{
			$puntaje[9] += $monto->puntaje;
		}
		
		// Calcula puntaje en funcion de frecuencia
		$frecuencia_uif = $this->frecuencia_uifRepository->findPorFrecuencia($cantidadVisita);

		$puntaje[6] = 0;
		foreach($frecuencia_uif as $frecuencia)
		{
			$puntaje[6] += $frecuencia->puntaje;
		}

		// Lee factor de riesgo para sacar ponderacion
		$factorriesgo_uif = $this->factorriesgo_uifRepository->all();
		$valorPuntaje = 0;
		foreach($factorriesgo_uif as $factor)
			$valorPuntaje += $factor->ponderacion * $puntaje[$factor->id] / 100.;

		// Busca en puntaje el valor calculado para sacar el riesgo
		$puntaje_uif = $this->puntaje_uifRepository->findPorPuntaje($valorPuntaje);

		$riesgo = 'FALTAN DATOS';
		if ($puntaje_uif)
			$riesgo = $puntaje_uif->riesgo;

		return ['riesgo' => $riesgo];
	}

	public function generaExportaOperacion($periodo, $limiteinformeuif, $empresaId)
	{
		return $this->cliente_premio_uifRepository->listaPremioParaExportar($periodo, $limiteinformeuif, $empresaId);
	}

	public function resumenExportaOperacion($premios): array
	{
		$coleccion = collect($premios);

		return [
			'cantidad' => $coleccion->count(),
			'total' => (float) $coleccion->sum('monto'),
		];
	}

	public function exportaOperacion($periodo, $limiteinformeuif, $empresaId): array
	{
		$premios = $this->cliente_premio_uifRepository->listaPremioParaExportar($periodo, $limiteinformeuif, $empresaId);

		return app(ClienteUifExportacionXmlService::class)->exportar($periodo, (int) $empresaId, $premios);
	}
}