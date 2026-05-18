<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTurnoGastronomia;
use App\Models\Ventas\TurnoGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\TurnoGastronomiaRepositoryInterface;
use Illuminate\Http\Request;

class TurnoGastronomiaController extends Controller
{
    public function __construct(
        private TurnoGastronomiaRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index()
    {
        can('listar-turno-gastronomia');

        $datas = $this->repository->all();

        return view('ventas.turno_gastronomia.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-turno-gastronomia');
        $data = new TurnoGastronomia(['activo' => true, 'orden' => 0]);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('ventas.turno_gastronomia.crear', compact('data', 'empresa_query'));
    }

    public function guardar(ValidacionTurnoGastronomia $request)
    {
        can('crear-turno-gastronomia');
        $this->assertEmpresaPermitida((int) $request->input('empresa_id'));
        $this->repository->create($request->all());

        return redirect('ventas/turno-gastronomia')->with('mensaje', 'Turno creado con éxito');
    }

    public function editar($id)
    {
        can('editar-turno-gastronomia');
        $data = $this->repository->findOrFail($id);
        $this->assertEmpresaPermitida((int) $data->empresa_id);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('ventas.turno_gastronomia.editar', compact('data', 'empresa_query'));
    }

    public function actualizar(ValidacionTurnoGastronomia $request, $id)
    {
        can('actualizar-turno-gastronomia');
        $this->assertEmpresaPermitida((int) $request->input('empresa_id'));
        $this->repository->update($request->all(), $id);

        return redirect('ventas/turno-gastronomia')->with('mensaje', 'Turno actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-turno-gastronomia');

        if ($request->ajax()) {
            $registro = $this->repository->findOrFail($id);
            $this->assertEmpresaPermitida((int) $registro->empresa_id);

            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        $empresasAsignadas = $this->empresaRepository->traeEmpresasAsignadas();

        if (count($empresasAsignadas) > 1 && ! in_array($empresaId, $empresasAsignadas, true)) {
            abort(403, 'Empresa no permitida para su usuario.');
        }
    }
}
