<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTipoliquidofreno;
use App\Models\Stock\Tipoliquidofreno;
use App\Repositories\Stock\TipoliquidofrenoRepositoryInterface;

class TipoliquidofrenoController extends Controller
{
    private $repository;

    public function __construct(TipoliquidofrenoRepositoryInterface $repository)
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
        can('listar-tipo-de-liquido-de-freno');
        $datas = $this->repository->all();

        return view('stock.tipoliquidofreno.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipo-de-liquido-de-freno');
        $data = new Tipoliquidofreno();

        return view('stock.tipoliquidofreno.crear', compact('data'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTipoliquidofreno $request)
    {
        $this->repository->create($request->all());

        return redirect('stock/tipoliquidofreno')->with('mensaje', 'Tipo de líquido de freno creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipo-de-liquido-de-freno');
        $data = $this->repository->findOrFail($id);

        return view('stock.tipoliquidofreno.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTipoliquidofreno $request, $id)
    {
        can('actualizar-tipo-de-liquido-de-freno');

        $this->repository->update($request->all(), $id);

        return redirect('stock/tipoliquidofreno')->with('mensaje', 'Tipo de líquido de freno actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-de-liquido-de-freno');

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
