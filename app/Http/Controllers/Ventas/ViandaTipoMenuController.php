<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionViandaTipoMenu;
use App\Models\Ventas\ViandaTipoMenu;
use App\Repositories\Ventas\ViandaTipoMenuRepositoryInterface;
use App\Support\Ventas\Vianda\ViandaEmpresaSupport;
use App\Support\Ventas\ViandaDiaSemanaSupport;
use Illuminate\Http\Request;

class ViandaTipoMenuController extends Controller
{
    public function __construct(
        private ViandaTipoMenuRepositoryInterface $repository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-vianda-tipo-menu-gastronomia');

        $empresaFiltro = (int) $request->input('empresa_id', 0);
        if ($empresaFiltro > 0 && ! ViandaEmpresaSupport::empresaPermitida($empresaFiltro)) {
            abort(403, 'No tiene acceso a la empresa seleccionada.');
        }

        $datas = $this->repository->all($empresaFiltro > 0 ? $empresaFiltro : null);
        $sinRegistros = $datas->isEmpty();
        $diasSemana = ViandaDiaSemanaSupport::ETIQUETAS;

        return view('ventas.vianda_tipo_menu.index', compact('datas', 'sinRegistros', 'diasSemana') + [
            'empresa_query' => ViandaEmpresaSupport::empresasSeleccionables(),
            'empresa_id' => $empresaFiltro > 0 ? $empresaFiltro : null,
            'mostrarFiltroEmpresa' => ViandaEmpresaSupport::empresasSeleccionables()->isNotEmpty(),
        ]);
    }

    public function crear()
    {
        can('crear-vianda-tipo-menu-gastronomia');

        $data = new ViandaTipoMenu(['estado' => 'A', 'empresa_id' => 1]);
        $diasSemana = ViandaDiaSemanaSupport::ETIQUETAS;
        $articulosPorDia = $this->repository->agruparArticulosPorDia($data);
        $empresa_query = ViandaEmpresaSupport::empresasSeleccionables();

        return view('ventas.vianda_tipo_menu.crear', compact('data', 'diasSemana', 'articulosPorDia', 'empresa_query'));
    }

    public function guardar(ValidacionViandaTipoMenu $request)
    {
        can('crear-vianda-tipo-menu-gastronomia');

        $this->repository->create($request->all());

        return redirect('ventas/gastronomia/viandas/tipos-menu')->with('mensaje', 'Tipo de menú de vianda creado con éxito');
    }

    public function editar($id)
    {
        can('editar-vianda-tipo-menu-gastronomia');

        $data = $this->repository->findOrFail($id);
        if (! ViandaEmpresaSupport::empresaPermitida((int) $data->empresa_id)) {
            abort(403, 'No tiene acceso a la empresa de este tipo de menú.');
        }
        $diasSemana = ViandaDiaSemanaSupport::ETIQUETAS;
        $articulosPorDia = $this->repository->agruparArticulosPorDia($data);
        $empresa_query = ViandaEmpresaSupport::empresasSeleccionables((int) $data->empresa_id);

        return view('ventas.vianda_tipo_menu.editar', compact('data', 'diasSemana', 'articulosPorDia', 'empresa_query'));
    }

    public function actualizar(ValidacionViandaTipoMenu $request, $id)
    {
        can('actualizar-vianda-tipo-menu-gastronomia');

        $this->repository->update($request->all(), $id);

        return redirect('ventas/gastronomia/viandas/tipos-menu')->with('mensaje', 'Tipo de menú de vianda actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-vianda-tipo-menu-gastronomia');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
