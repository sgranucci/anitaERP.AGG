<?php
namespace App\Services\Presupuesto;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Repositories\Presupuesto\PartidagastoRepositoryInterface;
use App\Repositories\Presupuesto\Partidagasto_EstadoRepositoryInterface;
use App\Repositories\Presupuesto\Partidagasto_ArchivoRepositoryInterface;
use App\Repositories\Presupuesto\Partidagasto_MontoRepositoryInterface;
use App\Repositories\Presupuesto\PresupuestoRepositoryInterface;
use App\Repositories\Presupuesto\Presupuesto_EscenarioRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Repositories\Stock\ArticuloRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Models\Presupuesto\Partidagasto_Estado;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App;
use Auth;
use DB;
use Exception;
use App\ApiAnita;

class PartidagastoService 
{
	private $partidagastoRepository;
    private $partidagasto_estadoRepository;
    private $partidagasto_archivoRepository;
	private $partidagasto_montoRepository;
	private $tipoasientoRepository;
	private $centrocostoRepository;
	private $proveedorRepository;
	private $articuloRepository;
	private $monedaRepository;
	private $cuentacontableRepository;
	private $presupuestoRepository;
	private $presupuesto_escenarioRepository;
	private $empresaRepository;
	private $asientoRepository;
	private $asiento_movimientoRepository;	

    public function __construct(PartidagastoRepositoryInterface $partidagastorepository,
                                Partidagasto_EstadoRepositoryInterface $partidagasto_estadorepository,
                                Partidagasto_ArchivoRepositoryInterface $partidagasto_archivorepository,
								Partidagasto_MontoRepositoryInterface $partidagasto_montorepository,
								TipoasientoRepositoryInterface $tipoasientorepository,
								PresupuestoRepositoryInterface $presupuestorepository,
								Presupuesto_EscenarioRepositoryInterface $presupuesto_escenariorepository,
								CentrocostoRepositoryInterface $centrocostorepository,
								ProveedorRepositoryInterface $proveedorrepository,
								CuentacontableRepositoryInterface $cuentacontableRepository,
								ArticuloRepositoryInterface $articuloRepository,
								MonedaRepositoryInterface $monedaRepository,
								AsientoRepositoryInterface $asientorepository,
								Asiento_MovimientoRepositoryInterface $asiento_movimientorepository,								
								EmpresaRepositoryInterface $empresaRepository
								)
    {
		$this->partidagastoRepository = $partidagastorepository;
        $this->partidagasto_estadoRepository = $partidagasto_estadorepository;
        $this->partidagasto_archivoRepository = $partidagasto_archivorepository;
		$this->partidagasto_montoRepository = $partidagasto_montorepository;
		$this->tipoasientoRepository = $tipoasientorepository;
		$this->presupuestoRepository = $presupuestorepository;
		$this->presupuesto_escenarioRepository = $presupuesto_escenariorepository;
		$this->centrocostoRepository = $centrocostorepository;
		$this->proveedorRepository = $proveedorrepository;
		$this->cuentacontableRepository = $cuentacontableRepository;
		$this->articuloRepository = $articuloRepository;
		$this->monedaRepository = $monedaRepository;
		$this->empresaRepository = $empresaRepository;
		$this->asientoRepository= $asientorepository;
		$this->asiento_movimientoRepository= $asiento_movimientorepository;		
    }

	public function guardaPartidagasto($request, $origen = null)
	{
		$data = $request->all();

   		// Crea estado
	   	$data['fechas'][] = Carbon::now();
	   	$data['estados'][] = Partidagasto_Estado::$enumEstado[array_search('A', array_column(Partidagasto_Estado::$enumEstado, 'valor'))]['nombre'];
		$data['usuario_ids'][] = Auth::user()->id;
	   	$data['observacionestados'][] = "Alta de Partida de Gasto";

		$data['creousuario_id'] = Auth::user()->id;

		DB::beginTransaction();
		try
		{
			$partidagasto = $this->partidagastoRepository->create($data);

			if ($partidagasto == 'Error')
				throw new Exception('Error en grabacion');

			// Guarda tablas asociadas
			if ($partidagasto)
			{
				Self::agrega($data, $partidagasto, $request);

				$anita = Self::grabaAnita($data);

				if (isset($anita['error']))
					throw new Exception('Error en grabacion anita. '.$anita['mensaje']);
			}

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();
			dd($e->getMessage());
			return ['mensaje' => 'error', 'errores' => $e->getMessage()];
		}
        return ['mensaje' => 'ok'];
	}

