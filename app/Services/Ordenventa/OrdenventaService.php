<?php
namespace App\Services\Ordenventa;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Repositories\Ordenventa\OrdenventaRepositoryInterface;
use App\Repositories\Ordenventa\Ordenventa_EstadoRepositoryInterface;
use App\Repositories\Ordenventa\Ordenventa_ArchivoRepositoryInterface;
use App\Repositories\Ordenventa\Ordenventa_CuotaRepositoryInterface;
use App\Services\Configuracion\ArbolaprobacionService;
use App\Models\Ordenventa\Ordenventa_Estado;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App;
use Auth;
use DB;
use Exception;

class OrdenventaService 
{
	private $ordenventaRepository;
    private $ordenventa_estadoRepository;
    private $ordenventa_archivoRepository;
	private $ordenventa_cuotaRepository;
	private $arbolaprobacionService;

    public function __construct(OrdenventaRepositoryInterface $ordenventarepository,
                                Ordenventa_EstadoRepositoryInterface $ordenventa_estadorepository,
                                Ordenventa_ArchivoRepositoryInterface $ordenventa_archivorepository,
								Ordenventa_CuotaRepositoryInterface $ordenventa_cuotarepository,
								ArbolaprobacionService $arbolaprobacionservice
								)
    {
		$this->ordenventaRepository = $ordenventarepository;
        $this->ordenventa_estadoRepository = $ordenventa_estadorepository;
        $this->ordenventa_archivoRepository = $ordenventa_archivorepository;
		$this->ordenventa_cuotaRepository = $ordenventa_cuotarepository;
		$this->arbolaprobacionService = $arbolaprobacionservice;
    }

	public function guardaOrdenventa($request, $origen = null)
	{
		$data = $request->all();

   		// Crea estado
	   	$data['fechas'][] = Carbon::now();
	   	$data['estados'][] = Ordenventa_Estado::$enumEstado[array_search('S', array_column(Ordenventa_Estado::$enumEstado, 'valor'))]['nombre'];
		$data['usuario_ids'][] = Auth::user()->id;
	   	$data['observacionestados'][] = "Alta de Orden de Venta";

		$data['creousuario_id'] = Auth::user()->id;

		DB::beginTransaction();
		try
		{
			$ordenventa = $this->ordenventaRepository->create($data);

			if ($ordenventa == 'Error')
				throw new Exception('Error en grabacion');

			// Guarda tablas asociadas
			if ($ordenventa)
				Self::agrega($data, $ordenventa, $request);

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();
			dd($e->getMessage());
			return ['mensaje' => 'error', 'errores' => $e->getMessage()];
		}
        return ['mensaje' => 'ok'];
	}

	// Agrega tablas asociadas
	private function agrega($data, $ordenventa, $request)
	{
		$ordenventa_estado = $this->ordenventa_estadoRepository->create($data, $ordenventa->id);
		$ordenventa_archivo = $this->ordenventa_archivoRepository->create($request, $ordenventa->id);

		// Si existen las tareas asume que graba desde administracion de ordenventas
		if (isset($data['montofacturas']))
			$ordenventa_cuota = $this->ordenventa_cuotaRepository->create($data, $ordenventa->id);

		// Llama al arbol de aprobacion
		$this->arbolaprobacionService->procesaArbolaprobacion('OV', $ordenventa->id, 'insert');
	}

    public function actualizaOrdenventa($request, $id, $origen = null)
    {
		$data = $request->all();

		DB::beginTransaction();
		try
		{
			Self::actualiza($data, $id, $request);

			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();

			return ['mensaje' => 'error', 'errores' => $e->getMessage()];
		}
        return ['mensaje' => 'ok'];
    }

	private function actualiza($data, $id, $request)
	{
		// Graba ordenventa
		$ordenventa = $this->ordenventaRepository->update($data, $id);

		if ($ordenventa === 'Error')
			throw new Exception('Error en grabacion ordenventa.');

		// Graba movimientos de estados y archivos
		$this->ordenventa_archivoRepository->update($request, $id);
		$this->ordenventa_cuotaRepository->update($data, $id);

		// Llama al arbol de aprobacion
		//$this->arbolaprobacionService->procesaArbolaprobacion('OV', $id, 'insert');
	}

	public function leeHistoriaOrdenventa($ordenventa_id)
	{
		return $this->ordenventa_estadoRepository->leeHistoriaOrdenventa($ordenventa_id);
	}

}