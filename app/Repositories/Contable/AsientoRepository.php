<?php

namespace App\Repositories\Contable;

use App\Models\Contable\Asiento;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Ventas\Venta;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Stock\RecepcionProveedorAnitaClaveSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;
use DB;

class AsientoRepository implements AsientoRepositoryInterface
{
    protected $model;
    protected $tableAnita = ['ctamov', 'subdiario'];
    protected $keyField = 'numeroasiento';
    protected $keyFieldAnita = ['ctav_empresa', 'ctav_nro_asiento', 'ctav_nro_linea'];

	private $centrocostoRepository;
	private $asiento_movimientoRepository;
	private $monedaRepository;
	private $empresaRepository;
	private $cuentacontableRepository;
	private $tipoasientoRepository;
	private $flGrabaAsiento, $numeroAsientoActual;
	private $path_sistema;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Asiento $asiento,
								CentrocostoRepositoryInterface $centrocostorepository,
								Asiento_MovimientoRepositoryInterface $asiento_movimientorepository,
								MonedaRepositoryInterface $monedarepository,
								EmpresaRepositoryInterface $empresarepository,
								TipoasientoRepositoryInterface $tipoasientorepository,
								CuentacontableRepositoryInterface $cuentacontablerepository
								)
    {
        $this->model = $asiento;
		$this->centrocostoRepository = $centrocostorepository;
		$this->asiento_movimientoRepository= $asiento_movimientorepository;
		$this->monedaRepository = $monedarepository;
		$this->empresaRepository = $empresarepository;
		$this->tipoasientoRepository = $tipoasientorepository;
		$this->cuentacontableRepository = $cuentacontablerepository;
		$this->path_sistema = null;
    }

    public function create(array $data)
    {
		$this->assertPeriodoContablePermitido($data);

		//$data['numeroasiento'] = self::ultimoAsiento($data['empresa_id']);
		if (array_key_exists('path_sistema', $data))
			$this->path_sistema = $data['path_sistema'];
		
		// Si es tipo PRE el numero de asiento es periodo y mes
		$tipoasiento = $this->tipoasientoRepository->find($data['tipoasiento_id']);

		$codigoTipoAsiento = '';
		if ($tipoasiento)
			$codigoTipoAsiento = $tipoasiento->abreviatura;

		if ($codigoTipoAsiento == 'PRE')
			$data['numeroasiento'] = substr($data['fecha'],0,4).substr($data['fecha'],5,2).$data['numerolinea'];
		else
			$data['numeroasiento'] = self::ultimoAsientoAnita($data['empresa_id']);

		$data['usuario_id'] = Auth()->id();

		if (! isset($data['estado_aprobacion'])) {
			$data['estado_aprobacion'] = Asiento::ESTADO_APROBACION_CONFIRMADO;
		}
		
		$asiento = $this->model->create($data);

		// omitir_anita: el caller sincroniza ctamov después (ej. recepción COM) para evitar doble escritura.
		$omitirAnita = filter_var($data['omitir_anita'] ?? false, FILTER_VALIDATE_BOOLEAN);

		// Graba anita solo si el asiento queda confirmado (no pendiente de aprobación)
		if (
			! $omitirAnita
			&& ($data['estado_aprobacion'] ?? Asiento::ESTADO_APROBACION_CONFIRMADO) === Asiento::ESTADO_APROBACION_CONFIRMADO
		) {
			self::guardarAnita($data);
		}

		return $asiento;
    }

    /**
     * @return array<string, mixed>
     */
    public function armarPayloadAnitaDesdeModelo(Asiento $asiento): array
    {
        $asiento->loadMissing(['asiento_movimientos.monedas']);

        $cuentacontableIds = [];
        $centrocostoIds = [];
        $monedaIds = [];
        $debes = [];
        $haberes = [];
        $cotizaciones = [];
        $observaciones = [];

        foreach ($asiento->asiento_movimientos as $mov) {
            $monto = (float) ($mov->monto ?? 0);
            $cuentacontableIds[] = $mov->cuentacontable_id;
            $centrocostoIds[] = $mov->centrocosto_id;
            $monedaIds[] = optional($mov->monedas)->codigo ?? (string) ($mov->moneda_id ?? '1');
            $debes[] = $monto > 0 ? $monto : '';
            $haberes[] = $monto < 0 ? abs($monto) : '';
            $cotizaciones[] = $mov->cotizacion ?? 0;
            $observaciones[] = $mov->observacion ?? '';
        }

        return array_merge($asiento->toArray(), [
            'cuentacontable_ids' => $cuentacontableIds,
            'centrocosto_ids' => $centrocostoIds,
            'moneda_ids' => $monedaIds,
            'debes' => $debes,
            'haberes' => $haberes,
            'cotizaciones' => $cotizaciones,
            'observaciones' => $observaciones,
        ]);
    }

    public function update(array $data, $id)
    {
		$data['usuario_id'] = Auth::user()->id;

		$asientoExistente = $this->model->find($id);
		if ($asientoExistente) {
			$dataParaValidar = array_merge($asientoExistente->toArray(), $data);
			$this->assertPeriodoContablePermitido($dataParaValidar);
		}

		$asiento = $this->model->findOrFail($id)->update($data);

		// omitir_anita: el caller sincroniza ctamov después de grabar movimientos
		// (evita reescribir Anita con el request antes de persistir líneas en ERP).
		$omitirAnita = filter_var($data['omitir_anita'] ?? false, FILTER_VALIDATE_BOOLEAN);
		if (! $omitirAnita) {
			self::actualizarAnita($data);
		}

		return $asiento;
    }

    /**
     * Reemplaza ctamov en Anita para un asiento ya existente en el ERP.
     *
     * @param  array<string, mixed>  $data
     */
    public function sincronizarCtamovAnita(array $data): void
    {
        if (empty($data['numeroasiento'])) {
            throw new \InvalidArgumentException('Falta numeroasiento para sincronizar ctamov en Anita.');
        }

        if (array_key_exists('path_sistema', $data)) {
            $this->path_sistema = $data['path_sistema'];
        }

        $this->assertPeriodoContablePermitido($data);
        $this->actualizarAnita($data);
    }

    public function eliminarCtamovAnitaPorNumero(int $empresaId, string $numeroAsiento): void
    {
        $numeroAsiento = trim($numeroAsiento);
        if ($empresaId <= 0 || $numeroAsiento === '') {
            return;
        }

        $empresa = $this->empresaRepository->findPorId($empresaId);
        $codigoEmpresa = $empresa ? $empresa->codigo : $empresaId;

        $this->eliminarAnita($codigoEmpresa, $numeroAsiento);
    }

    public function eliminarCtamovAnitaPorComprobante(
        int $empresaId,
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
    ): void {
        if ($empresaId <= 0 || $nro <= 0) {
            return;
        }

        $tipo = trim($tipo);
        $letra = trim($letra);
        if ($tipo === '') {
            return;
        }

        $empresa = $this->empresaRepository->findPorId($empresaId);
        $codigoEmpresa = $empresa ? $empresa->codigo : $empresaId;

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'delete',
            'tabla' => $this->tableAnita[0],
            'sistema' => 'contab',
            'whereArmado' => " WHERE ctav_empresa = '".str_replace("'", "''", (string) $codigoEmpresa)."'"
                ." AND ctav_tipo = '".str_replace("'", "''", $tipo)."'"
                ." AND ctav_letra = '".str_replace("'", "''", $letra)."'"
                .' AND ctav_sucursal = '.(int) $sucursal
                .' AND ctav_nro = '.(int) $nro,
        ];
        if (isset($this->path_sistema)) {
            $data['path_sistema'] = $this->path_sistema;
        }
        $apiAnita->apiCallEscritura($data);
    }

    public function delete($id)
    {
    	$asiento = Asiento::find($id);

		if ($asiento) {
			$this->assertPeriodoContablePermitido($asiento->toArray());
		}

		// Elimina anita
		if ($asiento)
		{
			$empresa = $this->empresaRepository->findPorId($asiento->empresa_id);
			if ($empresa)
				$codigoEmpresa = $empresa->codigo;
			else
				$codigoEmpresa = 1;
						
			$anita = self::eliminarAnita($codigoEmpresa, $asiento->numeroasiento);


        	$asiento = $this->model->destroy($id);
		}

		return $asiento;
    }

    public function find($id)
    {
        if (null == $asiento = $this->model->with("asiento_movimientos")
									->with("asiento_archivos")
									->with("tipoasientos")
									->with("empresas")
									->with("usuarios")
									->with(['ordencompras.proveedores:id,nombre'])
									->with(['comprobante_proveedores.proveedores:id,nombre', 'comprobante_proveedores.tipotransaccion_compras:id,abreviatura', 'comprobante_proveedores.ordencompras:id,numeroordencompra'])
									->with(['ventas.clientes:id,nombre', 'ventas.tipotransacciones:id,abreviatura', 'ventas.puntoventas:id,codigo'])
									->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $asiento;
    }

    public function findOrFail($id)
    {
        if (null == $asiento = $this->model->with("asiento_movimientos")
											->with("asiento_archivos")
											->with("tipoasientos")
											->with("empresas")
											->with("usuarios")
											->findOrFail($id))
			{
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $asiento;
    }

	public function leeAsientoPorClave($id, $clave)
	{
		return $this->model->where($clave, $id)
							->with("asiento_movimientos")
							->with("asiento_archivos")
							->with("tipoasientos")
							->with("empresas")
							->with("usuarios")
							->get();
	}

    public function sincronizarConAnita(){
		ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
		
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'contab',
						'campos' => $this->keyFieldAnita[0].",".$this->keyFieldAnita[1].",".$this->keyFieldAnita[2], 
						'tabla' => $this->tableAnita[0] );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$this->flGrabaAsiento = true;
		$this->numeroAsientoActual = 0;
        foreach ($dataAnita as $value) {
            $this->traerRegistroDeAnita($value->{$this->keyFieldAnita[0]},
										$value->{$this->keyFieldAnita[1]}, 
										$value->{$this->keyFieldAnita[2]});
        }

		$dataAnita = DB::table('anitasubdiario')
		->select('subd_nro_operacion',
				 'subd_fecha',
				 'subd_cod_mon')
		->orderBy('subd_nro_operacion')
		->get();

		$this->flGrabaAsiento = true;
		foreach($dataAnita as $value)
        	$this->traerRegistroDeAnitaSubdiario($value->subd_nro_operacion, $value->subd_fecha, $value->subd_cod_mon);
    }

    private function traerRegistroDeAnita($empresa, $asiento, $linea){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[0], 
			'sistema' => 'contab',
            'campos' => '
					ctav_empresa,
					ctav_nro_asiento,
					ctav_nro_linea,
					ctav_d_h,
					ctav_cuenta,
					ctav_fecha,
					ctav_tipo,
					ctav_letra,
					ctav_sucursal ,
					ctav_nro,
					ctav_importe,
					ctav_cotizacion,
					ctav_cod_mon,
					ctav_sistema,
					ctav_balancea,
					ctav_tipo_asiento,
					ctav_asi_mon_ref,
					ctav_ccosto,
					ctav_usuario_umod,
					ctav_fecha_umod,
					ctav_hora_umod,
					ctav_o_compra,
					ctav_desc_mov 
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita[0]." = '".$empresa."' AND ".
									   $this->keyFieldAnita[1]." = '".$asiento."' AND ".
									   $this->keyFieldAnita[2]." = '".$linea."' "
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (isset($dataAnita)) {
            $data = $dataAnita[0];

			if ($data->ctav_nro_asiento != $this->numeroAsientoActual)
			{
				$this->numeroAsientoActual = $data->ctav_nro_asiento;
				$this->flGrabaAsiento = true;
			}

			$empresa = $this->empresaRepository->findPorCodigo($data->ctav_empresa);
			if ($empresa)
				$empresa_id = $empresa->id;
			else
				$empresa_id = 1;
						
			$cuenta = $this->cuentacontableRepository->findPorCodigo($data->ctav_empresa, $data->ctav_cuenta);
			if ($cuenta)
				$cuentacontable_id = $cuenta->id;
			else
				$cuentacontable_id = NULL;

			$centrocosto = $this->centrocostoRepository->findPorCodigo($data->ctav_ccosto);
			if ($centrocosto)
				$centrocosto_id = $centrocosto->id;
			else
				$centrocosto_id = 1;

			$moneda = $this->monedaRepository->findPorCodigo($data->ctav_cod_mon);
			if ($moneda)
				$moneda_id = $moneda->id;
			else
				$moneda_id = NULL;
	
			if ($this->flGrabaAsiento)
			{
				$observacion = $data->ctav_sistema.' '.$data->ctav_tipo.' '.$data->ctav_letra.' '.
								$data->ctav_sucursal.' '.$data->ctav_nro;

				if ($data->ctav_tipo_asiento === '   ')
					$tipoasiento_id = 1;
				else
				{
					$tipoasiento = $this->tipoasientoRepository->findPorAbreviatura($data->ctav_tipo_asiento);
					if ($tipoasiento)
						$tipoasiento_id = $tipoasiento->id;
					else
						$tipoasiento_id = 1;
		
				}
				$arr_campos = [
					'empresa_id' => $empresa_id,
					'tipoasiento_id' => $tipoasiento_id,
					'numeroasiento' => $data->ctav_nro_asiento,
					'fecha' => $data->ctav_fecha,
					'venta_id' => null,
					'movimientostock_id' => null,
					'compra_id' => null,
					'caja_movimiento_id' => null,
					'ordencompra_id' => $data->ctav_o_compra,
					'recepcionproveedor_id' => null,
					'observacion' => $observacion,
					'usuario_id' => $usuario_id,
					];
		
				$asiento = $this->model->create($arr_campos);

				$this->flGrabaAsiento = false;
			}

			// Graba tabla de movimientos de asientos
			if ($cuentacontable_id != NULL)
			{
				$arr_asimov = [
					'asiento_id' => $asiento->id,
					'cuentacontable_id' => $cuentacontable_id, 
					'centrocosto_id' => $centrocosto_id, 
					'monto' => ($data->ctav_d_h == 'D' ? $data->ctav_importe : -$data->ctav_importe), 
					'moneda_id' => $moneda_id,
					'cotizacion' => $data->ctav_cotizacion, 
					'observacion' => $data->ctav_desc_mov
				];
				$this->asiento_movimientoRepository->createUnique($arr_asimov);
			}
        }
    }

	private function traerRegistroDeAnitaSubdiario($numeroOperacion, $fecha, $cod_mon) {
		$dataAnita = DB::table('anitasubdiario')
		->select(
				'subd_sistema',
				'subd_fecha',
				'subd_tipo',
				'subd_letra',
				'subd_sucursal',
				'subd_nro',
				'subd_emisor',
				'subd_tipo_mov',
				'subd_cuenta',
				'subd_contrapartida',
				'subd_nro_operacion',
				'subd_ref_tipo',
				'subd_ref_letra',
				'subd_ref_sucursal',
				'subd_ref_nro',
				'subd_ref_sistema',
				'subd_importe',
				'subd_cod_mon',
				'subd_cotizacion',
				'subd_desc_mov',
				'subd_nro_asiento',
				'subd_procesado',
				'subd_ccosto_cta',
				'subd_ccosto_con',
				'subd_nro_interno',
				'subd_empresa',
				'subd_usuario',
				'subd_fecha_ult_act',
				'subd_hora_ult_act')
		->where('subd_nro_operacion', $numeroOperacion)
		->where('subd_fecha', $fecha)
		->where('subd_cod_mon', $cod_mon)
		->get();

		$usuario_id = Auth::user()->id;
		$this->numeroAsientoActual = 0;

		foreach ($dataAnita as $data) {
			if ($data->subd_nro_operacion != $this->numeroAsientoActual)
			{
				$this->numeroAsientoActual = $data->subd_nro_operacion;
				$this->flGrabaAsiento = true;
			}

			$empresa = $this->empresaRepository->findPorCodigo($data->subd_empresa);
			if ($empresa)
				$empresa_id = $empresa->id;
			else
				$empresa_id = 1;
						
			$cuenta = $this->cuentacontableRepository->findPorCodigo($data->subd_empresa, $data->subd_cuenta);
			if ($cuenta)
				$cuentacontable_id = $cuenta->id;
			else
				$cuentacontable_id = NULL;

			$centrocosto = $this->centrocostoRepository->findPorCodigo($data->subd_ccosto_cta);
			if ($centrocosto)
				$centrocosto_id = $centrocosto->id;
			else
				$centrocosto_id = 1;

			$moneda = $this->monedaRepository->findPorCodigo($data->subd_cod_mon);
			if ($moneda)
				$moneda_id = $moneda->id;
			else
				$moneda_id = NULL;
	
			if ($this->flGrabaAsiento)
			{
				$observacion = $data->subd_sistema.' '.$data->subd_tipo.' '.$data->subd_letra.' '.
								$data->subd_sucursal.' '.$data->subd_nro;

				switch($data->subd_sistema)
				{
					case 'V':
						$tipoasiento_id = 7;
						break;
					case 'C':
						$tipoasiento_id = 8;
						break;
					case 'T':
						$tipoasiento_id = 9;
						break;
					case 'S':
						$tipoasiento_id = 10;
						break;
				}
				$arr_campos = [
					'empresa_id' => $empresa_id,
					'tipoasiento_id' => $tipoasiento_id,
					'numeroasiento' => $data->subd_nro_operacion,
					'fecha' => $data->subd_fecha,
					'venta_id' => null,
					'movimientostock_id' => null,
					'compra_id' => null,
					'caja_movimiento_id' => null,
					'ordencompra_id' => null,
					'recepcionproveedor_id' => null,
					'observacion' => $observacion,
					'usuario_id' => $usuario_id,
					];
		
				$asiento = $this->model->create($arr_campos);

				$this->flGrabaAsiento = false;
			}

			// Graba tabla de movimientos de asientos
			if ($cuentacontable_id != NULL)
			{
				$arr_asimov = [
					'asiento_id' => $asiento->id,
					'cuentacontable_id' => $cuentacontable_id, 
					'centrocosto_id' => $centrocosto_id, 
					'monto' => ($data->subd_tipo_mov == 'D' ? $data->subd_importe : -$data->subd_importe), 
					'moneda_id' => $moneda_id,
					'cotizacion' => $data->subd_cotizacion, 
					'observacion' => $data->subd_desc_mov
				];
				$this->asiento_movimientoRepository->createUnique($arr_asimov);
			}

			// Genera contrapartida
			$cuenta = $this->cuentacontableRepository->findPorCodigo($data->subd_empresa, $data->subd_contrapartida);
			if ($cuenta)
				$cuentacontable_id = $cuenta->id;
			else
				$cuentacontable_id = NULL;
	
			$centrocosto = $this->centrocostoRepository->findPorCodigo($data->subd_ccosto_con);
			if ($centrocosto)
				$centrocosto_id = $centrocosto->id;
			else
				$centrocosto_id = 1;

			// Graba tabla de movimientos de asientos
			if ($cuentacontable_id != NULL)
			{
				$arr_asimov = [
					'asiento_id' => $asiento->id,
					'cuentacontable_id' => $cuentacontable_id, 
					'centrocosto_id' => $centrocosto_id, 
					// Si va al debe la contrapartida va al haber
					'monto' => ($data->subd_tipo_mov == 'D' ? -$data->subd_importe : $data->subd_importe), 
					'moneda_id' => $moneda_id,
					'cotizacion' => $data->subd_cotizacion, 
					'observacion' => $data->subd_desc_mov
				];
				$this->asiento_movimientoRepository->createUnique($arr_asimov);
			}
        }
    }

	private function guardarAnita($request) 
	{
		// Graba asiento
		if (isset($request['cuentacontable_ids']))
		{
			$apiAnita = new ApiAnita();

			$centrocostos = $request['centrocosto_ids'] ?? [];
			$centrocostosPrev = $request['centrocosto_id_previo'] ?? [];
			$debes = $request['debes'] ?? [];
			$haberes = $request['haberes'] ?? [];
			$cuentacontables = $request['cuentacontable_ids'] ?? [];
			$observaciones = $request['observaciones'] ?? [];
			$moneda_ids = $request['moneda_ids'] ?? [];
			$cotizaciones = $request['cotizaciones'] ?? [];
			
			$fecha = Carbon::createFromFormat( 'Y-m-d', $request['fecha'])->format('Ymd');

			$empresa = $this->empresaRepository->findPorId($request['empresa_id']);
			
			if ($empresa)
				$codigoEmpresa = $empresa->codigo;
			else
				$codigoEmpresa = 1;

			$tipoasiento = $this->tipoasientoRepository->find($request['tipoasiento_id']);
			if ($tipoasiento)
				$codigoTipoAsiento = $tipoasiento->abreviatura;
			else
				$codigoTipoAsiento = 1;

			if (isset($request['sistema_ctav']) && $request['sistema_ctav'] !== '') {
				$sistema = $request['sistema_ctav'];
			} elseif ($codigoTipoAsiento == 'VTA') {
				$sistema = 'V';
			} else {
				$sistema = 'B';
			}

			$numeroOrdenCompra = (int) ($request['ctav_o_compra'] ?? 0);

			// Si el payload trae tipo (sync COM) usarlo; si falta pero el asiento es de recepción,
			// resolver clave COM para no reescribir ctamov con numeración en blanco.
			if (isset($request['tipo']) && trim((string) $request['tipo']) !== '') {
				$tipo = $request['tipo'];
				$letra = $request['letra'];
				$sucursal = $request['sucursal'];
				$nro = $request['nro'];
			} elseif (! empty($request['recepcionproveedor_id'])) {
				$recepcion = Recepcion_Proveedor::query()->find((int) $request['recepcionproveedor_id']);
				if ($recepcion) {
					$clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
					$tipo = $clave['tipo'];
					$letra = $clave['letra'];
					$sucursal = $clave['sucursal'];
					$nro = $clave['nro'];
					if (! isset($request['sistema_ctav']) || $request['sistema_ctav'] === '') {
						$sistema = 'C';
					}
				} else {
					$tipo = $letra = ' ';
					$sucursal = $nro = 0;
				}
			} else {
				$tipo = $letra = ' ';
				$sucursal = $nro = 0;
			}

			if (($cuentacontables[0] ?? null) != null)
				$qMovimiento = count($cuentacontables);
			else
				$qMovimiento = 0;
			for ($i_movimiento=0; $i_movimiento < $qMovimiento; $i_movimiento++) 
			{
				$observacion = preg_replace('([^A-Za-z0-9 ])', '', (string) ($observaciones[$i_movimiento] ?? ''));

				$d_h = null;
				$monto = 0;
				$debeLin = $debes[$i_movimiento] ?? '';
				$haberLin = $haberes[$i_movimiento] ?? '';

				if ($debeLin > 0 && $debeLin != '')
				{
					$d_h = 'D';
					$monto = $debeLin;
				}

				if (($haberLin != 0 || $debeLin < 0) && $haberLin != '')
				{
					$d_h = 'H';
					$monto = abs(floatval($haberLin)+floatval($debeLin));
				}

				// Línea sin importe: no grabar ctamov (evita ctav_d_h indefinido; no altera líneas con debe/haber válidos).
				if ($d_h === null) {
					continue;
				}

				$cuenta = $this->cuentacontableRepository->findPorId($cuentacontables[$i_movimiento] ?? null);
				if ($cuenta)
					$cuentacontable = $cuenta->codigo;
				else
					$cuentacontable = NULL;

				// Select CC vacío no viaja en el POST: usar hidden centrocosto_id_previo.
				$centrocostoIdLin = $centrocostos[$i_movimiento] ?? $centrocostosPrev[$i_movimiento] ?? 0;

				$codigoCentroCosto = 0;
				if ($centrocostoIdLin)
				{
					$centrocosto = $this->centrocostoRepository->findPorId($centrocostoIdLin);
					if ($centrocosto)
						$codigoCentroCosto = $centrocosto->codigo;
					else
						$codigoCentroCosto = 0;
				}

				$moneda = $this->monedaRepository->findPorCodigo($moneda_ids[$i_movimiento] ?? null);
				if ($moneda)
					$codigoMoneda = $moneda->codigo;
				else
					$codigoMoneda = '1';
				
				$data = array( 'tabla' => $this->tableAnita[0], 
						'acc' => 'insert',
						'sistema' => 'contab',
						'campos' => '
							ctav_empresa,
							ctav_nro_asiento,
							ctav_nro_linea,
							ctav_d_h,
							ctav_cuenta,
							ctav_fecha,
							ctav_tipo,
							ctav_letra,
							ctav_sucursal ,
							ctav_nro,
							ctav_importe,
							ctav_desc_mov,
							ctav_cotizacion,
							ctav_cod_mon,
							ctav_sistema,
							ctav_balancea,
							ctav_tipo_asiento,
							ctav_asi_mon_ref,
							ctav_ccosto'.(strtoupper(config('app.empresa')) == 'EL BIERZO' ? '' : ',
							ctav_usuario_umod,
							ctav_fecha_umod,
							ctav_hora_umod,
							ctav_o_compra 
						'),
						'valores' => " 
						'".$codigoEmpresa."', 
						'".$request['numeroasiento']."',
						'".$i_movimiento."',
						'".$d_h."',
						'".$cuentacontable."',
						'".$fecha."',
						'".$tipo."',
						'".$letra."',
						'".$sucursal."',
						'".$nro."',
						'".abs($monto)."',
						'".$observacion."',
						'".($cotizaciones[$i_movimiento] ?? 0)."',
						'".$codigoMoneda."',
						'".$sistema."',
						'".'S'."',
						'".$codigoTipoAsiento."',
						'".'-1'."',
						'".$codigoCentroCosto."'".(strtoupper(config('app.empresa')) == 'EL BIERZO' ? "":
						",
						'".' '."',
						'".'0'."',
						'".' '."',
						".$numeroOrdenCompra." ")
      			);
				if (isset($this->path_sistema))
					$data['path_sistema'] = $this->path_sistema;	
        		$asiento = $apiAnita->apiCallEscritura($data);
			}
		}
		return 'Success';
	}

	private function actualizarAnita($request) 
	{
		$empresa = $this->empresaRepository->findPorId($request['empresa_id']);
		if ($empresa)
			$codigoEmpresa = $empresa->codigo;
		else
			$codigoEmpresa = 1;

		// Borra asiento
		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[0], 
				'sistema' => 'contab',
				'whereArmado' => " WHERE ctav_empresa = '".$codigoEmpresa."' and ctav_nro_asiento = '".
									$request['numeroasiento']."' ");
		if (isset($this->path_sistema))
			$data['path_sistema'] = $this->path_sistema;										
        $apiAnita->apiCallEscritura($data);

		// Crea el asiento
		$asiento = Self::guardarAnita($request);


		return 'Success';
	}

	private function eliminarAnita($empresa, $codigo) 
	{
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[0], 
				'sistema' => 'contab',
				'whereArmado' => " WHERE ctav_empresa = '".$empresa."' and ctav_nro_asiento = '".$codigo."' ");
		if (isset($this->path_sistema))
			$data['path_sistema'] = $this->path_sistema;	
        $apiAnita->apiCallEscritura($data);
	}

	// Devuelve ultimo codigo de asientos + 1 para agregar nuevos en Anita

	private function ultimoAsiento($empresa_id) 
	{
		$asiento = $this->model->select('numeroasiento')->where('empresa_id', $empresa_id)->orderBy('id', 'desc')->first();
		
		$numeroasiento = 0;
        if ($asiento) 
		{
			$numeroasiento = $asiento->numeroasiento;
			$numeroasiento = $numeroasiento + 1;
		}
		else	
			$numeroasiento = 1;

		return $numeroasiento;
	}

	private function ultimoAsientoAnita($empresa_id) 
	{
		if (strtoupper(config('app.empresa')) == 'EL BIERZO')
		{
			// Lee numero de operacion
			$apiAnita = new ApiAnita();
			$data = array( 
				'acc' => 'list', 
				'tabla' => 'numerador',
				'sistema' => 'ventas',
				'campos' => '
					num_ult_numero
				' , 
				'whereArmado' => " WHERE num_clave='501'"
			);
			if (isset($this->path_sistema))
				$data['path_sistema'] = $this->path_sistema;
			$dataAnita = json_decode($apiAnita->apiCall($data));

			$numeroOperacion = $dataAnita[0]->num_ult_numero + 1;

			// Actualiza numero
			$apiAnita = new ApiAnita();
			$data = array( 'acc' => 'update', 
						'tabla' => 'numerador', 
						'sistema' => 'ventas',
						'valores' => 
							" num_ult_numero = '".$numeroOperacion."' ", 
						'whereArmado' => " WHERE num_clave='501'" );
			if (isset($this->path_sistema))
				$data['path_sistema'] = $this->path_sistema;						
			$numerador = $apiAnita->apiCallEscritura($data);
		}
		else
		{
			$empresa = $this->empresaRepository->find($empresa_id);

			if ($empresa)
				$codigoEmpresa = $empresa->codigo;
			else
				$codigoEmpresa = $empresa_id;

			// Lee numero de operacion
			$apiAnita = new ApiAnita();
			$data = array( 
				'acc' => 'list', 
				'tabla' => 'numabm', 
				'sistema' => 'shared',
				'campos' => '
					numa_ult_numero
				' , 
				'whereArmado' => " WHERE numa_sistema='contab' and numa_programa='a-ctamov.c' and numa_referencia='".$codigoEmpresa."'"
			);
			if (isset($this->path_sistema))
				$data['path_sistema'] = $this->path_sistema;			
			$dataAnita = json_decode($apiAnita->apiCall($data));

			if ($dataAnita)
			{
				$numeroOperacion = $dataAnita[0]->numa_ult_numero + 1;

				// Actualiza numero
				$apiAnita = new ApiAnita();
				$data = array( 'acc' => 'update', 
							'tabla' => 'numabm', 
							'sistema' => 'shared',
							'valores' => 
								" numa_ult_numero = '".$numeroOperacion."' ", 
							'whereArmado' => " WHERE numa_sistema='contab' and numa_programa='a-ctamov.c' and numa_referencia='".$codigoEmpresa."'" );
				if (isset($this->path_sistema))
					$data['path_sistema'] = $this->path_sistema;						
				$numerador = $apiAnita->apiCallEscritura($data);
			}
		}

		return $numeroOperacion;		
	}

	private function assertPeriodoContablePermitido(array $data): void
	{
		if (empty($data['empresa_id']) || empty($data['fecha'])) {
			return;
		}

		$alcance = (string) ($data['alcance_cierre_contable'] ?? $this->inferirAlcanceCierre($data));
		$opciones = [];

		if ($alcance === PeriodoContableCierreSupport::ALCANCE_FACTURACION) {
			$opciones['modofacturacion_pv'] = $data['modofacturacion_pv'] ?? null;
			if (! empty($data['fechajornada'])) {
				$opciones['fechajornada'] = (string) $data['fechajornada'];
			} elseif (! empty($data['venta_id'])) {
				$fechajornadaVenta = Venta::query()
					->whereKey((int) $data['venta_id'])
					->value('fechajornada');
				if ($fechajornadaVenta) {
					$opciones['fechajornada'] = (string) $fechajornadaVenta;
				}
			}
		}

		if (! empty($data['omitir_validacion'])) {
			$opciones['omitir_validacion'] = true;
		}

		PeriodoContableCierreSupport::assertOperacionPermitida(
			(int) $data['empresa_id'],
			(string) $data['fecha'],
			$alcance,
			null,
			$opciones
		);
	}

	private function inferirAlcanceCierre(array $data): string
	{
		if (! empty($data['cobranza_id'])) {
			return PeriodoContableCierreSupport::ALCANCE_COBRANZA;
		}

		if (! empty($data['caja_movimiento_id'])) {
			return PeriodoContableCierreSupport::ALCANCE_CAJA;
		}

		if (! empty($data['movimientostock_id'])) {
			$abrev = strtoupper((string) (
				MovimientoStock::query()
					->whereKey((int) $data['movimientostock_id'])
					->with('tipotransaccion_stock:id,abreviatura')
					->first()
					?->tipotransaccion_stock
					?->abreviatura
				?? ''
			));
			if ($abrev === 'EIND') {
				return PeriodoContableCierreSupport::ALCANCE_INDUMENTARIA;
			}

			return PeriodoContableCierreSupport::ALCANCE_STOCK;
		}

		if (! empty($data['venta_id'])) {
			return PeriodoContableCierreSupport::ALCANCE_FACTURACION;
		}

		if (! empty($data['recepcionproveedor_id'])) {
			return PeriodoContableCierreSupport::ALCANCE_RECEPCION_PROVEEDOR;
		}

		if (! empty($data['comprobante_proveedor_id'])) {
			return PeriodoContableCierreSupport::ALCANCE_STOCK;
		}

		if (! empty($data['tipoasiento_id'])) {
			$tipoasiento = $this->tipoasientoRepository->find($data['tipoasiento_id']);
			if ($tipoasiento) {
				return match (strtoupper((string) ($tipoasiento->abreviatura ?? ''))) {
					'VTA' => PeriodoContableCierreSupport::ALCANCE_FACTURACION,
					'TES', 'REM' => PeriodoContableCierreSupport::ALCANCE_CAJA,
					'COM', 'STK' => PeriodoContableCierreSupport::ALCANCE_STOCK,
					default => PeriodoContableCierreSupport::ALCANCE_CONTABLE,
				};
			}
		}

		return PeriodoContableCierreSupport::ALCANCE_CONTABLE;
	}
}
