<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Cotizacion;
use App\Repositories\Configuracion\Cotizacion_MonedaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Support\Configuracion\EntornoEmpresaSupport;
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

	/** Informix de algunas instalaciones (Interforming) no tiene cotiz_comp. */
	private bool $cotizCompEnAnita = true;

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

		// Si el día ya está en shared, actualiza. Si no, lo inserta. No borra la fila.
		$anita = self::actualizarAnita($data);


		return $cotizacion;
    }

    public function update(array $data, $id)
    {
		$data['usuario_id'] = Auth::user()->id;
		
        $cotizacion = $this->model->findOrFail($id)->update($data);

		// Actualiza anita
		$anita = self::actualizarAnita($data);


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

	/**
	 * La API diaria no pisa shared en Interforming: ahí la cotización se carga a mano.
	 * En el resto, alta si el día no existe y actualización de las monedas enviadas si existe.
	 *
	 * @param  array<int, array{venta: float, compra: float}>  $porCodigo
	 */
	public function grabarDiaApiEnAnita(string $fechaSql, array $porCodigo): void
	{
		if (EntornoEmpresaSupport::esInterforming() || $porCodigo === []) {
			return;
		}

		$resuelto = [];
		foreach ($porCodigo as $monedaId => $tasas) {
			$codigo = $this->codigoAnitaDeMoneda($monedaId);
			if ($codigo < 2 || $codigo > 9) {
				continue;
			}
			$resuelto[$codigo] = [
				'venta' => (float) ($tasas['venta'] ?? 0),
				'compra' => (float) ($tasas['compra'] ?? 0),
			];
		}
		if ($resuelto === []) {
			return;
		}

		$this->upsertShared($fechaSql, $resuelto);
	}

	private function guardarAnita($request) 
	{
		if (! $this->tieneMonedasParaAnita($request)) {
			return 'Success';
		}

		$porCodigo = $this->porCodigoDesdeRequest($request);
		if ($porCodigo === []) {
			return 'Success';
		}

		$this->upsertShared((string) $request['fecha'], $porCodigo);

		return 'Success';
	}

	private function actualizarAnita($request) 
	{
		return $this->guardarAnita($request);
	}

	/**
	 * @param  array<int, array{venta: float, compra: float}>  $porCodigo
	 */
	private function upsertShared(string $fechaSql, array $porCodigo): void
	{
		$fecha = Carbon::parse($fechaSql)->format('Ymd');
		$fechaAlfa = Carbon::parse($fechaSql)->format('d-m-Y');

		$this->upsertTablaShared(
			$this->tableAnita[0],
			'cot_fecha',
			'cot_cambio',
			'cot_fecha_alfa',
			$fecha,
			$fechaAlfa,
			$porCodigo,
			'venta',
		);

		if (! $this->cotizCompEnAnita) {
			return;
		}

		try {
			$this->upsertTablaShared(
				$this->tableAnita[1],
				'cotc_fecha',
				'cotc_cambio_com',
				'cotc_fecha_alfa',
				$fecha,
				$fechaAlfa,
				$porCodigo,
				'compra',
			);
		} catch (\RuntimeException $e) {
			if (! self::anitaNoTieneCotizComp($e->getMessage())) {
				throw $e;
			}
			$this->cotizCompEnAnita = false;
		}
	}

	/**
	 * @param  array<int, array{venta: float, compra: float}>  $porCodigo
	 */
	private function upsertTablaShared(
		string $tabla,
		string $campoFecha,
		string $prefijoCambio,
		string $campoFechaAlfa,
		string $fecha,
		string $fechaAlfa,
		array $porCodigo,
		string $lado,
	): void {
		$apiAnita = new ApiAnita();
		if ($this->filaExisteEnAnita($apiAnita, $tabla, $campoFecha, $fecha)) {
			$asignaciones = [];
			foreach ($porCodigo as $codigo => $tasas) {
				$codigo = (int) $codigo;
				if ($codigo < 2 || $codigo > 9) {
					continue;
				}
				$asignaciones[] = $prefijoCambio.$codigo." = '".$this->decimalSql((float) ($tasas[$lado] ?? 0))."'";
			}
			if ($asignaciones === []) {
				return;
			}
			$asignaciones[] = $campoFechaAlfa." = '".$fechaAlfa."'";
			$data = [
				'acc' => 'update',
				'tabla' => $tabla,
				'sistema' => 'shared',
				'valores' => implode(', ', $asignaciones),
				'whereArmado' => " WHERE ".$campoFecha." = '".$fecha."' ",
			];
		} else {
			$campos = [$campoFecha];
			$valores = ["'".$fecha."'"];
			for ($codigo = 2; $codigo <= 9; $codigo++) {
				$campos[] = $prefijoCambio.$codigo;
				$valores[] = "'".$this->decimalSql((float) ($porCodigo[$codigo][$lado] ?? 0))."'";
			}
			$campos[] = $campoFechaAlfa;
			$valores[] = "'".$fechaAlfa."'";
			$data = [
				'acc' => 'insert',
				'tabla' => $tabla,
				'sistema' => 'shared',
				'campos' => implode(', ', $campos),
				'valores' => implode(', ', $valores),
			];
		}

		if ($tabla === $this->tableAnita[1]) {
			$this->escribirCotizComp($apiAnita, $data);

			return;
		}

		$apiAnita->apiCallEscritura($data);
	}

	private function filaExisteEnAnita(ApiAnita $apiAnita, string $tabla, string $campoFecha, string $fecha): bool
	{
		$raw = $apiAnita->apiCall([
			'acc' => 'list',
			'sistema' => 'shared',
			'tabla' => $tabla,
			'campos' => $campoFecha,
			'whereArmado' => " WHERE ".$campoFecha." = '".$fecha."' ",
		]);
		$parsed = ApiAnita::parsearRespuestaLista(is_string($raw) ? $raw : null);
		if ($parsed['error_lectura'] !== null) {
			throw new \RuntimeException('No se pudo leer '.$tabla.' en Anita: '.$parsed['error_lectura']);
		}

		return $parsed['filas'] !== [];
	}

	/**
	 * @return array<int, array{venta: float, compra: float}>
	 */
	private function porCodigoDesdeRequest(array $request): array
	{
		$ids = $request['moneda_ids'] ?? [];
		$ventas = $request['cotizacionventas'] ?? [];
		$compras = $request['cotizacioncompras'] ?? [];
		$porCodigo = [];
		foreach ($ids as $i => $monedaId) {
			if ($monedaId === null || $monedaId === '') {
				continue;
			}
			$codigo = $this->codigoAnitaDeMoneda($monedaId);
			if ($codigo < 2 || $codigo > 9) {
				continue;
			}
			$porCodigo[$codigo] = [
				'venta' => (float) ($ventas[$i] ?? 0),
				'compra' => (float) ($compras[$i] ?? 0),
			];
		}

		return $porCodigo;
	}

	private function codigoAnitaDeMoneda($monedaId): int
	{
		$moneda = $this->monedaRepository->findPorId($monedaId);
		if ($moneda !== null && is_numeric(trim((string) $moneda->codigo))) {
			return (int) $moneda->codigo;
		}

		return (int) $monedaId;
	}

	private function decimalSql(float $valor): string
	{
		$texto = number_format($valor, 4, '.', '');

		return rtrim(rtrim($texto, '0'), '.') ?: '0';
	}

	private function tieneMonedasParaAnita($request): bool
	{
		if (! isset($request['moneda_ids']) || ! is_array($request['moneda_ids'])) {
			return false;
		}

		foreach ($request['moneda_ids'] as $monedaId) {
			if ($monedaId !== null && $monedaId !== '') {
				return true;
			}
		}

		return false;
	}

	private function eliminarAnita($fecha) 
	{
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[0], 
				'sistema' => 'shared',
				'whereArmado' => " WHERE cot_fecha = '".$fecha."'");
        $apiAnita->apiCallEscritura($data);

		if (! $this->cotizCompEnAnita)
			return;

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[1], 
				'sistema' => 'shared',
				'whereArmado' => " WHERE cotc_fecha = '".$fecha."'");
        $this->escribirCotizComp($apiAnita, $data);
	}

	/**
	 * cotiz_comp no está en todas las bases Anita. Si falta, la cotización de
	 * venta igual se graba y las próximas no vuelven a pegarle a esa tabla.
	 */
	private function escribirCotizComp(ApiAnita $apiAnita, array $data): void
	{
		try {
			$apiAnita->apiCallEscritura($data);
		} catch (\RuntimeException $e) {
			if (! self::anitaNoTieneCotizComp($e->getMessage())) {
				throw $e;
			}
			$this->cotizCompEnAnita = false;
		}
	}

	private static function anitaNoTieneCotizComp(string $mensaje): bool
	{
		return str_contains($mensaje, 'cotiz_comp')
			&& (str_contains($mensaje, 'is not in the database') || str_contains($mensaje, '206'));
	}

}
