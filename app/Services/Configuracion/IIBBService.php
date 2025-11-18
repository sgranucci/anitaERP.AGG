<?php
namespace App\Services\Configuracion;

use App\Models\Configuracion\Provincia;
use App\Repositories\Configuracion\Padron_IibbRepositoryInterface;

class IIBBService 
{
	protected $padron_iibbRepository;

	private $tasapercepcion;
	private $flLeyoPadron;

	public function __construct(Padron_IibbRepositoryInterface $padron_iibbRepository)
	{
		$this->padron_iibbRepository = $padron_iibbRepository;
	}

	public function leeTasaPercepcion($nroinscripcion, $jurisdiccion)
	{
		$tasapercepcion = 0;
		$this->flLeyoPadron = false;

		$tasapercepcion = $this->padron_iibbRepository->leePadronIibb($nroinscripcion, 'percepcion', $jurisdiccion);
		if ($tasapercepcion)
			$this->flLeyoPadron = true;

		return $tasapercepcion;
	}

	// Calcula percepciones de ingresos brutos para ventas

	public function calculaPercepcionIIBB($totalNeto, $nroinscripcion, $condicionIIBB, $provinciaInscripcion)
	{
		$percepcionesIIBB = [];

		if ($condicionIIBB != 'N')
		{
			$provinciasPercepcion = explode(",", env("ANITA_AGENTE_PERCEPCION_IIBB"));
			$tasasDescarte = explode(",", env("ANITA_TASAS_DESCARTE_IIBB"));
			$minimoNeto = explode(",", env("ANITA_MINIMO_NETO_IIBB"));
			$minimaPercepcion = explode(",", env("ANITA_MINIMA_PERCEPCION_IIBB"));
			$percepcionesIIBB = [];
			for ($i = 0; $i < count($provinciasPercepcion); $i++)
			{
				if ($totalNeto >= $minimoNeto[$i])
				{
					$tasa = self::leeTasaPercepcion($nroinscripcion, $provinciasPercepcion[$i]);

					if (!$this->flLeyoPadron)
						$tasa = $tasasDescarte[$i];

					$importePercepcion = $totalNeto * $tasa / 100.;
					
					//if ($i == 1)
					//	dd($totalNeto.' '.$minimoNeto[$i].' '.$importePercepcion.' '.$minimaPercepcion[$i].' '.$i.' '.$tasa);

					if ($importePercepcion >= $minimaPercepcion[$i] && $importePercepcion != 0)
					{
						$provincia = Provincia::where("jurisdiccion",$provinciasPercepcion[$i])->first();

						$concepto = "Perc. ".$provincia->nombre." ".($tasa < 0.00001 ? " " : $tasa."%");
						if ($provincia && $importePercepcion != 0)
						{
							$percepcionesIIBB[] = ["concepto"=>$concepto,
												"tasa"=>($tasa < 0.0001 ? 0 : $tasa),
												"baseimponible"=>$totalNeto,
												"jurisdiccion"=>$provinciasPercepcion[$i],
												"provincia_id"=>$provincia->id,
												"importe"=>$importePercepcion,
											];
						}
					}
				}
			}
		}
		return $percepcionesIIBB;
	}
}

