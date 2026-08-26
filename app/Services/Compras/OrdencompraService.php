<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Queries\Compras\ProveedorQueryInterface;
use App\Support\Compras\AnitaSync\Ordencompra\AnitaOcClave;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorCentrocostoDestinoSupport;
use Illuminate\Support\Facades\Log;

class OrdencompraService 
{
	private $proveedorQuery;

	public function __construct(ProveedorQueryInterface $proveedorQuery)
	{
		$this->proveedorQuery = $proveedorQuery;
	}

	public function leeOrdenCompra($numeroOrdenCompra)
	{
		$numeroOc = $this->resolverNumeroOrdenCompra($numeroOrdenCompra);
		if ($numeroOc <= 0) {
			return 'OC inexistente';
		}

		$cabecera = $this->consultarPendmaepPorNumero($numeroOc);
		if ($cabecera === null) {
			return 'OC inexistente';
		}

		$claveOc = AnitaOcClave::desdePendmaep($cabecera);
		$ordenCompra = $this->enriquecerCabeceraConPromae($cabecera);
		$itemOrdenCompra = $this->consultarItemsOrdenCompra($claveOc);
		$ordenCompra = PrecargaProveedorCentrocostoDestinoSupport::aplicarDestinoEnCabecera(
			$ordenCompra,
			$itemOrdenCompra
		);

		return ['ordencompra' => $ordenCompra, 'item' => $itemOrdenCompra];
	}

	/**
	 * Busca pendmaep por penmp_nro (clave canónica en Anita), con reintento ante respuesta vacía del bridge.
	 */
	private function consultarPendmaepPorNumero(int $numeroOc): ?object
	{
		$payload = [
			'acc' => 'list',
			'sistema' => 'compras',
			'tabla' => 'pendmaep',
			'campos' => implode(', ', [
				'penmp_proveedor',
				'penmp_ccosto',
				'penmp_ccosto_dest',
				'penmp_tipo',
				'penmp_letra',
				'penmp_sucursal',
				'penmp_nro',
			]),
			'whereArmado' => " WHERE penmp_nro={$numeroOc}",
		];

		$filas = $this->listAnitaConReintento($payload, 'ordencompra.pendmaep', $numeroOc);

		return $filas === [] ? null : $filas[0];
	}

	private function enriquecerCabeceraConPromae(object $cabecera): object
	{
		$codigoProveedor = trim((string) ($cabecera->penmp_proveedor ?? ''));
		if ($codigoProveedor === '') {
			$cabecera->prom_cuit = '';
			$cabecera->prom_letra = '';

			return $cabecera;
		}

		$payload = [
			'acc' => 'list',
			'sistema' => 'compras',
			'tabla' => 'promae',
			'campos' => 'prom_cuit, prom_letra',
			'whereArmado' => " WHERE prom_proveedor='".addslashes($codigoProveedor)."'",
		];

		$filas = $this->listAnitaConReintento($payload, 'ordencompra.promae', (int) ($cabecera->penmp_nro ?? 0));
		if ($filas !== []) {
			$cabecera->prom_cuit = $filas[0]->prom_cuit ?? '';
			$cabecera->prom_letra = $filas[0]->prom_letra ?? '';
		} else {
			$cabecera->prom_cuit = '';
			$cabecera->prom_letra = '';
		}

		return $cabecera;
	}

	/**
	 * @return list<object>
	 */
	private function consultarItemsOrdenCompra(AnitaOcClave $claveOc): array
	{
		$payload = [
			'acc' => 'list',
			'sistema' => 'compras',
			'tabla' => 'pendmovp,stkmae',
			'campos' => '
				penvp_articulo,
				penvp_cantidad,
				penvp_ccosto,
				stkm_tipo_articulo,
				stkm_agrupacion
			',
			'whereArmado' => $claveOc->wherePendmovp().' AND penvp_articulo=stkm_articulo',
		];

		return $this->listAnitaConReintento($payload, 'ordencompra.pendmovp', $claveOc->nro);
	}

	/**
	 * @return list<object>
	 */
	private function listAnitaConReintento(array $payload, string $contexto, int $numeroOc): array
	{
		$maxIntentos = max(1, (int) config('precarga_comprobante.anita_list_reintentos', 3));
		$esperaMs = max(0, (int) config('precarga_comprobante.anita_list_espera_ms', 250));
		$apiAnita = new ApiAnita();
		$ultimoError = null;

		for ($intento = 1; $intento <= $maxIntentos; $intento++) {
			$raw = (string) $apiAnita->apiCall($payload);
			$parsed = ApiAnita::parsearRespuestaLista($raw);
			$ultimoError = $parsed['error_lectura'];

			if ($ultimoError !== null) {
				$this->logConsultaAnita('warning', $contexto, $numeroOc, $intento, $maxIntentos, $ultimoError);
			} elseif ($parsed['filas'] !== []) {
				return $parsed['filas'];
			} else {
				$this->logConsultaAnita('warning', $contexto, $numeroOc, $intento, $maxIntentos, 'respuesta vacía del bridge Anita');
			}

			if ($intento < $maxIntentos && $esperaMs > 0) {
				usleep($esperaMs * 1000);
			}
		}

		return [];
	}

