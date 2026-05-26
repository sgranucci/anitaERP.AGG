<?php

namespace App\Http\Controllers\Sala;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionZonaSala;
use App\Models\Sala\ZonaSala;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Sala\ZonaSalaRepositoryInterface;
use Illuminate\Http\Request;

class ZonaSalaController extends Controller
{
    public function __construct(
        private ZonaSalaRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index()
    {
        can('listar-zona-sala');
        $datas = $this->repository->all();

        return view('sala.zona_sala.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-zona-sala');
        $data = new ZonaSala();
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('sala.zona_sala.crear', compact('data', 'empresa_query'));
    }

    public function guardar(ValidacionZonaSala $request)
    {
        $this->repository->create($request->all());

        return redirect('sala/zona-sala')->with('mensaje', 'Zona de sala creada con éxito');
    }

    public function editar($id)
    {
        can('editar-zona-sala');
        $data = $this->repository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('sala.zona_sala.editar', compact('data', 'empresa_query'));
    }

    public function actualizar(ValidacionZonaSala $request, $id)
    {
        can('actualizar-zona-sala');
        $this->repository->update($request->all(), $id);

        return redirect('sala/zona-sala')->with('mensaje', 'Zona de sala actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-zona-sala');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
