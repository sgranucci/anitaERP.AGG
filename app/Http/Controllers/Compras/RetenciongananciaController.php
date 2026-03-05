<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\EliminarMasivoRetenciongananciaRequest;
use App\Http\Requests\GuardarRetenciongananciaRequest;
use App\Http\Requests\ActualizarRetenciongananciaRequest;
use App\Models\Compras\Retencionganancia;
use App\Repositories\Compras\RetenciongananciaRepositoryInterface;
use App\Repositories\Compras\Retencionganancia_EscalaRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class RetenciongananciaController extends Controller
{
	private $retenciongananciaRepository, $retencionganancia_escalaRepository;

	public function __construct(RetenciongananciaRepositoryInterface $retenciongananciarepository,
								Retencionganancia_EscalaRepositoryInterface $retencionganancia_escalarepository)
    {
        $this->retenciongananciaRepository = $retenciongananciarepository;
		$this->retencionganancia_escalaRepository = $retencionganancia_escalarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-retencion-de-ganancias');
		
		$retencionesganancia = $this->retenciongananciaRepository->all();

		$formacalculo_enum = Retencionganancia::$enumFormaCalculo;

        return view('compras.retencionganancia.index', compact('retencionesganancia', 'formacalculo_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-retencion-de-ganancias');

		$formacalculo_enum = Retencionganancia::$enumFormaCalculo;

        return view('compras.retencionganancia.crear', compact('formacalculo_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(GuardarRetenciongananciaRequest $request)
    {
        $retencionganancia = $this->retenciongananciaRepository->create($request->all());

		if ($retencionganancia)
		{
    		$desdeMontos = $request->input('desdemontos', []);
    		$hastaMontos = $request->input('hastamontos', []);
            $montoRetenciones = $request->input('montoretenciones', []);
            $porcentajeRetenciones = $request->input('porcentajeretenciones', []);
            $excedentes = $request->input('excedentes', []);
    		for ($i_cuota=0; $i_cuota < count($desdeMontos); $i_cuota++) {
        		if ($hastaMontos[$i_cuota] > 0)
				{
        			$retencionganancia_escala = $this->retencionganancia_escalaRepository->create([
					  									'retencionganancia_id' => $retencionganancia->id,
            											'desdemonto' => $desdeMontos[$i_cuota], 
														'hastamonto' => $hastaMontos[$i_cuota], 
														'montoretencion' => $montoRetenciones[$i_cuota],
														'porcentajeretencion' => $porcentajeRetenciones[$i_cuota],
                                                        'excedentes' => $excedentes[$i_cuota],
														]);
        		}
    		}
		}

    	return redirect('compras/retencionganancia')->with('mensaje', 'Retencion de ganancias creada con exito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-retencion-de-ganancias');

		$retencionganancia = Retencionganancia::with('retencionganancia_escalas')->where('id', $id)->first();
		$formacalculo_enum = Retencionganancia::$enumFormaCalculo;
        return view('compras.retencionganancia.editar', compact('retencionganancia', 'formacalculo_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ActualizarRetenciongananciaRequest $request, $id)
    {
        can('actualizar-retencion-de-ganancias');

		$this->retenciongananciaRepository->update($request->all(), $id);

		$retencionganancia_escala = $this->retencionganancia_escalaRepository->deletePorRetencionganancia($id);

        $desdeMontos = $request->input('desdemontos', []);
		$hastaMontos = $request->input('hastamontos', []);
		$montoRetenciones = $request->input('montoretenciones', []);
		$porcentajeRetenciones = $request->input('porcentajeretenciones', []);
        $excedentes = $request->input('excedentes', []);
		for ($i_cuota=0; $i_cuota < count($desdeMontos); $i_cuota++) {
			if ($hastaMontos[$i_cuota] > 0) 
			{
				$retencionganancia_escala = $this->retencionganancia_escalaRepository->create([
													'retencionganancia_id' => $id,
													'desdemonto' => $desdeMontos[$i_cuota], 
													'hastamonto' => $hastaMontos[$i_cuota], 
													'montoretencion' => $montoRetenciones[$i_cuota],
													'porcentajeretencion' => $porcentajeRetenciones[$i_cuota],
                                                    'excedentes' => $excedentes[$i_cuota],
													]);
			}
		}

		return redirect('compras/retencionganancia')->with('mensaje', 'Retencion de ganancias actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-retencion-de-ganancias');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->retenciongananciaRepository->delete($id))
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
