<?php

namespace App\Services\Compras;

use App\Queries\Compras\ProveedorQueryInterface;
use App\ApiAnita;

class RequisicionService 
{
	private $proveedorQuery;

	public function __construct(ProveedorQueryInterface $proveedorQuery)
	{
		$this->proveedorQuery = $proveedorQuery;
	}

	public function leeRequisicionPorProveedor($busqueda, $proveedor_id)
	{
		$proveedor = $this->proveedorQuery->traeProveedorporId($proveedor_id);

		if ($proveedor)
		{
			$apiAnita = new ApiAnita();
			$leeAnita = array( 
				'acc' => 'list', 
				'sistema' => 'compras',
				'tabla' => 'reqmae, promae, emprmae', 
				'campos' => '
					reqm_nro as id,
					reqm_fecha as fecha,
					reqm_ccosto as ccorigen,
					reqm_ccosto_dest as ccdestino,
					prom_nombre as nombreproveedor,
					prom_cuit as cuit,
					reqm_empresa as empresa_id,
					empm_nombre as nombreempresa,
					reqm_es_urgente as esurgente,
					reqm_cond_pago as condicionpago,
					reqm_cod_mon as codigomoneda,
					reqm_estado as estado
				',
				'whereArmado' => " WHERE
					reqm_proveedor='".str_pad($proveedor->codigo, 6, "0", STR_PAD_LEFT)."' and
					reqm_proveedor=prom_proveedor and
					reqm_empresa=empm_empresa"
			);
			$dataAnita = json_decode($apiAnita->apiCall($leeAnita));

			if ($dataAnita)
			{
				$requisicion = $dataAnita;

				$apiAnita = new ApiAnita();
				$leeAnita = array( 
					'acc' => 'list', 
					'sistema' => 'compras',
					'tabla' => 'reqmov,stkmae', 
					'campos' => '
						reqv_nro as id,
						reqv_articulo as sku,
						reqv_cantidad as cantidad,
						reqv_precio as precio,
						stkm_tipo_articulo as tipo_articulo,
						stkm_agrupacion as codigoagrupacion,
						stkm_desc as descarticulo
					',
					'whereArmado' => " WHERE
						reqv_proveedor='".str_pad($proveedor->codigo, 6, "0", STR_PAD_LEFT)."' and
						reqv_articulo=stkm_articulo"
				);
				$dataAnita = json_decode($apiAnita->apiCall($leeAnita));

				$itemRequisicion = $dataAnita;

				return ['requisicion' => $requisicion, 'item' => $itemRequisicion];
			}
		}

		return ['Error' => 'Sin informacion'];
	}

}

