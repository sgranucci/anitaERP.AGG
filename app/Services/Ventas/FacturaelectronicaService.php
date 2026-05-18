<?php
namespace App\Services\Ventas;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Models\Configuracion\Empresa;
use App\Services\Configuracion\ImpuestoService;
use App\Repositories\Configuracion\CondicionivaRepositoryInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\ApiAnita;
use App\Services\Arca\ArcaWsfeFacturaElectronicaService;

class FacturaElectronicaService 
{
	protected $condicionivaRepository;

	protected ArcaWsfeFacturaElectronicaService $arcaWsfeFacturaElectronicaService;

	public function __construct(
		CondicionivaRepositoryInterface $condicionivarepository,
		ArcaWsfeFacturaElectronicaService $arcaWsfeFacturaElectronicaService,
	) {
    	$this->condicionivaRepository = $condicionivarepository;
		$this->arcaWsfeFacturaElectronicaService = $arcaWsfeFacturaElectronicaService;
    }

	/** Comprobantes nacionales wsfev1 vía SOAP (config arca_wsfe.transporte = soap). */
	private function debeUsarSoapWsfe(object $puntoventa): bool
	{
		return ($puntoventa->webservice ?? '') === 'wsfev1'
			&& (string) config('arca_wsfe.transporte', 'afip_php') === 'soap';
	}

	public function traeUltimoNumeroComprobante($nroinscripcion, $tipotransaccion, $puntoventa)
	{
		if ($puntoventa->modofacturacion == 'A') // CAEA
		{
			// Si es CAEA debe buscar ultimo numero emitido
		}
		else
		{
			if ($this->debeUsarSoapWsfe($puntoventa)) {
				$empresaId = (int) ($puntoventa->empresa_id ?? 0);
				if ($empresaId < 1) {
					return -1;
				}
				try {
					$n = $this->arcaWsfeFacturaElectronicaService->feCompUltimoAutorizado(
						$empresaId,
						(int) $puntoventa->codigo,
						(int) $tipotransaccion
					);

					return $n > 0 ? (string) $n : -1;
				} catch (\Throwable $e) {
					return -1;
				}
			}

			$req['solicitud']['servicio'] = $puntoventa->webservice;
			$req['solicitud']['funcion'] = 'SolicitarUltimoCompEnviado';
			$req['datos']['cuit']   = (double) str_replace("-","",$nroinscripcion);

			if ($puntoventa->webservice == 'wsfex_v1')
			{
				$req['solicitud']['funcion'] = 'SolicitarUltimoNroComp';
				$req['datos']['pvta'] = (int) $puntoventa->codigo;
				$req['datos']['tipo_cbt']   = (int) $tipotransaccion;	
			}
			else
			{
				$req['datos']['pventa'] = (int) $puntoventa->codigo;
				$req['datos']['tipo']   = (int) $tipotransaccion;
			}

			$base = "ultnrocomp-". Str::random(20);
			$nombreXml = $base.'.xml';
			$nombreXmlRespuesta = $base.'_RESP.xml';
			Storage::disk('public')->put($puntoventa->pathafip."/ent/$nombreXml", $this->GenerarXML ($req, null));

			$ret = $this->ejecutaAfip($puntoventa->pathafip, $nombreXml);

			//Storage::disk('public')->delete($puntoventa->pathafip."/ent/$nombreXml");
			$archivoXml = "storage/".$puntoventa->pathafip."/ent/$nombreXmlRespuesta";
			$ultimoNumero = -1;

			if (file_exists($archivoXml) && filesize($archivoXml) > 0) 
			{
				$resp = new \SimpleXMLElement($archivoXml, null, true);

				$mensaje = $resp->mensaje;
				if ($puntoventa->webservice == 'wsfex_v1')
				{
					$ultimoNumero = strval($resp->numero);
				}
				else
				{
					$tipo = $resp->detalle->tipo;
					$pventa = $resp->detalle->pventa;

					if ($tipo == $tipotransaccion && $pventa == $puntoventa->codigo)
						$ultimoNumero = strval($resp->detalle->numero);
				}
				
				Storage::disk('public')->delete($puntoventa->pathafip."/ent/$nombreXmlRespuesta");
			}
		}
		return $ultimoNumero;
	}

