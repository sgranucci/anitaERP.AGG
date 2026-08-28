<?php
namespace App\Services\Configuracion;

use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Provincia_Tasaiibb;
use App\Repositories\Configuracion\Padron_IibbRepositoryInterface;
use App\Repositories\Configuracion\Padron_Iibb_ArbaRepositoryInterface;
use App\Repositories\Configuracion\Padron_Iibb_CabaRepositoryInterface;
use App\Repositories\Configuracion\Padron_Coeficiente_TucumanRepositoryInterface;
use App\Repositories\Configuracion\CondicionIIBBRepositoryInterface;
use App\Repositories\Configuracion\ProvinciaRepositoryInterface;
use App\Support\Ventas\ClienteExclusionPercepcionSupport;

class IIBBService 
{
	protected $padron_iibbRepository;
	protected $padron_iibb_arbaRepository;
	protected $padron_iibb_cabaRepository;
	protected $padron_coeficiente_tucumanRepository;
	protected $condicion_iibbRepository;
	protected $provinciaRepository;

	private $tasapercepcion;
	private $flLeyoPadron;

	public function __construct(Padron_IibbRepositoryInterface $padron_iibbRepository,
								Padron_Iibb_ArbaRepositoryInterface $padron_iibb_arbaRepository,
								Padron_Iibb_CabaRepositoryInterface $padron_iibb_cabaRepository,
								Padron_Coeficiente_TucumanRepositoryInterface $padron_coeficiente_tucumanRepository,
								CondicionIIBBRepositoryInterface $condicion_iibbRepository,
								ProvinciaRepositoryInterface $provinciaRepository)
	{
		$this->padron_iibbRepository = $padron_iibbRepository;
		$this->padron_iibb_arbaRepository = $padron_iibb_arbaRepository;
		$this->padron_iibb_cabaRepository = $padron_iibb_cabaRepository;
		$this->padron_coeficiente_tucumanRepository = $padron_coeficiente_tucumanRepository;
		$this->condicion_iibbRepository = $condicion_iibbRepository;
		$this->provinciaRepository = $provinciaRepository;
	}

	public function leeTasaPercepcion($nroinscripcion, $jurisdiccion, $fechaFactura = null)
	{
		$this->flLeyoPadron = false;
		
		$cuit = str_replace("-", "", $nroinscripcion);
		switch($jurisdiccion)
		{
			case 901: // Caba
				$tasa_iibb = $this->padron_iibb_cabaRepository->findPorCuit($cuit, $fechaFactura);
				break;
			case 902: // Arba
				$tasa_iibb = $this->padron_iibb_arbaRepository->findPorCuit($cuit, $fechaFactura);
				break;
			case 904: // Cordoba
            case 908: // Entre Rios
            case 914: // Misiones
			case 921: // Santa Fe
            case 924: // Tucuman tasas y coeficientes
				$tasa_iibb = $this->padron_iibbRepository->leePadronIibb($cuit, 'percepcion', $jurisdiccion, $fechaFactura);
				break;
			default:
				$tasa_iibb = null;
				break;
		}

		// Solo se considera leído el padrón si trajo una alícuota. Las jurisdicciones
		// que resuelven por array devuelven estructura incluso cuando el CUIT no está.
		$this->flLeyoPadron = $this->tasaPercepcionDesdePadron($tasa_iibb) !== null;

		return $tasa_iibb;
	}

	/**
	 * Primera vigencia descargada para el CUIT en la jurisdicción (ARBA/CABA).
	 * Sirve para omitir el cotejo cuando la factura es anterior al padrón que hay.
	 */
	public function minDesdefechaPercepcion($nroinscripcion, $jurisdiccion): ?string
	{
		$cuit = str_replace('-', '', (string) $nroinscripcion);

		switch ((int) $jurisdiccion) {
			case 901:
				$min = $this->padron_iibb_cabaRepository->minDesdefechaPorCuit($cuit);
				break;
			case 902:
				$min = $this->padron_iibb_arbaRepository->minDesdefechaPorCuit($cuit);
				break;
			default:
				$min = null;
				break;
		}

		if ($min === null || $min === '') {
			return null;
		}

		return substr((string) $min, 0, 10);
	}

