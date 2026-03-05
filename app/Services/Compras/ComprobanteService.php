<?php

namespace App\Services\Compras;

use App\Repositories\Compras\Tipotransaccion_CompraRepositoryInterface;
use App\ApiAnita;

class ComprobanteService 
{
	protected $tipotransaccion_compraRepository;

	public function __construct(Tipotransaccion_CompraRepositoryInterface $tipotransaccion_compraRepository)
	{
		$this->tipotransaccion_compraRepository = $tipotransaccion_compraRepository;
	}

	public function leeTipoTransaccionCompraPorAbreviatura($abreviatura)
	{
		return $this->tipotransaccion_compraRepository->findPorAbreviatura($abreviatura);
	}

}

