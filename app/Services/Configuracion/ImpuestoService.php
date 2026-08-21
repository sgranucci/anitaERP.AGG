<?php
namespace App\Services\Configuracion;

use App\Models\Stock\Articulo;
use App\Models\Stock\Formula_Articulo;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Precio;
use App\Models\Stock\Tipoarticulo;
use App\Models\Configuracion\Impuesto;
use App\Services\Configuracion\IIBBService;
use App\Repositories\Configuracion\CondicionivaRepositoryInterface;
use App\Repositories\Ventas\Cliente_Cm05RepositoryInterface;
use App\Repositories\Ventas\AbastoRepositoryInterface;
use App\Services\Ventas\FacturacionService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\ExclusionPercepcionIvaSupport;
use App\Support\Stock\FormulaArticuloFactorCosto;
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

	/** Cache local del id del tipoarticulo configurado para disparar impuesto interno (p. ej. CIGARRILLO). */
	private ?int $tipoarticuloImpuestoInternoIdCache = null;
	private bool $tipoarticuloImpuestoInternoIdResolved = false;

	/** Cache local de listaprecio_id por empresa (clave = empresa_id, valor = listaprecio_id o null). */
	private array $listaprecioImpuestoInternoPorEmpresa = [];

	/** Cache local de coeficientes por (articulo_id, listaprecio_id, fecha). */
	private array $coeficienteImpuestoInternoCache = [];

	/** Cache local de fórmulas resueltas (id => Formula_Articulo con hijos). */
	private array $formulaImpuestoInternoCache = [];

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

		// Abasto / logística: solo El Bierzo (Anita a-comprob). AGG y resto no entran.
		$tasaAbasto = 0;
		$porcentajeLogistica = 0;
		if (EntornoEmpresaSupport::esElBierzo()) {
			if (isset($dataCliente['abasto_id'])) {
				$abasto = $this->abastoRepository->findPorId($dataCliente['abasto_id']);
				if ($abasto) {
					$tasaAbasto = $abasto->tasa;
				}
			}
			if (isset($dataCliente['porcentajelogistica'])) {
				$porcentajeLogistica = $dataCliente['porcentajelogistica'];
			}
		}

		// Lee el CM05 del cliente
		$cm05 = $this->cliente_cm05Repository->findPorClienteId($cliente_id);

		// Calcula netos por tasa
		$netos = [];
		$subtotales = [];
		$porcentajeDescuentoImportePie = 0;
		$totalImpuestoInterno = 0.; // Acumula impuesto interno por renglón.

		// Bruto con IVA incluido acumulado por tasa (items donde el precio ya trae el IVA dentro,
		// es decir incluyeimpuesto distinto de 'N' y '2'). Se usa para "cerrar" el IVA con la
		// fórmula bruto - bruto/(1+tasa/100), evitando el centavo de redondeo que aparece cuando
		// se hace round(neto_redondeado * tasa/100, 2) sobre el neto ya redondeado hacia arriba.
		$brutosIncluyenIvaPorTasa = [];
		$tasasMixtas = [];

		// Debe calcular el total de los items y sacar el descuento en porcentaje
		$totalBrutoAuxiliar = 0;
		$tasaDetraccion = 0.;
		
		foreach($dataItem as $item)
			$totalBrutoAuxiliar += ($item['cantidad'] * $item['precio']);

		$tasaPercepcionIva = (float) config('anita.tasa_percepcion_iva', 0);
		$aplicaPercepcionIva = ! $omitirPercepciones
			&& config('anita.agente_percepcion_iva') == 'si'
			&& $retieneIva != 'S'
			&& ! ExclusionPercepcionIvaSupport::estaExcluidoEnFecha($nroInscripcion, $fechaFactura ?? null);

		// Calcula las tasas de percepcion para agregar a la tasa de detraccion
        if ($aplicaPercepcionIva && config('facturacion.USA_DETRACCION') == 'S')
			$tasaDetraccion += $tasaPercepcionIva;

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

					// Acumula impuesto interno del renglón en el total del comprobante.
					if (! empty($neto['impuestoInternoMonto'])) {
						$totalImpuestoInterno += (float) $neto['impuestoInternoMonto'];
						$dataItem[$off-1]['impuesto_interno_monto'] = (float) $neto['impuestoInternoMonto'];
					}

					// Acumula netos por tasa de impuesto
					self::agregaItemTotales(($valorTasaImpuesto == 0. ? "Exento" : "Gravado al ".$valorTasaImpuesto."%"), $valorTasaImpuesto, 
						$totalNeto, $impuesto_id, $impuesto_codigo, $impuesto_codigoarca, $netos);

					// Acumula bruto con IVA incluido por tasa, para cerrar el IVA sin arrastrar redondeos.
					// "Incluye IVA" sigue la misma convención que el resto del servicio: cualquier valor
					// distinto de 'N' o '2' significa que el precio del item ya trae el IVA dentro.
					if ($flConIva && $valorTasaImpuesto > 0)
					{
						$tasaKey = (string) $valorTasaImpuesto;
						$incluyeIva = isset($item['incluyeimpuesto']) && $item['incluyeimpuesto'] != 'N' && $item['incluyeimpuesto'] != '2';
						if ($incluyeIva)
						{
							$brutoConIvaItem = round((float) $neto['totalConDescuento'] * (1. + $valorTasaImpuesto / 100.), 2);
							$brutosIncluyenIvaPorTasa[$tasaKey] = ($brutosIncluyenIvaPorTasa[$tasaKey] ?? 0.) + $brutoConIvaItem;
						}
						else
						{
							// Si hay items SIN IVA incluido en la misma tasa, no aplicamos el cierre por bruto
							// para no romper casos legacy (mezclas raras).
							$tasasMixtas[$tasaKey] = true;
						}
					}
				}
			}
		}

		// Logística El Bierzo (a-comprob): IVA con la alícuota del impuesto (21 %), no con el
		// % de logística. El ítem MTXCA "Logistica" se agrega al armar el CAE (no acá: dataItem
		// alimenta stock / venta_emision).
		if (EntornoEmpresaSupport::esElBierzo() && $porcentajeLogistica && ! $flGrabaComprobanteDividido) {
			$totalLogistica = round($totalNeto * $porcentajeLogistica / 100., 2);

			$impuesto = Impuesto::findOrFail(config('facturacion.IMPUESTO_LOGISTICA_ID'));

			if ($impuesto) {
				$descuentoPieLinea = 0.0;
				foreach ($dataItem as $lineaFactura) {
					if ((float) ($lineaFactura['cantidad'] ?? 0) != 0.0) {
						$descuentoPieLinea = (float) ($lineaFactura['descuentofinal'] ?? 0);
						break;
					}
				}

				if (($descuentoPieLinea + $porcentajeDescuentoImportePie) != 0.) {
					$descuentoFinal += ($totalLogistica * ($descuentoPieLinea +
										$porcentajeDescuentoImportePie) / 100.);

					$totalLogistica *= (1. - (($descuentoPieLinea + $porcentajeDescuentoImportePie) / 100.));
					$porcDescuento = ($descuentoPieLinea + $porcentajeDescuentoImportePie);
				}

				$totalLogistica = round($totalLogistica, 2);

				self::agregaItemTotales(
					'Total Logistica',
					(float) $impuesto->valor,
					$totalLogistica,
					$impuesto->id,
					$impuesto->codigo,
					$impuesto->codigoarca,
					$netos,
				);
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
					$tasaKey = (string) $netos[$i]['tasa'];

					// Si todos los items de esta tasa traían el IVA dentro del precio (incluyeimpuesto
					// distinto de 'N' y '2'), cerramos el IVA desde el bruto acumulado para que
					// neto + IVA = bruto exacto (sin el centavo arrastrado por el round del neto).
					if (isset($brutosIncluyenIvaPorTasa[$tasaKey]) && empty($tasasMixtas[$tasaKey]))
					{
						$brutoTasa = round((float) $brutosIncluyenIvaPorTasa[$tasaKey], 2);
						$importe = round($brutoTasa - $brutoTasa / (1. + $netos[$i]['tasa'] / 100.), 2);
						// Ajusta el "Gravado al X%" para que cierre: neto = bruto - iva.
						$netos[$i]['importe'] = round($brutoTasa - $importe, 2);
					}
					else
					{
						$importe = round($netos[$i]['importe'] * $netos[$i]['tasa'] / 100., 2);
					}

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

		// Agrega percepcion de iva si es agente de percepcion, el cliente no lo es y no está excluido en el padrón AFIP
        if ($aplicaPercepcionIva && !$flGrabaComprobanteDividido)
		{
			$importeNeto = $importePercepcion = 0.;
			for ($i = 0; $i < count($netos); $i++)
			{
				if($tasaPercepcionIva != 0.)
				{
					if($netos[$i]['tasa'] != 0.) // Solo trae los importes gravados
					{
						$importeNeto += $netos[$i]['importe'];
						$importePercepcion += round($netos[$i]['importe'] * $tasaPercepcionIva / 100., 2);
					}
				}
			}			
			$detalle = "Percepcion IVA ".$tasaPercepcionIva."%";
			$impuestos[] = ["concepto"=>$detalle,
						"baseimponible" => $importeNeto,
						"tasa"=>$tasaPercepcionIva,
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

		// Abasto El Bierzo (a-comprob: kilos × tasa; MTXCA tributo 99).
		if (EntornoEmpresaSupport::esElBierzo() && ! $flGrabaComprobanteDividido && $tasaAbasto > 0) {
			$totalAbasto = $totalCantidad * $tasaAbasto;

			$impuestos[] = [
				'concepto' => 'Total Abasto',
				'baseimponible' => 0,
				'tasa' => $tasaAbasto,
				'importe' => $totalAbasto,
			];
		}
		// Agrega impuesto interno como concepto del comprobante (sumatoria por renglón).
		$conceptosImpuestoInterno = [];
		if ($totalImpuestoInterno != 0.) {
			$totalImpuestoInternoRound = round($totalImpuestoInterno, 2);
			$conceptosImpuestoInterno[] = [
				"concepto" => "Impuesto Interno",
				"baseimponible" => 0,
				"tasa" => 0,
				"importe" => $totalImpuestoInternoRound,
			];
		}

		$conceptosTotales = array_merge($subtotales, $netos, $impuestos, $conceptosImpuestoInterno, $percepcionesIIBB);

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
		$totalImpuestoInterno = 0.;
		// Ver comentario en calculaImpuestoVenta: cierre del IVA por bruto cuando el item trae el IVA dentro.
		$brutosIncluyenIvaPorTasa = [];
		$tasasMixtas = [];

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
			if (! empty($neto['impuestoInternoMonto'])) {
				$totalImpuestoInterno += (float) $neto['impuestoInternoMonto'];
				$dataItem[$idx]['impuesto_interno_monto'] = (float) $neto['impuestoInternoMonto'];
			}
			self::agregaItemTotales(
				($valorTasaImpuesto == 0. ? 'Exento' : 'Gravado al '.$valorTasaImpuesto.'%'),
				$valorTasaImpuesto,
				$totalNetoLinea,
				$impuesto->id,
				$impuesto->codigo,
				$impuesto->codigoarca,
				$netos
			);

			if ($flConIva && $valorTasaImpuesto > 0) {
				$tasaKey = (string) $valorTasaImpuesto;
				$incluyeIva = isset($item['incluyeimpuesto']) && $item['incluyeimpuesto'] != 'N' && $item['incluyeimpuesto'] != '2';
				if ($incluyeIva) {
					$brutoConIvaItem = round((float) $neto['totalConDescuento'] * (1. + $valorTasaImpuesto / 100.), 2);
					$brutosIncluyenIvaPorTasa[$tasaKey] = ($brutosIncluyenIvaPorTasa[$tasaKey] ?? 0.) + $brutoConIvaItem;
				} else {
					$tasasMixtas[$tasaKey] = true;
				}
			}
		}

		if (($descuentoFinal + $porcentajeDescuentoImportePie) != 0.) {
			$detalle = $porcDescuento != 0. ? 'Descuento Gral. '.$porcDescuento.'%' : 'Descuento';
			self::agregaItemTotales($detalle, $porcDescuento, -$descuentoFinal, 0, 0, 0, $subtotales);
		}

		$impuestosArr = [];
		if ($flConIva) {
			for ($i = 0; $i < count($netos); $i++) {
				if ($netos[$i]['tasa'] != 0.) {
					$tasaKey = (string) $netos[$i]['tasa'];
					if (isset($brutosIncluyenIvaPorTasa[$tasaKey]) && empty($tasasMixtas[$tasaKey])) {
						$brutoTasa = round((float) $brutosIncluyenIvaPorTasa[$tasaKey], 2);
						$importe = round($brutoTasa - $brutoTasa / (1. + $netos[$i]['tasa'] / 100.), 2);
						$netos[$i]['importe'] = round($brutoTasa - $importe, 2);
					} else {
						$importe = round($netos[$i]['importe'] * $netos[$i]['tasa'] / 100., 2);
					}
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

		$conceptosImpuestoInterno = [];
		if ($totalImpuestoInterno != 0.) {
			$conceptosImpuestoInterno[] = [
				'concepto' => 'Impuesto Interno',
				'baseimponible' => 0,
				'tasa' => 0,
				'importe' => round($totalImpuestoInterno, 2),
			];
		}

		$conceptosTotales = array_merge($subtotales, $netos, $impuestosArr, $conceptosImpuestoInterno);
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
			'impuesto_interno_total' => round($totalImpuestoInterno, 4),
			'total' => round($totalFinal, 4),
			'filas_iva' => $filasIva,
		];
	}

	// Busca un valor en array

	public function buscaValor($arrayconcepto, $concepto, $key, $valor)
	{
		$valorRetorno = 0;
		// "Total" / "Subtotal" exactos: strpos("Total Abasto", "Total") inflaba el
		// importeTotal de MTXCA/WSFE (Bierzo) y ARCA rechazaba 115/116.
		$exacto = $key === 'Total' || $key === 'Subtotal';

		foreach ($arrayconcepto as $item) {
			$nombre = (string) ($item[$concepto] ?? '');
			if ($exacto) {
				if ($nombre === $key) {
					$valorRetorno += $item[$valor];
				}

				continue;
			}

			if (strpos($nombre, $key) !== false) {
				$valorRetorno += $item[$valor];
			}
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

		// Coeficiente de impuesto interno por unidad del item (0..1). El monto del impuesto
		// interno se descuenta del precio bruto del renglón antes de calcular neto e IVA, para
		// que IVA + neto + impuesto interno = bruto consumidor.
		$coefImpuestoInterno = (float) ($item['impuesto_interno_coeficiente'] ?? 0);
		if ($coefImpuestoInterno < 0) {
			$coefImpuestoInterno = 0;
		}
		$factorImpuestoInterno = max(0., 1. - $coefImpuestoInterno);

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
							$item['precio'] * $factorImpuestoInterno : ($item['precio'] * $factorImpuestoInterno / (1.+(($valorTasaImpuesto+$tasaDetraccion)/100))));

					$totalNeto = $importeSinDto;

					$totalDescuentoItem = 0;
				}
				else
				{
					$importeSinDto = $item['cantidad'] *
							($item['incluyeimpuesto'] == 'N' || $item['incluyeimpuesto'] == '2' ?
							$item['preciosindescuento'] * $factorImpuestoInterno : ($item['preciosindescuento'] * $factorImpuestoInterno / (1.+(($valorTasaImpuesto+$tasaDetraccion)/100))));

					$totalBruto = $importeSinDto;

					// Si es bierzo toma los kilos descontados directamente para el neto gravado
					if (config('app.empresa') == 'EL BIERZO')
						$importeConDto = $item['kilodescuento'] *
								($item['incluyeimpuesto'] == 'N' || $item['incluyeimpuesto'] == '2' ?
								$item['preciosindescuento'] * $factorImpuestoInterno : ($item['preciosindescuento'] * $factorImpuestoInterno / (1.+(($valorTasaImpuesto+$tasaDetraccion)/100))));
					else
						$importeConDto = $item['cantidad'] *
								($item['incluyeimpuesto'] == 'N' || $item['incluyeimpuesto'] == '2' ?
								$item['precio'] * $factorImpuestoInterno : ($item['precio'] * $factorImpuestoInterno / (1.+(($valorTasaImpuesto+$tasaDetraccion)/100))));

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
		// Monto de impuesto interno del renglón (cantidad * precio bruto unitario * coef).
		// Se calcula sobre el precio con IVA incluido cuando aplica, según la convención del item.
		$montoImpuestoInternoItem = 0.;
		if ($coefImpuestoInterno > 0 && $item['cantidad'] != 0) {
			$precioBrutoUnitario = (float) $item['precio'];
			$montoImpuestoInternoItem = round(((float) $item['cantidad']) * $precioBrutoUnitario * $coefImpuestoInterno, 2);
		}

		return [
			'totalSinDescuento' => $importeSinDto,
			'totalConDescuento' => $totalNeto,
			'totalDescuento' => $totalDescuento,
			'porcentajeDescuento' => $porcentajeDescuento,
			'impuestoInternoCoeficiente' => $coefImpuestoInterno,
			'impuestoInternoMonto' => $montoImpuestoInternoItem,
		];
	}

	/**
	 * Devuelve el listaprecio_id (interno del ERP) donde se cargan los coeficientes de impuesto
	 * interno para la empresa, o null si no está configurado. El mapa va por código de lista
	 * (facturacion.IMPUESTO_INTERNO_LISTAPRECIO_POR_EMPRESA: empresa_id => código de lista).
	 */
	public function listaprecioImpuestoInternoPorEmpresa(int $empresaId): ?int
	{
		if (array_key_exists($empresaId, $this->listaprecioImpuestoInternoPorEmpresa)) {
			return $this->listaprecioImpuestoInternoPorEmpresa[$empresaId];
		}

		$mapa = (array) config('facturacion.IMPUESTO_INTERNO_LISTAPRECIO_POR_EMPRESA', []);
		$codigo = $mapa[$empresaId] ?? null;
		if ($codigo === null || $codigo === '') {
			return $this->listaprecioImpuestoInternoPorEmpresa[$empresaId] = null;
		}

		$listaprecio = Listaprecio::query()->where('codigo', (string) $codigo)->first(['id']);
		if (! $listaprecio) {
			return $this->listaprecioImpuestoInternoPorEmpresa[$empresaId] = null;
		}

		return $this->listaprecioImpuestoInternoPorEmpresa[$empresaId] = (int) $listaprecio->id;
	}

	/**
	 * Coeficiente vigente para un artículo en la lista de impuesto interno indicada (fecha <= fechaFactura).
	 * Devuelve 0 si no hay precio cargado.
	 */
	public function coeficienteImpuestoInterno(int $articuloId, int $listaprecioId, string $fechaFactura): float
	{
		if ($articuloId <= 0 || $listaprecioId <= 0) {
			return 0.;
		}

		$cacheKey = $articuloId.'|'.$listaprecioId.'|'.$fechaFactura;
		if (array_key_exists($cacheKey, $this->coeficienteImpuestoInternoCache)) {
			return $this->coeficienteImpuestoInternoCache[$cacheKey];
		}

		try {
			$fecha = Carbon::parse($fechaFactura)->toDateString();
		} catch (\Throwable) {
			$fecha = $fechaFactura;
		}

		$precio = Precio::query()
			->where('articulo_id', $articuloId)
			->where('listaprecio_id', $listaprecioId)
			->where('fechavigencia', '<=', $fecha)
			->orderBy('fechavigencia', 'desc')
			->orderBy('id', 'desc')
			->value('precio');

		return $this->coeficienteImpuestoInternoCache[$cacheKey] = (float) ($precio ?? 0);
	}

	/**
	 * Id del tipoarticulo configurado para impuesto interno (default nombre = CIGARRILLO).
	 */
	private function tipoarticuloImpuestoInternoId(): ?int
	{
		if ($this->tipoarticuloImpuestoInternoIdResolved) {
			return $this->tipoarticuloImpuestoInternoIdCache;
		}

		$this->tipoarticuloImpuestoInternoIdResolved = true;

		return $this->tipoarticuloImpuestoInternoIdCache = Tipoarticulo::idControlContableCigarrillos();
	}

	/**
	 * Calcula el coeficiente efectivo de impuesto interno aplicable al precio bruto del ítem
	 * vendido. Expande la fórmula del artículo (si existe), respetando los opcionales elegidos
	 * y los insumos no opcionales. Por cada insumo cuyo `tipoarticulo` coincide con el
	 * configurado (default: CIGARRILLO) suma `cantidadEfectivaPorUnidadItem * coeficienteInsumo`.
	 * Si el propio artículo es de ese tipoarticulo y no se llegó a expandir nada, usa su
	 * propio coeficiente.
	 *
	 * @param  array<string|int, int|null>  $opcionalesSeleccion  (orden => articulo_id)
	 */
	public function coeficienteImpuestoInternoArticulo(
		int $articuloId,
		array $opcionalesSeleccion,
		int $empresaId,
		string $fechaFactura,
	): float {
		if ($articuloId <= 0) {
			return 0.;
		}

		$listaprecioId = $this->listaprecioImpuestoInternoPorEmpresa($empresaId);
		if ($listaprecioId === null) {
			return 0.;
		}

		$tipoCigarrillo = $this->tipoarticuloImpuestoInternoId();
		if ($tipoCigarrillo === null) {
			return 0.;
		}

		$articulo = Articulo::query()->find($articuloId, ['id', 'formula', 'tipoarticulo_id']);
		if (! $articulo) {
			return 0.;
		}

		$total = 0.;

		// Expande fórmula (no-opcionales + opcionales elegidos) y suma coeficientes de cigarrillos.
		if ($articulo->formula) {
			$opcMap = [];
			foreach ($opcionalesSeleccion as $orden => $aid) {
				$opcMap[(string) $orden] = $aid !== null && $aid !== '' ? (int) $aid : null;
			}

			$insumos = [];
			$this->expandirFormulaImpuestoInterno((int) $articulo->formula, 1., $opcMap, $insumos, 0);

			foreach ($insumos as $insumoArticuloId => $cantidadEfectiva) {
				$insumoArticulo = Articulo::query()->find($insumoArticuloId, ['id', 'tipoarticulo_id']);
				if (! $insumoArticulo || (int) $insumoArticulo->tipoarticulo_id !== $tipoCigarrillo) {
					continue;
				}
				$coefInsumo = $this->coeficienteImpuestoInterno((int) $insumoArticulo->id, $listaprecioId, $fechaFactura);
				if ($coefInsumo <= 0) {
					continue;
				}
				$total += $cantidadEfectiva * $coefInsumo;
			}
		}

		// Si el propio artículo es del tipo y no aportó nada vía fórmula, usar su coeficiente.
		if ($total <= 0 && (int) $articulo->tipoarticulo_id === $tipoCigarrillo) {
			$total += $this->coeficienteImpuestoInterno((int) $articulo->id, $listaprecioId, $fechaFactura);
		}

		return $total > 0 ? $total : 0.;
	}

	/**
	 * Expansión similar a GastronomiaFormulaConsumoService::expandFormula pero pensada solo
	 * para totalizar coeficiente de impuesto interno: respeta opcionales por orden y no
	 * persiste movimientos.
	 *
	 * @param  array<string, int|null>  $opcionalesPorOrden
	 * @param  array<int, float>  $aggregados  articulo_id => cantidadEfectivaPorUnidadItem
	 */
	private function expandirFormulaImpuestoInterno(
		int $formulaArticuloId,
		float $multiplier,
		array $opcionalesPorOrden,
		array &$aggregados,
		int $depth,
	): void {
		if ($depth > 25) {
			return; // Protege contra ciclos (consistente con GastronomiaFormulaConsumoService).
		}

		if (! array_key_exists($formulaArticuloId, $this->formulaImpuestoInternoCache)) {
			$this->formulaImpuestoInternoCache[$formulaArticuloId] = Formula_Articulo::query()
				->with(['formula_articulo_hijos' => fn ($q) => $q->orderBy('ordenopcional')->orderBy('id')])
				->find($formulaArticuloId);
		}

		$formula = $this->formulaImpuestoInternoCache[$formulaArticuloId];
		if (! $formula) {
			return;
		}

		$hijos = $formula->formula_articulo_hijos;

		// Insumos no opcionales (siempre presentes).
		foreach ($hijos->where('esopcional', false) as $hijo) {
			$this->procesarHijoImpuestoInterno($hijo, $multiplier, $opcionalesPorOrden, $aggregados, $depth);
		}

		// Opcionales: solo el elegido por cada orden contribuye.
		$opcionales = $hijos->where('esopcional', true)->groupBy(fn ($h) => (string) ($h->ordenopcional ?? '0'));
		foreach ($opcionales as $orden => $grupo) {
			$chosen = $opcionalesPorOrden[(string) $orden] ?? null;
			$decoded = \App\Support\Ventas\GastronomiaFormulaOpcionalSeleccion::decodificar($chosen);
			if ($decoded === null) {
				continue;
			}
			$match = $grupo->first(
				fn ($h) => \App\Support\Ventas\GastronomiaFormulaOpcionalSeleccion::coincideConHijo($h, $decoded)
			);
			if (! $match) {
				continue;
			}
			$this->procesarHijoImpuestoInterno($match, $multiplier, $opcionalesPorOrden, $aggregados, $depth);
		}
	}

	/**
	 * @param  array<string, int|null>  $opcionalesPorOrden
	 * @param  array<int, float>  $aggregados
	 */
	private function procesarHijoImpuestoInterno(
		$hijo,
		float $multiplier,
		array $opcionalesPorOrden,
		array &$aggregados,
		int $depth,
	): void {
		$factorLinea = (float) $hijo->cantidad * FormulaArticuloFactorCosto::efectivo($hijo->factorcosto);
		$mult = $multiplier * $factorLinea;

		if ($hijo->formula_hija_id) {
			$this->expandirFormulaImpuestoInterno((int) $hijo->formula_hija_id, $mult, $opcionalesPorOrden, $aggregados, $depth + 1);

			return;
		}

		if ($hijo->articulo_id) {
			$aid = (int) $hijo->articulo_id;
			$aggregados[$aid] = ($aggregados[$aid] ?? 0) + $mult;
		}
	}
}