	private function logConsultaAnita(
		string $nivel,
		string $contexto,
		int $numeroOc,
		int $intento,
		int $maxIntentos,
		string $detalle,
	): void {
		$canal = (string) config('precarga_comprobante.log_channel', 'precarga_proveedor_api');
		Log::channel($canal)->{$nivel}('ordencompra.anita_consulta', [
			'contexto' => $contexto,
			'numero_oc' => $numeroOc,
			'intento' => $intento,
			'max_intentos' => $maxIntentos,
			'detalle' => $detalle,
		]);
	}

	/**
	 * Acepta número simple (219635), con ceros (00219635), sucursal-número (0000-00219635)
	 * u otros textos con dígitos embebidos (OC 219635).
	 */
	private function resolverNumeroOrdenCompra($numeroOrdenCompra): int
	{
		$numeroOrdenCompra = trim((string) $numeroOrdenCompra);
		if ($numeroOrdenCompra === '') {
			return 0;
		}

		if (preg_match('/^(\d+)-(\d+)$/', $numeroOrdenCompra, $matches)) {
			return (int) $matches[2];
		}

		if (preg_match('/^\d+$/', $numeroOrdenCompra)) {
			return (int) $numeroOrdenCompra;
		}

		$digits = preg_replace('/\D/', '', $numeroOrdenCompra) ?? '';

		return $digits === '' ? 0 : (int) $digits;
	}

	public function leeOrdenCompraPorCodigo($codigocapex)
	{
		$apiAnita = new ApiAnita();
        $leeAnita = array( 
            'acc' => 'list', 
			'sistema' => 'compras',
			'tabla' => 'movpresup,pendmaep,promae,stkmae', 
            'campos' => '
				movp_fecha as fechaordencompra,
				movp_tipo,
				movp_nro,
				prom_nombre as nombreproveedor,
				penmp_cod_mon as moneda_id,
				movp_cotizacion as cotizacion,
				movp_importe as total,
				movp_mes as mes,
				movp_articulo as articulo,
				stkm_desc  
			',
			'whereArmado' => " WHERE
				movp_proyecto=".$codigocapex." and
				movp_tipo=penmp_tipo and
 				movp_nro=penmp_nro and
				penmp_proveedor=prom_proveedor and
				movp_articulo=stkm_articulo"
        );
        $dataAnita = json_decode($apiAnita->apiCall($leeAnita));

		return $dataAnita;
	}

	public function leeOrdencompraPorProveedor($busqueda, $proveedor_id)
	{
		$proveedor = $this->proveedorQuery->traeProveedorporId($proveedor_id);

		if ($proveedor)
		{
			$apiAnita = new ApiAnita();
			$leeAnita = array( 
				'acc' => 'list', 
				'sistema' => 'compras',
				'tabla' => 'pendmaep, promae, emprmae', 
				'campos' => '
					penmp_nro as id,
					penmp_fecha as fecha,
					penmp_fecha_ent as fechaentrega,
					penmp_ccosto as ccorigen,
					penmp_ccosto_dest as ccdestino,
					prom_nombre as nombreproveedor,
					prom_cuit as cuit,
					penmp_empresa as empresa_id,
					empm_nombre as nombreempresa,
					penmp_cond_pago as condicionpago,
					penmp_cod_mon as codigomoneda,
					penmp_cotizacion as cotizacion,
					penmp_requisicion as requisicion,
					penmp_es_anticipo as esanticipo,
					penmp_estado as estado
				',
				'whereArmado' => " WHERE
					penmp_proveedor='".str_pad($proveedor->codigo, 6, "0", STR_PAD_LEFT)."' and
					penmp_proveedor=prom_proveedor and
					penmp_empresa=empm_empresa"
			);
			$dataAnita = json_decode($apiAnita->apiCall($leeAnita));

			if ($dataAnita)
			{
				$ordencompra = $dataAnita;

				$apiAnita = new ApiAnita();
				$leeAnita = array( 
					'acc' => 'list', 
					'sistema' => 'compras',
					'tabla' => 'pendmovp,stkmae', 
					'campos' => '
						penvp_nro as id,
						penvp_articulo as sku,
						penvp_cantidad as cantidad,
						penvp_precio as precio,
						stkm_tipo_articulo as tipo_articulo,
						stkm_agrupacion as codigoagrupacion,
						stkm_desc as descarticulo
					',
					'whereArmado' => " WHERE
						penvp_proveedor='".str_pad($proveedor->codigo, 6, "0", STR_PAD_LEFT)."' and
						penvp_articulo=stkm_articulo"
				);
				$dataAnita = json_decode($apiAnita->apiCall($leeAnita));

				$itemOrdencompra = $dataAnita;

				return ['ordencompra' => $ordencompra, 'item' => $itemOrdencompra];
			}
		}

		return ['Error' => 'Sin informacion'];
	}	
}

