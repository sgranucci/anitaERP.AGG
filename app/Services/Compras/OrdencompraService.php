<?php

namespace App\Services\Compras;

use App\ApiAnita;

class OrdencompraService 
{
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
				penmp_tipo
			',
			'whereArmado' => " WHERE
				penmp_tipo='PEP' and
				penmp_letra='X' and
				penmp_sucursal=0 and
				penmp_nro=".$numeroOrdenCompra." and
				penmp_proveedor=prom_proveedor"
        );
        $dataAnita = json_decode($apiAnita->apiCall($leeAnita));

		if (count($dataAnita) > 0)
			$ordenCompra = $dataAnita[0];
		else
			return 'OC inexistente';

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
        $dataAnita = json_decode($apiAnita->apiCall($leeAnita));

		$itemOrdenCompra = $dataAnita;

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
				movp_cod_mon as moneda_id,
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
}

