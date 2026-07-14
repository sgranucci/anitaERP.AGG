<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCai;
use App\Repositories\Ventas\CaiRepositoryInterface;
use Illuminate\Http\Request;

class CaiController extends Controller
{
    private CaiRepositoryInterface $repository;

    public function __construct(CaiRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index()
    {
        can('listar-cai');
        $datas = $this->repository->all();

        return view('ventas.cai.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-cai');

        return view('ventas.cai.crear');
    }

    public function guardar(ValidacionCai $request)
    {
        $this->repository->create($request->validated());

        return redirect('ventas/cai')->with('mensaje', 'CAI creado con éxito');
    }

    public function editar($id)
    {
        can('editar-cai');
        $data = $this->repository->findOrFail($id);

        return view('ventas.cai.editar', compact('data'));
    }

    public function actualizar(ValidacionCai $request, $id)
    {
        can('actualizar-cai');
        $this->repository->update($request->validated(), $id);

        return redirect('ventas/cai')->with('mensaje', 'CAI actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-cai');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
