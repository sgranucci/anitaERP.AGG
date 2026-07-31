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
        $empresa_query_replicar = ViandaEmpresaSupport::empresasModulo();

        return view('ventas.vianda_tipo_menu.index', compact('datas', 'sinRegistros', 'diasSemana') + [
            'empresa_query' => ViandaEmpresaSupport::empresasSeleccionables(),
            'empresa_id' => $empresaFiltro > 0 ? $empresaFiltro : null,
            'mostrarFiltroEmpresa' => ViandaEmpresaSupport::empresasSeleccionables()->isNotEmpty(),
            'empresa_query_replicar' => $empresa_query_replicar,
            'puede_replicar_vianda_tipo_menu' => $this->puedeReplicarTipoMenu(),
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
        $empresa_query_replicar = ViandaEmpresaSupport::empresasModulo();

        return view('ventas.vianda_tipo_menu.editar', compact('data', 'diasSemana', 'articulosPorDia', 'empresa_query') + [
            'empresa_query_replicar' => $empresa_query_replicar,
            'puede_replicar_vianda_tipo_menu' => $this->puedeReplicarTipoMenu(),
        ]);
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

    /**
     * Replica el menú origen a una o más empresas destino, pisando artículos existentes
     * del menú homólogo (mismo código Anita).
     */
    public function replicar(Request $request, $id)
    {
        if (! $this->puedeReplicarTipoMenu()) {
            abort(403, 'No tiene permiso para replicar tipos de menú de vianda.');
        }

        $origen = $this->repository->findOrFail($id);
        if (! ViandaEmpresaSupport::empresaPermitida((int) $origen->empresa_id)) {
            abort(403, 'No tiene acceso a la empresa del menú origen.');
        }

        $empresasDestino = collect($request->input('empresa_destino_ids', []))
            ->map(static fn ($valor): int => (int) $valor)
            ->filter(static fn (int $valor): bool => $valor > 0)
            ->unique()
            ->values();

        if ($empresasDestino->isEmpty()) {
            $unica = (int) $request->input('empresa_destino_id', 0);
            if ($unica > 0) {
                $empresasDestino = collect([$unica]);
            }
        }

        if ($empresasDestino->isEmpty()) {
            return redirect()
                ->back()
                ->with('mensaje_error', 'Debe indicar al menos una empresa destino para replicar el menú.');
        }

        $modulo = collect(ViandaEmpresaSupport::idsModulo());
        $replicados = [];
        $errores = [];

        foreach ($empresasDestino as $empresaDestinoId) {
            if (! $modulo->contains($empresaDestinoId)) {
                $errores[] = 'Empresa '.$empresaDestinoId.': no pertenece al módulo de viandas.';
                continue;
            }
            if ($empresaDestinoId === (int) $origen->empresa_id) {
                $errores[] = 'No se puede replicar sobre la misma empresa origen.';
                continue;
            }

            try {
                $destino = $this->repository->replicarAEmpresa((int) $origen->id, $empresaDestinoId);
                $nombreEmpresa = optional($destino->empresa)->nombre ?: ('ID '.$empresaDestinoId);
                $replicados[] = $nombreEmpresa;
            } catch (\Throwable $e) {
                $errores[] = 'Empresa '.$empresaDestinoId.': '.$e->getMessage();
            }
        }

        if ($replicados === [] && $errores !== []) {
            return redirect()
                ->back()
                ->with('mensaje_error', 'No se pudo replicar el menú. '.implode(' ', $errores));
        }

        $mensaje = 'Menú «'.$origen->nombre.'» replicado a: '.implode(', ', $replicados).'. '
            .'Se pisaron los artículos del menú destino.';
        if ($errores !== []) {
            $mensaje .= ' Observaciones: '.implode(' ', $errores);
        }

        return redirect()
            ->route('consultar_vianda_tipo_menu_gastronomia')
            ->with('mensaje', $mensaje);
    }

    private function puedeReplicarTipoMenu(): bool
    {
        return can('actualizar-vianda-tipo-menu-gastronomia', false)
            || can('editar-vianda-tipo-menu-gastronomia', false);
    }
}
