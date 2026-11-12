<?php

namespace App\Http\Controllers\Ventas\GastronomiaCrown;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConfiguracionPcGastronomiaCrown;
use App\Models\Configuracion\Sala;
use App\Models\Stock\Listaprecio;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\GastronomiaCrown\GastroPlanoMesa;
use App\Models\Ventas\Puntoventa;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\SalidaRepositoryInterface;
use App\Repositories\Ventas\ConfiguracionPuntoventaGastronomiaRepositoryInterface;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\SqlDialectSupport;
use App\Support\Ventas\UsuarioSalaAsignadaSupport;
use Illuminate\Http\Request;

class ConfiguracionPcGastronomiaCrownController extends Controller
{
    public function __construct(
        private ConfiguracionPuntoventaGastronomiaRepositoryInterface $repository,
        private SalidaRepositoryInterface $salidaRepository,
        private EmpresaRepositoryInterface $empresaRepository,
        private TipotransaccionRepositoryInterface $tipotransaccionRepository,
    ) {
    }

    public function index()
    {
        $this->assertCrown();
        can('listar-configuracion-puntoventa-gastronomia');

        $datas = $this->repository->all();

        return view('ventas.gastronomia_crown.configuracion_pc.index', compact('datas'));
    }

    public function crear()
    {
        $this->assertCrown();
        can('crear-configuracion-puntoventa-gastronomia');

        $data = new ConfiguracionPuntoventaGastronomia();
        $empresaId = (int) config('cliente.EMPRESA_DEFAULT_ID');
        $vars = $this->variablesFormulario($data, $empresaId);

        return view('ventas.gastronomia_crown.configuracion_pc.crear', $vars);
    }

    public function guardar(ValidacionConfiguracionPcGastronomiaCrown $request)
    {
        $this->assertCrown();
        can('crear-configuracion-puntoventa-gastronomia');

        $config = $this->repository->create($this->datos($request));
        $this->sincronizarPlanos($config, $request);

        return redirect()->route('consultar_configuracion_pc_gastronomia_crown')
            ->with('mensaje', 'Configuración de PC creada con éxito');
    }

    public function editar($id)
    {
        $this->assertCrown();
        can('editar-configuracion-puntoventa-gastronomia');

        $data = $this->repository->findOrFail($id);
        $vars = $this->variablesFormulario($data, (int) $data->empresa_id);

        return view('ventas.gastronomia_crown.configuracion_pc.editar', $vars);
    }

    public function actualizar(ValidacionConfiguracionPcGastronomiaCrown $request, $id)
    {
        $this->assertCrown();
        can('actualizar-configuracion-puntoventa-gastronomia');

        $this->repository->update($this->datos($request), $id);
        $this->sincronizarPlanos($this->repository->findOrFail($id), $request);

        return redirect()->route('consultar_configuracion_pc_gastronomia_crown')
            ->with('mensaje', 'Configuración de PC actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        $this->assertCrown();
        can('borrar-configuracion-puntoventa-gastronomia');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(ValidacionConfiguracionPcGastronomiaCrown $request): array
    {
        $data = $request->validated();
        unset($data['plano_ids'], $data['plano_orden']);
        $data['waitry_habilitado'] = 0;
        $data['waitry_mesa_gastronomia_id'] = null;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function variablesFormulario(ConfiguracionPuntoventaGastronomia $data, int $empresaId): array
    {
        $empresaId = $empresaId > 0 ? $empresaId : null;
        $puntoventaQuery = Puntoventa::query()
            ->where('estado', 'A')
            ->when($empresaId !== null, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderBy('nombre');

        $planosAsignados = [];
        if ($data->exists) {
            $planosAsignados = $data->planos()
                ->pluck('configuracion_puntoventa_gastronomia_plano.orden', 'gastro_plano_mesa.id')
                ->map(fn ($orden) => (int) $orden)
                ->all();
        }

        return [
            'data' => $data,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'puntoventa_cae_query' => (clone $puntoventaQuery)->where('modofacturacion', 'C')->get(),
            'puntoventa_caea_query' => (clone $puntoventaQuery)->where('modofacturacion', 'A')->get(),
            'salida_query' => $this->salidaRepository->all()->sortBy('nombre')->values(),
            'listaprecio_query' => Listaprecio::query()
                ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))
                ->get(),
            'tipotransaccion_query' => $this->tipotransaccionRepository->all(['V'], ['A']),
            'tipotransaccion_nota_credito_query' => $this->tipotransaccionRepository->all(['C'], ['A']),
            'sala_query' => $this->salaQuery(),
            'plano_query' => $this->planoQuery($empresaId),
            'planos_asignados' => $planosAsignados,
        ];
    }

    private function salaQuery()
    {
        $q = Sala::query()->orderBy('codigo')->orderBy('nombre');
        $ids = UsuarioSalaAsignadaSupport::idsDesdeSesion();
        if (count($ids) >= 1) {
            $q->whereIn('id', $ids);
        }

        return $q->get(['id', 'codigo', 'nombre']);
    }

    private function planoQuery(?int $empresaId)
    {
        return GastroPlanoMesa::query()
            ->with('sala:id,codigo,nombre')
            ->when($empresaId !== null && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();
    }

    private function sincronizarPlanos(ConfiguracionPuntoventaGastronomia $config, Request $request): void
    {
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

    private function assertCrown(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            abort(404);
        }
    }
}
