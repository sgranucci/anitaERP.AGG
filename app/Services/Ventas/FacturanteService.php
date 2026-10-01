<?php
namespace App\Services\Ventas;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\Stock\Articulo_MovimientoService;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Repositories\Ventas\VentaRepositoryInterface;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Impuesto;
use App\Models\Contable\Cuentacontable;
use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Categoria;
use App\Models\Stock\Depmae;
use App\Models\Stock\Talle;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\ApiAnita;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalEmisionVinculoSupport;
use App\Support\Ventas\TipotransaccionOperacionStockSupport;
use Exception;
use SoapClient;
use Log;
use Illuminate\Support\Facades\DB;

class FacturanteService 
{
	var $client;
	private $facturacionService;
	protected $ventaRepository;
	protected $articuloQuery;
	private $arrayPago = [];
	private $stkmovLocalCache = [];
	private $stkmovLocalListo = [];
	
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
				'importe' => floatval($iva2),
				'impuesto_id' => 3,
			];
		}
		if (floatval($iva1) != 0)
		{
			$conceptosTotales[] = [
				'concepto' => "IVA",
				'tasa' => 10.5,
				'importe' => floatval($iva1),
				'impuesto_id' => 2,
			];
		}
		$dataFactura = [];

		try {
			if (is_object($arrayItems->ComprobanteItem))
				$this->procesaUnItem($arrayItems->ComprobanteItem, $dataFactura);
			else
				foreach ($arrayItems->ComprobanteItem as $item)
					$this->procesaUnItem($item, $dataFactura);
		} catch (\Throwable $e) {
			$numeroItem = $tipocomprobante.' '.$prefijo.'-'.$numero;
			Log::warning('facturante.item', [
				'comprobante' => $numeroItem,
				'mensaje' => $e->getMessage(),
			]);

			return ['error' => 'No se pudo leer un item de '.$numeroItem.': '.$e->getMessage()];
		}

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
			$this->persistirEnErp(
				$tipoComprobante,
				$letra,
				$puntoVenta,
				(int) $numero,
				$venta,
				$dataCAE,
				$conceptosTotales,
				$dataFactura,
				$signo,
				(string) $numeroCae,
				(string) $fechavencimientocae,
				$nombreCliente,
				(string) ($arrayCliente->NroDocumento ?? ''),
				$direccionCliente
			);

			return ['error' => 'Success', 'estado' => 'grabada', 'comprobante' => $comprobante];
		}
		catch (\Exception $e) {
			Log::info('Error al generar factura Facturante '.$e->getMessage());

			return ['error' => $e->getMessage()];
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

			// SKU-combinacion-talle. Facturante tambien manda conceptos sin guion (shipping).
			$partes = explode('-', trim((string) ($item->Codigo ?? '')));
			$sku = $partes[0] ?? '';
			$codigoCombinacion = $partes[1] ?? '';
			$talle = $partes[2] ?? '0';

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
				
				if ($codigoCombinacion !== '') {
					$combinacion = Combinacion::where('articulo_id', $articulo->id)
										->where('codigo', $codigoCombinacion)->first();
					if ($combinacion)
						$combinacion_id = $combinacion->id;
				}

				$talle = Talle::where('nombre', $talle)->first();

				if ($talle)
				{
					$talle_id = $talle->id;
					$talle_nombre = $talle->nombre;
				}
				else
				{
					$talle_id = $partes[2] ?? 0;
					$talle_nombre = isset($partes[2]) ? (string) $partes[2] : '0';
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
				'omitir_stock_linea' => $articulo_id === '' || $articulo_id === 0,
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
		// La cobranza de Facturante queda en el asiento de la venta en anitaERP.
		// No numera PRE ni escribe climov/subdiario en Anita ni en el bridge local.
		return null;
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
		$enErp = $this->buscarVentaErp($tipoComprobante, (int) $puntoVenta, $numero);
		if ($enErp !== null) {
			$montoErp = abs((float) $enErp->total);
			$montoNuevo = abs((float) ($venta['total'] ?? 0));
			if (abs($montoErp - $montoNuevo) < 0.02) {
				return ['estado' => 'identica'];
			}

			return [
				'estado' => 'distinta',
				'diferencias' => ['total (existente: '.$montoErp.', nuevo: '.$montoNuevo.')'],
			];
		}

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
				$estadoTexto = 'Falta en anitaERP';
			}
			elseif (!$enStock)
			{
				$estado = 'sin_stock';
				$resumen['sin_stock']++;
				$estadoTexto = 'Falta stock en anitaERP';
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

			$ventaErp = $this->buscarVentaErp($tipoComprobante, $puntoVenta, $numero);
			try {
				$creados = $this->crearStockDesdeVentaErp($ventaErp);
			} catch (\Exception $e) {
				$resultado['errores'][] = $detalle.': '.$e->getMessage();
				continue;
			}

			if ($creados === 0) {
				$resultado['errores'][] = $detalle.': la venta no tiene renglones con articulo';
				continue;
			}

			$resultado['procesados']++;
			$resultado['detalle'][] = $detalle;
		}

		$resultado['mensaje'] = ($dryRun ? 'Simulacion: ' : 'Recuperados: ').$resultado['procesados']
			.', sin venta en anitaERP: '.$resultado['omitidos_sin_admin']
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
		$venta = $this->buscarVentaErp($tipoComprobante, (int) $puntoVenta, $numero);
		if ($venta === null) {
			return false;
		}

		return DB::table('articulo_movimiento')->where('venta_id', $venta->id)->exists();
	}

	private function existeEnAdministracion($tipoComprobante, $letra, $puntoVenta, $numero)
	{
		return $this->buscarVentaErp($tipoComprobante, (int) $puntoVenta, $numero) !== null;
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

	public function tieneVentaErp(string $tipoComprobante, int $puntoVenta, $numero): bool
	{
		return $this->buscarVentaErp($tipoComprobante, $puntoVenta, $numero) !== null;
	}

	public function importarFaltantesDesdeBridges(string $desde, string $hasta, bool $dryRun = true): array
	{
		$desdeYmd = Carbon::parse($desde)->format('Ymd');
		$hastaYmd = Carbon::parse($hasta)->format('Ymd');
		$apiAnita = new ApiAnita();
		$raw = $apiAnita->apiCall([
			'acc' => 'list',
			'tabla' => 'venta',
			'sistema' => 'ventas',
			'campos' => 'ven_tipo, ven_letra, ven_sucursal, ven_nro, ven_fecha, ven_monto, ven_gravado, ven_impuesto1, ven_exento, ven_perc_ing_bruto, ven_nombre_cliente, ven_cuit_cli, ven_direccion_cli',
			'whereArmado' => " WHERE ven_fecha >= '".$desdeYmd."' AND ven_fecha <= '".$hastaYmd
				."' AND ven_sucursal IN (21,23,26,27) AND ven_tipo IN ('FAC','NCD','NDB') ",
		]);
		$filas = json_decode($raw);
		if (! is_array($filas)) {
			return ['error' => 'No se pudo leer el bridge Anita: '.substr((string) $raw, 0, 240)];
		}

		$resultado = [
			'bridge' => count($filas),
			'a_crear' => [],
			'ya_en_erp' => 0,
			'sin_stock' => [],
			'creadas' => 0,
			'stock_completado' => 0,
			'errores' => [],
		];

		foreach ($filas as $fila) {
			$tipo = trim((string) $fila->ven_tipo);
			$letra = trim((string) $fila->ven_letra);
			$puntoVenta = (int) $fila->ven_sucursal;
			$numero = (int) $fila->ven_nro;
			$etiqueta = $tipo.' '.$letra.' '.$puntoVenta.'-'.$numero;
			$venta = $this->buscarVentaErp($tipo, $puntoVenta, $numero);
			if ($venta === null) {
				$resultado['a_crear'][] = $etiqueta;
				if ($dryRun) {
					continue;
				}
				try {
					$this->grabarVentaDesdeBridge($fila);
					$resultado['creadas']++;
				} catch (\Exception $e) {
					$resultado['errores'][] = $etiqueta.': '.$e->getMessage();
				}
				continue;
			}

			$resultado['ya_en_erp']++;
			if (DB::table('articulo_movimiento')->where('venta_id', $venta->id)->exists()) {
				continue;
			}
			$resultado['sin_stock'][] = $etiqueta;
			if ($dryRun) {
				continue;
			}
			try {
				$this->crearStockDesdeVentaErp($venta);
				$resultado['stock_completado']++;
			} catch (\Exception $e) {
				$resultado['errores'][] = $etiqueta.' stock: '.$e->getMessage();
			}
		}

		return $resultado;
	}

	private function persistirEnErp(
		string $tipoComprobante,
		string $letra,
		int $puntoVenta,
		int $numero,
		array $venta,
		array $dataCAE,
		array $conceptosTotales,
		array $dataFactura,
		float $signo,
		string $cae,
		string $fechaVencimientoCae,
		string $nombreCliente,
		string $documentoCliente,
		string $direccionCliente
	): int {
		$puntoventa = Puntoventa::query()
			->where('codigo', str_pad((string) $puntoVenta, 5, '0', STR_PAD_LEFT))
			->first();
		if (! $puntoventa) {
			throw new Exception('No existe el punto de venta '.$puntoVenta.' en anitaERP.');
		}

		$tipotransaccion = Tipotransaccion::query()
			->where('abreviatura', $tipoComprobante)
			->where('estado', 'A')
			->first();
		if (! $tipotransaccion) {
			throw new Exception('No existe el tipo '.$tipoComprobante.' en anitaERP.');
		}

		$empresa = Empresa::query()->find($puntoventa->empresa_id);
		if (! $empresa) {
			throw new Exception('La empresa del punto de venta no existe.');
		}

		$cliente = Cliente::query()->where('codigo', '0')->orderBy('id')->first();
		if (! $cliente) {
			throw new Exception('No existe el cliente consumidor final (codigo 0).');
		}
		$clienteGraba = clone $cliente;
		if ($nombreCliente !== '') {
			$clienteGraba->nombre = $nombreCliente;
		}
		if ($direccionCliente !== '') {
			$clienteGraba->domicilio = $direccionCliente;
		}
		if ($documentoCliente !== '') {
			$clienteGraba->numerodocumento = $documentoCliente;
		}

		$codigoDeposito = $puntoVenta === 27 ? '27' : '10';
		$deposito = Depmae::query()
			->where('empresa_id', $empresa->id)
			->where('codigo', $codigoDeposito)
			->first();
		if (! $deposito) {
			throw new Exception('No existe el deposito '.$codigoDeposito.' en anitaERP.');
		}

		$cuentaVentaId = (int) (Cuentacontable::query()
			->where('empresa_id', $empresa->id)
			->where('codigo', '411000003')
			->value('id') ?? 0);

		$dataFactura = $this->normalizarRenglonesErp($dataFactura, $cuentaVentaId);
		$fecha = Carbon::parse($venta['fecha'])->format('Y-m-d');
		$asiento = $this->facturacionService->armaContabilidad(
			$this->renglonesNetosParaAsiento($dataFactura),
			$conceptosTotales,
			(int) $empresa->id,
			abs((float) ($venta['total'] ?? 0))
		);
		$dataCAE['codigoempresa'] = $dataCAE['codigoempresa'] ?? ($empresa->codigo ?? 1);

		$ret = $this->facturacionService->grabaFacturaERP(
			$empresa,
			$tipotransaccion->codigo,
			$tipotransaccion,
			$fecha,
			$clienteGraba,
			abs((float) ($venta['total'] ?? 0)),
			1,
			1,
			'Facturante',
			$letra,
			$puntoventa,
			$numero,
			null,
			$conceptosTotales,
			[],
			$dataFactura,
			$asiento,
			trim($tipoComprobante.' '.$letra.' '.$puntoventa->codigo.' '.$numero),
			$signo,
			0,
			0,
			$dataCAE,
			0,
			null,
			null,
			[
				'deposito_id' => (int) $deposito->id,
				'omitir_sincronizacion_anita' => true,
				'omitir_stkmov_anita' => true,
				'omitir_solicitud_arca_cae' => true,
				'omitir_numera_anita_fin' => true,
				'omitir_cuenta_corriente' => true,
				'forzar_operacion_stock' => $signo < 0
					? TipotransaccionOperacionStockSupport::ENTRADA
					: TipotransaccionOperacionStockSupport::SALIDA,
			]
		);

		if (! is_array($ret) || trim((string) ($ret['error'] ?? '')) !== '') {
			$detalle = trim((string) ($ret['mensaje'] ?? $ret['error'] ?? 'No se pudo grabar la venta en anitaERP.'));
			throw new Exception($detalle !== '' ? $detalle : 'No se pudo grabar la venta en anitaERP.');
		}

		$ventaId = (int) ($ret['venta_id'] ?? 0);
		if ($ventaId <= 0) {
			throw new Exception('La venta no quedo grabada en anitaERP.');
		}

		if (trim($cae) !== '') {
			$this->ventaRepository->update([
				'cae' => $cae,
				'fechavencimientocae' => Carbon::parse($fechaVencimientoCae)->format('Y-m-d'),
			], $ventaId);
		}

		FacturacionLocalEmisionVinculoSupport::vincularVenta($ventaId, 'facturante');

		return $ventaId;
	}

	private function grabarVentaDesdeBridge(object $fila): int
	{
		$tipo = trim((string) $fila->ven_tipo);
		$letra = trim((string) $fila->ven_letra);
		$puntoVenta = (int) $fila->ven_sucursal;
		$numero = (int) $fila->ven_nro;
		$fecha = $this->fechaAnitaAYmd((string) $fila->ven_fecha);
		$signo = $tipo === 'NCD' ? -1.0 : 1.0;

		$compaux = $this->listarBridge('compaux', 'ventas',
			'compa_orden, compa_articulo, compa_cantidad, compa_precio, compa_desc, compa_tipo_iva, compa_incl_imp, compa_dto',
			" WHERE compa_tipo='".$tipo."' AND compa_letra='".$letra."' AND compa_sucursal=".$puntoVenta." AND compa_nro_fact=".$numero
		);
		if ($compaux === []) {
			throw new Exception('Sin renglones en compaux del bridge Anita.');
		}

		$stkmov = $this->movimientosStockLocal($fecha, $tipo, $letra, $puntoVenta, $numero);
		$tallePorSku = [];
		foreach ($stkmov as $mov) {
			$tallePorSku[$this->skuNormalizado((string) $mov->stkv_articulo)] = $mov;
		}

		$dataFactura = [];
		foreach ($compaux as $linea) {
			if (trim((string) $linea->compa_articulo) === '' || trim((string) $linea->compa_articulo) === 'texto') {
				continue;
			}
			$sku = $this->skuNormalizado((string) $linea->compa_articulo);
			$articulo = $this->articuloQuery->traeArticuloPorSku($sku);
			if (! $articulo) {
				$articulo = $this->articuloQuery->traeArticuloPorSku(str_pad($sku, 13, '0', STR_PAD_LEFT));
			}
			$impuestoId = (int) ($linea->compa_tipo_iva ?: 3);
			$tasa = (float) (Impuesto::query()->whereKey($impuestoId)->value('valor') ?? 21);
			$precio = (float) $linea->compa_precio;
			if (strtoupper(trim((string) ($linea->compa_incl_imp ?? 'S'))) === 'S' && $tasa > 0) {
				$precio = round($precio * (1 + ($tasa / 100)), 2);
			}
			$mov = $tallePorSku[$sku] ?? null;
			$talleId = 0;
			$combinacionId = 0;
			$codigoCombinacion = '';
			if ($mov) {
				$talle = Talle::query()->where('nombre', trim((string) $mov->stkv_partida))->first();
				$talleId = $talle ? (int) $talle->id : 0;
				$codigoCombinacion = trim((string) ($mov->stkv_color ?? ''));
				if ($articulo && $codigoCombinacion !== '') {
					$combinacion = Combinacion::query()
						->where('articulo_id', $articulo->id)
						->where('codigo', $codigoCombinacion)
						->first();
					$combinacionId = $combinacion ? (int) $combinacion->id : 0;
				}
			}
			$dataFactura[] = [
				'cantidad' => (float) $linea->compa_cantidad,
				'precio' => $precio,
				'descuento' => (float) ($linea->compa_dto ?? 0),
				'descuentointegrado' => '',
				'incluyeimpuesto' => '1',
				'impuesto_id' => $impuestoId,
				'articulo_id' => $articulo ? (int) $articulo->id : 0,
				'sku' => $sku,
				'descripcion' => (string) ($linea->compa_desc ?? $sku),
				'combinacion_id' => $combinacionId,
				'codigocombinacion' => $codigoCombinacion,
				'moneda_id' => 1,
				'listaprecio_id' => 1,
				'medidas' => [[
					'talle' => $talleId,
					'cantidad' => (float) $linea->compa_cantidad,
				]],
			];
		}
		if ($dataFactura === []) {
			throw new Exception('compaux no tiene articulos.');
		}

		$vencae = $this->listarBridge('vencae', 'ventas',
			'venc_nro_cae, venc_fecha_vto',
			" WHERE venc_tipo='".$tipo."' AND venc_letra='".$letra."' AND venc_sucursal=".$puntoVenta." AND venc_nro=".$numero
		);
		$cae = isset($vencae[0]) ? trim((string) $vencae[0]->venc_nro_cae) : '';
		$fechaCae = isset($vencae[0]) ? $this->fechaAnitaAYmd((string) $vencae[0]->venc_fecha_vto) : $fecha;

		$gravado = (float) ($fila->ven_gravado ?? 0);
		$iva = (float) ($fila->ven_impuesto1 ?? 0);
		$exento = (float) ($fila->ven_exento ?? 0);
		$percepcion = (float) ($fila->ven_perc_ing_bruto ?? 0);
		$conceptos = [];
		if ($percepcion != 0.0) {
			$conceptos[] = [
				'concepto' => 'Percepcion IIBB',
				'jurisdiccion' => '902',
				'provincia_id' => 2,
				'tasa' => $gravado != 0.0 ? $percepcion / $gravado : 0,
				'importe' => $percepcion,
			];
		}
		if ($iva != 0.0) {
			$conceptos[] = [
				'concepto' => 'Total Iva',
				'tasa' => 21,
				'importe' => $iva,
				'impuesto_id' => 3,
			];
		}

		return $this->persistirEnErp(
			$tipo,
			$letra,
			$puntoVenta,
			$numero,
			[
				'codigo' => $tipo,
				'numerocomprobante' => $numero,
				'fecha' => $fecha,
				'total' => abs((float) $fila->ven_monto),
				'moneda_id' => 1,
			],
			[
				'gravado' => $gravado,
				'iva' => $iva,
				'total' => abs((float) $fila->ven_monto),
				'nogravado' => 0,
				'exento' => $exento,
			],
			$conceptos,
			$dataFactura,
			$signo,
			$cae,
			$fechaCae,
			trim((string) ($fila->ven_nombre_cliente ?? '')),
			trim((string) ($fila->ven_cuit_cli ?? '')),
			trim((string) ($fila->ven_direccion_cli ?? ''))
		);
	}

	private function crearStockDesdeVentaErp(?Venta $venta): int
	{
		if (! $venta) {
			throw new Exception('La venta no existe en anitaERP.');
		}
		if (DB::table('articulo_movimiento')->where('venta_id', $venta->id)->exists()) {
			return 0;
		}

		$tipo = Tipotransaccion::query()->find($venta->tipotransaccion_id);
		$operacion = ($tipo && $tipo->esNotaCredito())
			? TipotransaccionOperacionStockSupport::ENTRADA
			: TipotransaccionOperacionStockSupport::SALIDA;
		$lineas = DB::table('venta_emision')->where('venta_id', $venta->id)->orderBy('numeroitem')->get();
		$creados = 0;
		$servicio = app(Articulo_MovimientoService::class);
		foreach ($lineas as $linea) {
			if ((int) ($linea->articulo_id ?? 0) <= 0) {
				continue;
			}
			$payload = TipotransaccionOperacionStockSupport::firmarPayloadMovimiento([
				'fecha' => Carbon::parse($venta->fecha)->format('Y-m-d'),
				'fechajornada' => Carbon::parse($venta->fechajornada ?: $venta->fecha)->format('Y-m-d'),
				'tipotransaccion_id' => (int) $venta->tipotransaccion_id,
				'venta_id' => (int) $venta->id,
				'articulo_id' => (int) $linea->articulo_id,
				'combinacion_id' => (int) ($linea->combinacion_id ?? 0),
				'talle_id' => (int) ($linea->talle_id ?? 0),
				'concepto' => $tipo->nombre ?? 'Facturante',
				'cantidad' => abs((float) $linea->cantidad),
				'precio' => (float) $linea->precio,
				'costo' => 0,
				'descuento' => $linea->descuento,
				'descuentointegrado' => $linea->descuentointegrado,
				'moneda_id' => (int) ($linea->moneda_id ?: 1),
				'incluyeimpuesto' => $linea->incluyeimpuesto,
				'listaprecio_id' => null,
				'deposito_id' => (int) ($linea->deposito_id ?: 0),
			], $operacion);
			$servicio->guardaArticuloMovimiento('create', $payload, []);
			$creados++;
		}

		return $creados;
	}

	private function buscarVentaErp(string $tipoComprobante, int $puntoVenta, $numero): ?Venta
	{
		$puntoventaId = Puntoventa::query()
			->where('codigo', str_pad((string) $puntoVenta, 5, '0', STR_PAD_LEFT))
			->value('id');
		$tipoId = Tipotransaccion::query()
			->where('abreviatura', $tipoComprobante)
			->where('estado', 'A')
			->value('id');
		if (! $puntoventaId || ! $tipoId) {
			return null;
		}

		return Venta::query()
			->where('puntoventa_id', $puntoventaId)
			->where('tipotransaccion_id', $tipoId)
			->where('numerocomprobante', (int) $numero)
			->first();
	}

	private function normalizarRenglonesErp(array $dataFactura, int $cuentaVentaId): array
	{
		foreach ($dataFactura as &$item) {
			$talleId = (int) ($item['medidas'][0]['talle'] ?? $item['talle_id'] ?? 0);
			if ($talleId > 0 && Talle::query()->whereKey($talleId)->exists()) {
				$item['talle_id'] = $talleId;
			}
			$articuloId = (int) ($item['articulo_id'] ?? 0);
			if ($articuloId <= 0) {
				unset($item['articulo_id']);
			} else {
				$item['articulo_id'] = $articuloId;
				$cuentaArticulo = (int) (Articulo::query()->whereKey($articuloId)->value('cuentacontableventa_id') ?? 0);
				$item['cuentacontable_id'] = $cuentaArticulo > 0 ? $cuentaArticulo : $cuentaVentaId;
			}
			if (empty($item['detalle'])) {
				$item['detalle'] = (string) ($item['descripcion'] ?? '');
			}
			if ((int) ($item['combinacion_id'] ?? 0) <= 0) {
				unset($item['combinacion_id']);
			}
		}
		unset($item);

		return $dataFactura;
	}

	private function renglonesNetosParaAsiento(array $dataFactura): array
	{
		$netos = [];
		foreach ($dataFactura as $item) {
			$copia = $item;
			$tasa = (float) (Impuesto::query()->whereKey((int) ($item['impuesto_id'] ?? 0))->value('valor') ?? 0);
			if (($item['incluyeimpuesto'] ?? '') === '1' && $tasa > 0) {
				$copia['precio'] = ((float) $item['precio']) / (1 + ($tasa / 100));
			}
			$bonificacion = (float) ($item['descuento'] ?? 0);
			if ($bonificacion > 0 && $bonificacion <= 100) {
				$copia['precio'] = ((float) $copia['precio']) * (1 - ($bonificacion / 100));
			}
			$netos[] = $copia;
		}

		return $netos;
	}

	private function movimientosStockLocal(string $fecha, string $tipo, string $letra, int $sucursal, int $numero): array
	{
		$fechaYmd = Carbon::parse($fecha)->format('Ymd');
		$lote = $fechaYmd.'|'.$sucursal;
		if (! isset($this->stkmovLocalListo[$lote])) {
			$filas = $this->listarBridge(
				'stkmov',
				'ventas',
				'stkv_tipo, stkv_letra, stkv_nro, stkv_articulo, stkv_partida, stkv_color, stkv_cantidad',
				" WHERE stkv_fecha='".$fechaYmd."' AND stkv_sucursal=".$sucursal
					." AND stkv_tipo IN ('FAC','NCD','NDB') ",
				'LOCAL_IP',
				'IFX_SERVER_LOCAL'
			);
			$mapa = [];
			foreach ($filas as $mov) {
				$clave = trim((string) $mov->stkv_tipo).'|'.trim((string) $mov->stkv_letra).'|'
					.(int) $mov->stkv_nro.'|'.$this->skuNormalizado((string) $mov->stkv_articulo);
				$mapa[$clave] = $mov;
			}
			$this->stkmovLocalCache[$lote] = $mapa;
			$this->stkmovLocalListo[$lote] = true;
		}

		$prefijo = $tipo.'|'.$letra.'|'.$numero.'|';
		$salida = [];
		foreach ($this->stkmovLocalCache[$lote] as $clave => $mov) {
			if (strpos($clave, $prefijo) === 0) {
				$salida[] = $mov;
			}
		}

		return $salida;
	}

	private function listarBridge(
		string $tabla,
		string $sistema,
		string $campos,
		string $where,
		?string $servidor = null,
		?string $ifxServer = null
	): array {
		$apiAnita = new ApiAnita();
		$data = [
			'acc' => 'list',
			'tabla' => $tabla,
			'sistema' => $sistema,
			'campos' => $campos,
			'whereArmado' => $where,
		];
		if ($servidor !== null) {
			$data['servidor'] = $servidor;
			$data['ifx_server'] = $ifxServer;
		}
		$filas = json_decode($apiAnita->apiCall($data));

		return is_array($filas) ? $filas : [];
	}

	private function skuNormalizado(string $sku): string
	{
		$sku = trim($sku);
		$sinCeros = ltrim($sku, '0');

		return $sinCeros !== '' ? $sinCeros : '0';
	}

	private function fechaAnitaAYmd(string $fecha): string
	{
		$fecha = trim($fecha);
		if (preg_match('/^\d{8}$/', $fecha)) {
			return Carbon::createFromFormat('Ymd', $fecha)->format('Y-m-d');
		}

		return Carbon::parse($fecha)->format('Y-m-d');
	}

}