	// Agrega tablas asociadas
	private function agrega(&$data, $partidagasto, $request)
	{
		$partidagasto_estado = $this->partidagasto_estadoRepository->create($data, $partidagasto->id);
		$partidagasto_archivo = $this->partidagasto_archivoRepository->create($request, $partidagasto->id);
		$partidagasto_monto = $this->partidagasto_montoRepository->create($data, $partidagasto->id);
	}

    public function actualizaPartidagasto($request, $id, $origen = null)
    {
		$data = $request->all();

		DB::beginTransaction();
		try
		{
			Self::actualiza($data, $id, $request);

			$anita = Self::actualizaAnita($data);

			if (isset($anita['error']))
				throw new Exception('Error en actualización anita. '.$anita['mensaje']);

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();
			dd($e->getMessage());
			return ['mensaje' => 'error', 'errores' => $e->getMessage()];
		}
        return ['mensaje' => 'ok'];
    }

	private function actualiza(&$data, $id, $request)
	{
		// Graba partidagasto
		$partidagasto = $this->partidagastoRepository->update($data, $id);

		if ($partidagasto === 'Error')
			throw new Exception('Error en grabacion partida de gasto.');

		// Graba movimientos de archivos
		$this->partidagasto_archivoRepository->update($request, $id);

		$this->partidagasto_montoRepository->update($data, $id);
	}

	public function actualizaEstadoPartidagasto($estado, $id)
	{
		DB::beginTransaction();
		try
		{
			$partidagasto = $this->partidagastoRepository->update($estado, $id);

			// Crea estado
			if (isset($estado['estado']))
			{
				$data = [];
				$data['fechas'][] = Carbon::now();
				$data['usuario_ids'][] = Auth::user()->id;

				switch($estado['estado'])
			 	{
				case 'ANULADA':
					$data['observacionestados'][] = "Anulación de Partida de Gasto";
					$data['estados'][] = Partidagasto_Estado::$enumEstado[array_search('B', array_column(Partidagasto_Estado::$enumEstado, 'valor'))]['nombre'];
					break;
				case 'CERRADA':
					$data['observacionestados'][] = "Cierre de Partida de Gasto";
					$data['estados'][] = Partidagasto_Estado::$enumEstado[array_search('C', array_column(Partidagasto_Estado::$enumEstado, 'valor'))]['nombre'];
					break;
				case 'ACTIVA':
					$data['observacionestados'][] = "Activación de Partida de Gasto";
					$data['estados'][] = Partidagasto_Estado::$enumEstado[array_search('A', array_column(Partidagasto_Estado::$enumEstado, 'valor'))]['nombre'];
				}

				$data['creousuario_id'][] = Auth::user()->id;

				$partidagasto_estado = $this->partidagasto_estadoRepository->create($data, $id);
			}			

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();

			return ['mensaje' => 'error', 'errores' => $e->getMessage()];
		}		
	}

	public function leeHistoriaPartidagasto($partidagasto_id)
	{
		return $this->partidagasto_estadoRepository->leeHistoriaPartidagasto($partidagasto_id);
	}

