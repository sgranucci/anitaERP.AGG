<?php

namespace App\Http\Controllers\Sala;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPrioridadSala;
use App\Models\Sala\PrioridadSala;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Sala\PrioridadSalaRepositoryInterface;
use Illuminate\Http\Request;

class PrioridadSalaController extends Controller
{
    public function __construct(
        private PrioridadSalaRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index()
    {
        can('listar-prioridad-sala');
        $datas = $this->repository->all();

        return view('sala.prioridad_sala.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-prioridad-sala');
        $data = new PrioridadSala();
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('sala.prioridad_sala.crear', compact('data', 'empresa_query'));
    }

    public function guardar(ValidacionPrioridadSala $request)
    {
        $this->repository->create($request->all());

        return redirect('sala/prioridad-sala')->with('mensaje', 'Prioridad de sala creada con éxito');
    }

    public function editar($id)
    {
        can('editar-prioridad-sala');
        $data = $this->repository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('sala.prioridad_sala.editar', compact('data', 'empresa_query'));
    }

    public function actualizar(ValidacionPrioridadSala $request, $id)
    {
        can('actualizar-prioridad-sala');
        $this->repository->update($request->all(), $id);

        return redirect('sala/prioridad-sala')->with('mensaje', 'Prioridad de sala actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-prioridad-sala');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
