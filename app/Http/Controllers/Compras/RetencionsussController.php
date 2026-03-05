<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionRetencionsuss;
use App\Models\Compras\Retencionsuss;
use App\Repositories\Compras\RetencionsussRepositoryInterface;
use App\Repositories\Compras\Retencionsuss_EscalaRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class RetencionsussController extends Controller
{
	private $retencionsussRepository;

	public function __construct(RetencionsussRepositoryInterface $retencionsussrepository)
    {
        $this->retencionsussRepository = $retencionsussrepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-retencion-de-suss');
		
		$retencionessuss = $this->retencionsussRepository->all();

		$formacalculo_enum = Retencionsuss::$enumFormaCalculo;

        return view('compras.retencionsuss.index', compact('retencionessuss', 'formacalculo_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-retencion-de-suss');

		$formacalculo_enum = Retencionsuss::$enumFormaCalculo;

        return view('compras.retencionsuss.crear', compact('formacalculo_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionRetencionsuss $request)
    {
        $retencionsuss = $this->retencionsussRepository->create($request->all());

    	return redirect('compras/retencionsuss')->with('mensaje', 'Retencion de suss creada con exito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-retencion-de-suss');

		$retencionsuss = $this->retencionsussRepository->find($id);
		$formacalculo_enum = Retencionsuss::$enumFormaCalculo;
        return view('compras.retencionsuss.editar', compact('retencionsuss', 'formacalculo_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionRetencionsuss $request, $id)
    {
        can('actualizar-retencion-de-suss');

		$this->retencionsussRepository->update($request->all(), $id);

		return redirect('compras/retencionsuss')->with('mensaje', 'Retencion de suss actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-retencion-de-suss');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->retencionsussRepository->delete($id))
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
