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

	public function leeTasaPercepcion($nroinscripcion, $jurisdiccion)
	{
		$this->flLeyoPadron = false;
		
		$cuit = str_replace("-", "", $nroinscripcion);
		switch($jurisdiccion)
		{
			case 901: // Caba
				$tasa_iibb = $this->padron_iibb_cabaRepository->findPorCuit($cuit);
				break;
			case 902: // Arba
				$tasa_iibb = $this->padron_iibb_arbaRepository->findPorCuit($cuit);
				break;
			case 904: // Cordoba
            case 908: // Entre Rios
            case 914: // Misiones
			case 921: // Santa Fe
            case 924: // Tucuman tasas y coeficientes
				$tasa_iibb = $this->padron_iibbRepository->leePadronIibb($cuit, 'percepcion', $jurisdiccion);
				break;
		}

		if ($tasa_iibb)
			$this->flLeyoPadron = true;

		return $tasa_iibb;
	}

	// Calcula percepciones de ingresos brutos para ventas

	public function calculaPercepcionIIBB($totalNeto, $numeroDocumento, $condicioniibb_id, $provincia_id, $cm05, $fechaFactura)
	{
		$percepcionesIIBB = [];

		$condicioniibb = $this->condicion_iibbRepository->find($condicioniibb_id);

		if ($condicioniibb->formacalculo != 'N' && $condicioniibb->estado == 'A')
		{
			$jurisdiccionesPercepcion = explode(",", env("ANITA_AGENTE_PERCEPCION_IIBB"));
			$tasasDescarte = explode(",", env("ANITA_TASAS_DESCARTE_IIBB"));
			$minimoNeto = explode(",", env("ANITA_MINIMO_NETO_IIBB"));
			$minimaPercepcion = explode(",", env("ANITA_MINIMA_PERCEPCION_IIBB"));

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
				$provinciaCm05 = $cm05->where('provincia_id', $provincia->id); 

				// Si la provincia tiene CM05 usa los parametros cargados
				if ($totalNeto >= $minimoNeto[$i])
				{
					$flPercibe = true;
					$coeficienteCm05 = 1.;
					if (count($provinciaCm05) > 0)
					{
						// Verifica exclusion
						if ($provinciaCm05->certificadonoretencion == 'S')
						{
							if ($fechaFactura >= $provinciaCm05->desdefechanoretencion &&
								$fechaFactura <= $provinciaCm05->hastafechanoretencion)
								$flPercibe = false;
						}

						// Verifica forma de calculo
						if ($provinciaCm05->tipopercepcion == 'C') // Percibe por coeficiente
							$coeficienteCm05 = $provinciaCm05->coeficiente;
					}
					if ($flPercibe)
					{
						$tasaPercepcion = self::leeTasaPercepcion($numeroDocumento, $jurisdiccionesPercepcion[$i]);

						$tasa = 0.;
						if (isset($tasaPercepcion['tasapercepcion']))
							$tasa = $tasaPercepcion['tasapercepcion'];
						else
						{
							// Si el cliente esta en la jurisdiccion y no leyo padron asume tasa de descarte
							if ($jurisdiccionesPercepcion[$i] == $jurisdiccionCliente)
								$tasa = $tasasDescarte[$i];
						}

						$importePercepcion = $totalNeto * $tasa / 100. * $coeficienteCm05;
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

