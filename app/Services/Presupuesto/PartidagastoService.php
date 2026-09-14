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