	public function solicitaCAE($nroinscripcion, $tipotransaccion, $puntoventa, $datos)
	{
		if ($this->debeUsarSoapWsfe($puntoventa)) {
			$empresaId = (int) ($puntoventa->empresa_id ?? 0);
			if ($empresaId < 1) {
				return ['Error' => 'Punto de venta sin empresa asociada; no se puede emitir con WSFE SOAP.'];
			}
			try {
				$out = $this->arcaWsfeFacturaElectronicaService->solicitaCaeDomestico(
					$empresaId,
					$puntoventa,
					(int) $tipotransaccion,
					$datos
				);

				return [
					'cae' => $out['cae'],
					'fechavencimientocae' => $out['fechavencimientocae'],
				];
			} catch (\Throwable $e) {
				return ['Error' => $e->getMessage()];
			}
		}

		// Si es exportacion tiene que pedir request
		if ($puntoventa->webservice == 'wsfex_v1')
		{
			$nroId = -1;
			
			$req['solicitud']['servicio'] = $puntoventa->webservice;
			$req['solicitud']['funcion'] = 'SolicitarUltNroRequest';
			$req['datos']['cuit']   = (double) str_replace("-","",$nroinscripcion);

			$base = "solicitaID-". Str::random(20);
			$nombreXml = $base.'.xml';
			$nombreXmlRespuesta = $base.'_RESP.xml';
			Storage::disk('public')->put($puntoventa->pathafip."/ent/$nombreXml", $this->GenerarXML ($req, null));

			$ret = $this->ejecutaAfip($puntoventa->pathafip, $nombreXml);

			//Storage::disk('public')->delete($puntoventa->pathafip."/ent/$nombreXml");
			
			$resp = new \SimpleXMLElement("storage/".$puntoventa->pathafip."/ent/$nombreXmlRespuesta", null, true);
			
			if ($resp->estado == '0' && $resp->mensaje == 'OK')
			{
				$mensaje = $resp->mensaje;
				$nroId = strval($resp->numero)+1;
			}
			
			Storage::disk('public')->delete($puntoventa->pathafip."/ent/$nombreXmlRespuesta");
			$req = [];
		}
		else
		{
			// Lee condicion de iva del cliente
			$condicioniva = $this->condicionivaRepository->find($datos['condicioniva_id']);

			if ($condicioniva)
				$condicionIvaReceptor_id = $condicioniva->codigoexterno;
			else	
				$condicionIvaReceptor_id = 1; // Asume inscripto por default
		}

		$req['solicitud']['servicio'] = $puntoventa->webservice;
		$req['solicitud']['funcion'] = 'SolicitarCAE';
		
		if ($puntoventa->webservice == 'wsfex_v1')
		{
			$req['encabezado']['cuit']   = (double) str_replace("-","",$nroinscripcion);
			$req['encabezado']['id']   = (int) $nroId;
			$req['encabezado']['fecha_cbt'] = $datos['fechacomprobante'];
			$req['encabezado']['tipo_cbte']   = (int) $tipotransaccion;
			$req['encabezado']['punto_vta'] = (int) $puntoventa->codigo;
			$req['encabezado']['cbte_nro'] = $datos['numerocomprobante'];
			$req['encabezado']['tipo_expo']   = (int) 1; // Bienes

			if ($tipotransaccion == 19)
				$req['encabezado']['permiso_existente']   = 'N';
			else
				$req['encabezado']['permiso_existente']   = '';

			$req['encabezado']['dst_cmp'] = $datos['pais'];
			$req['encabezado']['cliente'] = str_replace("&"," ",$datos['nombrecliente']);
			$req['encabezado']['cuit_pais_cliente'] = str_replace("-","",$datos['nroinscripcion']);
			$req['encabezado']['domicilio_cliente'] = str_replace("&"," ",$datos['domicilio']);
			$req['encabezado']['id_impositivo'] = ' ';

			$req['encabezado']['moneda_id'] = $datos['moneda'];
			$req['encabezado']['moneda_ctz'] = $datos['cotizacion'];

			$req['encabezado']['obs_comerciales'] = ' ';
			$req['encabezado']['imp_total'] = Round($datos['total'],2);
			$req['encabezado']['obs'] = ' ';
			$req['encabezado']['forma_pago'] = $datos['formapagoexportacion'];
			$req['encabezado']['incoterms'] = $datos['incoterms'];
			if ($datos['incoterms'] == '')
				$req['encabezado']['incoterms_ds'] = '';
			else
				$req['encabezado']['incoterms_ds'] = ' ';

			if ($datos['moneda'] != 'PES')
				$req['encabezado']['CanMisMonExt'] = 'N';
				
			$req['encabezado']['idioma_cbte'] = '2';
			$req['encabezado']['permisos'] = ' ';
			$req['encabezado']['cmp_asoc'] = ' ';
 
			// Arma items
			$qItem = 0;
			for ($i = 0; $i < count($datos['items']); $i++)
			{
				$it = 'item_'.$qItem++;
				$req['detalle'][$it]['pro_codigo'] = $datos['items'][$i]['sku'];
				$req['detalle'][$it]['pro_ds'] = $datos['items'][$i]['descripcion'];
				$req['detalle'][$it]['pro_qty'] = $datos['items'][$i]['cantidad'];
				$req['detalle'][$it]['pro_umed'] = $datos['items'][$i]['codigounidadmedida'];
				$req['detalle'][$it]['pro_precio_uni'] = $datos['items'][$i]['precio'];
				$req['detalle'][$it]['pro_total_item'] = $datos['items'][$i]['precio'] * $datos['items'][$i]['cantidad'];
			}

			// Agrega impuestos como items
			$totalIngresosBrutos = 0;
			for ($j = 0; $j < count($datos['tributos']); $j++)
			{
				if ($datos['tributos'][$j]['importe'] != 0)
					$totalIngresosBrutos += $datos['tributos'][$j]['importe'];
			}

			if ($totalIngresosBrutos != 0)
			{
				$it = 'item_'.$qItem++;
				$req['detalle'][$it]['pro_codigo'] = ' ';
				$req['detalle'][$it]['pro_ds'] = 'Ingresos Brutos';
				$req['detalle'][$it]['pro_qty'] = 1;
				$req['detalle'][$it]['pro_umed'] = 7;
				$req['detalle'][$it]['pro_precio_uni'] = $totalIngresosBrutos;
				$req['detalle'][$it]['pro_total_item'] = $totalIngresosBrutos;
			}
		}
		else
		{
			$req['cabecera']['cuit']   = (double) str_replace("-","",$nroinscripcion);
			$req['cabecera']['pto_venta'] = (int) $puntoventa->codigo;
			$req['cabecera']['tipo_cbt']   = (int) $tipotransaccion;
		
			$req['detalle']['concepto'] = 1;

			$req['detalle']['doc_tipo'] = $datos['tipodoc'];
			$req['detalle']['doc_nro'] = str_replace("-","",$datos['numerodocumento']);
			$req['detalle']['cbt_desde'] = $datos['numerocomprobante'];
			$req['detalle']['cbt_hasta'] = $datos['numerocomprobante'];
			$req['detalle']['fecha_cbt'] = $datos['fechacomprobante'];
			$req['detalle']['importe_total'] = Round($datos['total'],2);
			$req['detalle']['importe_total_conc'] = Round($datos['nogravado'],2);
			$req['detalle']['importe_neto'] = Round($datos['gravado'],2);
			$req['detalle']['importe_op_ex'] = Round($datos['exento'],2);
			$req['detalle']['importe_iva'] = Round($datos['iva'],2);
			$req['detalle']['importe_trib'] = Round($datos['tributo'],2);
			$req['detalle']['fecha_serv_desde'] = '';
			$req['detalle']['fecha_serv_hasta'] = '';
			$req['detalle']['fecha_vto_pago'] = '';
			$req['detalle']['moneda_id'] = $datos['moneda'];
			$req['detalle']['moneda_cotizac'] = $datos['cotizacion'];

			if ($datos['fechacomprobante'] >= 20250406)
				$req['detalle']['cond_iva_receptor_id'] = $condicionIvaReceptor_id;

			if ($datos['fechaasignaciondesde'] > 0 && count($datos['comprobantesasociados']) == 0 &&
				($tipotransaccion == 3 || $tipotransaccion == 8 || $tipotransaccion == 203 || $tipotransaccion == 53))
			{
				$req['detalle']['fchdesde'] = $datos['fechaasignaciondesde'];
				$req['detalle']['fchhasta'] = $datos['fechaasignacionhasta'];
			}

			// Comprobantes asociados
			foreach($datos['comprobantesasociados'] as $asociado)
			{
				$req['detalle']['cbteasoc']['tipo'] = $asociado['tipo'];
				$req['detalle']['cbteasoc']['ptovta'] = $asociado['ptovta'];
				$req['detalle']['cbteasoc']['nro'] = $asociado['nro'];
			}

			// Tributos provinciales
			if (count($datos['tributos']) > 1)
			{
				for ($j = 0; $j < count($datos['tributos']); $j++)
				{
					if ($datos['tributos'][$j]['importe'] != 0)
					{
						$it = 'tributo_'.$j;
						$req['detalle']['tributos'][$it]['id'] = $datos['tributos'][$j]['id'];
						$req['detalle']['tributos'][$it]['base_imp'] = Round($datos['tributos'][$j]['base_imp'],2);
						$req['detalle']['tributos'][$it]['importe'] = Round($datos['tributos'][$j]['importe'],2);
						$req['detalle']['tributos'][$it]['alicuota'] = $datos['tributos'][$j]['alicuota'];
						$req['detalle']['tributos'][$it]['desc'] = $datos['tributos'][$j]['desc'];
					}
				}
			}
			else 
			{
				if (count($datos['tributos']) > 0)
				{
					$req['detalle']['tributos']['tributo']['id'] = $datos['tributos'][0]['id'];
					$req['detalle']['tributos']['tributo']['base_imp'] = Round($datos['tributos'][0]['base_imp'],2);
					$req['detalle']['tributos']['tributo']['importe'] = Round($datos['tributos'][0]['importe'],2);
					$req['detalle']['tributos']['tributo']['alicuota'] = $datos['tributos'][0]['alicuota'];
					$req['detalle']['tributos']['tributo']['desc'] = $datos['tributos'][0]['desc'];
				}
			}
		
			// Impuestos nacionales
			if (count($datos['impuestos']) > 1)
			{
				for ($j = 0; $j < count($datos['impuestos']); $j++)
				{
					if ($datos['impuestos'][$j]['importe'] != 0)
					{
						$it = 'aliciva'.$j;
						$req['detalle']['iva'][$it]['id'] = $datos['impuestos'][$j]['id'];
						$req['detalle']['iva'][$it]['base_imp'] = Round($datos['impuestos'][$j]['base_imp'],2);
						$req['detalle']['iva'][$it]['importe'] = Round($datos['impuestos'][$j]['importe'],2);
					}
				}
			}
			else
			{
				if ($datos['impuestos'][0]['importe'] != 0)
				{
					$req['detalle']['iva']['aliciva']['id'] = $datos['impuestos'][0]['id'];
					$req['detalle']['iva']['aliciva']['base_imp'] = Round($datos['impuestos'][0]['base_imp'],2);
					$req['detalle']['iva']['aliciva']['importe'] = Round($datos['impuestos'][0]['importe'],2);
				}
			}
		}

		$base = "solicitaCAE-". Str::random(20);
		$nombreXml = $base.'.xml';
		$nombreXmlRespuesta = $base.'_RESP.xml';

		// Reemplaza etiquetas numeros de id de repetidas
		$xml = preg_replace('/_(\d+)>/i', '>', $this->GenerarXML($req,null));
		Storage::disk('public')->put($puntoventa->pathafip."/ent/$nombreXml", $xml);

		$ret = $this->ejecutaAfip($puntoventa->pathafip, $nombreXml);

		//Storage::disk('public')->delete($puntoventa->pathafip."/ent/$nombreXml");
		
		$resp = new \SimpleXMLElement("storage/".$puntoventa->pathafip."/ent/$nombreXmlRespuesta", null, true);

		$fechaVto = 0;
		if ($puntoventa->webservice == 'wsfex_v1' ? $resp->estado == '0' || $resp->estado == '' : 
			$resp->cabecera->resultado == 'A')
		{
			$mensaje = $resp->mensaje;

			if ($puntoventa->webservice == 'wsfex_v1')
			{
				$cae = $resp->cae;
				$fechaVto = $resp->fvto;
				$tipo = $resp->tipo_cbte;
				$pventa = $resp->punto_vta;
				$numero = $resp->cbte_nro;
				$resultado = 'A';
			}
			else
			{
				$tipo = $resp->cabecera->cbte_tipo;
				$resultado = $resp->cabecera->resultado;
				$pventa = $resp->cabecera->pto_venta;
				$numero = $resp->detalle->cbt_desde;
			}
					
			$cae = ''; $fechaVto = '';
			if ($tipo == $tipotransaccion && $pventa == $puntoventa->codigo && $numero == $datos['numerocomprobante'])
			{
				if ($puntoventa->webservice == 'wsfex_v1')
				{
					$cae = $resp->cae;
					$fechaVto = $resp->fvto;
				}
				else
				{
					$cae = $resp->detalle->cae;
					$fechaVto = $resp->detalle->cae_fch_vto;
				}
			}
		}
		else // Verifica si el comprobante entro en AFIP
		{
			$ultimoNumero = Self::traeUltimoNumeroComprobante($nroinscripcion, $tipotransaccion, $puntoventa);

			if ($ultimoNumero + 1 == $datos['numerocomprobante'])
			{
				$resultado = Self::consultaCompEnviado($nroinscripcion, $tipotransaccion, $puntoventa,
														$datos['numerocomprobante']);

				if ($resultado != -1)
				{
					$cae = $resultado['cae'];
					$fechaVto = $resultado['fechavencimientocae'];
				}
			}
		}

		//Storage::disk('public')->delete($puntoventa->pathafip."/ent/$nombreXmlRespuesta");

		if ($resultado == 'A')
			return ['cae' => $cae, 'fechavencimientocae' => $fechaVto];
		else	
		{
			$retorno = $resp->detalle->observaciones->obs->msg;

			return ['Error' => $retorno];
		}
	}

