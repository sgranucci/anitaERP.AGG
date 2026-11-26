<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTipoAhorro;
use App\Models\Compras\TipoAhorro;
use App\Repositories\Compras\TipoAhorroRepositoryInterface;
use Illuminate\Http\Request;

/**
 * ABM de tipos de ahorro (KPI de ahorro en requisición y OC).
 */
class TipoAhorroController extends Controller
{
    public function __construct(private TipoAhorroRepositoryInterface $repository)
    {
    }

    public function index()
    {
        can('listar-tipo-ahorro');
        $datas = $this->repository->paginar();

        return view('compras.tipoahorro.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-tipo-ahorro');
        $data = new TipoAhorro(['activo' => true, 'orden' => 0]);

        return view('compras.tipoahorro.crear', compact('data'));
    }

    public function guardar(ValidacionTipoAhorro $request)
    {
        can('crear-tipo-ahorro');
        $this->repository->create($request->all());

        return redirect('compras/tipoahorro')->with('mensaje', 'Tipo de ahorro creado con éxito');
    }

    public function editar($id)
    {
        can('editar-tipo-ahorro');
        $data = $this->repository->findOrFail($id);

        return view('compras.tipoahorro.editar', compact('data'));
    }

    public function actualizar(ValidacionTipoAhorro $request, $id)
    {
        can('actualizar-tipo-ahorro');
        $this->repository->update($request->all(), $id);

        return redirect('compras/tipoahorro')->with('mensaje', 'Tipo de ahorro actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-ahorro');

        if (! $request->ajax()) {
            abort(404);
        }

        // Un tipo usado en líneas no se borra: se desactiva (queda el histórico del KPI).
        if ($this->repository->lineasQueLoUsan((int) $id) > 0) {
            return response()->json([
                'mensaje' => 'ng',
                'detalle' => 'El tipo tiene líneas de requisición u OC. Desactivalo en lugar de eliminarlo.',
            ]);
        }

        return response()->json(['mensaje' => $this->repository->delete($id) ? 'ok' : 'ng']);
    }
}
