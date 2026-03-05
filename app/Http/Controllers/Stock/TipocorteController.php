<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Tipocorte;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTipocorte;
use DataTables;

class TipocorteController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-tipo-cortes');

        $datas = Tipocorte::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Tipocorte = new Tipocorte();
        	$Tipocorte->sincronizarConAnita();
	
        	$datas = Tipocorte::orderBy('id')->paginate(50);
		}  

        return view('stock.tipocorte.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipo-cortes');
        return view('stock.tipocorte.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTipocorte $request)
    {
        $tipocorte = Tipocorte::create($request->all());

		// Graba anita
		$Tipocorte = new Tipocorte();
        $Tipocorte->guardarAnita($request, $tipocorte->id);

        return redirect('stock/tipocorte')->with('mensaje', 'Tipo de corte creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipo-cortes');
        $data = Tipocorte::findOrFail($id);
        return view('stock.tipocorte.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTipocorte $request, $id)
    {
        can('actualizar-tipo-cortes');
        Tipocorte::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Tipocorte = new Tipocorte();
        $Tipocorte->actualizarAnita($request, $id);

        return redirect('stock/tipocorte')->with('mensaje', 'Tipo de corte actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-cortes');

		// Elimina anita
		$Tipocorte = new Tipocorte();
        $Tipocorte->eliminarAnita($id);

        if ($request->ajax()) {
            if (Tipocorte::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
