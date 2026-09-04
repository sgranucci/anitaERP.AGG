<?php

namespace App\Repositories\Contable;

use App\Models\Contable\Asiento;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Ventas\Venta;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Contable\AsientoAlcanceCierreSupport;
use App\Support\Ventas\PedidoFacturacionProfiler;
use App\Support\Contable\AsientoAnitaNumeracionLock;
use App\Support\Contable\AsientoAnitaNumeracionSupport;
use App\Support\Contable\AsientoBalanceSupport;
use App\Support\Contable\AsientoCtamovRollbackSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaEmisorSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Numerico\NumeroDecimalLocalSupport;
use App\Support\Stock\RecepcionProveedorAnitaClaveSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
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

    /**
     * Siempre pisa el path. Si el caller no lo manda, vuelve a Anita default (Bierzo).
     * Un path Villafranca residual del asiento anterior no debe filtrar al siguiente.
     *
     * @param  array<string, mixed>  $data
     */
    private function asignarPathSistema(array $data): void
    {
        $path = $data['path_sistema'] ?? null;
        $this->path_sistema = (is_string($path) && trim($path) !== '')
            ? rtrim($path, '/')
            : null;
    }

    public function create(array $data)
    {
		$this->assertPeriodoContablePermitido($data);

		//$data['numeroasiento'] = self::ultimoAsiento($data['empresa_id']);
		$this->asignarPathSistema($data);
		
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
			// Registrar ANTES de ctamov: si guardarAnita inserta líneas y luego tira,
			// el afterRollBack de MySQL igual borra el huérfano en Informix.
			AsientoCtamovRollbackSupport::registrarSiHayTransaccion(
				(int) ($data['empresa_id'] ?? 0),
				(string) ($data['numeroasiento'] ?? ''),
			);
			PedidoFacturacionProfiler::etapa('asiento_ctamov_anita_inicio');
			self::guardarAnita($data);
			PedidoFacturacionProfiler::etapa('asiento_ctamov_anita_fin');
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

        $this->asignarPathSistema($data);

        $this->assertPeriodoContablePermitido($data);
        AsientoBalanceSupport::assertBalanceadoDesdePayload($data, 'asiento (Anita ctamov)');
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

	private function guardarAnita($request, int $intento = 1) 
	{
		// Graba asiento
		$this->assertPayloadCtamovGrabable($request);

		$maxIntentos = max(1, 1 + (int) config('contable.asiento_ctamov_reintentos_si_vacio', 1));
		$apiAnita = new ApiAnita();

		$centrocostos = $request['centrocosto_ids'] ?? [];
		$centrocostosPrev = $request['centrocosto_id_previo'] ?? [];
		$debes = NumeroDecimalLocalSupport::listaAFloat($request['debes'] ?? []);
		$haberes = NumeroDecimalLocalSupport::listaAFloat($request['haberes'] ?? []);
		$cuentacontables = $request['cuentacontable_ids'] ?? [];
		$observaciones = $request['observaciones'] ?? [];
		$moneda_ids = $request['moneda_ids'] ?? [];
		$cotizaciones = NumeroDecimalLocalSupport::listaAFloat($request['cotizaciones'] ?? []);

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

		if ($qMovimiento <= 0) {
			throw new \RuntimeException(
				'No se puede sincronizar ctamov: el asiento no tiene cuentas contables válidas.'
			);
		}

		$lineasInsertadas = 0;
		$insertsSinConfirmacion = 0;
		$debeEsperado = 0.0;
		$haberEsperado = 0.0;

		try {
			for ($i_movimiento=0; $i_movimiento < $qMovimiento; $i_movimiento++) 
			{
				$observacion = $this->descripcionCtamovConEmisor(
					(string) ($observaciones[$i_movimiento] ?? ''),
					$request['anita_emisor'] ?? null,
				);

				$d_h = null;
				$monto = 0.0;
				$debeLin = $debes[$i_movimiento] ?? 0.0;
				$haberLin = $haberes[$i_movimiento] ?? 0.0;

				if ($debeLin > 0)
				{
					$d_h = 'D';
					$monto = $debeLin;
				}

				if (($haberLin != 0 || $debeLin < 0) && $haberLin != 0)
				{
					$d_h = 'H';
					$monto = abs($haberLin + $debeLin);
				}

				// Línea sin importe: no grabar ctamov (evita ctav_d_h indefinido; no altera líneas con debe/haber válidos).
				if ($d_h === null) {
					continue;
				}

				$cuenta = $this->cuentacontableRepository->findPorId($cuentacontables[$i_movimiento] ?? null);
				if ($cuenta)
					$cuentacontable = $cuenta->codigo;
				else
					throw new \RuntimeException(
						'No se puede sincronizar ctamov: cuenta contable inexistente en la línea '.($i_movimiento + 1).'.'
					);

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
        		$respuestaInsert = $apiAnita->apiCallEscritura($data, 'asiento_ctamov_insert');
				if (! ApiAnita::respuestaBridgeEscrituraExitosa($respuestaInsert)) {
					$insertsSinConfirmacion++;
					Log::warning('asiento_ctamov.insert_sin_confirmacion_filas', [
						'empresa' => $codigoEmpresa,
						'numeroasiento' => $request['numeroasiento'] ?? null,
						'nro_linea' => $i_movimiento,
						'intento' => $intento,
						'respuesta' => mb_substr(trim((string) $respuestaInsert), 0, 200),
					]);
				}
				$lineasInsertadas++;
				if ($d_h === 'D') {
					$debeEsperado += abs((float) $monto);
				} else {
					$haberEsperado += abs((float) $monto);
				}
			}

			if ($lineasInsertadas <= 0) {
				throw new \RuntimeException(
					'No se puede sincronizar ctamov: no se insertó ninguna línea.'
				);
			}

			// El bridge puede responder sin error y no persistir nada. Leer de vuelta
			// evita confirmar el asiento ERP con ctamov vacío (incidente TM#467).
			$dataVerificacion = [
				'acc' => 'list',
				'tabla' => $this->tableAnita[0],
				'sistema' => 'contab',
				'campos' => 'ctav_nro_linea,ctav_d_h,ctav_importe,ctav_desc_mov',
				'whereArmado' => " WHERE ctav_empresa = '".$codigoEmpresa
					."' AND ctav_nro_asiento = '".(string) $request['numeroasiento']."'",
				'orderBy' => 'ctav_nro_linea',
			];
			if (isset($this->path_sistema)) {
				$dataVerificacion['path_sistema'] = $this->path_sistema;
			}
			$respuestaVerificacion = $apiAnita->apiCall($dataVerificacion);
			$verificacion = ApiAnita::parsearRespuestaLista((string) $respuestaVerificacion);
			if ($verificacion['error_lectura'] !== null) {
				throw new \RuntimeException(
					'No se pudo verificar ctamov después de grabar: '.$verificacion['error_lectura']
				);
			}

			$filasVerificadas = $verificacion['filas'];
			$debeVerificado = 0.0;
			$haberVerificado = 0.0;
			foreach ($filasVerificadas as $fila) {
				$importe = abs((float) ($fila->ctav_importe ?? 0));
				if (strtoupper(trim((string) ($fila->ctav_d_h ?? ''))) === 'D') {
					$debeVerificado += $importe;
				} else {
					$haberVerificado += $importe;
				}
			}

			$verificacionOk = count($filasVerificadas) === $lineasInsertadas
				&& abs($debeVerificado - $debeEsperado) < 0.01
				&& abs($haberVerificado - $haberEsperado) < 0.01;

			if (! $verificacionOk) {
				$detalle = sprintf(
					'esperado %d líneas D %.2f H %.2f; leído %d líneas D %.2f H %.2f',
					$lineasInsertadas,
					$debeEsperado,
					$haberEsperado,
					count($filasVerificadas),
					$debeVerificado,
					$haberVerificado
				);

				// Bridge “OK” vacío / Anita ocupado: borrar restos y reintentar una vez.
				if ($intento < $maxIntentos) {
					Log::warning('asiento_ctamov.verificacion_fallida_reintento', [
						'empresa' => $codigoEmpresa,
						'numeroasiento' => $request['numeroasiento'] ?? null,
						'intento' => $intento,
						'max_intentos' => $maxIntentos,
						'inserts_sin_confirmacion' => $insertsSinConfirmacion,
						'detalle' => $detalle,
					]);
					try {
						$this->eliminarAnita($codigoEmpresa, (string) $request['numeroasiento']);
					} catch (\Throwable $cleanupEx) {
						Log::warning('asiento_ctamov.reintento_cleanup_fallo', [
							'empresa' => $codigoEmpresa,
							'numeroasiento' => $request['numeroasiento'] ?? null,
							'error' => $cleanupEx->getMessage(),
						]);
					}
					usleep(300000);

					return $this->guardarAnita($request, $intento + 1);
				}

				$msg = 'Verificación ctamov fallida: '.$detalle.'.';
				if (count($filasVerificadas) === 0) {
					$msg .= ' El bridge no persistió las líneas (Anita ocupado o respuesta vacía). Reintente en unos segundos.';
				}

				throw new \RuntimeException($msg);
			}
		} catch (\Throwable $e) {
			// Sin TX Informix: si falló a mitad, borrar lo ya insertado para no dejar ctamov desbalanceado.
			if ($lineasInsertadas > 0) {
				try {
					$this->eliminarAnita($codigoEmpresa, (string) $request['numeroasiento']);
				} catch (\Throwable $cleanupEx) {
					Log::warning('asiento_ctamov.cleanup_parcial_fallo', [
						'empresa' => $codigoEmpresa,
						'numeroasiento' => $request['numeroasiento'] ?? null,
						'lineas_insertadas' => $lineasInsertadas,
						'error_original' => $e->getMessage(),
						'error_cleanup' => $cleanupEx->getMessage(),
					]);
				}
			}

			throw $e;
		}

		return 'Success';
	}

	/**
	 * ctav_desc_mov tiene 30 caracteres y no hay campo emisor en ctamov.
	 * El código del proveedor va primero para que el mayor (Anita y ERP) lo vea.
	 *
	 * Solo A-Z, 0-9 y espacio: evita que comillas, pipes u otros caracteres
	 * rompan el VALUES del bridge y disparen "1213 Character to numeric conversion".
	 */
	private function descripcionCtamovConEmisor(string $observacion, mixed $emisor): string
	{
		$texto = $this->sanitizarDescripcionCtamov($observacion);
		$codigo = MayorPlanoCuentaEmisorSupport::normalizarCodigo((string) $emisor);
		$codigo = $this->sanitizarDescripcionCtamov($codigo);
		if ($codigo !== '' && ! preg_match('/^'.preg_quote($codigo, '/').'\b/', $texto)) {
			$texto = trim($codigo.' '.$texto);
		}

		return mb_substr($texto, 0, 30);
	}

	/**
	 * Texto seguro para ctav_desc_mov en INSERT Informix vía bridge (valores entre comillas).
	 * Permite . % - (paridad p-vtabingo: «Dev. pozo acum.», «Canon … 4%»).
	 */
	private function sanitizarDescripcionCtamov(string $texto): string
	{
		// Quitar comillas, pipes, acentos y saltos; conservar letra/dígito/espacio/.%- .
		$limpio = preg_replace('/[^A-Za-z0-9 .%\\-]+/u', '', $texto) ?? '';
		$limpio = trim((string) preg_replace('/\s+/u', ' ', $limpio));

		// Por si quedó alguna comilla: duplicar estilo SQL (Informix).
		return str_replace("'", "''", $limpio);
	}

	private function assertPayloadCtamovGrabable(array $request): void
	{
		if (! isset($request['cuentacontable_ids'])
			|| ! is_array($request['cuentacontable_ids'])
			|| $request['cuentacontable_ids'] === []) {
			throw new \RuntimeException(
				'No se puede sincronizar ctamov: el asiento no tiene líneas contables.'
			);
		}

		$debes = $request['debes'] ?? [];
		$haberes = $request['haberes'] ?? [];
		$lineasConImporte = 0;

		foreach ($request['cuentacontable_ids'] as $i => $cuentaId) {
			if ((int) $cuentaId <= 0) {
				throw new \RuntimeException(
					'No se puede sincronizar ctamov: cuenta contable inválida en la línea '.($i + 1).'.'
				);
			}
			if (! $this->cuentacontableRepository->findPorId((int) $cuentaId)) {
				throw new \RuntimeException(
					'No se puede sincronizar ctamov: cuenta contable inexistente en la línea '.($i + 1).'.'
				);
			}

			if (NumeroDecimalLocalSupport::aFloat($debes[$i] ?? 0) > 0
				|| NumeroDecimalLocalSupport::aFloat($haberes[$i] ?? 0) > 0) {
				$lineasConImporte++;
			}
		}

		if ($lineasConImporte <= 0) {
			throw new \RuntimeException(
				'No se puede sincronizar ctamov: ninguna línea tiene importe.'
			);
		}

		AsientoBalanceSupport::assertBalanceadoDesdePayload(
			$request,
			'asiento (Anita ctamov)'
		);
	}

	private function actualizarAnita($request) 
	{
		// Validar antes del delete: un payload vacío nunca debe borrar el ctamov vigente.
		$this->assertPayloadCtamovGrabable(is_array($request) ? $request : (array) $request);

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
        $apiAnita->apiCallEscritura($data, 'asiento_ctamov_delete');

		try {
			Self::guardarAnita($request);
		} catch (\Throwable $e) {
			// Si el reinsert falló a mitad, guardarAnita ya intentó limpiar; reforzar delete.
			try {
				$this->eliminarAnita($codigoEmpresa, (string) $request['numeroasiento']);
			} catch (\Throwable $cleanupEx) {
				Log::warning('asiento_ctamov.reinsert_cleanup_fallo', [
					'empresa' => $codigoEmpresa,
					'numeroasiento' => $request['numeroasiento'] ?? null,
					'error_original' => $e->getMessage(),
					'error_cleanup' => $cleanupEx->getMessage(),
				]);
			}
			throw $e;
		}

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
		return AsientoAnitaNumeracionLock::conExclusividad((int) $empresa_id, function () use ($empresa_id) {
			return $this->reservarNumeroAsientoAnita((int) $empresa_id);
		});
	}

	/**
	 * Reserva el próximo número en el numerador Anita y garantiza que ctamov
	 * no tenga ya líneas para ese nro (salta ocupados dejados por Anita nativo).
	 */
	private function reservarNumeroAsientoAnita(int $empresa_id)
	{
		$empresa = $this->empresaRepository->find($empresa_id);
		$codigoEmpresa = $empresa ? $empresa->codigo : $empresa_id;

		$candidato = $this->leerSiguienteCandidatoNumeradorAnita($codigoEmpresa);
		$eleccion = AsientoAnitaNumeracionSupport::siguienteLibre(
			$candidato,
			fn (int $nro) => $this->ctamovTieneLineas($codigoEmpresa, $nro),
		);

		$this->persistirNumeradorAnita($codigoEmpresa, $eleccion['numero']);

		if ($eleccion['saltados'] !== []) {
			Log::warning('asiento_anita.numeracion.salto_ocupados', [
				'empresa_id' => $empresa_id,
				'codigo_empresa' => $codigoEmpresa,
				'candidato_inicial' => $candidato,
				'asignado' => $eleccion['numero'],
				'saltados' => $eleccion['saltados'],
			]);
		}

		return $eleccion['numero'];
	}

	/**
	 * Lee numabm / numerador y devuelve el candidato (último + 1) sin persistir.
	 */
	private function leerSiguienteCandidatoNumeradorAnita(int|string $codigoEmpresa): int
	{
		$apiAnita = new ApiAnita();

		if (strtoupper(config('app.empresa')) == 'EL BIERZO') {
			$data = [
				'acc' => 'list',
				'tabla' => 'numerador',
				'sistema' => 'ventas',
				'campos' => 'num_ult_numero',
				'whereArmado' => " WHERE num_clave='501'",
			];
			if (isset($this->path_sistema)) {
				$data['path_sistema'] = $this->path_sistema;
			}
			$parsed = ApiAnita::parsearRespuestaLista((string) $apiAnita->apiCall($data));
			if ($parsed['error_lectura'] !== null || $parsed['filas'] === []) {
				throw new \RuntimeException(
					'No se pudo leer numerador Anita (clave 501): '
					.($parsed['error_lectura'] ?? 'sin filas')
				);
			}

			return (int) ($parsed['filas'][0]->num_ult_numero ?? 0) + 1;
		}

		$data = [
			'acc' => 'list',
			'tabla' => 'numabm',
			'sistema' => 'shared',
			'campos' => 'numa_ult_numero',
			'whereArmado' => " WHERE numa_sistema='contab' and numa_programa='a-ctamov.c' and numa_referencia='"
				.str_replace("'", "''", (string) $codigoEmpresa)."'",
		];
		if (isset($this->path_sistema)) {
			$data['path_sistema'] = $this->path_sistema;
		}
		$parsed = ApiAnita::parsearRespuestaLista((string) $apiAnita->apiCall($data));
		if ($parsed['error_lectura'] !== null || $parsed['filas'] === []) {
			throw new \RuntimeException(
				'No se pudo leer numabm Anita (a-ctamov.c emp '.$codigoEmpresa.'): '
				.($parsed['error_lectura'] ?? 'sin filas')
			);
		}

		return (int) ($parsed['filas'][0]->numa_ult_numero ?? 0) + 1;
	}

	private function persistirNumeradorAnita(int|string $codigoEmpresa, int $numeroAsignado): void
	{
		$apiAnita = new ApiAnita();

		if (strtoupper(config('app.empresa')) == 'EL BIERZO') {
			$data = [
				'acc' => 'update',
				'tabla' => 'numerador',
				'sistema' => 'ventas',
				'valores' => " num_ult_numero = '".$numeroAsignado."' ",
				'whereArmado' => " WHERE num_clave='501'",
			];
		} else {
			$data = [
				'acc' => 'update',
				'tabla' => 'numabm',
				'sistema' => 'shared',
				'valores' => " numa_ult_numero = '".$numeroAsignado."' ",
				'whereArmado' => " WHERE numa_sistema='contab' and numa_programa='a-ctamov.c' and numa_referencia='"
					.str_replace("'", "''", (string) $codigoEmpresa)."'",
			];
		}
		if (isset($this->path_sistema)) {
			$data['path_sistema'] = $this->path_sistema;
		}
		$apiAnita->apiCallEscritura($data, 'asiento_numerador_reservar');
	}

	/**
	 * true si Informix ya tiene al menos una línea ctamov para empresa+nro.
	 */
	private function ctamovTieneLineas(int|string $codigoEmpresa, int $nroAsiento): bool
	{
		$apiAnita = new ApiAnita();
		$data = [
			'acc' => 'list',
			'tabla' => $this->tableAnita[0],
			'sistema' => 'contab',
			'campos' => 'ctav_nro_asiento',
			'whereArmado' => " WHERE ctav_empresa = '".str_replace("'", "''", (string) $codigoEmpresa)."'"
				." AND ctav_nro_asiento = '".(int) $nroAsiento."'",
		];
		if (isset($this->path_sistema)) {
			$data['path_sistema'] = $this->path_sistema;
		}
		$parsed = ApiAnita::parsearRespuestaLista((string) $apiAnita->apiCall($data));
		if ($parsed['error_lectura'] !== null) {
			throw new \RuntimeException(
				'No se pudo verificar ocupación de ctamov asiento '.$nroAsiento.': '.$parsed['error_lectura']
			);
		}

		return count($parsed['filas']) > 0;
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
			(int) (Auth::id() ?? 0) ?: null,
			$opciones
		);
	}

	private function inferirAlcanceCierre(array $data): string
	{
		return AsientoAlcanceCierreSupport::inferir($data);
	}
}
