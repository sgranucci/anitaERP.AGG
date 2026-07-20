<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionNombrebase_Sueldos;
use App\Repositories\Sueldos\Nombrebase_SueldosRepositoryInterface;
use Illuminate\Http\Request;

class Nombrebase_SueldosController extends Controller
{
    private Nombrebase_SueldosRepositoryInterface $repository;

    public function __construct(Nombrebase_SueldosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index()
    {
        can('listar-nombrebase-sueldos');
        $datas = $this->repository->all();

        return view('sueldos.nombrebase.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-nombrebase-sueldos');

        return view('sueldos.nombrebase.crear');
    }

    public function guardar(ValidacionNombrebase_Sueldos $request)
    {
        can('crear-nombrebase-sueldos');
        $this->repository->create($request->validated());

        return redirect('sueldos/nombrebase')
            ->with('mensaje', 'Nombre de base creado con éxito');
    }

    public function editar($id)
    {
        can('editar-nombrebase-sueldos');
        $data = $this->repository->findOrFail($id);

        return view('sueldos.nombrebase.editar', compact('data'));
    }

    public function actualizar(ValidacionNombrebase_Sueldos $request, $id)
    {
        can('actualizar-nombrebase-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/nombrebase')
            ->with('mensaje', 'Nombre de base actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-nombrebase-sueldos');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    public function sincronizarAnita(Request $request)
    {
        can('actualizar-nombrebase-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $resultado = $this->repository->sincronizarConAnita();

        if (! empty($resultado['errores'])) {
            return redirect('sueldos/nombrebase')
                ->with('error', 'No se pudo sincronizar con Anita: '.implode(' | ', $resultado['errores']));
        }

        return redirect('sueldos/nombrebase')->with(
            'mensaje',
            'Sincronización con Anita: '.$resultado['importados'].' importados, '
                .$resultado['omitidos'].' ya existentes (de '.$resultado['en_anita'].' en Anita).'
        );
    }
}
