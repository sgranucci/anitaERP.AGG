<?php
namespace App\Services\Ventas;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Repositories\Ventas\VentaRepositoryInterface;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Categoria;
use App\Models\Stock\Talle;
use App\ApiAnita;
use Exception;
use SoapClient;
use Log;

class FacturanteService 
{
	var $client;
	private $facturacionService;
	protected $ventaRepository;
	protected $articuloQuery;
	private $arrayPago = [];
	
    public function __construct(FacturacionService $facturacionservice,
								VentaRepositoryInterface $ventarepository,
								ArticuloQueryInterface $articuloquery
								)
    {
		$this->facturacionService = $facturacionservice;
		$this->ventaRepository = $ventarepository;
		$this->articuloQuery = $articuloquery;
    }

	public function listadoComprobanteFull($params) 
	{
		//Prueba
		//$auth = array(
		//	"Empresa" => 3430,
		//	"Hash" => "test",
		//	"Usuario" => "pruebalistar"
		//);

		// Prueba Ferli
		$auth = array(
			"Empresa" => 48599,
			"Hash" => "7KK35wnaefrewaT11jgE",
			"Usuario" => "interfazapi@ferli.com.ar"
		);

		$parametros = array(
					'Autenticacion' => $auth,
					'FechaDesde' => $params['desdefecha'],
					'FechaHasta' => $params['hastafecha'],
					'NroPagina' => 1,
					'CantidadComprobantesPorPagina' => 1000
		);

		$request = array("request" => $parametros);

		$this->client = $this->_client();
		try {
		  $result = $this->client->ListadoComprobantesFull($request);
		  return($result->ListadoComprobantesFullResult->ListadoComprobantes->Comprobante);
		}
		catch (\Exception $e) {
		 	Log::info('Caught Exception :'. $e->getMessage());
			return $e;       // just re-throw it
		}
	}

	private function _client() 
	{
		$wsdl = "http://www.facturante.com/api/comprobantes.svc?wsdl";
		try {
		  $this->client = new \SoapClient($wsdl);
		return $this->client;
		}
		catch ( \Exception $e) {
		  Log::info('Caught Exception in client'. $e->getMessage());
		}
	}

