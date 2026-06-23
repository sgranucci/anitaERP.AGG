<?php

namespace App\Http\Controllers\Sala;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTecnicoLaboratorio;
use App\Models\Sala\TecnicoLaboratorio;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Sala\TecnicoLaboratorioRepositoryInterface;
use Illuminate\Http\Request;

class TecnicoLaboratorioController extends Controller
{
    public function __construct(
        private TecnicoLaboratorioRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index()
    {
        can('listar-tecnico-laboratorio');
        $datas = $this->repository->all();

        return view('sala.tecnico_laboratorio.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-tecnico-laboratorio');
        $data = new TecnicoLaboratorio(['activo' => 'S']);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('sala.tecnico_laboratorio.crear', compact('data', 'empresa_query'));
    }

    public function guardar(ValidacionTecnicoLaboratorio $request)
    {
        $this->repository->create($request->all());

        return redirect('sala/tecnico-laboratorio')->with('mensaje', 'T&eacute;cnico de laboratorio creado con &eacute;xito');
    }

    public function editar($id)
    {
        can('editar-tecnico-laboratorio');
        $data = $this->repository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('sala.tecnico_laboratorio.editar', compact('data', 'empresa_query'));
    }

    public function actualizar(ValidacionTecnicoLaboratorio $request, $id)
    {
        can('actualizar-tecnico-laboratorio');
        $this->repository->update($request->all(), $id);

        return redirect('sala/tecnico-laboratorio')->with('mensaje', 'T&eacute;cnico de laboratorio actualizado con &eacute;xito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-tecnico-laboratorio');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
