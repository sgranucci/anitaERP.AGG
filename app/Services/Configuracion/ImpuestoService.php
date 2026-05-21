<?php
namespace App\Services\Configuracion;

use App\Models\Stock\Articulo;
use App\Models\Configuracion\Impuesto;
use App\Services\Configuracion\IIBBService;
use App\Repositories\Configuracion\CondicionivaRepositoryInterface;
use App\Repositories\Ventas\Cliente_Cm05RepositoryInterface;
use App\Repositories\Ventas\AbastoRepositoryInterface;
use App\Services\Ventas\FacturacionService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App;
use Auth;

class ImpuestoService extends FacturacionService
{
	protected $IIBBService;
	protected $condicionivaRepository;
	protected $cliente_cm05Repository;
	protected $abastoRepository;

    public function __construct(
								IIBBService $IIBBservice,
								Cliente_Cm05RepositoryInterface $cliente_cm05repository,
								CondicionivaRepositoryInterface $condicionivarepository,
								AbastoRepositoryInterface $abastorepository
								)
    {
        $this->IIBBService = $IIBBservice;
		$this->cliente_cm05Repository = $cliente_cm05repository;
		$this->condicionivaRepository = $condicionivarepository;
		$this->abastoRepository = $abastorepository;
    }

	public function calculaImpuestoVenta(&$dataItem, $dataCliente, $fechaFactura, $flGrabaComprobanteDividido = null)
	{
		$dataItem = $this->normalizaItemsCalculoImpuesto($dataItem);

		// Inicializa variables
		$totalFinal = 0.;
		$descuentoItem = 0.;
		$descuentoFinal = 0.;
		$totalBruto = 0.;
		$numerodocumento = "";
		$condicioniibb_id = "";
		$provincia = 0;
		$totalNeto = 0.;

		if (!isset($flGrabaComprobanteDividido))
			$flGrabaComprobanteDividido = false;

		$omitirPercepciones = ! empty($dataCliente['omitir_percepciones']);

		// Asigna datos cliente
		$nroInscripcion = $dataCliente['numerodocumento'];
		$retieneIva = $dataCliente['retieneiva'];
		$condicioniibb_id = $dataCliente['condicioniibb_id'];
		$provincia = $dataCliente['provincia'];
		$cliente_id = $dataCliente['id'];
		$porcDescuento = 0;

		if (isset($dataCliente['descuentoimportepie']))
			$descuentoImportePie = $dataCliente['descuentoimportepie'];
		else
			$descuentoImportePie = 0;

		// Lee condicion de iva del cliente
		$condicioniva = $this->condicionivaRepository->find($dataCliente['condicioniva_id']);

		$flConIva = true;
		if ($condicioniva)
		{
			if ($condicioniva->coniva == 'N')
				$flConIva = false;
		}

		// Lee el abasto
		$tasaAbasto = 0;
		if (strtoupper(config("app.empresa") == "EL BIERZO") && isset($dataCliente['abasto_id']))
		{
			$abasto = $this->abastoRepository->findPorId($dataCliente['abasto_id']);

			if ($abasto)
				$tasaAbasto = $abasto->tasa;
		}

		// Porcentaje de logistica
		$porcentajeLogistica = 0;
		if (strtoupper(config("app.empresa") == "EL BIERZO") && isset($dataCliente['porcentajelogistica']))
			$porcentajeLogistica = $dataCliente['porcentajelogistica'];

		// Lee el CM05 del cliente
		$cm05 = $this->cliente_cm05Repository->findPorClienteId($cliente_id);

		// Calcula netos por tasa
		$netos = [];
		$subtotales = [];
		$porcentajeDescuentoImportePie = 0;

		// Debe calcular el total de los items y sacar el descuento en porcentaje
		$totalBrutoAuxiliar = 0;
		$tasaDetraccion = 0.;
		
		foreach($dataItem as $item)
			$totalBrutoAuxiliar += ($item['cantidad'] * $item['precio']);

		// Calcula las tasas de percepcion para agregar a la tasa de detraccion
        if (! $omitirPercepciones && env('ANITA_AGENTE_PERCEPCION_IVA') == 'si' && $retieneIva != 'S' && config('facturacion.USA_DETRACCION') == 'S')
			$tasaDetraccion += env('ANITA_TASA_PERCEPCION_IVA');

		// Agrega impuestos provinciales (también en tasa de detracción si aplica)
		$percepcionesIIBB = [];
		if (! $omitirPercepciones && ! $flGrabaComprobanteDividido) {
			$percepcionesIIBB = $this->IIBBService->calculaPercepcionIIBB($totalBrutoAuxiliar, $nroInscripcion,
				$condicioniibb_id, $provincia, $cm05, $fechaFactura);
		}

		if (config('facturacion.USA_DETRACCION') == 'S' && ! $omitirPercepciones)
		{
			foreach ($percepcionesIIBB as $percepcion)
				$tasaDetraccion += $percepcion['tasa'];
		}

		if ($descuentoImportePie != 0.)
		{
			// Debe calcular el total de los items y sacar el descuento en porcentaje
			foreach($dataItem as $item)
			{
				// Lee tasa impuesto del item
				if ($flGrabaComprobanteDividido)
					$valorTasaImpuesto = $this->tasaImpuesto;
				else
				{
					$impuesto = Impuesto::findOrFail($item['impuesto_id']);

					$valorTasaImpuesto = $impuesto->valor;
				}

				// Asume que no tiene impuesto incluido si el cliente no lleva iva
				if (!$flConIva)
				{
					$item['incluyeimpuesto'] = 'N';
					$valorTasaImpuesto = 0;
				}
				// Calcula importe del item
				$importeSinDescuento = $item['cantidad'] * 
					($item['incluyeimpuesto'] == 'N' || $item['incluyeimpuesto'] == '2' ? 
					$item['precio'] : ($item['precio'] / (1.+(($valorTasaImpuesto +  $tasaDetraccion)/100))));

				$totalBruto += $importeSinDescuento;
			}
			$porcentajeDescuentoImportePie = 1 - ($descuentoImportePie / $totalBruto);
		}

		$totalCantidad = 0;
		$off = 0;
		foreach($dataItem as $item)
		{
			$off++;
			if ($item['cantidad'] != 0)
			{
				$neto = Self::calculaNetoItem($item, $flGrabaComprobanteDividido, $flConIva, $tasaDetraccion, $porcentajeDescuentoImportePie);

				// Acumula cantidad
				if ($neto['totalSinDescuento'] != 0)
				{
					$totalCantidad += $item['cantidad'];

					// Calcula importe del item
					$totalItem = $neto['totalSinDescuento'];

					// Acumula subtotales
					self::agregaItemTotales("Subtotal", 0, $totalItem, 0, 0, 0, $subtotales);

					// Agrega descuento final
					$descuentoFinal += $neto['totalDescuento'];
					$porcDescuento = $neto['porcentajeDescuento'];

					// Lee tasa impuesto del item
					$impuesto_id = $impuesto_codigo = $impuesto_codigoarca = null;
					if ($flGrabaComprobanteDividido)
					{
						$valorTasaImpuesto = $this->tasaImpuesto ?? 0;

						$impuesto = true;
					}
					else
					{
						$impuesto = Impuesto::findOrFail($item['impuesto_id']);

						$valorTasaImpuesto = $impuesto->valor;

						$impuesto_id = $impuesto->id;
						$impuesto_codigo = $impuesto->codigo;
						$impuesto_codigoarca = $impuesto->codigoarca;
					}

					$totalNeto = round($neto['totalConDescuento'], 2);

					// Asigna total neto para calculos posteriores
					$dataItem[$off-1]['totalcondescuento'] = $totalNeto;

					// Acumula netos por tasa de impuesto
					self::agregaItemTotales(($valorTasaImpuesto == 0. ? "Exento" : "Gravado al ".$valorTasaImpuesto."%"), $valorTasaImpuesto, 
						$totalNeto, $impuesto_id, $impuesto_codigo, $impuesto_codigoarca, $netos);
				}
			}
		}

		// Agraga item de logistica
		if (strtoupper(config('app.empresa')) == 'EL BIERZO' && $porcentajeLogistica && !$flGrabaComprobanteDividido)
		{
			$totalLogistica = round($totalNeto * $porcentajeLogistica / 100., 2);
			
			$impuesto = Impuesto::findOrFail(config('facturacion.IMPUESTO_LOGISTICA_ID'));

			if ($impuesto)
			{
				if (($item['descuentofinal']+$porcentajeDescuentoImportePie) != 0.)
				{
					$descuentoFinal += ($totalLogistica * ($item['descuentofinal'] +
										$porcentajeDescuentoImportePie) / 100.);

					$totalLogistica *= (1. - (($item['descuentofinal']+$porcentajeDescuentoImportePie) / 100.));
					$porcDescuento = ($item['descuentofinal']+$porcentajeDescuentoImportePie);
				}

				// Acumula netos por tasa de impuesto
				self::agregaItemTotales("Total Logistica", $porcentajeLogistica, 
					$totalLogistica, $impuesto->id, $impuesto->codigo, $impuesto->codigoarca, $netos);
			}
		}

		if (($descuentoFinal+$porcentajeDescuentoImportePie) != 0.)
		{
			if ($porcDescuento != 0.)
				$detalle = "Descuento Gral. ".$porcDescuento.'%';
			else
				$detalle = "Descuento";
			
			$totalNeto -= $descuentoFinal;

			self::agregaItemTotales($detalle, $porcDescuento, -$descuentoFinal, 0, 0, 0, $subtotales);
		}

		// Agrega impuestos nacionales
		$impuestos = [];
		if ($flConIva)
		{
			for ($i = 0; $i < count($netos); $i++)
			{
				if($netos[$i]['tasa'] != 0.)
				{
					$detalle = "Iva ".$netos[$i]['tasa']."%";
					$importe = round($netos[$i]['importe'] * $netos[$i]['tasa'] / 100., 2);
	
					$impuestos[] = ["concepto"=>$detalle,
								"baseimponible" => $netos[$i]['importe'],
								"tasa"=>$netos[$i]['tasa'],
								"importe"=>$importe,
								"impuesto_id"=>$netos[$i]['impuesto_id'],
								"codigo"=>$netos[$i]['codigo'],
								"codigoarca"=>$netos[$i]['codigoarca']
							];
				}
			}
		}

		// Agrega percepcion de iva si es agente de percepcion y el cliente no lo es
        if (! $omitirPercepciones && env('ANITA_AGENTE_PERCEPCION_IVA') == 'si' && $retieneIva != 'S' && !$flGrabaComprobanteDividido)
		{
			$importeNeto = $importePercepcion = 0.;
			for ($i = 0; $i < count($netos); $i++)
			{
				if(env('ANITA_TASA_PERCEPCION_IVA') != 0.)
				{
					if($netos[$i]['tasa'] != 0.) // Solo trae los importes gravados
					{
						$importeNeto += $netos[$i]['importe'];
						$importePercepcion += round($netos[$i]['importe'] * env('ANITA_TASA_PERCEPCION_IVA') / 100., 2);
					}
				}
			}			
			$detalle = "Percepcion IVA ".env('ANITA_TASA_PERCEPCION_IVA')."%";
			$impuestos[] = ["concepto"=>$detalle,
						"baseimponible" => $importeNeto,
						"tasa"=>env('ANITA_TASA_PERCEPCION_IVA'),
						"importe"=>$importePercepcion
					];			
		}

		// Agrega impuestos provinciales
		$percepcionesIIBB = [];
		if (! $omitirPercepciones && ! $flGrabaComprobanteDividido)
		{
			$importeNeto = 0.;
			for ($i = 0; $i < count($netos); $i++)
			{
				if($netos[$i]['tasa'] != 0.) // Solo trae los importes gravados
					$importeNeto += $netos[$i]['importe'];
			}	
			$percepcionesIIBB = $this->IIBBService->calculaPercepcionIIBB($importeNeto, $nroInscripcion, 
																		$condicioniibb_id, $provincia, $cm05, $fechaFactura);
		}

		// Para El Bierzo agrega total de abasto
		if (strtoupper(config('app.empresa')) == 'EL BIERZO' && !$flGrabaComprobanteDividido)
		{
			if ($tasaAbasto > 0)
			{
				$totalAbasto = $totalCantidad * $tasaAbasto;

				$impuestos[] = ["concepto"=>"Total Abasto",
								"baseimponible" => 0,
								"tasa"=>$tasaAbasto,
								"importe"=>$totalAbasto,
								];				
			}
		}
		$conceptosTotales = array_merge($subtotales, $netos, $impuestos, $percepcionesIIBB);
		
		// Agrega total final
		for ($i = 0, $totalFinal = 0; $i < count($conceptosTotales); $i++)
		{
			if ($conceptosTotales[$i]['concepto'] != "Subtotal" &&
				substr($conceptosTotales[$i]['concepto'], 0, 9) != "Descuento")
				$totalFinal += $conceptosTotales[$i]['importe'];
		}

		if ($descuentoImportePie != 0.)
		{
			$detalle = "Descuento por importe";
			$totalFinal -= $descuentoImportePie;

			self::agregaItemTotales($detalle, 0, -$descuentoImportePie, 0, 0, 0, $conceptosTotales);
		}

		$totalFinal = round($totalFinal, 2);

		$conceptosTotales[] = ["concepto"=>"Total",
								"tasa"=>0,
								"importe"=>$totalFinal,
							];

		return $conceptosTotales;
	}

