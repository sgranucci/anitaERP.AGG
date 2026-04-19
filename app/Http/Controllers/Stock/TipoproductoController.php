<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTipoproducto;
use App\Models\Stock\Tipoproducto;
use App\Repositories\Stock\TipoproductoRepositoryInterface;

class TipoproductoController extends Controller
{
    private $repository;

    public function __construct(TipoproductoRepositoryInterface $repository)
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
        can('listar-tipo-de-producto');
        $datas = $this->repository->all();

        return view('stock.tipoproducto.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipo-de-producto');
        $data = new Tipoproducto();

        return view('stock.tipoproducto.crear', compact('data'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTipoproducto $request)
    {
        $this->repository->create($request->all());

        return redirect('stock/tipoproducto')->with('mensaje', 'Tipo de producto creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipo-de-producto');
        $data = $this->repository->findOrFail($id);

        return view('stock.tipoproducto.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTipoproducto $request, $id)
    {
        can('actualizar-tipo-de-producto');

        $this->repository->update($request->all(), $id);

        return redirect('stock/tipoproducto')->with('mensaje', 'Tipo de producto actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-de-producto');

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