	public function buscaCAEA($nroinscripcion, $fechafactura)
	{
		$nroinscripcion = str_replace("-","",$nroinscripcion);

		$fechaCarbon = Carbon::parse($fechafactura);

		// Calcular el inicio y fin de la quincena
		if ($fechaCarbon->day <= 15) {
			$inicioQuincena = $fechaCarbon->copy()->startOfMonth()->format('Ymd');
			$finQuincena = $fechaCarbon->copy()->startOfMonth()->addDays(14)->format('Ymd');
		} else {
			$inicioQuincena = $fechaCarbon->copy()->startOfMonth()->addDays(15)->format('Ymd');
			$finQuincena = $fechaCarbon->copy()->endOfMonth()->format('Ymd');
		}

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'tabla' => 'caea', 
						'campos' => '
							caea_nro_caea,
							caea_fecha_tope
						' ,
						'whereArmado' => " WHERE caea_cuit = '".$nroinscripcion."' AND
												caea_desde_fecha <= ".$inicioQuincena." AND
												caea_hasta_fecha >= ".$finQuincena);
		$fila = ApiAnita::primeraFilaLista($apiAnita->apiCall($data));

		if ($fila !== null && isset($fila->caea_nro_caea)) {
			return ['cae' => $fila->caea_nro_caea, 'fechavencimientocae' => $finQuincena];
		}

		return ['Error' => 'No pudo asignar CAEA'];
	}

