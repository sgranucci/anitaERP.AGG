<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Lote;
use App\Models\Configuracion\Pais;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionLote;
use App\Repositories\Stock\LoteRepositoryInterface;

class LoteController extends Controller
{
	private $repository;

    public function __construct(LoteRepositoryInterface $repository)
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
        can('listar-lotes');
		$datas = $this->repository->all();
        
        return view('stock.lote.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-lotes');
        $pais_query = Pais::orderBy('nombre')->get();

        return view('stock.lote.crear', compact('pais_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionLote $request)
    {
		$this->repository->create($request->all());

        return redirect('stock/lote')->with('mensaje', 'Lote creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-lotes');
        $data = $this->repository->findOrFail($id);
        $pais_query = Pais::orderBy('nombre')->get();

        return view('stock.lote.editar', compact('data', 'pais_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionLote $request, $id)
    {
        can('actualizar-lotes');
        $this->repository->update($request->all(), $id);

        return redirect('stock/lote')->with('mensaje', 'Lote actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-lotes');

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