	/**
	 * Solo impuestos nacionales (IVA sobre netos): sin IIBB, sin percepción IVA, sin detracción asociada a percepciones,
	 * sin líneas de logística El Bierzo. Los importes del arreglo deben estar en moneda ya homogénea (p. ej. moneda del primer ítem).
	 *
	 * @param  array<int, array<string, mixed>>  $dataItem  cantidad, precio, preciosindescuento, impuesto_id, incluyeimpuesto, descuentofinal, kilodescuento
	 * @return array{
	 *   subtotal_bruto_sin_iva: float,
	 *   importe_descuento: float,
	 *   neto_sin_iva: float,
	 *   iva_total: float,
	 *   total: float,
	 *   filas_iva: list<array{tasa: float, importe: float}>
	 * }
	 */
	public function calculaImpuestosNacionalesItems(array &$dataItem, bool $flConIva = true): array
	{
		$dataItem = $this->normalizaItemsCalculoImpuesto($dataItem);

		$descuentoFinal = 0.;
		$porcDescuento = 0.;
		$netos = [];
		$subtotales = [];
		$flGrabaComprobanteDividido = false;
		$tasaDetraccion = 0.;
		$porcentajeDescuentoImportePie = 0.;
		$subtotalBruto = 0.;

		foreach ($dataItem as $idx => $item) {
			if (($item['cantidad'] ?? 0) == 0) {
				continue;
			}
			$neto = self::calculaNetoItem($item, $flGrabaComprobanteDividido, $flConIva, $tasaDetraccion, $porcentajeDescuentoImportePie);
			if (($neto['totalSinDescuento'] ?? 0) == 0) {
				continue;
			}
			$subtotalBruto += (float) $neto['totalSinDescuento'];
			$totalItem = $neto['totalSinDescuento'];
			self::agregaItemTotales('Subtotal', 0, $totalItem, 0, 0, 0, $subtotales);
			$descuentoFinal += $neto['totalDescuento'];
			$porcDescuento = $neto['porcentajeDescuento'];
			$impuesto = Impuesto::findOrFail($item['impuesto_id']);
			$valorTasaImpuesto = $impuesto->valor;
			$totalNetoLinea = round((float) $neto['totalConDescuento'], 2);
			$dataItem[$idx]['totalcondescuento'] = $totalNetoLinea;
			self::agregaItemTotales(
				($valorTasaImpuesto == 0. ? 'Exento' : 'Gravado al '.$valorTasaImpuesto.'%'),
				$valorTasaImpuesto,
				$totalNetoLinea,
				$impuesto->id,
				$impuesto->codigo,
				$impuesto->codigoarca,
				$netos
			);
		}

		if (($descuentoFinal + $porcentajeDescuentoImportePie) != 0.) {
			$detalle = $porcDescuento != 0. ? 'Descuento Gral. '.$porcDescuento.'%' : 'Descuento';
			self::agregaItemTotales($detalle, $porcDescuento, -$descuentoFinal, 0, 0, 0, $subtotales);
		}

		$impuestosArr = [];
		if ($flConIva) {
			for ($i = 0; $i < count($netos); $i++) {
				if ($netos[$i]['tasa'] != 0.) {
					$importe = round($netos[$i]['importe'] * $netos[$i]['tasa'] / 100., 2);
					$impuestosArr[] = [
						'concepto' => 'Iva '.$netos[$i]['tasa'].'%',
						'baseimponible' => $netos[$i]['importe'],
						'tasa' => $netos[$i]['tasa'],
						'importe' => $importe,
						'impuesto_id' => $netos[$i]['impuesto_id'],
						'codigo' => $netos[$i]['codigo'],
						'codigoarca' => $netos[$i]['codigoarca'],
					];
				}
			}
		}

		$conceptosTotales = array_merge($subtotales, $netos, $impuestosArr);
		$totalFinal = 0.;
		for ($i = 0; $i < count($conceptosTotales); $i++) {
			if ($conceptosTotales[$i]['concepto'] != 'Subtotal'
				&& substr((string) $conceptosTotales[$i]['concepto'], 0, 9) != 'Descuento') {
				$totalFinal += $conceptosTotales[$i]['importe'];
			}
		}

		$netoSinIva = 0.;
		for ($i = 0; $i < count($netos); $i++) {
			$netoSinIva += $netos[$i]['importe'];
		}

		$ivaTotal = 0.;
		$filasIva = [];
		foreach ($impuestosArr as $row) {
			$ivaTotal += $row['importe'];
			$filasIva[] = ['tasa' => (float) $row['tasa'], 'importe' => (float) $row['importe']];
		}

		return [
			'subtotal_bruto_sin_iva' => round($subtotalBruto, 4),
			'importe_descuento' => round($descuentoFinal, 4),
			'neto_sin_iva' => round($netoSinIva, 4),
			'iva_total' => round($ivaTotal, 4),
			'total' => round($totalFinal, 4),
			'filas_iva' => $filasIva,
		];
	}

