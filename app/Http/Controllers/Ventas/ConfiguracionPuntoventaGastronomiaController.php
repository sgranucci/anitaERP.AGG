<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConfiguracionPuntoventaGastronomia;
use App\Models\Stock\Depmae;
use App\Models\Stock\Listaprecio;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Ventas\Puntoventa;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\SalidaRepositoryInterface;
use App\Repositories\Ventas\ConfiguracionPuntoventaGastronomiaRepositoryInterface;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use App\Repositories\Ventas\UbicacionGastronomiaRepositoryInterface;
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
            $empresaId
        );

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
        ));
    }

    public function guardar(ValidacionConfiguracionPuntoventaGastronomia $request)
    {
        $this->repository->create($request->all());

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
            (int) $data->empresa_id
        );

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
        ));
    }

    public function actualizar(ValidacionConfiguracionPuntoventaGastronomia $request, $id)
    {
        can('actualizar-configuracion-puntoventa-gastronomia');
        $this->repository->update($request->all(), $id);

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
            ->orderByRaw('CAST(codigo AS UNSIGNED) ASC');
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
            ->orderByRaw('CAST(codigo AS UNSIGNED) ASC')
            ->get();
        $tipotransaccion_query = $this->tipotransaccionRepository->all(['V', 'C'], ['A']);
        $tipotransaccion_nota_credito_query = $this->tipotransaccionRepository->all(['C'], ['A']);
        $tipotransaccion_caja_query = Tipotransaccion_Caja::query()
            ->where('operacion', 'C')
            ->orderBy('nombre')
            ->get(['id', 'abreviatura', 'nombre']);
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
