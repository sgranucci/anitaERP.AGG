<?php

namespace App\Http\Controllers\Caja\Bingo;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionBingoTurno;
use App\Models\Caja\Bingo\TurnoBingo;
use App\Repositories\Caja\Bingo\TurnoBingoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Http\Request;

class TurnoBingoController extends Controller
{
    public function __construct(
        private readonly TurnoBingoRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index()
    {
        can('listar-turno-bingo');

        return view('caja.bingo.turno.index', [
            'datas' => $this->repository->all(),
        ]);
    }

    public function crear()
    {
        can('crear-turno-bingo');

        return view('caja.bingo.turno.crear', [
            'data' => new TurnoBingo(['activo' => true, 'orden' => 0]),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function guardar(ValidacionBingoTurno $request)
    {
        can('crear-turno-bingo');
        $this->assertEmpresaPermitida((int) $request->input('empresa_id'));
        $this->repository->create($request->all());

        return redirect()->route('bingo_turno')->with('mensaje', 'Turno creado con éxito');
    }

    public function editar($id)
    {
        can('editar-turno-bingo');
        $data = $this->repository->findOrFail($id);
        $this->assertEmpresaPermitida((int) $data->empresa_id);

        return view('caja.bingo.turno.editar', [
            'data' => $data,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function actualizar(ValidacionBingoTurno $request, $id)
    {
        can('actualizar-turno-bingo');
        $this->assertEmpresaPermitida((int) $request->input('empresa_id'));
        $this->repository->update($request->all(), $id);

        return redirect()->route('bingo_turno')->with('mensaje', 'Turno actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-turno-bingo');

        if ($request->ajax()) {
            $registro = $this->repository->findOrFail($id);
            $this->assertEmpresaPermitida((int) $registro->empresa_id);

            return response()->json([
                'mensaje' => $this->repository->delete($id) ? 'ok' : 'ng',
            ]);
        }

        abort(404);
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }
    }
}