	// Graba partidagasto en Anita
	public function grabaAnita($data, $servidor = null, $ifx_server = null)
	{
		// Lee centro de costo
		$centrocosto = $this->centrocostoRepository->find($data['centrocosto_id']);

		$codigoCentroCosto = 0;
		if ($centrocosto)
			$codigoCentroCosto = $centrocosto->codigo;

		// Lee el presupuesto
		$presupuesto = $this->presupuestoRepository->find($data['presupuesto_id']);

		$codigoPresupuesto = 0;
		$codigoEscenario = 0;
		if ($presupuesto)
		{
			$codigoPresupuesto = $presupuesto->codigo;

			if ($presupuesto->presupuesto_escenarios)
				$codigoEscenario = $presupuesto->presupuesto_escenarios->codigo;
		}

		// Busca en todo el array los montos cargados
		$montoMes = [];
		$montoTotal = 0;
		for ($j = 0; $j <= 12; $j++)
			$montoMes[$j] = 0;			
		if (isset($data['periodos']))
		{
			for ($j = 0; $j < count($data['periodos']); $j++)
			{
				$periodo = $data['periodos'][$j] ?? '';
				$monto = (float) ($data['montos'][$j] ?? 0);
				$mes = (int) substr(str_replace('/', '-', $periodo), 5, 2);

				$montoMes[$mes] += $monto;
				$montoTotal += $monto;
			}
		}

		// Lee el articulo
		$articulo = $this->articuloRepository->find($data['articulo_id']);

		$codigoArticulo = '';
		if ($articulo)
			$codigoArticulo = $articulo->sku;

		// Lee la cuenta contable
		$cuentacontable = $this->cuentacontableRepository->find($data['cuentacontable_id']);

		$codigoCuentaContable = '';
		if ($cuentacontable)
			$codigoCuentaContable = $cuentacontable->codigo;

		// Lee el proveedor
		$proveedor = $this->proveedorRepository->find($data['proveedor_id']);

		$codigoProveedor = '';
		if ($proveedor)
			$codigoProveedor = $proveedor->codigo;

		// Graba proyectopartidagasto
        $apiAnita = new ApiAnita();

		$grabaAnita = array( 	'tabla' => 'partidas', 
						'acc' => 'insert',
						'sistema' => 'base_admin',
            			'campos' => ' 
							ipartidaid,
							ipresupuestoid,
							iempresaid,
							iusuarioid,
							iccosid,
							bmodificado,
							ccodigomoneda,
							fimportetotal,
							carticulo,
							icuentacontableid,
							bllevaproveedor,
							cproveedor,
							ccomentario,
							fmontoenero,
							fmontofebrero,  
							fmontomarzo,     
							fmontoabril,     
							fmontomayo,      
							fmontojunio,     
							fmontojulio,    
							fmontoagosto,    
							fmontoseptiembre,
							fmontooctubre,   
							fmontonoviembre,
							fmontodiciembre,
							icondicioniva,   
							imontoiva,      
							ifechaalta,      
							choraalta,       
							iindiceid,       
							iescenarioid,    
							fcoeficiente',
            			'valores' => " 
							'".$data['codigo']."', 
							'".$data['presupuesto_id']."', 
							'".$data['empresa_id']."',
							'".Auth::user()->id."',
							'".$codigoCentroCosto."',
							'".'0'."',
							'".$data['moneda_id']."',
							'".$montoTotal."',
							'".str_pad($codigoArticulo, 13, "0", STR_PAD_LEFT)."',
							'".$codigoCuentaContable."',
							'".'0',"',
							'".str_pad($codigoProveedor, 6, "0", STR_PAD_LEFT)."',
							'".$data['detalle']."',
							'".$montoMes[1]."',
							'".$montoMes[2]."',
							'".$montoMes[3]."',
							'".$montoMes[4]."',
							'".$montoMes[5]."',
							'".$montoMes[6]."',
							'".$montoMes[7]."',
							'".$montoMes[8]."',
							'".$montoMes[9]."',
							'".$montoMes[10]."',
							'".$montoMes[11]."',
							'".$montoMes[12]."',
							'".'0'."',
							'".'0'."',
							'".date_format(Carbon::now(), 'Ymd')."',
							'".date_format(Carbon::now(), 'H:i')."',
							'".'0'."',
							'".$codigoEscenario."',
							'".'1'."'"
					);

        $proyectopartidagasto = $apiAnita->apiCallEscritura($grabaAnita);

		return ['Success'];
	}

