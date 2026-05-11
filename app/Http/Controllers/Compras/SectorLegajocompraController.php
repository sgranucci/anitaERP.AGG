<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionSectorLegajocompra;
use App\Models\Compras\SectorLegajocompra;
use App\Repositories\Compras\SectorLegajocompraRepository;
use Illuminate\Http\Request;

class SectorLegajocompraController extends Controller
{
    private $repository;

    public function __construct(SectorLegajocompraRepository $repository)
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
        can('listar-sector-legajocompra');
        $datas = $this->repository->all();

        return view('compras.sectorlegajocompra.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-sector-legajocompra');
        $data = new SectorLegajocompra;

        return view('compras.sectorlegajocompra.crear', compact('data'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionSectorLegajocompra $request)
    {
        $this->repository->create($request->all());

        return redirect('compras/sector_legajocompra')->with('mensaje', 'Sector legajo compra creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-sector-legajocompra');
        $data = $this->repository->findOrFail($id);

        return view('compras.sectorlegajocompra.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionSectorLegajocompra $request, $id)
    {
        can('actualizar-sector-legajocompra');

        $this->repository->update($request->all(), $id);

        return redirect('compras/sector_legajocompra')->with('mensaje', 'Sector legajo compra actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-sector-legajocompra');

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
