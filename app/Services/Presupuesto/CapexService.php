<?php
namespace App\Services\Presupuesto;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Repositories\Presupuesto\CapexRepositoryInterface;
use App\Repositories\Presupuesto\Capex_EstadoRepositoryInterface;
use App\Repositories\Presupuesto\Capex_ArchivoRepositoryInterface;
use App\Repositories\Presupuesto\Capex_PartidaRepositoryInterface;
use App\Repositories\Presupuesto\Capex_Partida_MontoRepositoryInterface;
use App\Repositories\Presupuesto\PresupuestoRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Models\Presupuesto\Capex_Estado;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App;
use Auth;
use DB;
use Exception;

class CapexService 
{
	private $capexRepository;
    private $capex_estadoRepository;
    private $capex_archivoRepository;
	private $capex_partidaRepository;
	private $capex_partida_montoRepository;
	private $centrocostoRepository;
	private $presupuestoRepository;
	private $proveedorRepository;

    public function __construct(CapexRepositoryInterface $capexrepository,
                                Capex_EstadoRepositoryInterface $capex_estadorepository,
                                Capex_ArchivoRepositoryInterface $capex_archivorepository,
								Capex_PartidaRepositoryInterface $capex_partidarepository,
								Capex_Partida_MontoRepositoryInterface $capex_partida_montorepository,
								PresupuestoRepositoryInterface $presupuestorepository,
								CentrocostoRepositoryInterface $centrocostorepository,
								ProveedorRepositoryInterface $proveedorrepository
								)
    {
		$this->capexRepository = $capexrepository;
        $this->capex_estadoRepository = $capex_estadorepository;
        $this->capex_archivoRepository = $capex_archivorepository;
		$this->capex_partidaRepository = $capex_partidarepository;
		$this->capex_partida_montoRepository = $capex_partida_montorepository;
		$this->presupuestoRepository = $presupuestorepository;
		$this->centrocostoRepository = $centrocostorepository;
		$this->proveedorRepository = $proveedorrepository;
    }