	public function generaFactura($tipocomprobante, $prefijo, $numero, $condicionventa, $fechahora, $total,
								$totalneto, $iva1, $iva2, $subtotalnoalcanzado, $subtotalexcento,
								$percepcioniibb, $items, $numeroCae, $fechavencimientocae, $cliente,
								$mediopago)
	{
		$arrayItems = json_decode($items);
		$arrayCliente = json_decode($cliente);

		// Arma forma de pago
		$tarjeta = '';
		$cuentaFinanciera = '';

		switch($mediopago)
		{
		case '1':
			$tarjeta = 'MEP';
			break;
		case '2':
			$tarjeta = 'TN';
			break;
		case '3':
			$tarjeta = 'GO';
			break;
		case '4':
			$tarjeta = 'TR';
			break;
		case '5':
			$tarjeta = 'NBO';
			break;
		}
	
		// Busca cuenta
		switch($tarjeta)
		{
		case "MEP":
			$cuentaFinanciera = "00000608";
			break;
		case "TN":
			$cuentaFinanciera = "00000609";
			break;
		case "GO":
			$cuentaFinanciera = "00000610";
			break;
		case "TR":
			$cuentaFinanciera = "004781/5";
			break;
		case "NBO":
			$cuentaFinanciera = "11310112";
			break;
		}

		// Graba anita
		$puntoVenta = intval($prefijo);
		$letra = substr($tipocomprobante, -1);

		switch($tipocomprobante)
		{
			case 'FA':
			case 'FB':
			case 'FC':
				$tipoComprobante = 'FAC';
				$signo = 1.;
				break;
			case 'NCA':
			case 'NCB':
			case 'NCC':
				$tipoComprobante = 'NCD';
				$signo = -1.;
				break;
			case 'NDA':
			case 'NDB':
			case 'NDC':
				$tipoComprobante = 'NDB';
				$signo = 1.;
				break;
		}
		$condicionVenta_Id = 3;
		switch($condicionventa)
		{
			case 1:
				$condicionVenta_Id = 3;
				break;
			default:
				$condicionVenta_Id = 101;
				break;
		}
		// Arma tabla venta
		$nombreCliente = iconv( 'UTF-8', 'ASCII//TRANSLIT', $arrayCliente->RazonSocial );
		$direccionCliente = iconv( 'UTF-8', 'ASCII//TRANSLIT', $arrayCliente->DireccionFiscal );
		
		$venta = [
					'codigo' => $tipoComprobante,
					'numerocomprobante' => $numero,
					'fecha' => $fechahora,
					'fechajornada' => $fechahora,
					'total' => $total,
					'moneda_id' => 1,
					'condicionventa_id' => $condicionVenta_Id,
					'lugarentrega' => $direccionCliente,
					'nombrecliente' => $nombreCliente,
					'documentocliente' => $arrayCliente->NroDocumento,
					'transporte_id' => 0,
					'descuentointegrado' => '',
					'cliente_id' => 0
		];

		$dataCAE = [
					'gravado' => floatval($totalneto),
					'iva' => floatval($iva2)+floatval($iva1),
					'total' => floatval($total),
					'nogravado' => floatval($subtotalnoalcanzado),
					'exento' => floatval($subtotalexcento)
		];

		$conceptosTotales = [];
		$cuentacorriente = [];
		
		if (floatval($percepcioniibb) != 0)
		{
			$tasa = 0;
			if (floatval($totalneto) != 0)
				$tasa = floatval($percepcioniibb) / floatval($totalneto);

			$conceptosTotales[] = [
					'concepto' => "Percepcion IIBB",
					'jurisdiccion' => "902",
					'provincia_id' => 2,
					'tasa' => $tasa,
					'importe' => floatval($percepcioniibb)
			];
		}
		if (floatval($iva2) != 0)
		{
			$conceptosTotales[] = [
				'concepto' => "Total Iva",
				'tasa' => 21,
				'importe' => floatval($iva2)
			];			
		}
		if (floatval($iva1) != 0)
		{
			$conceptosTotales[] = [
				'concepto' => "IVA",
				'tasa' => 10.5,
				'importe' => floatval($iva1)
			];			
		}
		$dataFactura = [];

		if (is_object($arrayItems->ComprobanteItem))
			Self::procesaUnItem($arrayItems->ComprobanteItem, $dataFactura);
		else
			foreach ($arrayItems->ComprobanteItem as $item)
				Self::procesaUnItem($item, $dataFactura);

		$cuentaVenta = '411000003';
		$contrapartida = '114110007';
		$moneda_id = '1';

		$cae['cae'] = $numeroCae;
		$cae['fechavencimientocae'] = $fechavencimientocae;
		$comprobante = $tipoComprobante.' '.$letra.' '.$puntoVenta.'-'.$numero;

		$validacionVenta = $this->validarVentaExistente(
			$tipoComprobante, $letra, $puntoVenta, $numero, $venta, $dataCAE,
			floatval($percepcioniibb), $condicionVenta_Id
		);
		if ($validacionVenta !== null && config('app.empresa') === 'Calzados Ferli')
		{
			if ($validacionVenta['estado'] === 'identica')
			{
				return [
					'error' => 'Success',
					'estado' => 'omitida',
					'comprobante' => $comprobante,
					'mensaje' => $comprobante.' ya existe con los mismos datos'
				];
			}

			return [
				'error' => 'Success',
				'estado' => 'conflicto',
				'comprobante' => $comprobante,
				'diferencias' => $validacionVenta['diferencias'],
				'mensaje' => $comprobante.' existe con datos distintos: '
					.implode('; ', $validacionVenta['diferencias'])
			];
		}

		try {
			if (config('app.empresa') === 'Calzados Ferli') {
				$dataCAE['codigoempresa'] = $dataCAE['codigoempresa'] ?? 1;
				$anita = $this->facturacionService->grabaAnita(
								$puntoVenta, $letra, 0, 0,
								$venta, $dataCAE, $conceptosTotales, $cuentacorriente, $dataFactura, $signo,
								'V', 0, true, 0, 0, '',
								'LOCAL_IP', 'IFX_SERVER_LOCAL');

				if (is_array($anita) && isset($anita['error']) && $anita['error'] !== '' && $anita['error'] !== 'Success') {
					if (($anita['error'] ?? '') == 'Errvend')
						throw new Exception('No tiene vendedor asignado.');
					throw new Exception($anita['mensaje'] ?? $anita['error']);
				}

				if ($anita === 'Error' || (is_string($anita) && strpos($anita, 'Error') !== false))
					throw new Exception(is_string($anita) ? $anita : 'Error en grabacion anita.');

				if ($anita === 'Errvend')
					throw new Exception('No tiene vendedor asignado.');
			} else {
				$anita = $this->facturacionService->grabaAnita($puntoVenta, $letra, 0, 0,
								$venta, $dataCAE, $conceptosTotales, $cuentacorriente, $dataFactura, $signo,
								$cuentaVenta, $contrapartida, true,
								'LOCAL_IP', 'IFX_SERVER_LOCAL');

				if ($anita == 'Error')
					throw new Exception('Error en grabacion anita.');

				if ($anita == 'Errvend')
					throw new Exception('No tiene vendedor asignado.');
			}
			
			$fecha = Carbon::now();
			$tipo = 'COC';
			Self::grabaTesmov($cuentaFinanciera, $fechahora, $tipo, $letra, $puntoVenta, $numero, $total*$signo);

			// Graba cobranza de la factura
			// Graba subdiario
			// Arma detalle
			$detalle = $tipo." ".$letra." ".$puntoVenta."-".$numero;
			
			// Lee numerador de operacion contable
			$numeroOperacion = $this->facturacionService->leeNumeroOperacionSubdiario();

			// Busca cuenta financiera
			if ($cuentaFinanciera != '')
			{
				$tesmae = Self::leeCuentaFinanciera($cuentaFinanciera);

				if ($tesmae)
					$cuentaContable = $tesmae[0]->tesm_cta_contable;
			}			
			$cuenta = $cuentaContable;
			$contrapartida = 114110007;

			// Graba subdiario
			$apiAnita = new ApiAnita();

			if ($signo == -1)
				$d_h = 'H';
			else
				$d_h = 'D';

			$data = array( 	'tabla' => 'subdiario', 
					'acc' => 'insert',
					'campos' => ' 
								subd_sistema, subd_fecha, subd_tipo, subd_letra, subd_sucursal, subd_nro,
								subd_emisor, subd_tipo_mov, subd_cuenta, subd_contrapartida,
								subd_nro_operacion, subd_ref_tipo, subd_ref_letra, subd_ref_sucursal,
								subd_ref_nro, subd_ref_sistema, subd_importe, subd_cod_mon,
								subd_cotizacion, subd_desc_mov, subd_nro_asiento,
								subd_procesado, subd_ccosto_cta, subd_ccosto_con,
								subd_nro_interno
							',
					'valores' => "
					'".'T'."',
					'".date('Ymd', strtotime($fechahora))."',
					'".$tipoComprobante."',
					'".$letra."',
					'".$puntoVenta."',
					'".$numero."',
					'"."000000"."',
					'".$d_h."',
					'".$cuenta."',
					'".$contrapartida."',
					'".$numeroOperacion."',
					'"."COC"."',
					'".$letra."',
					'".$puntoVenta."',
					'".$numero."',
					'".'V'."',
					'".$total."',
					'".$moneda_id."',
					'".'1'."',
					'".$detalle."',
					'".'0'."',
					'".' '."',
					'".'0'."',
					'".'0'."',
					'".'0'."'
					"
			);
			$subdiario = $apiAnita->apiCallEscritura($data);


			$vencae = $this->facturacionService->grabaVenCae(substr($venta['codigo'], 0, 3), $letra, 
				$puntoVenta, $venta['numerocomprobante'], $cae['cae'], 
				date('Ymd', strtotime($cae['fechavencimientocae'])));

			return ['error' => 'Success', 'estado' => 'grabada', 'comprobante' => $comprobante];
		}
		catch (\Exception $e) {
			Log::info('Error al generar factura TiendaNube '. $e->getMessage());

			if (config('app.empresa') === 'Calzados Ferli') {
				if ($tipoComprobante ?? '')
					$this->facturacionService->borraAnita($tipoComprobante, $letra, $puntoVenta, $numero, 1);

				return ['error' => $e->getMessage()];
			}

			throw $e;
		}
	}