	/**
	 * Alícuota de percepción del padrón, normalizada.
	 *
	 * ARBA y CABA devuelven un modelo con "tasapercepcion"; las demás jurisdicciones
	 * devuelven un array con la alícuota en "tasa".
	 *
	 * @param  mixed  $registroPadron
	 * @return float|null null cuando el CUIT no está en el padrón vigente
	 */
	public function tasaPercepcionDesdePadron($registroPadron): ?float
	{
		if (! $registroPadron) {
			return null;
		}

		if (is_array($registroPadron)) {
			$tasa = $registroPadron['tasapercepcion'] ?? $registroPadron['tasa'] ?? null;
		} else {
			$tasa = $registroPadron->tasapercepcion ?? $registroPadron->tasa ?? null;
		}

		return ($tasa !== null && $tasa !== '') ? (float) $tasa : null;
	}

	/**
	 * Tasa de retención IIBB desde padrón (ARBA/CABA/otras).
	 *
	 * @return array{tasa: float|null, tipocontribuyente: string|null, origen: string}|null
	 */
	public function leeTasaRetencion($nroinscripcion, $jurisdiccion, $fecha = null): ?array
	{
		$cuit = str_replace('-', '', (string) $nroinscripcion);
		$registro = null;

		switch ((int) $jurisdiccion) {
			case 901:
				$registro = $this->padron_iibb_cabaRepository->findPorCuit($cuit, $fecha);
				break;
			case 902:
				$registro = $this->padron_iibb_arbaRepository->findPorCuit($cuit, $fecha);
				break;
			case 904:
			case 908:
			case 914:
			case 921:
			case 924:
				$registro = $this->padron_iibbRepository->leePadronIibb($cuit, 'retencion', $jurisdiccion, $fecha);
				break;
		}

		if ($registro === null) {
			return null;
		}

		if (is_array($registro)) {
			$tasa = $registro['tasa'] ?? $registro['tasaretencion'] ?? null;

			return [
				'tasa' => $tasa !== null && $tasa !== '' ? (float) $tasa : null,
				'tipocontribuyente' => $registro['tipocontribuyente'] ?? null,
				'origen' => 'padron',
			];
		}

		$tasa = $registro->tasaretencion ?? null;

		return [
			'tasa' => $tasa !== null && $tasa !== '' ? (float) $tasa : null,
			'tipocontribuyente' => $registro->tipocontribuyente ?? null,
			'origen' => 'padron',
		];
	}

	public function leyoPadron(): bool
	{
		return (bool) $this->flLeyoPadron;
	}

	// Calcula percepciones de ingresos brutos para ventas

