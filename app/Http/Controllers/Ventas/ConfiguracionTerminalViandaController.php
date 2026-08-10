<?php

namespace App\Http\Controllers\Ventas;

use App\Support\Database\SqlDialectSupport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConfiguracionTerminalVianda;
use App\Models\Stock\Depmae;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Models\Ventas\ConfiguracionTerminalVianda;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\SalidaRepositoryInterface;
use App\Repositories\Ventas\ConfiguracionTerminalViandaRepositoryInterface;
use App\Repositories\Ventas\UbicacionGastronomiaRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfiguracionTerminalViandaController extends Controller
{
    public function __construct(
        private ConfiguracionTerminalViandaRepositoryInterface $repository,
        private UbicacionGastronomiaRepositoryInterface $ubicacionGastronomiaRepository,
        private SalidaRepositoryInterface $salidaRepository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index()
    {
        can('listar-configuracion-terminal-vianda');

        $datas = $this->repository->all();

        return view('ventas.configuracion_terminal_vianda.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-configuracion-terminal-vianda');

        $data = new ConfiguracionTerminalVianda();
        $empresaId = (int) config('cliente.EMPRESA_DEFAULT_ID');
        $this->cargarSelects(
            $empresa_query,
            $ubicacion_query,
            $salida_query,
            $listaprecio_query,
            $tipotransaccion_query,
            $deposito_query,
            $empresaId
        );

        return view('ventas.configuracion_terminal_vianda.crear', compact(
            'data',
            'empresa_query',
            'ubicacion_query',
            'salida_query',
            'listaprecio_query',
            'tipotransaccion_query',
            'deposito_query',
        ));
    }

    public function guardar(ValidacionConfiguracionTerminalVianda $request)
    {
        $this->repository->create($request->all());

        return redirect()->route('consultar_configuracion_terminal_vianda')
            ->with('mensaje', 'Configuración creada con éxito');
    }

    public function editar($id)
    {
        can('editar-configuracion-terminal-vianda');

        $data = $this->repository->findOrFail($id);
        $this->cargarSelects(
            $empresa_query,
            $ubicacion_query,
            $salida_query,
            $listaprecio_query,
            $tipotransaccion_query,
            $deposito_query,
            (int) $data->empresa_id
        );

        return view('ventas.configuracion_terminal_vianda.editar', compact(
            'data',
            'empresa_query',
            'ubicacion_query',
            'salida_query',
            'listaprecio_query',
            'tipotransaccion_query',
            'deposito_query',
        ));
    }

    public function actualizar(ValidacionConfiguracionTerminalVianda $request, $id)
    {
        can('actualizar-configuracion-terminal-vianda');
        $this->repository->update($request->all(), $id);

        return redirect()->route('consultar_configuracion_terminal_vianda')
            ->with('mensaje', 'Configuración actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-configuracion-terminal-vianda');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    public function apiDepositosPorEmpresa(int $empresaId): JsonResponse
    {
        if (! can('crear-configuracion-terminal-vianda', false)
            && ! can('editar-configuracion-terminal-vianda', false)) {
            abort(403);
        }

        if ($empresaId <= 0) {
            return response()->json(['depositos' => [], 'ubicaciones' => []]);
        }

        $this->assertEmpresaPermitida($empresaId);

        return response()->json([
            'depositos' => $this->queryDepositosPorEmpresa($empresaId)
                ->get(['id', 'codigo', 'nombre'])
                ->map(fn ($dep) => [
                    'id' => $dep->id,
                    'label' => trim($dep->codigo.' — '.$dep->nombre),
                ])
                ->values()
                ->all(),
            'ubicaciones' => $this->ubicacionGastronomiaRepository->listarParaSelect($empresaId)
                ->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->nombre])
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Depmae>
     */
    private function queryDepositosPorEmpresa(?int $empresaId)
    {
        return Depmae::query()
            ->paraUsuarioAutorizado()
            ->when($empresaId !== null && $empresaId > 0, fn ($q) => $q->paraEmpresa($empresaId))
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'));
    }

    private function cargarSelects(
        &$empresa_query,
        &$ubicacion_query,
        &$salida_query,
        &$listaprecio_query,
        &$tipotransaccion_query,
        &$deposito_query,
        ?int $empresaId = null,
    ): void {
        $empresa_query = $this->empresaRepository->allFiltrado();
        $ubicacion_query = $this->ubicacionGastronomiaRepository->listarParaSelect($empresaId);
        $salida_query = $this->salidaRepository->all()->sortBy('nombre')->values();
        $listaprecio_query = Listaprecio::query()
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))
            ->get();
        $tipotransaccion_query = Tipotransaccion_Stock::query()
            ->where('operacion', 'S')
            ->where('estado', 'A')
            ->orderBy('nombre')
            ->get();
        $deposito_query = $this->queryDepositosPorEmpresa($empresaId)
            ->get(['id', 'codigo', 'nombre']);
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }
    }
}