	private function procesaUnItem($item, &$dataFactura)
	{
		if (isset($item->Detalle) ? $item->Detalle != "Descuentos y promociones" : true)
		{
			$impuesto_id = 3;
			if (isset($item->IVA))
			{
				switch(floatval($item->IVA))
				{
					case 0:
						$impuesto_id = 2;
						break;
					case 10.5:
						$impuesto_id = 2;
						break;
					case 21:
						$impuesto_id = 3;
						break;					
				} 
			}

			// Tiene que buscar el articulo en base al SKU
			$codigo = explode("-", $item->Codigo);
			$sku = $codigo[0];
			$codigoCombinacion = $codigo[1];
			if (isset($codigo[2]))
				$talle = $codigo[2];
			else	
				$talle = '0';

			// Busca el articulo
			$articulo = $this->articuloQuery->traeArticuloPorSku($sku);
			$combinacion_id = $talle_id = 0;
			$codigoCategoria = "";
			$talle_nombre = "";
			$articulo_id = "";
			if ($articulo)
			{
				// Trae la categoria
				$categoria = Categoria::find($articulo->categoria_id);
				if ($categoria)
					$codigoCategoria = $categoria->codigo;
				
				$combinacion = Combinacion::where('articulo_id', $articulo->id)
									->where('codigo', $codigoCombinacion)->first();
				if ($combinacion)
					$combinacion_id = $combinacion->id;

				$talle = Talle::where('nombre', $talle)->first();

				if ($talle)
				{
					$talle_id = $talle->id;
					$talle_nombre = $talle->nombre;
				}
				else
				{
					$talle_id = $codigo[2];
					$talle_nombre = $codigo[2];
				}

				$articulo_id = $articulo->id;
			}
			else
			{
				$talle_id = $talle;
				$talle_nombre = $talle;
			}

			$medida = [];
			$medida[] = [
				'id' => 1,
				'talle' => $talle_id,
				'medida' => $talle_nombre,
				'cantidad' => floatval($item->Cantidad),
				'precio' => floatval($item->PrecioUnitario),
				'pedido' => ''
			];

			$dataFactura[] = ["cantidad" => floatval($item->Cantidad),
				"precio" => floatval($item->PrecioUnitario),
				"descuento" => floatval($item->Bonificacion),
				"descuentointegrado" => '',
				"descuentofinal" => 0,
				"descuentointegradofinal" => '',
				"incluyeimpuesto" => '1',
				"impuesto_id" => $impuesto_id,
				"articulo_id" => $articulo_id,
				"sku" => $sku,
				"descripcion" => $item->Detalle,
				"codigounidadmedida" => 1,
				'categoria' => $codigoCategoria,
				"combinacion_id" => $combinacion_id,
				'codigocombinacion' => $codigoCombinacion,
				'modulo_id' => 30,
				'moneda_id' => 1,
				'listaprecio_id' => 1,
				'despacho' => '',
				'loteimportacion_id' => null,
				'ordentrabajo_id' => 0,
				'pedido_combinacion_id' => 0,
				'medidas' => $medida
			];
		}
	}

