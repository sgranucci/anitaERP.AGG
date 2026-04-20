<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTipoliquido;
use App\Models\Stock\Tipoliquido;
use App\Repositories\Stock\TipoliquidoRepositoryInterface;

class TipoliquidoController extends Controller
{
    private $repository;

    public function __construct(TipoliquidoRepositoryInterface $repository)
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
        can('listar-tipo-de-liquido');
        $datas = $this->repository->all();

        return view('stock.tipoliquido.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipo-de-liquido');
        $data = new Tipoliquido();

        return view('stock.tipoliquido.crear', compact('data'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTipoliquido $request)
    {
        $this->repository->create($request->all());

        return redirect('stock/tipoliquido')->with('mensaje', 'Tipo de líquido creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipo-de-liquido');
        $data = $this->repository->findOrFail($id);

        return view('stock.tipoliquido.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTipoliquido $request, $id)
    {
        can('actualizar-tipo-de-liquido');

        $this->repository->update($request->all(), $id);

        return redirect('stock/tipoliquido')->with('mensaje', 'Tipo de líquido actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-de-liquido');

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