	public function consultaCompEnviado($nroinscripcion, $tipotransaccion, $puntoventa, $numero)
	{
		if ($this->debeUsarSoapWsfe($puntoventa)) {
			$empresaId = (int) ($puntoventa->empresa_id ?? 0);
			if ($empresaId < 1) {
				return -1;
			}

			return $this->arcaWsfeFacturaElectronicaService->consultaComprobanteEmitido(
				$empresaId,
				(int) $puntoventa->codigo,
				(int) $tipotransaccion,
				(int) $numero
			);
		}

		$req['solicitud']['servicio'] = $puntoventa->webservice;
		$req['solicitud']['funcion'] = 'ConsultaCompEnviado';
		$req['datos']['cuit']   = (double) str_replace("-","",$nroinscripcion);
        $req['datos']['pventa'] = (int) $puntoventa->codigo;
        $req['datos']['tipo']   = (int) $tipotransaccion;
		$req['datos']['nro']   = (int) $numero;

		$base = "ultnrocomp-". Str::random(20);
		$nombreXml = $base.'.xml';
		$nombreXmlRespuesta = $base.'_RESP.xml';
		Storage::disk('public')->put($puntoventa->pathafip."/ent/$nombreXml", $this->GenerarXML ($req, null));

		$ret = $this->ejecutaAfip($puntoventa->pathafip, $nombreXml);

		Storage::disk('public')->delete($puntoventa->pathafip."/ent/$nombreXml");

		$resp = new \SimpleXMLElement("storage/".$puntoventa->pathafip."/ent/$nombreXmlRespuesta", null, true);

		$mensaje = $resp->mensaje;
		$tipo = $resp->detalle->tipo;
		$pventa = $resp->detalle->pventa;
		$ultimoNumero = $resp->detalle->nro;
		$cae = $resp->detalle->cae;
		$fechavto = $resp->detalle->fevto;
		$resultado = $resp->detalle->resultado;

		Storage::disk('public')->delete($puntoventa->pathafip."/ent/$nombreXmlRespuesta");

		if ($resultado == 'A')
			return ['cae' => $cae, 'fechavencimientocae' => $fechaVto];
		else
			return -1;
	}

