<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Formapago;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionFormapago;
use App\Repositories\Ventas\FormapagoRepositoryInterface;

class FormapagoController extends Controller
{
	private $repository;

    public function __construct(FormapagoRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-formas-de-pago');
		$datas = $this->repository->all();

        return view('ventas.formapago.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-formas-de-pago');

        return view('ventas.formapago.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionFormapago $request)
    {
		$this->repository->create($request->all());

        return redirect('ventas/formapago')->with('mensaje', 'Forma de pago creada con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-formas-de-pago');
        $data = $this->repository->findOrFail($id);

        return view('ventas.formapago.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionFormapago $request, $id)
    {
        can('actualizar-formas-de-pago');
        $this->repository->update($request->all(), $id);

        return redirect('ventas/formapago')->with('mensaje', 'Forma de pago actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-formas-de-pago');

        if ($request->ajax()) {
        	if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
