<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionAreaComandaGastronomia;
use App\Models\Ventas\AreaComandaGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\AreaComandaGastronomiaRepositoryInterface;
use Illuminate\Http\Request;

class AreaComandaGastronomiaController extends Controller
{
    public function __construct(
        private AreaComandaGastronomiaRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index()
    {
        can('listar-area-comanda-gastronomia');

        $datas = $this->repository->all();

        return view('ventas.area_comanda_gastronomia.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-area-comanda-gastronomia');
        $data = new AreaComandaGastronomia();
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('ventas.area_comanda_gastronomia.crear', compact('data', 'empresa_query'));
    }

    public function guardar(ValidacionAreaComandaGastronomia $request)
    {
        $this->repository->create($request->all());

        return redirect('ventas/area-comanda-gastronomia')->with('mensaje', 'Área de comanda creada con éxito');
    }

    public function editar($id)
    {
        can('editar-area-comanda-gastronomia');
        $data = $this->repository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('ventas.area_comanda_gastronomia.editar', compact('data', 'empresa_query'));
    }

    public function actualizar(ValidacionAreaComandaGastronomia $request, $id)
    {
        can('actualizar-area-comanda-gastronomia');
        $this->repository->update($request->all(), $id);

        return redirect('ventas/area-comanda-gastronomia')->with('mensaje', 'Área de comanda actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-area-comanda-gastronomia');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
