<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Cotizacion;
use App\Repositories\Configuracion\Cotizacion_MonedaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;

class CotizacionRepository implements CotizacionRepositoryInterface
{
    protected $model;
    protected $tableAnita = ['cotizacion', 'cotiz_comp'];
    protected $keyField = 'fecha';
    protected $keyFieldAnita = ['cot_fecha', 'cotc_fecha'];

	private $cotizacion_monedaRepository;
	private $monedaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cotizacion $cotizacion,
								Cotizacion_MonedaRepositoryInterface $cotizacion_monedarepository,
								MonedaRepositoryInterface $monedarepository
								)
    {
        $this->model = $cotizacion;
		$this->cotizacion_monedaRepository= $cotizacion_monedarepository;
		$this->monedaRepository = $monedarepository;
    }

	public function all()
	{

	}

    public function create(array $data)
    {
		if (isset(Auth::user()->id))
			$data['usuario_id'] = Auth::user()->id;

		$cotizacion = $this->model->create($data);

		// Graba anita
		$anita = self::guardarAnita($data);

		if (strpos($anita, 'Error') !== false)
			throw new Exception($anita);

		return $cotizacion;
    }

    public function update(array $data, $id)
    {
		$data['usuario_id'] = Auth::user()->id;
		
        $cotizacion = $this->model->findOrFail($id)->update($data);

		// Actualiza anita
		$anita = self::actualizarAnita($data);

		if (strpos($anita, 'Error') !== false)
			throw new Exception($anita);

		return $cotizacion;
    }

    public function delete($id)
    {
    	$cotizacion = Cotizacion::find($id);

		// Elimina anita
		if ($cotizacion)
		{
			$fecha = Carbon::createFromFormat( 'Y-m-d', $cotizacion->fecha)->format('Ymd');

			$anita = self::eliminarAnita($fecha);

			if (strpos($anita, 'Error') !== false)
				return 'Error';

        	$cotizacion = $this->model->destroy($id);
		}

		return $cotizacion;
    }

    public function find($id)
    {
        if (null == $cotizacion = $this->model->with("cotizacion_monedas")
									->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cotizacion;
    }

    public function findOrFail($id)
    {
        if (null == $cotizacion = $this->model->with("cotizacion_monedas")
											->findOrFail($id))
			{
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $cotizacion;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');
	  	ini_set('memory_limit', '512M');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'shared',
						'campos' => $this->keyFieldAnita[0], 
						'tabla' => $this->tableAnita[0] );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        foreach ($dataAnita as $value) {
            $this->traerRegistroDeAnita($value->{$this->keyFieldAnita[0]});
        }
    }

    private function traerRegistroDeAnita($fecha){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[0], 
			'sistema' => 'shared',
            'campos' => '
					cot_fecha,
					cot_cambio2,
					cot_cambio3,
					cot_cambio4,
					cot_cambio5,
					cot_cambio6,
					cot_cambio7,
					cot_cambio8,
					cot_cambio9
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita[0]." = '".$fecha."' "
        );
        $dataAnitaVenta = json_decode($apiAnita->apiCall($data));

		$apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[1], 
			'sistema' => 'shared',
            'campos' => '
					cotc_fecha,
					cotc_cambio_com2,
					cotc_cambio_com3,
					cotc_cambio_com4,
					cotc_cambio_com5,
					cotc_cambio_com6,
					cotc_cambio_com7,
					cotc_cambio_com8,
					cotc_cambio_com9
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita[1]." = '".$fecha."' "
        );
        $dataAnitaCompra = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

		$cotizaciones = [];
        if (isset($dataAnitaVenta) && count($dataAnitaVenta) > 0) {
            $data = $dataAnitaVenta[0];
			
			$fecha = $data->cot_fecha;

			for($mon = 2; $mon < 9; $mon++)
			{
				$variable = 'cot_cambio'.$mon;
				
				if ($data->{$variable} > 0)
				{
					$cotizaciones[] = [
								'moneda' => $mon,
								'cotizacionventa' => $data->{$variable},
								'cotizacioncompra' => 0
								];
				}
			}
		}
		if (isset($dataAnitaCompra) && count($dataAnitaCompra) > 0) {
            $data = $dataAnitaCompra[0];

			$fecha = $data->cotc_fecha;

			for($mon = 2; $mon < 9; $mon++)
			{
				$variable = 'cotc_cambio_com'.$mon;
				
				if ($data->{$variable} > 0)
				{
					$flEncontro = false;
					for ($i = 0; $i < count($cotizaciones); $i++)
					{
						if ($cotizaciones[$i]['moneda'] == $mon)
						{
							$flEncontro = true;
							$cotizaciones[$i]['cotizacioncompra'] = $data->{$variable};
						}
					}
					if (!$flEncontro)
					{
						$cotizaciones[] = [
							'moneda' => $mon,
							'cotizacionventa' => 0,
							'cotizacioncompra' => $data->{$variable}
							];
					}
				}
			}
		}

		// Crea cotizacion
		$arr_campos = [
			'fecha' => $fecha,
			'usuario_id' => $usuario_id,
			];
	
		$cotizacion = $this->model->create($arr_campos);

		foreach($cotizaciones as $cotizacion_moneda)
		{
			$moneda = $this->monedaRepository->findPorCodigo($cotizacion_moneda['moneda']);
			if ($moneda)
			{
				$moneda_id = $moneda->id;
		
				// Graba tabla de movimientos de cotizaciones
				$arr_asimov = [
					'cotizacion_id' => $cotizacion->id,
					'moneda_id' => $moneda_id,
					'cotizacionventa' => $cotizacion_moneda['cotizacionventa'], 
					'cotizacioncompra' => $cotizacion_moneda['cotizacioncompra']
				];
				$this->cotizacion_monedaRepository->createDirecto($arr_asimov);
			}
        }
    }

	private function guardarAnita($request) 
	{
		// Graba cotizacion
		if (isset($request['moneda_ids']))
		{
			$apiAnita = new ApiAnita();

			$moneda_ids = $request['moneda_ids'];
			$cotizacionVentas = $request['cotizacionventas'];
			$cotizacionCompras = $request['cotizacioncompras'];
			$fecha = Carbon::createFromFormat( 'Y-m-d', $request['fecha'])->format('Ymd');
			$fechaFormateada = Carbon::createFromFormat( 'Y-m-d', $request['fecha'])->format('d-m-Y');

			if ($moneda_ids[0] != null)
				$qMovimiento = count($moneda_ids);
			else
				$qMovimiento = 0;
			$cotizacionVenta = [];
			$cotizacionCompra = [];
			for ($i = 0; $i < 10; $i++)
			{
				$cotizacionVenta[$i] = 0;
				$cotizacionCompra[$i] = 0;
			}
			for ($i_movimiento=0; $i_movimiento < $qMovimiento; $i_movimiento++) 
			{
				if ($moneda_ids[$i_movimiento] !== null)
				{
					$moneda = $this->monedaRepository->findPorCodigo($moneda_ids[$i_movimiento]);
					if ($moneda)
						$codigoMoneda = $moneda->codigo;
					else
						$codigoMoneda = 1;

					if ($codigoMoneda > 1)
					{
						$cotizacionVenta[$codigoMoneda] = $cotizacionVentas[$i_movimiento];
						$cotizacionCompra[$codigoMoneda] = $cotizacionCompras[$i_movimiento];
					}
				}
			}

			// Graba cotizacion de venta
			$data = array( 'tabla' => $this->tableAnita[0], 
					'acc' => 'insert',
					'sistema' => 'shared',
					'campos' => '
							cot_fecha,
							cot_cambio2,
							cot_cambio3,
							cot_cambio4,
							cot_cambio5,
							cot_cambio6,
							cot_cambio7,
							cot_cambio8,
							cot_cambio9,
							cot_fecha_alfa
						',
						'valores' => " 
						'".$fecha."', 
						'".($cotizacionVenta[2] > 0 ? $cotizacionVenta[2] : 0)."',
						'".($cotizacionVenta[3] > 0 ? $cotizacionVenta[3] : 0)."',
						'".($cotizacionVenta[4] > 0 ? $cotizacionVenta[4] : 0)."',
						'".($cotizacionVenta[5] > 0 ? $cotizacionVenta[5] : 0)."',
						'".($cotizacionVenta[6] > 0 ? $cotizacionVenta[6] : 0)."',
						'".($cotizacionVenta[7] > 0 ? $cotizacionVenta[7] : 0)."',
						'".($cotizacionVenta[8] > 0 ? $cotizacionVenta[8] : 0)."',
						'".($cotizacionVenta[9] > 0 ? $cotizacionVenta[9] : 0)."',
						'".$fechaFormateada."' "
       		);
			$cotizacion = $apiAnita->apiCall($data);
			
			if (strpos($cotizacion, 'Error') !== false)
				return 'Error grabación cotizacion anita';

			// Graba cotizacion de compra
			$apiAnita = new ApiAnita();
			$data = array( 'tabla' => $this->tableAnita[1], 
					'acc' => 'insert',
					'sistema' => 'shared',
					'campos' => '
							cotc_fecha,
							cotc_cambio_com2,
							cotc_cambio_com3,
							cotc_cambio_com4,
							cotc_cambio_com5,
							cotc_cambio_com6,
							cotc_cambio_com7,
							cotc_cambio_com8,
							cotc_cambio_com9,
							cotc_fecha_alfa
						',
						'valores' => " 
						'".$fecha."', 
						'".($cotizacionCompra[2] > 0 ? $cotizacionCompra[2] : 0)."',
						'".($cotizacionCompra[3] > 0 ? $cotizacionCompra[3] : 0)."',
						'".($cotizacionCompra[4] > 0 ? $cotizacionCompra[4] : 0)."',
						'".($cotizacionCompra[5] > 0 ? $cotizacionCompra[5] : 0)."',
						'".($cotizacionCompra[6] > 0 ? $cotizacionCompra[6] : 0)."',
						'".($cotizacionCompra[7] > 0 ? $cotizacionCompra[7] : 0)."',
						'".($cotizacionCompra[8] > 0 ? $cotizacionCompra[8] : 0)."',
						'".($cotizacionCompra[9] > 0 ? $cotizacionCompra[9] : 0)."',
						'".$fechaFormateada."' "
			);
			$cotizacion = $apiAnita->apiCall($data);

			if (strpos($cotizacion, 'Error') !== false)
				return 'Error grabación cotiz_comp anita';
		}
		return 'Success';
	}

	private function actualizarAnita($request) 
	{
		$fecha = Carbon::createFromFormat( 'Y-m-d', $request['fecha'])->format('Ymd');

		// Borra cotizacion
		Self::eliminarAnita($fecha);

		$cotizacion = Self::guardarAnita($request);

		return 'Success';
	}

	private function eliminarAnita($fecha) 
	{
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[0], 
				'sistema' => 'shared',
				'whereArmado' => " WHERE cot_fecha = '".$fecha."'");
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[1], 
				'sistema' => 'shared',
				'whereArmado' => " WHERE cotc_fecha = '".$fecha."'");
        $apiAnita->apiCall($data);
	}

}
