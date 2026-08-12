<?php
namespace App\Services\Configuracion;

use App\Repositories\Configuracion\CotizacionRepositoryInterface;
use App\Repositories\Configuracion\Cotizacion_MonedaRepositoryInterface;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Support\Configuracion\CotizacionVigenteSupport;

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

	/**
	 * Cotización vigente de la moneda en la fecha: si la tabla no tiene valor propio de ese día
	 * (fila cargada en cero o día sin carga), toma la última cotización real anterior.
	 * Ver App\Support\Configuracion\CotizacionVigenteSupport.
	 */
	public function leeCotizacionDiaria($fecha, $moneda_id)
	{
		$refMoneda = ((int) $moneda_id === 1) ? 2 : (int) $moneda_id;

		return [
			'cotizacionventa' => CotizacionVigenteSupport::venta($fecha, $refMoneda)['valor'],
			'cotizacioncompra' => CotizacionVigenteSupport::compra($fecha, $refMoneda)['valor'],
		];
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

