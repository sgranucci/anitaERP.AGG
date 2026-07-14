<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCobrador;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\CobradorRepositoryInterface;
use Illuminate\Http\Request;

class CobradorController extends Controller
{
    private $repository;

    private $empresaRepository;

    public function __construct(
        CobradorRepositoryInterface $repository,
        EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->repository = $repository;
        $this->empresaRepository = $empresaRepository;
    }

    public function index()
    {
        can('listar-cobrador');
        $datas = $this->repository->all();

        return view('ventas.cobrador.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-cobrador');
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('ventas.cobrador.crear', compact('empresa_query'));
    }

    public function guardar(ValidacionCobrador $request)
    {
        $this->repository->create($request->all());

        return redirect('ventas/cobrador')->with('mensaje', 'Cobrador creado con éxito');
    }

    public function editar($id)
    {
        can('editar-cobrador');
        $data = $this->repository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('ventas.cobrador.editar', compact('data', 'empresa_query'));
    }

    public function actualizar(ValidacionCobrador $request, $id)
    {
        can('actualizar-cobrador');
        $this->repository->update($request->all(), $id);

        $params = ['id' => $id];
        if (request('origen') === 'modal_consulta') {
            $params['origen'] = 'modal_consulta';
            $params['vista'] = 'consulta';

            return redirect()->route('editar_cobrador', $params)
                ->with('mensaje', 'Cobrador actualizado con éxito');
        }

        return redirect('ventas/cobrador')->with('mensaje', 'Cobrador actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-cobrador');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    public function consultaCobrador(Request $request)
    {
        if (! can('listar-cobrador', false)
            && ! can('listar-clientes', false)
            && ! can('editar-clientes', false)
            && ! can('crear-clientes', false)) {
            abort(403);
        }

        return $this->repository->consultaCobrador((string) ($request->get('consulta') ?? ''));
    }

    public function leeUnCobrador(string $codigo)
    {
        if (! can('listar-cobrador', false)
            && ! can('listar-clientes', false)
            && ! can('editar-clientes', false)
            && ! can('crear-clientes', false)) {
            abort(403);
        }

        return $this->repository->findPorCodigo($codigo);
    }
}
