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
		//try {
			$anita = $this->facturacionService->grabaAnita($puntoVenta, $letra, 0, 0,
							$venta, $dataCAE, $conceptosTotales, $cuentacorriente, $dataFactura, $signo, 
							$cuentaVenta, $contrapartida, true,
							'LOCAL_IP', 'IFX_SERVER_LOCAL');

			if ($anita == 'Error')
				throw new Exception('Error en grabacion anita.');

			if ($anita == 'Errvend')
				throw new Exception('No tiene vendedor asignado.');
			
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
			$subdiario = $apiAnita->apiCall($data);

			if (strpos($subdiario, 'Error') !== false)
				return 'Error';

			$vencae = $this->facturacionService->grabaVenCae(substr($venta['codigo'], 0, 3), $letra, 
				$puntoVenta, $venta['numerocomprobante'], $cae['cae'], 
				date('Ymd', strtotime($cae['fechavencimientocae'])));
		//}
		//catch ( \Exception $e) {

			//Log::info('Error al generar factura TiendaNube '. $e->getMessage());

			// Borra factura de anita
			//if ($venta['codigo'] ?? '')
			//	$this->facturacionService->borraAnita(substr($venta['codigo'], 0, 3), $letra, 
			//					$puntoVenta, $venta['numerocomprobante']);
		//}
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
				'loteimportacion_id' => 0,
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
				//$subdiario = $apiAnita->apiCall($data);

				//if (strpos($subdiario, 'Error') !== false)
				//	return 'Error';

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
		$climov = $apiAnita->apiCall($data);

		if (strpos($climov, 'Error') !== false)
			return 'Error';
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
		$tesmov = $apiAnita->apiCall($data);

		if (strpos($tesmov, 'Error') !== false)
			return 'Error';
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
		}

		return $cuentaFinanciera;
	}

}