	public function generaPre($total, $mediopago)
	{
		$tarjeta = '';
		switch($mediopago)
		{
		case '1':
			$tarjeta = 'MEP';
			break;
		case '2':
			$tarjeta = 'TN';
			break;
		case '3':
			$tarjeta = 'GO';
			break;
		case '4':
			$tarjeta = 'TR';
			break;
		case '5':
			$tarjeta = 'NBO';
			break;
		}

		for ($ii = 0, $flAgrego = false; $ii < count($this->arrayPago); $ii++)
		{
			if ($tarjeta == $this->arrayPago[$ii]['tarjeta'])
			{
				$flAgrego = true;
				$this->arrayPago[$ii]['total'] += $total;
			}
		}
		if (!$flAgrego)
		{
			// Arma array del pago
			$this->arrayPago[] = [
				'tarjeta' => $tarjeta,
				'moneda_id' => 1,
				'total' => $total
			];
		}
	}

	public function grabaPre($fecha)
	{
		// Barre por cada impuesto para grabar asiento contable
		foreach ($this->arrayPago as $pago)
		{
			// Graba solo los importes distintos a 0
			if ($pago['total'] != 0)
			{
				$total = $pago['total'];
				$cuentaFinanciera = '';
				$cuentaContable = 0;
				$cuentaTarjeta = 113100007;
				$moneda_id = $pago['moneda_id'];

				// Busca cuenta
				$cuentaFinanciera = Self::generaCuenta($pago['tarjeta']);

				// Busca cuenta financiera
				if ($cuentaFinanciera != '')
				{
					$tesmae = Self::leeCuentaFinanciera($cuentaFinanciera);

					if ($tesmae)
						$cuentaContable = $tesmae[0]->tesm_cta_contable;
				}

				// Numera la PRE
				$letra = 'A';
				$puntoVenta = 1;
				$numeroPre = $this->ventaRepository->traeUltimoNumeroRemito('PRE', $letra, $puntoVenta);

				// Graba climov
				$fecha = Carbon::now();
				$climov = Self::grabaClimov(substr($cuentaFinanciera,-6), $fecha, "PRE", 
											$letra, $puntoVenta, $numeroPre, $total, $moneda_id);

				// Graba venta

				// Graba subdiario
				// Arma detalle
				//$detalle = "PRE"." ".$letra." ".$puntoVenta."-".$numeroPre;
				
				// Lee numerador de operacion contable
				//$numeroOperacion = $this->facturacionService->leeNumeroOperacionSubdiario();

				// Graba subdiario
				//$apiAnita = new ApiAnita();

				//$data = array( 	'tabla' => 'subdiario', 
				//		'acc' => 'insert',
				//		'campos' => ' 
				//					subd_sistema, subd_fecha, subd_tipo, subd_letra, subd_sucursal, subd_nro,
				//					subd_emisor, subd_tipo_mov, subd_cuenta, subd_contrapartida,
				//					subd_nro_operacion, subd_ref_tipo, subd_ref_letra, subd_ref_sucursal,
				//					subd_ref_nro, subd_ref_sistema, subd_importe, subd_cod_mon,
				//					subd_cotizacion, subd_desc_mov, subd_nro_asiento,
				//					subd_procesado, subd_ccosto_cta, subd_ccosto_con,
				//					subd_nro_interno
				//				',
				//		'valores' => "
				//		'".'V'."',
				//		'".date('Ymd', strtotime($fecha))."',
				//		'"."PRE"."',
				//		'".$letra."',
				//		'".$puntoVenta."',
				//		'".$numeroPre."',
				//		'".substr($cuentaFinanciera,-6)."',
				//		'".'H'."',
				//		'".$cuentaTarjeta."',
				//		'".$cuentaContable."',
				//		'".$numeroOperacion."',
				//		'"."PRE"."',
				//		'".$letra."',
				//		'".$puntoVenta."',
				//		'".$numeroPre."',
				//		'".'V'."',
				//		'".$total."',
				//		'".$moneda_id."',
				//		'".'1'."',
				//		'".$detalle."',
				//		'".'0'."',
				//		'".' '."',
				//		'".'0'."',
				//		'".'0'."',
				//		'".'0'."'
				//		"
				//);
				//$subdiario = $apiAnita->apiCallEscritura($data);


				// Numera el remito
				if ($this->ventaRepository->numeraAnita('PRE', $letra, $puntoVenta) == 'Error')
					return 'Error';
			}
		}
		
	}

