<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTotemWaitryGastronomia;
use App\Models\Ventas\TotemWaitryGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\TotemWaitryGastronomiaRepositoryInterface;
use App\Repositories\Ventas\UbicacionGastronomiaRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TotemWaitryGastronomiaController extends Controller
{
    public function __construct(
        private TotemWaitryGastronomiaRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
        private UbicacionGastronomiaRepositoryInterface $ubicacionGastronomiaRepository,
    ) {
    }

    public function index()
    {
        can('listar-totem-waitry-gastronomia');

        $datas = $this->repository->all();

        return view('ventas.totem_waitry_gastronomia.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-totem-waitry-gastronomia');

        $data = new TotemWaitryGastronomia();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresaDefaultId = (int) ($empresa_query->first()?->id ?? config('cliente.EMPRESA_DEFAULT_ID'));
        $ubicacion_query = $this->ubicacionGastronomiaRepository->listarParaSelect($empresaDefaultId > 0 ? $empresaDefaultId : null);

        return view('ventas.totem_waitry_gastronomia.crear', compact('data', 'empresa_query', 'ubicacion_query'));
    }

    public function guardar(ValidacionTotemWaitryGastronomia $request)
    {
        $this->repository->create($request->all());

        return redirect('ventas/totem-waitry-gastronomia')->with('mensaje', 'Tótem Waitry creado con éxito');
    }

    public function editar($id)
    {
        can('editar-totem-waitry-gastronomia');

        $data = $this->repository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $ubicacion_query = $this->ubicacionGastronomiaRepository->listarParaSelect((int) $data->empresa_id);

        return view('ventas.totem_waitry_gastronomia.editar', compact('data', 'empresa_query', 'ubicacion_query'));
    }

    public function actualizar(ValidacionTotemWaitryGastronomia $request, $id)
    {
        can('actualizar-totem-waitry-gastronomia');

        $this->repository->update($request->all(), $id);

        return redirect('ventas/totem-waitry-gastronomia')->with('mensaje', 'Tótem Waitry actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-totem-waitry-gastronomia');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    public function ubicacionesPorEmpresa(int $empresaId): JsonResponse
    {
        can('listar-totem-waitry-gastronomia');

        $ubicaciones = $this->ubicacionGastronomiaRepository->listarParaSelect($empresaId)
            ->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->nombre])
            ->values();

        return response()->json(['ubicaciones' => $ubicaciones]);
    }
}
