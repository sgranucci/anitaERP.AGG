<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionRetencioniva;
use App\Models\Compras\Retencioniva;
use App\Repositories\Compras\RetencionivaRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class RetencionivaController extends Controller
{
	private $retencionivaRepository;

	public function __construct(RetencionivaRepositoryInterface $retencionivarepository)
    {
        $this->retencionivaRepository = $retencionivarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-retencion-de-iva');
		
		$retencionesiva = $this->retencionivaRepository->all();

		$formacalculo_enum = Retencioniva::$enumFormaCalculo;

        return view('compras.retencioniva.index', compact('retencionesiva', 'formacalculo_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-retencion-de-iva');

		$formacalculo_enum = Retencioniva::$enumFormaCalculo;

        return view('compras.retencioniva.crear', compact('formacalculo_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionRetencioniva $request)
    {
        $retencioniva = $this->retencionivaRepository->create($request->all());

    	return redirect('compras/retencioniva')->with('mensaje', 'Retencion de iva creada con exito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-retencion-de-iva');

		$retencioniva = $this->retencionivaRepository->find($id);
		$formacalculo_enum = Retencioniva::$enumFormaCalculo;
        return view('compras.retencioniva.editar', compact('retencioniva', 'formacalculo_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionRetencioniva $request, $id)
    {
        can('actualizar-retencion-de-iva');

		$this->retencionivaRepository->update($request->all(), $id);

		return redirect('compras/retencioniva')->with('mensaje', 'Retencion de iva actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-retencion-de-iva');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->retencionivaRepository->delete($id))
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