	public function leeComprobante($tipocomprobante, $letra, $sucursal, $numero)
	{
		$apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 
			'tabla' => 'venta',
			'sistema' => 'ventas',
            'campos' => '
                ven_tipo,
                ven_letra,
				ven_sucursal,
				ven_nro
            ' , 
            'whereArmado' => " WHERE ven_tipo='".$tipocomprobante."' ". 
							"AND ven_letra='".$letra."' ".
							"AND ven_sucursal=".$sucursal." ".
							"AND ven_nro=".$numero." "
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		return $dataAnita;
	}


	private function leeCuentaFinanciera($cuentafinanciera)
	{
		$apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 
			'tabla' => 'tesmae',
			'sistema' => 'che_ban',
            'campos' => '
                tesm_cuenta,
                tesm_desc,
				tesm_cta_contable
            ' , 
            'whereArmado' => " WHERE tesm_cuenta='".$cuentafinanciera."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));
		return $dataAnita;
	}

	private function grabaClimov($codigocliente, $fecha, $tipo, $letra, $puntoventa, $numerocomprobante, 
								$total, $moneda_id)
	{
		// Graba climov
		$apiAnita = new ApiAnita();

		$data = array( 	'tabla' => 'climov', 
						'acc' => 'insert',
						'campos' => ' 
							cliv_cliente, cliv_tipo, cliv_letra, cliv_sucursal, cliv_nro, cliv_ref_tipo,
							cliv_ref_letra, cliv_ref_sucursal, cliv_ref_nro, cliv_fecha, cliv_fecha_vto,
							cliv_monto, cliv_cod_mon, cliv_cotizacion, cliv_nro_cuota, cliv_t_cobrado,
							cliv_fecha_cobro, cliv_cedio_a, cliv_estado ',
						'valores' => "
							'".$codigocliente."', 
							'".$tipo."',
							'".$letra."',
							'".$puntoventa."',
							'".$numerocomprobante."',
							'".' '."',
							'".' '."',
							'".'0'."',
							'".'0'."',
							'".date('Ymd', strtotime($fecha))."',
							'".date('Ymd', strtotime($fecha))."',
							'".$total."',
							'".$moneda_id."',
							'".'1'."',
							'".'1'."',
							'".'0'."',
							'".'0'."',
							'".'0'."',
							'".'I'."'
						"
				);
		$climov = $apiAnita->apiCallEscritura($data);

	}	

	private function grabaTesmov($cuenta, $fecha, $tipo, $letra, $puntoventa, $numero, $total)
	{
		$moneda_id = '1';

		// Graba climov
		$apiAnita = new ApiAnita();

		$data = array( 	'tabla' => 'tesmov', 
			'sistema' => 'che_ban',
			'acc' => 'insert',
			'campos' => ' 
				tesv_cuenta, tesv_fecha_mov, tesv_fecha_dev, tesv_tipo, tesv_letra,
				tesv_sucursal, tesv_nro, tesv_importe, tesv_cotizacion, tesv_desc_mov,
				tesv_conciliado, tesv_contrapartida ',
			'valores' => "
				'".$cuenta."', 
				'".date('Ymd', strtotime($fecha))."',
				'".date('Ymd', strtotime($fecha))."',
				'".$tipo."',
				'".$letra."',
				'".$puntoventa."',
				'".$numero."',
				'".$total."',
				'".'1'."',
				'"."Cobro ".$numero."',
				' ',
				' '
				"
		);
		$tesmov = $apiAnita->apiCallEscritura($data);

	}	

	private function generaCuenta($tarjeta)
	{
		// Busca cuenta
		$cuentaFinanciera = '';
		switch($tarjeta)
		{
		case "MEP":
			$cuentaFinanciera = "00000608";
			break;
		case "TN":
			$cuentaFinanciera = "00000609";
			break;
		case "GO":
			$cuentaFinanciera = "00000610";
			break;
		case "TR":
			$cuentaFinanciera = "004781/5";
			break;
		case "NBO":
			$cuentaFinanciera = "11310112";
			break;
		}

		return $cuentaFinanciera;
	}

