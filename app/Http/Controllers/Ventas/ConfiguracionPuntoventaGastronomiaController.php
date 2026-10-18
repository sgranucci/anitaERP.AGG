<?php

namespace App\Http\Controllers\Ventas;

use App\Support\Database\SqlDialectSupport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConfiguracionPuntoventaGastronomia;
use App\Models\Stock\Depmae;
use App\Models\Stock\Listaprecio;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\GastronomiaCrown\GastroPlanoMesa;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\WaitryMesaGastronomia;
use App\Models\Configuracion\Sala;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\SalidaRepositoryInterface;
use App\Repositories\Ventas\ConfiguracionPuntoventaGastronomiaRepositoryInterface;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use App\Repositories\Ventas\UbicacionGastronomiaRepositoryInterface;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfiguracionPuntoventaGastronomiaController extends Controller
{
    public function __construct(
        private ConfiguracionPuntoventaGastronomiaRepositoryInterface $repository,
        private UbicacionGastronomiaRepositoryInterface $ubicacionGastronomiaRepository,
        private SalidaRepositoryInterface $salidaRepository,
        private EmpresaRepositoryInterface $empresaRepository,
        private TipotransaccionRepositoryInterface $tipotransaccionRepository,
    ) {
    }

    public function index()
    {
        can('listar-configuracion-puntoventa-gastronomia');

        $datas = $this->repository->all();

        return view('ventas.configuracion_puntoventa_gastronomia.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-configuracion-puntoventa-gastronomia');

        $data = new ConfiguracionPuntoventaGastronomia();
        $empresaId = (int) config('cliente.EMPRESA_DEFAULT_ID');
        $this->cargarSelects(
            $empresa_query,
            $puntoventa_cae_query,
            $puntoventa_caea_query,
            $ubicacion_query,
            $salida_query,
            $listaprecio_query,
            $tipotransaccion_query,
            $tipotransaccion_nota_credito_query,
            $tipotransaccion_caja_query,
            $deposito_query,
            $mesas_waitry_query,
            $empresaId
        );
        $sala_query = $this->salaQuery();
        $plano_query = $this->planoQuery($empresaId);
        $planos_asignados = [];

        return view('ventas.configuracion_puntoventa_gastronomia.crear', compact(
            'data',
            'empresa_query',
            'puntoventa_cae_query',
            'puntoventa_caea_query',
            'ubicacion_query',
            'salida_query',
            'listaprecio_query',
            'tipotransaccion_query',
            'tipotransaccion_nota_credito_query',
            'tipotransaccion_caja_query',
            'deposito_query',
            'mesas_waitry_query',
            'sala_query',
            'plano_query',
            'planos_asignados',
        ));
    }

    public function guardar(ValidacionConfiguracionPuntoventaGastronomia $request)
    {
        $config = $this->repository->create($request->all());
        $this->sincronizarPlanos($config, $request);

        return redirect('ventas/configuracion-puntoventa-gastronomia')
            ->with('mensaje', 'Configuración creada con éxito');
    }

    public function editar($id)
    {
        can('editar-configuracion-puntoventa-gastronomia');

        $data = $this->repository->findOrFail($id);
        $this->cargarSelects(
            $empresa_query,
            $puntoventa_cae_query,
            $puntoventa_caea_query,
            $ubicacion_query,
            $salida_query,
            $listaprecio_query,
            $tipotransaccion_query,
            $tipotransaccion_nota_credito_query,
            $tipotransaccion_caja_query,
            $deposito_query,
            $mesas_waitry_query,
            (int) $data->empresa_id
        );
        $sala_query = $this->salaQuery();
        $plano_query = $this->planoQuery((int) $data->empresa_id);
        $planos_asignados = $data->planos()
            ->pluck('configuracion_puntoventa_gastronomia_plano.orden', 'gastro_plano_mesa.id')
            ->map(fn ($orden) => (int) $orden)
            ->all();

        return view('ventas.configuracion_puntoventa_gastronomia.editar', compact(
            'data',
            'empresa_query',
            'puntoventa_cae_query',
            'puntoventa_caea_query',
            'ubicacion_query',
            'salida_query',
            'listaprecio_query',
            'tipotransaccion_query',
            'tipotransaccion_nota_credito_query',
            'tipotransaccion_caja_query',
            'deposito_query',
            'mesas_waitry_query',
            'sala_query',
            'plano_query',
            'planos_asignados',
        ));
    }

    public function actualizar(ValidacionConfiguracionPuntoventaGastronomia $request, $id)
    {
        can('actualizar-configuracion-puntoventa-gastronomia');
        $this->repository->update($request->all(), $id);
        $this->sincronizarPlanos($this->repository->findOrFail($id), $request);

        return redirect('ventas/configuracion-puntoventa-gastronomia')
            ->with('mensaje', 'Configuración actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-configuracion-puntoventa-gastronomia');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    public function apiSelectsPorEmpresa(int $empresaId): JsonResponse
    {
        if (! can('crear-configuracion-puntoventa-gastronomia', false)
            && ! can('editar-configuracion-puntoventa-gastronomia', false)) {
            abort(403);
        }

        if ($empresaId <= 0) {
            return response()->json([
                'puntoventa_cae' => [],
                'puntoventa_caea' => [],
                'ubicaciones' => [],
                'depositos' => [],
                'mesas_waitry' => [],
                'planos' => [],
            ]);
        }

        $this->assertEmpresaPermitida($empresaId);

        $puntoventaQuery = Puntoventa::query()
            ->where('estado', 'A')
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre');

        return response()->json([
            'puntoventa_cae' => $this->formatPuntoventaOptions(
                // C = CAE clásico; E = electrónico Anita/AGG (mismo camino WSFE en emisión).
                (clone $puntoventaQuery)->whereIn('modofacturacion', ['C', 'E'])->get(['id', 'codigo', 'nombre'])
            ),
            'puntoventa_caea' => $this->formatPuntoventaOptions(
                (clone $puntoventaQuery)->where('modofacturacion', 'A')->get(['id', 'codigo', 'nombre'])
            ),
            'ubicaciones' => $this->ubicacionGastronomiaRepository->listarParaSelect($empresaId)
                ->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->nombre])
                ->values()
                ->all(),
            'depositos' => $this->formatDepositoOptions(
                $this->queryDepositosPorEmpresa($empresaId)->get(['id', 'codigo', 'nombre'])
            ),
            'mesas_waitry' => $this->mesasWaitryPorEmpresa($empresaId)
                ->map(fn (WaitryMesaGastronomia $mesa) => ['id' => $mesa->id, 'label' => $mesa->etiqueta()])
                ->values()
                ->all(),
            'planos' => $this->planoQuery($empresaId)
                ->map(fn (GastroPlanoMesa $plano) => [
                    'id' => (int) $plano->id,
                    'label' => $plano->etiqueta(),
                    'sala' => trim((string) (optional($plano->sala)->codigo.' '.optional($plano->sala)->nombre)),
                    'orden' => (int) $plano->orden,
                ])
                ->values()
                ->all(),
        ]);
    }

    private function formatPuntoventaOptions($collection): array
    {
        return $collection->map(fn ($pv) => [
            'id' => $pv->id,
            'label' => trim($pv->codigo.' — '.$pv->nombre),
        ])->values()->all();
    }

    private function formatDepositoOptions($collection): array
    {
        return $collection->map(fn ($dep) => [
            'id' => $dep->id,
            'label' => trim($dep->codigo.' — '.$dep->nombre),
        ])->values()->all();
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
        &$puntoventa_cae_query,
        &$puntoventa_caea_query,
        &$ubicacion_query,
        &$salida_query,
        &$listaprecio_query,
        &$tipotransaccion_query,
        &$tipotransaccion_nota_credito_query,
        &$tipotransaccion_caja_query,
        &$deposito_query,
        &$mesas_waitry_query,
        ?int $empresaId = null,
    ): void {
        $empresa_query = $this->empresaRepository->allFiltrado();

        $puntoventaQuery = Puntoventa::query()
            ->where('estado', 'A')
            ->when($empresaId !== null && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderBy('nombre');

        $puntoventa_cae_query = (clone $puntoventaQuery)->whereIn('modofacturacion', ['C', 'E'])->get();
        $puntoventa_caea_query = (clone $puntoventaQuery)->where('modofacturacion', 'A')->get();

        $ubicacion_query = $this->ubicacionGastronomiaRepository->listarParaSelect($empresaId);
        $salida_query = $this->salidaRepository->all()->sortBy('nombre')->values();
        $listaprecio_query = Listaprecio::query()
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))
            ->get();
        $tipotransaccion_query = $this->tipotransaccionRepository->all(['V', 'C'], ['A']);
        $tipotransaccion_nota_credito_query = $this->tipotransaccionRepository->all(['C'], ['A']);
        $tipotransaccion_caja_query = Tipotransaccion_Caja::query()
            ->where('operacion', 'C')
            ->orderBy('nombre')
            ->get(['id', 'abreviatura', 'nombre']);
        $deposito_query = $this->queryDepositosPorEmpresa($empresaId)
            ->get(['id', 'codigo', 'nombre']);
        $mesas_waitry_query = $this->mesasWaitryPorEmpresa($empresaId);
    }

    /**
     * @return \Illuminate\Support\Collection<int, WaitryMesaGastronomia>
     */
    private function mesasWaitryPorEmpresa(?int $empresaId)
    {
        return WaitryMesaGastronomia::query()
            ->when($empresaId !== null && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->where('activo', true)
            ->orderBy('servicio')
            ->orderBy('nombre')
            ->get();
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, Sala>
     */
    private function salaQuery()
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return collect();
        }

        $q = Sala::query()->orderBy('codigo')->orderBy('nombre');
        $ids = \App\Support\Ventas\UsuarioSalaAsignadaSupport::idsDesdeSesion();
        if (count($ids) >= 1) {
            $q->whereIn('id', $ids);
        }

        return $q->get(['id', 'codigo', 'nombre']);
    }

    /**
     * Planos de mesas disponibles para asignar a la PC (solo Crown).
     *
     * @return \Illuminate\Support\Collection<int, GastroPlanoMesa>
     */
    private function planoQuery(?int $empresaId)
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return collect();
        }

        return GastroPlanoMesa::query()
            ->with('sala:id,codigo,nombre')
            ->when($empresaId !== null && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Sincroniza el pivot `configuracion_puntoventa_gastronomia_plano` conservando el orden del formulario.
     */
    private function sincronizarPlanos(ConfiguracionPuntoventaGastronomia $config, Request $request): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! $request->has('plano_ids') && ! $request->has('plano_orden')) {
            return;
        }

        $ordenPorPlano = (array) $request->input('plano_orden', []);
        $idsValidos = GastroPlanoMesa::query()
            ->whereIn('id', array_map('intval', (array) $request->input('plano_ids', [])))
            ->where('empresa_id', $config->empresa_id)
            ->pluck('id');

        $sync = [];
        foreach ($idsValidos as $planoId) {
            $sync[(int) $planoId] = ['orden' => (int) ($ordenPorPlano[$planoId] ?? 0)];
        }

        $config->planos()->sync($sync);
    }
}