	// Graba partidagasto en Anita
	public function actualizaAnita($data, $servidor = null, $ifx_server = null)
	{
		// Lee centro de costo
		$centrocosto = $this->centrocostoRepository->find($data['centrocosto_id']);

		$codigoCentroCosto = 0;
		if ($centrocosto)
			$codigoCentroCosto = $centrocosto->codigo;

		// Lee el presupuesto
		$presupuesto = $this->presupuestoRepository->find($data['presupuesto_id']);

		$codigoPresupuesto = 0;
		$codigoEscenario = 0;
		if ($presupuesto)
		{
			$codigoPresupuesto = $presupuesto->codigo;

			if ($presupuesto->presupuesto_escenarios)
				$codigoEscenario = $presupuesto->presupuesto_escenarios[0]->codigo;
		}
		
		// Busca en todo el array los items que corresponden a cada partida
		$montoMes = [];
		$montoTotal = 0;
		for ($j = 0; $j <= 12; $j++)
			$montoMes[$j] = 0;		
		if (isset($data['periodos']))
		{
			for ($j = 0; $j < count($data['periodos']); $j++)
			{
				$periodo = $data['periodos'][$j]
					?? $data['periodo_monto_armados'][$j]
					?? '';
				$monto = (float) ($data['montos'][$j] ?? $data['monto_armados'][$j] ?? 0);
				$mes = (int) substr(str_replace('/', '-', $periodo), 5, 2);

				$montoMes[$mes] += $monto;
				$montoTotal += $monto;
			}
		}		

		// Lee el articulo
		$articulo = $this->articuloRepository->find($data['articulo_id']);

		$codigoArticulo = '';
		if ($articulo)
			$codigoArticulo = $articulo->sku;

		// Lee la cuenta contable
		$cuentacontable = $this->cuentacontableRepository->find($data['cuentacontable_id']);

		$codigoCuentaContable = '';
		if ($cuentacontable)
			$codigoCuentaContable = $cuentacontable->codigo;

		// Lee el proveedor
		$proveedor = $this->proveedorRepository->find($data['proveedor_id']);

		$codigoProveedor = '';
		if ($proveedor)
			$codigoProveedor = $proveedor->codigo;

		// Graba proyectopartidagasto
        $apiAnita = new ApiAnita();

		$grabaAnita = array( 	'tabla' => 'partidas', 
						'acc' => 'update',
						'sistema' => 'base_admin',
            			'valores' => "
							ipresupuestoid = '".$codigoPresupuesto."',
							iempresaid = '".$data['empresa_id']."',
							iusuarioid = '".Auth::user()->id."',
							iccosid = '".$codigoCentroCosto."',
							ccodigomoneda = '".$data['moneda_id']."',
							fimportetotal = '".$montoTotal."',
							carticulo = '".str_pad($codigoArticulo, 13, "0", STR_PAD_LEFT)."',
							icuentacontableid = '".$codigoCuentaContable."',
							cproveedor = '".str_pad($codigoProveedor, 6, "0", STR_PAD_LEFT)."',
							ccomentario = '".$data['detalle']."',
							fmontoenero = '".$montoMes[1]."',
							fmontofebrero = '".$montoMes[2]."',
							fmontomarzo = '".$montoMes[3]."',
							fmontoabril = '".$montoMes[4]."',
							fmontomayo = '".$montoMes[5]."',
							fmontojunio = '".$montoMes[6]."',
							fmontojulio = '".$montoMes[7]."',
							fmontoagosto = '".$montoMes[8]."',
							fmontoseptiembre = '".$montoMes[9]."',
							fmontooctubre = '".$montoMes[10]."', 
							fmontonoviembre = '".$montoMes[11]."',
							fmontodiciembre = '".$montoMes[12]."',
							iescenarioid = '".$codigoEscenario."' ",
						'whereArmado' => " WHERE ipartidaid ='".$data['codigo']."' "
					);
        $proyectopartidagasto = $apiAnita->apiCallEscritura($grabaAnita);


		return ['Success'];
	}

	// Borra partidagasto en Anita
	public function borraAnita($data)
	{
        $apiAnita = new ApiAnita();
        $grabaAnita = array( 'acc' => 'delete', 
						'sistema' => 'base_admin',
						'tabla' => 'partidas', 
						'whereArmado' => " WHERE ipartidaid='".$data['codigo']."' "
						);

		$proyectopartidagasto = $apiAnita->apiCallEscritura($grabaAnita);

	}

