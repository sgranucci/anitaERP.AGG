<?php

namespace App\Http\Controllers\Caja\Estacionamiento;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionEstacionamientoTurno;
use App\Models\Caja\Estacionamiento\TurnoEstacionamiento;
use App\Repositories\Caja\Estacionamiento\TurnoEstacionamientoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Http\Request;

class TurnoEstacionamientoController extends Controller
{
    public function __construct(
        private TurnoEstacionamientoRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index()
    {
        can('listar-turno-estacionamiento');

        $datas = $this->repository->all();

        return view('caja.estacionamiento.turno.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-turno-estacionamiento');
        $data = new TurnoEstacionamiento(['activo' => true, 'orden' => 0]);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('caja.estacionamiento.turno.crear', compact('data', 'empresa_query'));
    }

    public function guardar(ValidacionEstacionamientoTurno $request)
    {
        can('crear-turno-estacionamiento');
        $this->assertEmpresaPermitida((int) $request->input('empresa_id'));
        $this->repository->create($request->all());

        return redirect('caja/estacionamiento/turno')->with('mensaje', 'Turno creado con éxito');
    }

    public function editar($id)
    {
        can('editar-turno-estacionamiento');
        $data = $this->repository->findOrFail($id);
        $this->assertEmpresaPermitida((int) $data->empresa_id);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('caja.estacionamiento.turno.editar', compact('data', 'empresa_query'));
    }

    public function actualizar(ValidacionEstacionamientoTurno $request, $id)
    {
        can('actualizar-turno-estacionamiento');
        $this->assertEmpresaPermitida((int) $request->input('empresa_id'));
        $this->repository->update($request->all(), $id);

        return redirect('caja/estacionamiento/turno')->with('mensaje', 'Turno actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-turno-estacionamiento');

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
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'Empresa no permitida para su usuario.');
        }
    }
}