	public function calculaPercepcionIIBB($totalNeto, $numeroDocumento, $condicioniibb_id, $provincia_id, $cm05, $fechaFactura, $cliente_id = null)
	{
		$percepcionesIIBB = [];

		$condicioniibb = $this->condicion_iibbRepository->find($condicioniibb_id);

		if ($condicioniibb->formacalculo != 'N' && $condicioniibb->estado == 'A')
		{
			$jurisdiccionesPercepcion = array_values(array_filter(array_map('trim', explode(',', (string) config('anita.agente_percepcion_iibb', '')))));
			$tasasDescarte = array_map('trim', explode(',', (string) config('anita.tasas_descarte_iibb', '0,0')));
			$minimoNeto = array_map('trim', explode(',', (string) config('anita.minimo_neto_iibb', '0,0')));
			$minimaPercepcion = array_map('trim', explode(',', (string) config('anita.minima_percepcion_iibb', '0,0')));

			// Lee provincia en donde es local el cliente
			$jurisdiccionCliente = null;
			if ($provincia_id != null)
			{
				$provinciaLocal = $this->provinciaRepository->find($provincia_id);

				if ($provinciaLocal)
					$jurisdiccionCliente = $provinciaLocal->jurisdiccion;
			}
			else
				$jurisdiccionCliente = $jurisdiccionesPercepcion[0];

			$percepcionesIIBB = [];
			// Calcula IIBB por cada jurisdiccion que la empresa percibe
			for ($i = 0; $i < count($jurisdiccionesPercepcion); $i++)
			{
				$provincia = $this->provinciaRepository->findPorJurisdiccion($jurisdiccionesPercepcion[$i]);

				if (! $provincia) {
					continue;
				}

				if (!isset($minimoNeto[$i]))
					$minimoNeto[$i] = 0;

				if (!isset($minimaPercepcion[$i]))
					$minimaPercepcion[$i] = 0;

				if (!isset($tasasDescarte[$i]))
					$tasasDescarte[$i] = 0;
				
				// Busca minimos y tasa de descarte de la jurisdiccion
				foreach ($provincia->provincia_tasaiibbs as $tasa)
				{
					if ($condicioniibb_id == $tasa->condicioniibb_id)
					{
						$minimoNeto[$i] = $tasa->minimoneto;
						$minimaPercepcion[$i] = $tasa->minimopercepcion;
						$tasasDescarte[$i] = $tasa->tasa;
					}
				}
				// Verifica si tiene CM05 o no en la jurisdiccion
				$provinciaCm05 = $cm05 instanceof \Illuminate\Support\Collection
					? $cm05->firstWhere('provincia_id', $provincia->id)
					: null;

				// Si la provincia tiene CM05 usa los parametros cargados
				if ($totalNeto >= $minimoNeto[$i])
				{
					$flPercibe = true;
					$coeficienteCm05 = 1.;
					if ($provinciaCm05)
					{
						// Verifica exclusion
						if (($provinciaCm05->certificadonoretencion ?? '') == 'S')
						{
							if ($fechaFactura >= $provinciaCm05->desdefechanoretencion &&
								$fechaFactura <= $provinciaCm05->hastafechanoretencion)
								$flPercibe = false;
						}

						// Verifica forma de calculo
						if (($provinciaCm05->tipopercepcion ?? '') == 'C') // Percibe por coeficiente
							$coeficienteCm05 = $provinciaCm05->coeficiente;
					}
					if ($flPercibe)
					{
						$registroPadron = $this->leeTasaPercepcion($numeroDocumento, $jurisdiccionesPercepcion[$i], $fechaFactura);
						$tasaPadron = $this->tasaPercepcionDesdePadron($registroPadron);

						// La alicuota del padron manda, incluso cuando es 0.
						$tasa = 0.;
						if ($tasaPadron !== null)
							$tasa = $tasaPadron;
						else
						{
							// Si el cliente esta en la jurisdiccion y no leyo padron asume tasa de descarte
							if ($jurisdiccionesPercepcion[$i] == $jurisdiccionCliente)
								$tasa = $tasasDescarte[$i];
						}

						// Solapa del cliente: reemplaza padron/descarte; si no hay padron, igual aplica.
						$tasaExclusion = ClienteExclusionPercepcionSupport::tasaIibb(
							$cliente_id !== null ? (int) $cliente_id : null,
							(int) $provincia->id,
							$fechaFactura
						);
						if ($tasaExclusion !== null)
							$tasa = $tasaExclusion;

						if ($tasa <= 0.00001)
							continue;

						$importePercepcion = round($totalNeto * $tasa / 100. * $coeficienteCm05, 2);
						//if ($i == 1)
						//	dd($totalNeto.' '.$minimoNeto[$i].' '.$importePercepcion.' '.$minimaPercepcion[$i].' '.$i.' '.$tasa);

						if ($importePercepcion >= $minimaPercepcion[$i] && $importePercepcion != 0)
						{
							$concepto = "Perc. ".$provincia->nombre." ".($tasa < 0.00001 ? " " : $tasa."%");
							if ($provincia && $importePercepcion != 0)
							{
								$percepcionesIIBB[] = ["concepto"=>$concepto,
													"tasa"=>($tasa < 0.0001 ? 0 : $tasa),
													"baseimponible"=>$totalNeto,
													"jurisdiccion"=>$jurisdiccionesPercepcion[$i],
													"provincia_id"=>$provincia->id,
													"importe"=>$importePercepcion,
												];
							}
						}
					}
				}
			}
		}
		return $percepcionesIIBB;
	}
}

