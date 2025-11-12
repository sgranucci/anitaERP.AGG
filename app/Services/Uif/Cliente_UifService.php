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
use App\Models\Uif\Cliente_Uif;
use App\Models\Uif\Cliente_Premio_Uif;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
								CotizacionService $cotizacionservice
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
    }

	public function guardaCliente_Uif($request, $origen = null)
	{
		DB::beginTransaction();
		try
		{
			$data = $request->all();
			$estado = Cliente_Uif::$enumEstado[array_search('A', array_column(Cliente_Uif::$enumEstado, 'valor'))]['nombre'];
			$data['estado'] = $estado;
			$fotodocumento = $request->file('fotodocumento');
			
			// Guarda fisicamente el archivo
			if ($fotodocumento)
			{
				$path = public_path()."/storage/imagenes/fotos_documentos_uif/";
				$file = $fotodocumento->getClientOriginalName();
				$fileName = $path . $data['id'] . '-' . $fotodocumento->getClientOriginalName();

				$fotodocumento->move($path, $fileName);

				$data['fotodocumento'] = $id.'-'.$file;
			}

			$cliente_uif = $this->cliente_uifRepository->create($data);

			if ($cliente_uif == 'Error')
				throw new Exception('Error en grabacion');

			// Guarda tablas asociadas
			if ($cliente_uif)
				Self::agrega($data, $cliente_uif, $request);

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();

			// Borra el asiento creado
			dd($e->getMessage());

			return ['errores' => $e->getMessage()];
		}
        return ['mensaje' => 'ok'];
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
			$data = $request->all();

			$fotodocumento = $request->file('fotodocumento');

			// Guarda fisicamente el archivo
			if ($fotodocumento)
			{
				$path = public_path()."/storage/imagenes/fotos_documentos_uif/";
				$file = $fotodocumento->getClientOriginalName();
				$fileName = $path . $id . '-' . $fotodocumento->getClientOriginalName();

				$fotodocumento->move($path, $fileName);

				$data['fotodocumento'] = $id.'-'.$file;
			}

			Self::actualiza($data, $id, $request);

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();

			dd($e->getMessage());
			
			return ['errores' => $e->getMessage()];
		}
        return ['mensaje' => 'ok'];
    }

	private function actualiza($data, $id, $request)
	{
		// Graba cliente_uif
		$cliente_uif = $this->cliente_uifRepository->update($data, $id);

		if ($cliente_uif === 'Error')
			throw new Exception('Error en grabacion cliente');

		$this->cliente_archivo_uifRepository->update($request, $id);

		$cliente_riesgo_uif = $this->cliente_riesgo_uifRepository->update($data, $id);
	}

	public function guardaCliente_Premio_Uif($request)
	{
		$data = $request->all();

		DB::beginTransaction();
		try
		{
			$cliente_premio_uif = $this->cliente_premio_uifRepository->createUnique($request->all());

			if ($cliente_premio_uif == 'Error')
				throw new Exception('Error en grabacion');

			// Guarda tablas asociadas
			if ($cliente_premio_uif)
				$this->cliente_premio_archivo_uifRepository->create($request, $cliente_premio_uif->id);

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();

			// Borra el asiento creado

			return ['errores' => $e->getMessage()];
		}
        return ['mensaje' => 'ok'];
	}

    public function actualizaCliente_Premio_Uif($request, $id)
    {
		$data = $request->all();

		DB::beginTransaction();
		try
		{
			$cliente_premio_uif = $this->cliente_premio_uifRepository->updateUnique($data, $id);

			if ($cliente_premio_uif == 'Error')
				throw new Exception('Error en grabacion');

			$this->cliente_premio_archivo_uifRepository->update($request, $id);

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();

			dd($e->getMessage());
			
			return ['errores' => $e->getMessage()];
		}
        return ['mensaje' => 'ok'];
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

	public function generaExportaOperacion($periodo, $limiteinformeuif)
	{
		return $this->cliente_premio_uifRepository->listaPremioParaExportar($periodo, $limiteinformeuif);
	}

	 function exportaOperacion($periodo, $limiteinformeuif)
	{
		$cliente_premio_uif = $this->cliente_premio_uifRepository->listaPremioParaExportar($periodo, $limiteinformeuif);

		system('rm -f '.base_path().'/public/storage/archivos/exporta_clientes_uif/*');

		$_id_archivo = 0;
		$_total_importe = 0.;
		foreach($cliente_premio_uif as $premio)
		{
			$_id_archivo++;
			$_total_importe += $premio->monto;
			$archivo_write = "/public/archivos/exporta_clientes_uif/".$_id_archivo . "_" . $premio->nombrecliente . ".xml";
			$archivo     = $archivo_write;
			$datosPremio = "";

			$datosPremio .= "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";

			$datosPremio .= "<Operacion>\n";
			$datosPremio .= "\t<Apostadores_cobranza_de_premios_mayores_a_50000 Version=\"1.1\">\n";

			// Formatea apellido y nombre
			$_nombre = $premio->nombrecliente;
			$_array_nombre = explode(" ", $_nombre, 2);

			$datosPremio .= "\t\t<Apellido>".$_array_nombre[0]."</Apellido>\n";
			$datosPremio .= "\t\t<Nombre>".$_array_nombre[1]."</Nombre>\n";

			$datosPremio .= "\t\t<Nacionalidad>".$premio->nombrepais."</Nacionalidad>\n";

			$_tipo_documento = "";
			switch($premio->abreviaturatipodocumento)
			{
				case 'DNI':
					if ($premio->nombrepais != "Argentina")
						$_tipo_documento = "Documento EXT";
					else
						$_tipo_documento = "Documento Nacional de Identidad";
					break;

				case "LE":
					$_tipo_documento = "Libreta de Enrolamiento";
					break;

				case "LC":
					$_tipo_documento = "Libreta C┬ívica";
					break;

				case "CDI":
					$_tipo_documento = "Documento EXT";
					break;

				case "PAS":
					if ($premio->nombrepais != "Argentina")
						$_tipo_documento = "Pasaporte";
					else
						$_tipo_documento = "Pasaporte EXT";
					break;
			}

			$datosPremio .= "\t\t<Tipo_Documento>".$_tipo_documento."</Tipo_Documento>\n";
			$datosPremio .= "\t\t<N94mero_Documento>".$premio->numerodocumento."</N94mero_Documento>\n";
			$datosPremio .= "\t\t<Calle>".$premio->domicilio."</Calle>\n";
			$datosPremio .= "\t\t<Nro>0</Nro>\n";
			$datosPremio .= "\t\t<Piso>".$premio->piso."</Piso>\n";
			$datosPremio .= "\t\t<Departamento>".$premio->departamento."</Departamento>\n";
			$datosPremio .= "\t\t<Localidad>".$premio->nombrelocalidad."</Localidad>\n";
			$datosPremio .= "\t\t<Provincia>".$premio->nombreprovincia."</Provincia>\n";
			$datosPremio .= "\t\t<Pa92s>".$premio->nombrepais."</Pa92s>\n";

			$datosPremio .= "\t\t<Radicada_en_el_Exterior>false</Radicada_en_el_Exterior>\n";
			$datosPremio .= "\t\t<Radicada_en_Para92so_Fiscal>false</Radicada_en_Para92so_Fiscal>\n";
			$datosPremio .= "\t\t<Es_Peps>false</Es_Peps>\n";

			$_fecha = $premio->fechaentrega;

			$datosPremio .= "\t\t<Fecha_de_Operaci93n>".$_fecha."</Fecha_de_Operaci93n>\n";

			$datosPremio .= "\t\t<Tipo_de_Moneda>Peso Argentino</Tipo_de_Moneda>\n";

			$datosPremio .= "\t\t<Monto_Total>".floor($premio->monto)."</Monto_Total>\n";
			$datosPremio .= "\t\t<Monto_Total_en_Pesos>".floor($premio->monto)."</Monto_Total_en_Pesos>\n";
			$datosPremio .= "\t\t<Pago_en_favor_de_Terceros>false</Pago_en_favor_de_Terceros>\n";
			$datosPremio .= "\t\t<Pago>\n";
			$datosPremio .= "\t\t\t<Forma_de_Pago>Efectivo</Forma_de_Pago>\n";
			$datosPremio .= "\t\t\t<Porcentaje_del_pago_total>100</Porcentaje_del_pago_total>\n";
			$datosPremio .= "\t\t\t<Fecha_de_pago>".$_fecha."</Fecha_de_pago>\n";
			$datosPremio .= "\t\t</Pago>\n";
			$datosPremio .= "\t</Apostadores_cobranza_de_premios_mayores_a_50000>\n";
			$datosPremio .= "</Operacion>\n";

			Storage::disk('local')->put($archivo, $datosPremio);
		}
	}
}