	public function armaTipoTransaccion($letra, $modofacturacion, &$tipotransaccion, $puntoventa, $totalcomprobante)
	{
		// Arma el tipo de transaccion
		if ($letra == 'B')
			$tipotransaccion += 5;
		elseif ($letra == 'E' || ($puntoventa->webservice == 'wsfex_v1'))
			$tipotransaccion += 18;
		elseif ($letra == 'M')
			$tipotransaccion += 50;

		// Si es FCE
		if ($modofacturacion == 'C')
		{
			// Si supera limite asume factura electronica MyPiME
			if ($totalComprobate >= config("facturacion.LIMITE_FCE"))
				$tipotransaccion += 200;
		}
	}

	private function ejecutaAfip($pathafip, $nombrexml)
	{
		$path = Storage::path($nombrexml);

		$cmd = "cd storage/$pathafip; ./afip.php ".str_replace(".xml", "", $nombrexml)." 1>&2 2>err";
		//$array_cmd = explode(' ', $cmd);
		//$process = new Process($array_cmd);
		//$process->run();
		//if (!$process->isSuccessful()) {
		//	throw new ProcessFailedException($process);
		//}
		//echo $process->getOutput();
		system($cmd, $retorno);

		return($retorno);
	}
	
	private function GenerarXML ($data, $xml) {
        if ($xml == null) {
            $xml = simplexml_load_string ("<?xml version='1.0' encoding='utf-8'?><req />");
        }

        foreach($data as $key => $value) {
            if (is_numeric($key)) {
                $key = "unknownNode_". (string) $key;
			}
			
			if (is_array($value)) {
                $node = $xml->addChild($key);

                $this->GenerarXML ($value, $node);

            } else {
                $value = htmlentities($value);
                $xml->addChild($key,$value);
            }
		}
		
		return $xml->asXML();
    }