	public function guardaCapex($request, $origen = null)
	{
		$data = $request->all();

   		// Crea estado
	   	$data['fechas'][] = Carbon::now();
	   	$data['estados'][] = Capex_Estado::$enumEstado[array_search('A', array_column(Capex_Estado::$enumEstado, 'valor'))]['nombre'];
		$data['usuario_ids'][] = Auth::user()->id;
	   	$data['observacionestados'][] = "Alta de Capex";
		$data['creousuario_id'] = Auth::user()->id;

		DB::beginTransaction();
		try
		{
			$capex = $this->capexRepository->create($data);

			if ($capex == 'Error')
				throw new Exception('Error en grabacion');

			// Guarda tablas asociadas
			if ($capex)
			{
				$data['codigo'] = $capex->codigo;

				Self::agrega($data, $capex, $request);
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
	private function agrega(&$data, $capex, $request)
	{
		$capex_estado = $this->capex_estadoRepository->create($data, $capex->id);
		$capex_archivo = $this->capex_archivoRepository->create($request, $capex->id);

		if (isset($data['moneda_ids']))
			$capex_partida = $this->capex_partidaRepository->create($data, $capex->id);

		if (isset($data['periodo_monto_armados']))
		{
			for ($i = 0; $i < count($data['moneda_ids']); $i++)
			{
				$dataPartidaMonto['periodos'] = [];
				$dataPartidaMonto['montos'] = [];
				$dataPartidaMonto['creousuario_ids'] = [];
				$dataPartidaMonto['capex_partida_ids'] = [];
				$dataPartidaMonto['capex_ids'] = [];

				// Busca en todo el array los items que corresponden a cada partida
				for ($j = 0; $j < count($data['periodo_monto_armados']); $j++)
				{
					if ($data['items'][$i] == $data['item_monto_armados'][$j])
					{
						$dataPartidaMonto['capex_partida_ids'][] = $data['capex_partida_ids'][$i];
						$dataPartidaMonto['capex_ids'][] = $capex->id;
						$dataPartidaMonto['periodos'][] = $data['periodo_monto_armados'][$j];
						$dataPartidaMonto['montos'][] = $data['monto_armados'][$j];
						$dataPartidaMonto['creousuario_ids'][] = $data['creousuario_id_monto_armados'][$j];						
					}
				}

				$capex_partida_monto = $this->capex_partida_montoRepository->create($dataPartidaMonto);
			}
		}
	}

    public function actualizaCapex($request, $id, $origen = null)
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
		// Graba capex
		$capex = $this->capexRepository->find($id);

		if ($capex)
			$capex = $this->capexRepository->update($data, $id);
		else
		{
			$capex = $this->capexRepository->create($data);

			$id = $capex->id;
		}

		if ($capex === 'Error')
			throw new Exception('Error en grabacion capex.');

		// Graba movimientos de estados y archivos
		$this->capex_archivoRepository->update($request, $id);
		$this->capex_partidaRepository->update($data, $id);

		// Si hay algun monto de partida para agregar o actualizar procesa
		if (isset($data['periodo_monto_armados']))
		{
			for ($i = 0; $i < count($data['moneda_ids']); $i++)
			{
				$dataPartidaMonto['periodos'] = [];
				$dataPartidaMonto['montos'] = [];
				$dataPartidaMonto['creousuario_ids'] = [];
				$dataPartidaMonto['capex_partida_ids'] = [];
				$dataPartidaMonto['capex_ids'] = [];

				// Busca en todo el array los items que corresponden a cada partida
				for ($j = 0; $j < count($data['periodo_monto_armados']); $j++)
				{
					if ($data['items'][$i] == $data['item_monto_armados'][$j])
					{
						$dataPartidaMonto['capex_partida_ids'][] = $data['capex_partida_ids'][$i];
						$dataPartidaMonto['capex_ids'][] = $id;
						$dataPartidaMonto['periodos'][] = $data['periodo_monto_armados'][$j];
						$dataPartidaMonto['montos'][] = $data['monto_armados'][$j];
						$dataPartidaMonto['creousuario_ids'][] = $data['creousuario_id_monto_armados'][$j];
					}
				}
				if (count($dataPartidaMonto['capex_partida_ids']) > 0)
					$capex_partida_monto = $this->capex_partida_montoRepository->update($dataPartidaMonto);
			}
		}		
	}

	public function actualizaEstadoCapex($estado, $id)
	{
		DB::beginTransaction();
		try
		{
			$capex = $this->capexRepository->update($estado, $id);

			// Crea estado
			if (isset($estado['estado']))
			{
				$data = [];
				$data['fechas'][] = Carbon::now();
				$data['usuario_ids'][] = Auth::user()->id;

				switch($estado['estado'])
			 	{
				case 'ANULADO':
					$data['observacionestados'][] = "Anulación de Capex";
					$data['estados'][] = Capex_Estado::$enumEstado[array_search('B', array_column(Capex_Estado::$enumEstado, 'valor'))]['nombre'];
					break;
				case 'CERRADO':
					$data['observacionestados'][] = "Cierre de Capex";
					$data['estados'][] = Capex_Estado::$enumEstado[array_search('C', array_column(Capex_Estado::$enumEstado, 'valor'))]['nombre'];
					break;
				case 'ACTIVO':
					$data['observacionestados'][] = "Activación de Capex";
					$data['estados'][] = Capex_Estado::$enumEstado[array_search('A', array_column(Capex_Estado::$enumEstado, 'valor'))]['nombre'];
				}

				$data['creousuario_id'][] = Auth::user()->id;

				$capex_estado = $this->capex_estadoRepository->create($data, $id);
			}			

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();

			return ['mensaje' => 'error', 'errores' => $e->getMessage()];
		}		
	}

	public function leeHistoriaCapex($capex_id)
	{
		return $this->capex_estadoRepository->leeHistoriaCapex($capex_id);
	}
}
