<?php

namespace App\Http\Controllers\Solicitudpago;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionFormapagosol;
use App\Repositories\Solicitudpago\FormapagosolRepositoryInterface;
use Illuminate\Http\Request;

class FormapagosolController extends Controller
{
    private FormapagosolRepositoryInterface $repository;

    public function __construct(FormapagosolRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index()
    {
        can('listar-forma-pago-solicitud');
        $datas = $this->repository->all();

        return view('solicitudpago.formapagosol.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-forma-pago-solicitud');

        return view('solicitudpago.formapagosol.crear');
    }

    public function guardar(ValidacionFormapagosol $request)
    {
        $this->repository->create($request->validated());

        return redirect('solicitudpago/formapagosol')
            ->with('mensaje', 'Forma de pago creada con éxito');
    }

    public function editar($id)
    {
        can('editar-forma-pago-solicitud');
        $data = $this->repository->findOrFail($id);

        return view('solicitudpago.formapagosol.editar', compact('data'));
    }

    public function actualizar(ValidacionFormapagosol $request, $id)
    {
        can('actualizar-forma-pago-solicitud');
        $this->repository->update($request->validated(), $id);

        return redirect('solicitudpago/formapagosol')
            ->with('mensaje', 'Forma de pago actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-forma-pago-solicitud');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