	// Prepara tabla de tributos
	public function armaTributo($conceptosTotales, &$tributos, &$totalTributo)
	{
		$totalTributo = 0;
		foreach ($conceptosTotales as $concepto)
		{
			if ($concepto['concepto'] == 'Percepcion IVA')
			{
				$tributos[] = [
					'id' => 1,
					'base_imp' => $concepto['baseimponible'],
					'alicuota' => $concepto['tasa'],
					'desc' => $concepto['concepto'],
					'importe' => $concepto['importe']
				];
				$totalTributo += $concepto['importe'];
			}
			if (array_key_exists('jurisdiccion', $concepto) && $concepto['jurisdiccion'] != 0.)
			{
				$tributos[] = [
					'id' => 2,
					'base_imp' => $concepto['baseimponible'],
					'alicuota' => $concepto['tasa'],
					'desc' => $concepto['concepto'],
					'importe' => $concepto['importe']
				];
				$totalTributo += $concepto['importe'];
			}
		}
	}

	// Prepara tabla de impuestos
	public function armaImpuesto($conceptosTotales, &$impuestos)
	{
		foreach ($conceptosTotales as $concepto)
		{
			$pos = strpos($concepto['concepto'], 'Iva ');

			if ($pos >= 0 && $pos !== false)
			{
				$impuestos[] = [
					'id' => $concepto['codigoarca'],
					'codigo' => $concepto['codigo'],
					'base_imp' => $concepto['baseimponible'] ?? 0,
					'alicuota' => $concepto['tasa'],
					'desc' => $concepto['concepto'],
					'importe' => $concepto['importe']
				];
			}
		}
	}
}