	public function leeVentaAnita($tipocomprobante, $letra, $sucursal, $numero)
	{
		$apiAnita = new ApiAnita();
		$data = array(
			'acc' => 'list',
			'tabla' => 'venta',
			'sistema' => 'ventas',
			'campos' => '
				ven_tipo, ven_letra, ven_sucursal, ven_nro, ven_fecha, ven_exento,
				ven_gravado, ven_impuesto1, ven_monto, ven_cuit_cli, ven_nombre_cliente,
				ven_perc_ing_bruto, ven_cond_venta, ven_cod_mon
			',
			'whereArmado' => " WHERE ven_tipo='".$tipocomprobante."' ".
				"AND ven_letra='".$letra."' ".
				"AND ven_sucursal=".$sucursal." ".
				"AND ven_nro=".$numero." "
		);

		$venta = json_decode($apiAnita->apiCall($data));
		if (!is_array($venta) || count($venta) == 0)
			return null;

		return $venta[0];
	}

	public function validarVentaExistente($tipoComprobante, $letra, $puntoVenta, $numero,
		$venta, $dataCAE, $percepcionIibb, $condicionVentaId)
	{
		$existente = $this->leeVentaAnita($tipoComprobante, $letra, $puntoVenta, $numero);
		if ($existente === null)
			return null;

		$esperado = [
			'fecha' => date('Ymd', strtotime($venta['fecha'])),
			'exento' => floatval($dataCAE['exento']) + floatval($dataCAE['nogravado']),
			'gravado' => floatval($dataCAE['gravado']),
			'iva' => floatval($dataCAE['iva']),
			'monto' => abs(floatval($venta['total'])),
			'cuit' => trim($venta['documentocliente'] ?? ''),
			'percepcion_iibb' => floatval($percepcionIibb),
			'condicion_venta' => intval($condicionVentaId),
			'moneda' => intval($venta['moneda_id'])
		];

		$actual = [
			'fecha' => trim($existente->ven_fecha),
			'exento' => floatval($existente->ven_exento),
			'gravado' => floatval($existente->ven_gravado),
			'iva' => floatval($existente->ven_impuesto1),
			'monto' => floatval($existente->ven_monto),
			'cuit' => trim($existente->ven_cuit_cli),
			'percepcion_iibb' => floatval($existente->ven_perc_ing_bruto),
			'condicion_venta' => intval($existente->ven_cond_venta),
			'moneda' => intval($existente->ven_cod_mon)
		];

		$etiquetas = [
			'fecha' => 'fecha',
			'exento' => 'exento',
			'gravado' => 'gravado',
			'iva' => 'IVA',
			'monto' => 'total',
			'cuit' => 'documento cliente',
			'percepcion_iibb' => 'percepcion IIBB',
			'condicion_venta' => 'condicion de venta',
			'moneda' => 'moneda'
		];

		$diferencias = [];
		foreach ($esperado as $campo => $valor)
		{
			if (!$this->valoresVentaCoinciden($campo, $valor, $actual[$campo]))
			{
				$diferencias[] = $etiquetas[$campo].' (existente: '.$actual[$campo]
					.', nuevo: '.$valor.')';
			}
		}

		if (count($diferencias) == 0)
			return ['estado' => 'identica'];

		return ['estado' => 'distinta', 'diferencias' => $diferencias];
	}

	public function armaMensajeResumenFacturacion($resumen)
	{
		$partes = [];
		$partes[] = 'Grabadas: '.$resumen['grabadas'];
		$partes[] = 'Omitidas (mismos datos): '.count($resumen['omitidas']);

		if (count($resumen['omitidas']) > 0)
			$partes[] = 'Omitidas: '.implode(', ', $resumen['omitidas']);

		if (count($resumen['conflictos']) > 0)
		{
			$partes[] = 'Conflictos (datos distintos): '.count($resumen['conflictos']);
			$partes[] = implode(' | ', $resumen['conflictos']);
		}

		return implode('. ', $partes);
	}

	private function valoresVentaCoinciden($campo, $esperado, $actual)
	{
		if (in_array($campo, ['fecha', 'cuit', 'condicion_venta', 'moneda']))
			return (string) $esperado === (string) $actual;

		return abs(floatval($esperado) - floatval($actual)) < 0.02;
	}

