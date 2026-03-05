<?php
namespace App\Services\Configuracion;

use App\Repositories\Configuracion\CotizacionRepositoryInterface;
use App\Repositories\Configuracion\Cotizacion_MonedaRepositoryInterface;
use App\Queries\Configuracion\CotizacionQueryInterface;

class CotizacionService 
{
	protected $cotizacionRepository;
	protected $cotizacion_movimientoRepository;
	protected $cotizacionQuery;

	public function __construct(CotizacionRepositoryInterface $cotizacionrepository, 
								Cotizacion_MonedaRepositoryInterface $cotizacion_movimientorepository,
								CotizacionQueryInterface $cotizacionquery)
	{
		$this->cotizacionRepository = $cotizacionrepository;
		$this->cotizacion_movimientoRepository = $cotizacion_movimientorepository;
		$this->cotizacionQuery = $cotizacionquery;
	}

	public function leeCotizacionDiaria($fecha, $moneda_id)
	{
		$cotizacion = $this->cotizacionQuery->leeCotizacionDiaria($fecha, $moneda_id);

		$cotizacionVenta = $cotizacionCompra = 0;
		foreach($cotizacion->cotizacion_monedas as $cotizacion_moneda)
		{
			if ($moneda_id == 1)
				$refMoneda = 2;
			else
				$refMoneda = $moneda_id;

			if ($cotizacion_moneda->moneda_id == $refMoneda)
			{
				$cotizacionVenta = $cotizacion_moneda->cotizacionventa;
				$cotizacionCenta = $cotizacion_moneda->cotizacioncompra;
				$flEncontro = true;
			}
		}
		return ['cotizacionventa' => $cotizacionVenta, 'cotizacioncompra' => $cotizacionCompra];
	}

	public function calculaCotizacionVenta($fecha, $moneda_id, $cotizacion = null)
	{
		if (isset($cotizacion))
			$cotizacionVenta = $cotizacion;
		else
		{
			$cot = Self::leeCotizacionDiaria($fecha, $moneda_id);

			$cotizacionVenta = 0;
			if ($cot)
				$cotizacionVenta = $cot['cotizacionventa'];
		}

		if ($cotizacionVenta == 0)
			$cotizacionVenta = 1.;

		return $cotizacionVenta;
	}
}

