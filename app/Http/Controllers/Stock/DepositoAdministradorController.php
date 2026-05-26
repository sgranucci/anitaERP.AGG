<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionDepositoAdministrador;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Depmae;
use App\Repositories\Stock\Deposito_AdministradorRepositoryInterface;
use Illuminate\Http\Request;

class DepositoAdministradorController extends Controller
{
    public function __construct(
        private readonly Deposito_AdministradorRepositoryInterface $repository,
    ) {}

    public function index()
    {
        can('listar-deposito-administrador');
        $datas = $this->repository->all();

        return view('stock.deposito_administrador.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-deposito-administrador');
        $depositos = Depmae::orderBy('nombre')->get(['id', 'nombre']);
        $usuarios = Usuario::orderBy('nombre')->get(['id', 'nombre', 'email']);

        return view('stock.deposito_administrador.crear', compact('depositos', 'usuarios'));
    }

    public function guardar(ValidacionDepositoAdministrador $request)
    {
        can('crear-deposito-administrador');

        $this->repository->create([
            'deposito_id' => (int) $request->input('deposito_id'),
            'usuario_id' => (int) $request->input('usuario_id'),
            'principal' => (bool) $request->input('principal', false),
            'recibe_avisos' => (bool) $request->input('recibe_avisos', true),
            'aprueba_recepcion' => (bool) $request->input('aprueba_recepcion', true),
        ]);

        return redirect('stock/deposito-administrador')
            ->with('mensaje', 'Administrador asignado al depósito.');
    }

    public function editar(int $id)
    {
        can('editar-deposito-administrador');
        $data = $this->repository->find($id);
        $depositos = Depmae::orderBy('nombre')->get(['id', 'nombre']);
        $usuarios = Usuario::orderBy('nombre')->get(['id', 'nombre', 'email']);

        return view('stock.deposito_administrador.editar', compact('data', 'depositos', 'usuarios'));
    }

    public function actualizar(ValidacionDepositoAdministrador $request, int $id)
    {
        can('actualizar-deposito-administrador');

        $this->repository->update($id, [
            'deposito_id' => (int) $request->input('deposito_id'),
            'usuario_id' => (int) $request->input('usuario_id'),
            'principal' => (bool) $request->input('principal', false),
            'recibe_avisos' => (bool) $request->input('recibe_avisos', true),
            'aprueba_recepcion' => (bool) $request->input('aprueba_recepcion', true),
        ]);

        return redirect('stock/deposito-administrador')
            ->with('mensaje', 'Asignación actualizada.');
    }

    public function eliminar(Request $request, int $id)
    {
        can('borrar-deposito-administrador');
        $this->repository->delete($id);

        if ($request->ajax()) {
            return response()->json(['mensaje' => 'ok']);
        }

        return redirect('stock/deposito-administrador')->with('mensaje', 'Asignación eliminada.');
    }
}
