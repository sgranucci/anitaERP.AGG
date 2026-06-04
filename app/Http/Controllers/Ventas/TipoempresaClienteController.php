<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTipoempresaCliente;
use App\Repositories\Ventas\TipoempresaClienteRepositoryInterface;
use Illuminate\Http\Request;

class TipoempresaClienteController extends Controller
{
    private $repository;

    public function __construct(TipoempresaClienteRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index()
    {
        can('listar-tipo-empresa-cliente');
        $datas = $this->repository->all();

        return view('ventas.tipoempresa_cliente.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-tipo-empresa-cliente');

        return view('ventas.tipoempresa_cliente.crear');
    }

    public function guardar(ValidacionTipoempresaCliente $request)
    {
        $this->repository->create($request->all());

        return redirect('ventas/tipoempresa-cliente')->with('mensaje', 'Tipo de empresa creado con éxito');
    }

    public function editar($id)
    {
        can('editar-tipo-empresa-cliente');
        $data = $this->repository->findOrFail($id);

        return view('ventas.tipoempresa_cliente.editar', compact('data'));
    }

    public function actualizar(ValidacionTipoempresaCliente $request, $id)
    {
        can('actualizar-tipo-empresa-cliente');

        $this->repository->update($request->all(), $id);

        return redirect('ventas/tipoempresa-cliente')->with('mensaje', 'Tipo de empresa actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-empresa-cliente');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