	public function verificarPeriodoFacturante($desdefecha, $hastafecha)
	{
		$retorno = $this->listadoComprobanteFull([
			'desdefecha' => $desdefecha,
			'hastafecha' => $hastafecha
		]);

		if ($retorno instanceof \Exception)
			return ['error' => 'Error al leer Facturante: '.$retorno->getMessage()];

		if ($retorno === null)
		{
			return [
				'desdefecha' => $desdefecha,
				'hastafecha' => $hastafecha,
				'resumen' => [
					'total' => 0,
					'completos' => 0,
					'sin_admin' => 0,
					'sin_stock' => 0,
					'no_importa' => 0,
					'todo_ok' => true,
					'mensaje' => 'No hay comprobantes en Facturante para el periodo seleccionado.'
				],
				'detalle' => []
			];
		}

		$comprobantes = is_array($retorno) ? $retorno : [$retorno];
		$detalle = [];
		$resumen = [
			'total' => 0,
			'completos' => 0,
			'sin_admin' => 0,
			'sin_stock' => 0,
			'no_importa' => 0,
		];

		foreach ($comprobantes as $comprobante)
		{
			if (!isset($comprobante->Prefijo))
				continue;

			$resumen['total']++;
			$letra = substr($comprobante->TipoComprobante, -1);
			$tipoComprobante = $this->mapearTipoComprobanteAnita($comprobante->TipoComprobante);
			$puntoVenta = intval($comprobante->Prefijo);
			$numero = $comprobante->Numero;
			$comprobanteLabel = $tipoComprobante.' '.$letra.' '.$puntoVenta.'-'.$numero;
			$mediopago = $this->resolverMedioPago($comprobante->Prefijo);
			$enAdmin = $this->existeEnAdministracion($tipoComprobante, $letra, $puntoVenta, $numero);
			$enStock = $this->tieneStockLocal($tipoComprobante, $letra, $puntoVenta, $numero);

			if ($mediopago == '6')
			{
				$estado = 'no_importa';
				$resumen['no_importa']++;
				$estadoTexto = 'No requiere importacion ERP';
			}
			elseif (!$enAdmin)
			{
				$estado = 'sin_admin';
				$resumen['sin_admin']++;
				$estadoTexto = 'Falta en administracion';
			}
			elseif (!$enStock)
			{
				$estado = 'sin_stock';
				$resumen['sin_stock']++;
				$estadoTexto = 'Falta stock en Lugano';
			}
			else
			{
				$estado = 'completo';
				$resumen['completos']++;
				$estadoTexto = 'OK';
			}

			$clienteNombre = isset($comprobante->Cliente->RazonSocial)
				? $comprobante->Cliente->RazonSocial : '';

			$detalle[] = [
				'comprobante' => $comprobanteLabel,
				'fecha' => isset($comprobante->FechaHora)
					? date('d/m/Y', strtotime($comprobante->FechaHora)) : '',
				'cliente' => $clienteNombre,
				'total' => isset($comprobante->Total) ? $comprobante->Total : '',
				'mediopago' => $mediopago,
				'en_admin' => $enAdmin,
				'en_stock' => $enStock,
				'estado' => $estado,
				'estado_texto' => $estadoTexto
			];
		}

		$resumen['todo_ok'] = ($resumen['sin_admin'] == 0 && $resumen['sin_stock'] == 0);
		$resumen['mensaje'] = $this->armaMensajeVerificacion($resumen);

		return [
			'desdefecha' => $desdefecha,
			'hastafecha' => $hastafecha,
			'resumen' => $resumen,
			'detalle' => $detalle
		];
	}

	public function armaMensajeVerificacion($resumen)
	{
		if ($resumen['total'] == 0)
			return 'No hay comprobantes en Facturante para el periodo.';

		$partes = [
			'Facturante: '.$resumen['total'],
			'Completos: '.$resumen['completos'],
		];

		if ($resumen['sin_admin'] > 0)
			$partes[] = 'Sin administracion: '.$resumen['sin_admin'];
		if ($resumen['sin_stock'] > 0)
			$partes[] = 'Sin stock Lugano: '.$resumen['sin_stock'];
		if ($resumen['no_importa'] > 0)
			$partes[] = 'No importa ERP: '.$resumen['no_importa'];

		if ($resumen['todo_ok'])
			$partes[] = 'Todo correcto en comprobantes que requieren importacion';

		return implode('. ', $partes);
	}

