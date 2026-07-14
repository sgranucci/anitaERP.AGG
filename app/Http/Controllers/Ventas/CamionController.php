<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCamion;
use App\Repositories\Ventas\CamionRepositoryInterface;
use Illuminate\Http\Request;

class CamionController extends Controller
{
    private $repository;

    public function __construct(CamionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index()
    {
        can('listar-camion');
        $datas = $this->repository->all();

        return view('ventas.camion.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-camion');

        return view('ventas.camion.crear');
    }

    public function guardar(ValidacionCamion $request)
    {
        can('crear-camion');
        $this->repository->create($request->validated());

        return redirect('ventas/camion')->with('mensaje', 'Camión creado con éxito');
    }

    public function editar($id)
    {
        can('editar-camion');
        $data = $this->repository->findOrFail($id);

        return view('ventas.camion.editar', compact('data'));
    }

    public function actualizar(ValidacionCamion $request, $id)
    {
        can('actualizar-camion');
        $this->repository->update($request->validated(), $id);

        return redirect('ventas/camion')->with('mensaje', 'Camión actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-camion');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    public function consultaCamion(Request $request)
    {
        if (! can('listar-camion', false)
            && ! can('crear-certificado-sanitario', false)
            && ! can('listar-certificado-sanitario', false)) {
            abort(403);
        }

        return $this->repository->consultaCamion((string) ($request->get('consulta') ?? ''));
    }

    public function leeUnCamion(string $codigo)
    {
        if (! can('listar-camion', false)
            && ! can('crear-certificado-sanitario', false)
            && ! can('listar-certificado-sanitario', false)) {
            abort(403);
        }

        return $this->repository->findPorCodigo($codigo);
    }
}