	public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => 'ipartidaid',
						'tabla' => 'partidas',
						'sistema' => 'base_admin',
						'whereArmado' => " WHERE ipresupuestoid = 53670" );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		foreach ($dataAnita as $value) 
		{
			$this->traerRegistroDeAnita($value->ipartidaid);
		}
    }

    public function traerRegistroDeAnita($key){

        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 
			'tabla' => 'partidas', 
			'sistema' => 'base_admin',
            'campos' => '
					ipartidaid,
					ipresupuestoid,
					iempresaid,
					iusuarioid,
					iccosid,
					bmodificado,
					ccodigomoneda,
					fimportetotal,
					carticulo,
					icuentacontableid,
					bllevaproveedor,
					cproveedor,
					ccomentario,
					fmontoenero,
					fmontofebrero,  
					fmontomarzo,     
					fmontoabril,     
					fmontomayo,      
					fmontojunio,     
					fmontojulio,    
					fmontoagosto,    
					fmontoseptiembre,
					fmontooctubre,   
					fmontonoviembre,
					fmontodiciembre,
					icondicioniva,   
					imontoiva,      
					ifechaalta,      
					choraalta,       
					iindiceid,       
					iescenarioid,    
					fcoeficiente',
            'whereArmado' => " WHERE ipartidaid ='".$key."' "
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $dataPartidagasto = $dataAnita[0];

			$presupuesto = $this->presupuestoRepository->findPorCodigo($dataPartidagasto->ipresupuestoid);
			
			if ($presupuesto)
				$presupuesto_id = $presupuesto->id;	
			else
				$presupuesto_id = null;

			$presupuesto_escenario = $this->presupuesto_escenarioRepository->findPorCodigo($dataPartidagasto->iescenarioid);
			
			if ($presupuesto_escenario)
				$presupuesto_escenario_id = $presupuesto_escenario->id;	
			else
				$presupuesto_escenario_id = null;

			$centrocosto = $this->centrocostoRepository->findPorCodigo($dataPartidagasto->iccosid);
			
			if ($centrocosto)
				$centrocosto_id = $centrocosto->id;	
			else
				$centrocosto_id = null;

			$proveedor = $this->proveedorRepository->findPorCodigo(ltrim($dataPartidagasto->cproveedor,'0'));
			
			if ($proveedor)
				$proveedor_id = $proveedor->id;	
			else
				$proveedor_id = null;

			$articulo = $this->articuloRepository->findPorSku(ltrim($dataPartidagasto->carticulo,'0'));
			
			if ($articulo)
				$articulo_id = $articulo->id;	
			else
				$articulo_id = null;

			$cuentacontable = $this->cuentacontableRepository->findPorCodigo($dataPartidagasto->iempresaid, $dataPartidagasto->icuentacontableid);
			
			if ($cuentacontable)
				$cuentacontable_id = $cuentacontable->id;	
			else
				$cuentacontable_id = null;

			$arrayCampos = [
				'empresa_id' => $dataPartidagasto->iempresaid, 
				'presupuesto_id' => $presupuesto_id, 
				'presupuesto_escenario_id' => $presupuesto_escenario_id,
				'centrocosto_id' => $centrocosto_id, 
				'moneda_id' => $dataPartidagasto->ccodigomoneda,
				'cuentacontable_id' => $cuentacontable_id,
				'articulo_id' => $articulo_id,
				'proveedor_id' => $proveedor_id,
				'detalle' => $dataPartidagasto->ccomentario, 
				'estado' => 'ACTIVA', 
				'codigo' => $dataPartidagasto->ipartidaid, 
				'creousuario_id' => $usuario_id
			];

			$partidagasto = $this->partidagastoRepository->createDesdeAnita($arrayCampos);

			// Graba montos mensuales
			for ($i = 1; $i <= 12; $i++)
			{
				$monto = 0;
				switch($i)
				{
					case 1:
						$monto = $dataPartidagasto->fmontoenero;
						$periodo = $presupuesto->anio.'-01';
						break;
					case 2:
						$monto = $dataPartidagasto->fmontofebrero;
						$periodo = $presupuesto->anio.'-02';
						break;
					case 3:
						$monto = $dataPartidagasto->fmontomarzo;
						$periodo = $presupuesto->anio.'-03';							
						break;
					case 4:
						$monto = $dataPartidagasto->fmontoabril;
						$periodo = $presupuesto->anio.'-04';							
						break;
					case 5:
						$monto = $dataPartidagasto->fmontomayo;
						$periodo = $presupuesto->anio.'-05';							
						break;
					case 6:
						$monto = $dataPartidagasto->fmontojunio;
						$periodo = $presupuesto->anio.'-06';
						break;
					case 7:
						$monto = $dataPartidagasto->fmontojulio;
						$periodo = $presupuesto->anio.'-07';							
						break;
					case 8:
						$monto = $dataPartidagasto->fmontoagosto;
						$periodo = $presupuesto->anio.'-08';							
						break;
					case 9:
						$monto = $dataPartidagasto->fmontoseptiembre;
						$periodo = $presupuesto->anio.'-09';							
						break;
					case 10:
						$monto = $dataPartidagasto->fmontooctubre;
						$periodo = $presupuesto->anio.'-10';							
						break;
					case 11:
						$monto = $dataPartidagasto->fmontonoviembre;
						$periodo = $presupuesto->anio.'-11';							
						break;
					case 12:
						$monto = $dataPartidagasto->fmontodiciembre;
						$periodo = $presupuesto->anio.'-12';							
						break;
				}
				
				if ($monto != 0)
				{
					$arrayCampos = [
							'partidagasto_id' => $partidagasto->id, 
							'periodo' => $periodo, 
							'monto' => $monto, 
							'creousuario_id' => $usuario_id
						];

					$partidagasto_monto = $this->partidagasto_montoRepository->createUnique($arrayCampos);
				}
			}

			// Crea estado
			$data = [];
			$data['fechas'][] = Carbon::now();
			$data['estados'][] = Partidagasto_Estado::$enumEstado[array_search('A', array_column(Partidagasto_Estado::$enumEstado, 'valor'))]['nombre'];
			$data['usuario_ids'][] = Auth::user()->id;
			$data['observacionestados'][] = "Alta de Partida de Gasto desde Anita";

			$data['creousuario_id'] = Auth::user()->id;

			$partidagasto_monto = $this->partidagasto_estadoRepository->create($data, $partidagasto->id);
		}
	}

	public function generaAsiento($empresa_id, $presupuesto_id, $presupuesto_escenario_id)
	{
		$data = $this->partidagastoRepository->leePartidaGasto($empresa_id, $presupuesto_id, $presupuesto_escenario_id);

		// Busca tipo de asiento de tesoreria
		$tipoasiento = $this->tipoasientoRepository->findPorAbreviatura('PRE');

		if ($tipoasiento)
			$arrayAsiento['tipoasiento_id'] = $tipoasiento->id;
		else
			throw new Exception('Error en grabacion, no existe tipo de asiento de tesoreria');

		$empresa = $this->empresaRepository->find($empresa_id);

		if ($empresa)
		{
			// Busca por el codigo + 10
			$empresa = $this->empresaRepository->findPorCodigo($empresa->codigo+10);

			if ($empresa)
				$empresa_id = $empresa->id;
		}
		$arrayAsiento['empresa_id'] = $empresa_id;

		// Genera los asientos
		$off = 0;
		$asientosGenerados = [];
		foreach ($data as $partida)
		{
			if ($partida->monto != 0)
			{
				DB::beginTransaction();
				try
				{
					if ($partida->monto > 0)
						$d_h = 'D';
					else
						$d_h = 'H';

					// Arma el asiento contable
					$arrayAsiento['fecha'] = $partida->periodo.'-01';
					$arrayAsiento['observacion'] = $partida->nombrepresupuesto;
					$arrayAsiento['cuentacontable_ids'][0] = $partida->cuentacontable_id;
					$arrayAsiento['moneda_ids'][0] = $partida->moneda_id;
					$arrayAsiento['centrocosto_ids'][0] = $partida->centrocosto_id;
					$arrayAsiento['debes'][0] = $arrayAsiento['haberes'][0] = 0;
					$arrayAsiento['numerolinea'] = $off++;

					if ($d_h == 'D')
						$arrayAsiento['debes'][0] = $partida->monto;
					else
						$arrayAsiento['haberes'][0] = abs($partida->monto);

					$arrayAsiento['cotizaciones'][0] = 1;
					$arrayAsiento['observaciones'][0] = $partida->nombrepresupuesto." Part.: ".$partida->codigopartida;

					$arrayAsiento['tipo'] = "PAR";
					$arrayAsiento['letra'] = ' ';
					$arrayAsiento['sucursal'] = 0;
					$arrayAsiento['nro'] = $partida->codigopartida;

					$asiento = $this->asientoRepository->create($arrayAsiento);

					if ($asiento == 'Error')
						throw new Exception('Error en grabacion anita.');

					if ($asiento)
						$asiento_movimiento = $this->asiento_movimientoRepository->create($arrayAsiento, $asiento->id);

					$asientosGenerados[] = [
										'nombreempresa' => $partida->nombreempresa,
										'nombrepresupuesto' => $partida->nombrepresupuesto,
										'id' => substr($arrayAsiento['fecha'],0,4).substr($arrayAsiento['fecha'],5,2).$arrayAsiento['numerolinea'],
										'codigocuentacontable' => $partida->codigocuentacontable,
										'nombrecuentacontable' => $partida->nombrecuentacontable,
										'nombrecentrocosto' => $partida->nombrecentrocosto,
										'abreviaturamoneda' => $partida->abreviaturamoneda,
										'monto' => $partida->monto,
										'codigopartida' => $partida->codigopartida,
										'fecha' => $arrayAsiento['fecha']
					];
					DB::commit();
				} catch (\Exception $e) {
					DB::rollback();

					// Borra el asiento creado
					dd($e->getMessage());

					return ['errores' => $e->getMessage()];
				}
			}
		}
		return $asientosGenerados;
	}
}