	public function recuperarStockLocal($desdefecha, $hastafecha, $dryRun = false)
	{
		$comprobantes = $this->listadoComprobanteFull([
			'desdefecha' => $desdefecha,
			'hastafecha' => $hastafecha
		]);

		if (!is_array($comprobantes))
			return ['error' => 'No se pudieron leer comprobantes de Facturante', 'procesados' => 0];

		$resultado = [
			'procesados' => 0,
			'omitidos_sin_admin' => 0,
			'omitidos_con_stock' => 0,
			'omitidos_mediopago' => 0,
			'errores' => [],
			'detalle' => []
		];

		foreach ($comprobantes as $comprobante)
		{
			if (!isset($comprobante->Prefijo))
				continue;

			$mediopago = $this->resolverMedioPago($comprobante->Prefijo);
			if ($mediopago == '6')
			{
				$resultado['omitidos_mediopago']++;
				continue;
			}

			$letra = substr($comprobante->TipoComprobante, -1);
			$tipoComprobante = $this->mapearTipoComprobanteAnita($comprobante->TipoComprobante);
			$puntoVenta = intval($comprobante->Prefijo);
			$numero = $comprobante->Numero;

			if (!$this->existeEnAdministracion($tipoComprobante, $letra, $puntoVenta, $numero))
			{
				$resultado['omitidos_sin_admin']++;
				continue;
			}

			if ($this->tieneStockLocal($tipoComprobante, $letra, $puntoVenta, $numero))
			{
				$resultado['omitidos_con_stock']++;
				continue;
			}

			$tipoFacturante = $this->mapearTipoComprobanteFacturante($comprobante->TipoComprobante);
			$dataFactura = $this->armaDataFacturaDesdeItems($comprobante->Items);
			if (count($dataFactura) == 0)
			{
				$resultado['errores'][] = $tipoComprobante.' '.$letra.' '.$puntoVenta.'-'.$numero.': sin items validos';
				continue;
			}

			$venta = [
				'codigo' => $tipoFacturante,
				'numerocomprobante' => $numero,
				'fecha' => $comprobante->FechaHora,
				'moneda_id' => 1
			];

			$detalle = $tipoComprobante.' '.$letra.' '.$puntoVenta.'-'.$numero;
			if ($dryRun)
			{
				$resultado['procesados']++;
				$resultado['detalle'][] = $detalle.' (simulacion)';
				continue;
			}

			$stock = app(FacturacionServiceFerli::class)->grabaStockLocal(
				$puntoVenta, $letra, $venta, $dataFactura
			);

			if ($stock != 'Success')
			{
				$resultado['errores'][] = $detalle.': '.$stock;
				continue;
			}

			$resultado['procesados']++;
			$resultado['detalle'][] = $detalle;
		}

		$resultado['mensaje'] = ($dryRun ? 'Simulacion: ' : 'Recuperados: ').$resultado['procesados']
			.', sin venta en admin: '.$resultado['omitidos_sin_admin']
			.', ya con stock: '.$resultado['omitidos_con_stock']
			.', medio pago no transfiere: '.$resultado['omitidos_mediopago']
			.', errores: '.count($resultado['errores']);

		return $resultado;
	}

	public function armaDataFacturaDesdeItems($items)
	{
		$dataFactura = [];
		if (!isset($items->ComprobanteItem))
			return $dataFactura;

		if (is_object($items->ComprobanteItem))
		{
			$this->procesaUnItem($items->ComprobanteItem, $dataFactura);
		}
		else
		{
			foreach ($items->ComprobanteItem as $item)
				$this->procesaUnItem($item, $dataFactura);
		}

		return $dataFactura;
	}

	public function tieneStockLocal($tipoComprobante, $letra, $puntoVenta, $numero)
	{
		$apiAnita = new ApiAnita();
		$data = array(
			'acc' => 'list',
			'tabla' => 'stkmov',
			'sistema' => 'ventas',
			'campos' => 'stkv_nro',
			'whereArmado' => " WHERE stkv_tipo='".$tipoComprobante."' AND stkv_letra='".$letra
				."' AND stkv_sucursal=".$puntoVenta." AND stkv_nro=".$numero,
			'servidor' => 'LOCAL_IP',
			'ifx_server' => 'IFX_SERVER_LOCAL'
		);
		$stkmov = json_decode($apiAnita->apiCall($data));

		return is_array($stkmov) && count($stkmov) > 0;
	}

	private function existeEnAdministracion($tipoComprobante, $letra, $puntoVenta, $numero)
	{
		$venta = $this->leeComprobante($tipoComprobante, $letra, $puntoVenta, $numero);

		return isset($venta[0]->ven_nro) && $venta[0]->ven_nro == $numero;
	}

	private function resolverMedioPago($prefijo)
	{
		if ($prefijo == 21 || $prefijo == 27)
			return '1';
		if ($prefijo == 23)
			return '2';
		if ($prefijo == 26)
			return '5';

		return '6';
	}

	private function mapearTipoComprobanteAnita($tipoComprobante)
	{
		switch ($tipoComprobante)
		{
			case 'FA':
			case 'FB':
			case 'FC':
				return 'FAC';
			case 'NCA':
			case 'NCB':
			case 'NCC':
				return 'NCD';
			case 'NDA':
			case 'NDB':
			case 'NDC':
				return 'NDB';
		}

		return substr($tipoComprobante, 0, 3);
	}

	private function mapearTipoComprobanteFacturante($tipoComprobante)
	{
		return $this->mapearTipoComprobanteAnita($tipoComprobante);
	}

}
