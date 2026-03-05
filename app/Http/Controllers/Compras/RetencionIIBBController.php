<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\EliminarMasivoRetencionIIBBRequest;
use App\Http\Requests\GuardarRetencionIIBBRequest;
use App\Http\Requests\ActualizarRetencionIIBBRequest;
use App\Models\Compras\RetencionIIBB;
use App\Models\Configuracion\Provincia;
use App\Models\Contable\Cuentacontable;
use App\Repositories\Compras\RetencionIIBBRepositoryInterface;
use App\Repositories\Compras\RetencionIIBB_CondicionRepositoryInterface;
use App\Repositories\Configuracion\CondicionIIBBRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class RetencionIIBBController extends Controller
{
	private $retencionIIBBRepository, $retencionIIBB_condicionRepository;
    private $condicionIIBBRepository;

	public function __construct(RetencionIIBBRepositoryInterface $retencionIIBBrepository,
								RetencionIIBB_CondicionRepositoryInterface $retencionIIBB_condicionrepository,
                                CondicionIIBBRepositoryInterface $condicionIIBBrepository)
    {
        $this->retencionIIBBRepository = $retencionIIBBrepository;
		$this->retencionIIBB_condicionRepository = $retencionIIBB_condicionrepository;
        $this->condicionIIBBRepository = $condicionIIBBrepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-retencion-de-IIBB');
		
		$retencionesIIBB = $this->retencionIIBBRepository->all();

        return view('compras.retencionIIBB.index', compact('retencionesIIBB'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-retencion-de-IIBB');
        $condicionIIBB_query = $this->condicionIIBBRepository->all();
        $provincia_query = Provincia::orderBy('nombre')->get();
        $cuentacontable_query = Cuentacontable::orderBy('nombre')->get();

        return view('compras.retencionIIBB.crear', compact('provincia_query', 'cuentacontable_query',
                                                            'condicionIIBB_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(GuardarRetencionIIBBRequest $request)
    {
        $retencionIIBB = $this->retencionIIBBRepository->create($request->all());

		if ($retencionIIBB)
		{
            $condicionIIBB_ids = $request->input('condicionIIBB_ids', []);
    		$minimoRetenciones = $request->input('minimoretenciones', []);
    		$minimoImponibles = $request->input('minimoimponibles', []);
            $porcentajeRetenciones = $request->input('porcentajeretenciones', []);
    		for ($i_cuota=0; $i_cuota < count($condicionIIBB_ids); $i_cuota++) {
        		if ($condicionIIBB_ids[$i_cuota] > 0)
				{
        			$retencionIIBB_condicion = $this->retencionIIBB_condicionRepository->create([
					  									'retencionIIBB_id' => $retencionIIBB->id,
                                                        'condicionIIBB_id' => $condicionIIBB_ids[$i_cuota],
            											'minimoretencion' => $desdeMontos[$i_cuota], 
														'minimoimponible' => $hastaMontos[$i_cuota], 
														'porcentajeretencion' => $porcentajeRetenciones[$i_cuota],
                                                        'excedentes' => $excedentes[$i_cuota],
														]);
        		}
    		}
		}

    	return redirect('compras/retencionIIBB')->with('mensaje', 'Retencion de IIBB creada con exito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-retencion-de-IIBB');

		$retencionIIBB = $this->retencionIIBBRepository->find($id);

        $condicionIIBB_query = $this->condicionIIBBRepository->all();
        $provincia_query = Provincia::orderBy('nombre')->get();
        $cuentacontable_query = Cuentacontable::orderBy('nombre')->get();

        return view('compras.retencionIIBB.editar', compact('provincia_query', 'cuentacontable_query',
                                                            'retencionIIBB', 'condicionIIBB_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ActualizarRetencionIIBBRequest $request, $id)
    //public function actualizar(Request $request, $id)
    {
        can('actualizar-retencion-de-IIBB');
//dd($request);
		$this->retencionIIBBRepository->update($request->all(), $id);

		$retencionIIBB_condicion = $this->retencionIIBB_condicionRepository->deletePorRetencionIIBB($id);

        $condicionIIBB_ids = $request->input('condicionIIBB_ids', []);
        $minimoRetenciones = $request->input('minimoretenciones', []);
        $minimoImponibles = $request->input('minimoimponibles', []);
        $porcentajeRetenciones = $request->input('porcentajeretenciones', []);
        for ($i_cuota=0; $i_cuota < count($condicionIIBB_ids); $i_cuota++) {
            if ($condicionIIBB_ids[$i_cuota] > 0)
            {
                $retencionIIBB_condicion = $this->retencionIIBB_condicionRepository->create([
                                                    'retencionIIBB_id' => $id,
                                                    'condicionIIBB_id' => $condicionIIBB_ids[$i_cuota],
                                                    'minimoretencion' => $minimoRetenciones[$i_cuota], 
                                                    'minimoimponible' => $minimoImponibles[$i_cuota], 
                                                    'porcentajeretencion' => $porcentajeRetenciones[$i_cuota],
                                                    ]);
            }
        }

		return redirect('compras/retencionIIBB')->with('mensaje', 'Retencion de IIBB actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-retencion-de-IIBB');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->retencionIIBBRepository->delete($id))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
