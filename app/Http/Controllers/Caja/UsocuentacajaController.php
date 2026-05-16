<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionUsocuentacaja;
use App\Models\Caja\Usocuentacaja;
use App\Repositories\Caja\UsocuentacajaRepositoryInterface;
use Illuminate\Http\Request;

class UsocuentacajaController extends Controller
{
    private $repository;

    public function __construct(UsocuentacajaRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index()
    {
        can('listar-usocuentacaja');
        $datas = $this->repository->all();

        return view('caja.usocuentacaja.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-usocuentacaja');
        $data = new Usocuentacaja();

        return view('caja.usocuentacaja.crear', compact('data'));
    }

    public function guardar(ValidacionUsocuentacaja $request)
    {
        $this->repository->create($request->all());

        return redirect('caja/usocuentacaja')->with('mensaje', 'Uso de cuenta de caja creado con éxito');
    }

    public function editar($id)
    {
        can('editar-usocuentacaja');
        $data = $this->repository->findOrFail($id);

        return view('caja.usocuentacaja.editar', compact('data'));
    }

    public function actualizar(ValidacionUsocuentacaja $request, $id)
    {
        can('actualizar-usocuentacaja');

        $this->repository->update($request->all(), $id);

        return redirect('caja/usocuentacaja')->with('mensaje', 'Uso de cuenta de caja actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-usocuentacaja');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
