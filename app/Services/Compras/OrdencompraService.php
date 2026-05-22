<?php

namespace App\Services\Compras;

use App\Queries\Compras\ProveedorQueryInterface;
use App\ApiAnita;

class OrdencompraService 
{
	private $proveedorQuery;

	public function __construct(ProveedorQueryInterface $proveedorQuery)
	{
		$this->proveedorQuery = $proveedorQuery;
	}

	public function leeOrdenCompra($numeroOrdenCompra)
	{
		$apiAnita = new ApiAnita();
        $leeAnita = array( 
            'acc' => 'list', 
			'sistema' => 'compras',
			'tabla' => 'pendmaep, promae', 
            'campos' => '
				penmp_proveedor,
				penmp_ccosto_dest,
				prom_cuit,
				prom_letra,
				penmp_tipo
			',
			'whereArmado' => " WHERE
				penmp_tipo='PEP' and
				penmp_letra='X' and
				penmp_sucursal=0 and
				penmp_nro=".$numeroOrdenCompra." and
				penmp_proveedor=prom_proveedor"
        );
        $raw = (string) $apiAnita->apiCallEscritura($leeAnita);
        $filas = ApiAnita::decodificarListaFilas($raw);

		if (count($filas) > 0) {
			$ordenCompra = $filas[0];
		} else {
			return 'OC inexistente';
		}

		$apiAnita = new ApiAnita();
        $leeAnita = array( 
            'acc' => 'list', 
			'sistema' => 'compras',
			'tabla' => 'pendmovp,stkmae', 
            'campos' => '
				penvp_articulo,
				penvp_cantidad,
				stkm_tipo_articulo,
				stkm_agrupacion
			',
			'whereArmado' => " WHERE
				penvp_tipo='PEP' and
				penvp_letra='X' and
				penvp_sucursal=0 and
				penvp_nro=".$numeroOrdenCompra." and
				penvp_articulo=stkm_articulo"
        );
        $itemOrdenCompra = ApiAnita::decodificarListaFilas((string) $apiAnita->apiCall($leeAnita));

		return ['ordencompra' => $ordenCompra, 'item' => $itemOrdenCompra];
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