	// Busca un valor en array

	public function buscaValor($arrayconcepto, $concepto, $key, $valor)
	{
		$valorRetorno = 0;
		
		foreach($arrayconcepto as $item)
		{
			$pos = strpos($item[$concepto], $key);

			if ($pos >= 0 && $pos !== false)
				$valorRetorno += $item[$valor];
		}
		return $valorRetorno;
	}

	// Agrega item 
	private function agregaItemTotales($concepto, $tasa, $totalItem, $impuesto_id, $codigo, $codigoarca, &$tabla)
	{
		// Acumula subtotales
		$fl_encontro = false;
		for ($i = 0; $i < count($tabla); $i++)
		{
			if ($tabla[$i]['concepto'] == $concepto)
			{
				$fl_encontro = true;
				$tabla[$i]['importe'] += $totalItem;
				break;
			}
		}
		if (!$fl_encontro)
		{
			$tabla[] = ["concepto"=>$concepto,
						"tasa"=>$tasa,
						"importe"=>$totalItem,
						"impuesto_id"=>$impuesto_id,
						"codigo"=>$codigo,
						"codigoarca"=>$codigoarca
						];
		}
	}

	/**
	 * Completa claves de ítem de facturación que pueden omitirse en el payload (API, OC, etc.).
	 *
	 * @param  array<int, array<string, mixed>>  $dataItem
	 * @return array<int, array<string, mixed>>
	 */
	private function normalizaItemsCalculoImpuesto(array $dataItem): array
	{
		foreach ($dataItem as $idx => $item) {
			$dataItem[$idx] = $this->normalizaItemCalculoImpuesto($item);
		}

		return $dataItem;
	}

