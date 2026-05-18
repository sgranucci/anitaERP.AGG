<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionUbicacionGastronomia;
use App\Models\Ventas\UbicacionGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\UbicacionGastronomiaRepositoryInterface;
use Illuminate\Http\Request;

class UbicacionGastronomiaController extends Controller
{
    public function __construct(
        private UbicacionGastronomiaRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index()
    {
        can('listar-ubicaciones-gastronomia');

        $datas = $this->repository->all();

        return view('ventas.ubicaciones_gastronomia.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-ubicaciones-gastronomia');
        $data = new UbicacionGastronomia();
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('ventas.ubicaciones_gastronomia.crear', compact('data', 'empresa_query'));
    }

    public function guardar(ValidacionUbicacionGastronomia $request)
    {
        $this->repository->create($request->all());

        return redirect('ventas/ubicaciones-gastronomia')->with('mensaje', 'Ubicación creada con éxito');
    }

    public function editar($id)
    {
        can('editar-ubicaciones-gastronomia');
        $data = $this->repository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('ventas.ubicaciones_gastronomia.editar', compact('data', 'empresa_query'));
    }

    public function actualizar(ValidacionUbicacionGastronomia $request, $id)
    {
        can('actualizar-ubicaciones-gastronomia');
        $this->repository->update($request->all(), $id);

        return redirect('ventas/ubicaciones-gastronomia')->with('mensaje', 'Ubicación actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-ubicaciones-gastronomia');

        if ($request->ajax()) {
            if ($this->repository->tieneMesasAsociadas((int) $id)) {
                return response()->json(['mensaje' => 'ng', 'error' => 'La ubicación tiene mesas asociadas.']);
            }

            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
