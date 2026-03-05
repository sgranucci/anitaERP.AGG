<?php

namespace App\Http\Controllers\Contable;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Contable\Rubrocontable;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionRubrocontable;

class RubrocontableController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-rubros-contables');
        $datas = Rubrocontable::orderBy('id')->get();

        return view('contable.rubrocontable.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-rubros-contables');
        return view('contable.rubrocontable.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionRubrocontable $request)
    {
        $rubrocontable = Rubrocontable::create($request->all());

        return redirect('contable/rubrocontable')->with('mensaje', 'Rubro contable creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-rubros-contables');
        $data = Rubrocontable::findOrFail($id);
        return view('contable.rubrocontable.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionRubrocontable $request, $id)
    {
        can('actualizar-rubros-contables');
        Rubrocontable::findOrFail($id)->update($request->all());

        return redirect('contable/rubrocontable')->with('mensaje', 'Rubro contable actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-rubros-contables');

        if ($request->ajax()) {
            if (Rubrocontable::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