	/**
	 * @param  array<string, mixed>  $item
	 * @return array<string, mixed>
	 */
	private function normalizaItemCalculoImpuesto(array $item): array
	{
		$cantidad = (float) ($item['cantidad'] ?? 0);
		$precio = (float) ($item['precio'] ?? 0);

		return array_merge($item, [
			'cantidad' => $cantidad,
			'precio' => $precio,
			'preciosindescuento' => (float) ($item['preciosindescuento'] ?? $precio),
			'kilodescuento' => (float) ($item['kilodescuento'] ?? $cantidad),
			'incluyeimpuesto' => $item['incluyeimpuesto'] ?? 'N',
			'descuentofinal' => (float) ($item['descuentofinal'] ?? 0),
		]);
	}

	public function calculaNetoItem($item, $flGrabaComprobanteDividido, $flConIva, $tasaDetraccion, $porcentajeDescuentoImportePie)
	{
		$item = $this->normalizaItemCalculoImpuesto($item);

		$importeSinDto = 0.;
		$totalNeto = $totalDescuento = $porcentajeDescuento = 0.;
		$totalDescuentoItem = 0.;

		if ($item['cantidad'] != 0)
		{
			// Lee tasa impuesto del item
			if ($flGrabaComprobanteDividido)
			{
				$valorTasaImpuesto = $this->tasaImpuesto ?? 0;

				$impuesto = true;

				$impuesto_id = null;
				$impuesto_codigo = null;
				$impuesto_codigoarca = null;
			}
			else
			{
				$impuesto = Impuesto::findOrFail($item['impuesto_id']);

				$valorTasaImpuesto = $impuesto->valor;

				$impuesto_id = $impuesto->id;
				$impuesto_codigo = $impuesto->codigo;
				$impuesto_codigoarca = $impuesto->codigoarca;
			}

			// Asume que no tiene impuesto incluido si el cliente no lleva iva
			if (!$flConIva)
			{
				$item['incluyeimpuesto'] = 'N';
				$valorTasaImpuesto = 0;
			}

			if ($impuesto)
			{
				// Calcula importe del item
				if (config('facturacion.NETEA_DESCUENTO_LINEA'))
				{
					$importeSinDto = $item['cantidad'] * 
							($item['incluyeimpuesto'] == 'N' || $item['incluyeimpuesto'] == '2' ? 
							$item['precio'] : ($item['precio'] / (1.+(($valorTasaImpuesto+$tasaDetraccion)/100))));

					$totalNeto = $importeSinDto;	
					
					$totalDescuentoItem = 0;
				}
				else
				{
					$importeSinDto = $item['cantidad'] * 
							($item['incluyeimpuesto'] == 'N' || $item['incluyeimpuesto'] == '2' ? 
							$item['preciosindescuento'] : ($item['preciosindescuento'] / (1.+(($valorTasaImpuesto+$tasaDetraccion)/100))));	
							
					$totalBruto = $importeSinDto;

					// Si es bierzo toma los kilos descontados directamente para el neto gravado
					if (config('app.empresa') == 'EL BIERZO')
						$importeConDto = $item['kilodescuento'] * 
								($item['incluyeimpuesto'] == 'N' || $item['incluyeimpuesto'] == '2' ? 
								$item['preciosindescuento'] : ($item['preciosindescuento'] / (1.+(($valorTasaImpuesto+$tasaDetraccion)/100))));					
					else
						$importeConDto = $item['cantidad'] * 
								($item['incluyeimpuesto'] == 'N' || $item['incluyeimpuesto'] == '2' ? 
								$item['precio'] : ($item['precio'] / (1.+(($valorTasaImpuesto+$tasaDetraccion)/100))));					

					// Asigna total sin descuento porque el item ya viene neteado con el descuento de linea
					$totalNeto = $importeConDto;

					$totalDescuentoItem = $totalBruto - $totalNeto;
				}

				// Agrega descuento final
				if (($item['descuentofinal']+$porcentajeDescuentoImportePie) != 0.)
				{
					$totalDescuento = ($totalNeto * ($item['descuentofinal'] +
										$porcentajeDescuentoImportePie) / 100.);

					$totalNeto *= (1. - (($item['descuentofinal']+$porcentajeDescuentoImportePie) / 100.));
					$porcentajeDescuento = ($item['descuentofinal']+$porcentajeDescuentoImportePie);
				}

				// Agrega el descuento de linea al descuento de pie
				if ($totalDescuentoItem != 0.)
					$totalDescuento += $totalDescuentoItem;
			}
		}
		return ['totalSinDescuento' => $importeSinDto, 'totalConDescuento' => $totalNeto, 'totalDescuento' => $totalDescuento, 'porcentajeDescuento' => $porcentajeDescuento];
	}
}

