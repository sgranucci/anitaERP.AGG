<?php

namespace App\Http\Controllers\Caja\Estacionamiento;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConfiguracionPuntoventaEstacionamiento;
use App\Models\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamiento;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Ventas\Puntoventa;
use App\Repositories\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamientoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\SalidaRepositoryInterface;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfiguracionPuntoventaEstacionamientoController extends Controller
{
    public function __construct(
        private ConfiguracionPuntoventaEstacionamientoRepositoryInterface $repository,
        private SalidaRepositoryInterface $salidaRepository,
        private EmpresaRepositoryInterface $empresaRepository,
        private TipotransaccionRepositoryInterface $tipotransaccionRepository,
    ) {
    }

    public function index()
    {
        can('listar-configuracion-puntoventa-estacionamiento');

        $datas = $this->repository->all();

        return view('caja.estacionamiento.configuracion_puntoventa.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-configuracion-puntoventa-estacionamiento');

        $data = new ConfiguracionPuntoventaEstacionamiento();
        $empresaId = (int) config('cliente.EMPRESA_DEFAULT_ID');
        $this->cargarSelects(
            $empresa_query,
            $puntoventa_cae_query,
            $puntoventa_caea_query,
            $salida_query,
            $tipotransaccion_query,
            $tipotransaccion_nota_credito_query,
            $tipotransaccion_caja_query,
            $empresaId
        );

        return view('caja.estacionamiento.configuracion_puntoventa.crear', compact(
            'data',
            'empresa_query',
            'puntoventa_cae_query',
            'puntoventa_caea_query',
            'salida_query',
            'tipotransaccion_query',
            'tipotransaccion_nota_credito_query',
            'tipotransaccion_caja_query',
        ));
    }

    public function guardar(ValidacionConfiguracionPuntoventaEstacionamiento $request)
    {
        can('crear-configuracion-puntoventa-estacionamiento');
        $this->repository->create($request->all());

        return redirect('caja/estacionamiento/configuracion-puntoventa')
            ->with('mensaje', 'Configuración creada con éxito');
    }

    public function editar($id)
    {
        can('editar-configuracion-puntoventa-estacionamiento');

        $data = $this->repository->findOrFail($id);
        $this->cargarSelects(
            $empresa_query,
            $puntoventa_cae_query,
            $puntoventa_caea_query,
            $salida_query,
            $tipotransaccion_query,
            $tipotransaccion_nota_credito_query,
            $tipotransaccion_caja_query,
            (int) $data->empresa_id
        );

        return view('caja.estacionamiento.configuracion_puntoventa.editar', compact(
            'data',
            'empresa_query',
            'puntoventa_cae_query',
            'puntoventa_caea_query',
            'salida_query',
            'tipotransaccion_query',
            'tipotransaccion_nota_credito_query',
            'tipotransaccion_caja_query',
        ));
    }

    public function actualizar(ValidacionConfiguracionPuntoventaEstacionamiento $request, $id)
    {
        can('actualizar-configuracion-puntoventa-estacionamiento');
        $this->repository->update($request->all(), $id);

        return redirect('caja/estacionamiento/configuracion-puntoventa')
            ->with('mensaje', 'Configuración actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-configuracion-puntoventa-estacionamiento');

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
        if (! can('crear-configuracion-puntoventa-estacionamiento', false)
            && ! can('editar-configuracion-puntoventa-estacionamiento', false)) {
            abort(403);
        }

        if ($empresaId <= 0) {
            return response()->json([
                'puntoventa_cae' => [],
                'puntoventa_caea' => [],
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
        ]);
    }

    private function formatPuntoventaOptions($collection): array
    {
        return $collection->map(fn ($pv) => [
            'id' => $pv->id,
            'label' => trim($pv->codigo.' — '.$pv->nombre),
        ])->values()->all();
    }

    private function cargarSelects(
        &$empresa_query,
        &$puntoventa_cae_query,
        &$puntoventa_caea_query,
        &$salida_query,
        &$tipotransaccion_query,
        &$tipotransaccion_nota_credito_query,
        &$tipotransaccion_caja_query,
        ?int $empresaId = null,
    ): void {
        $empresa_query = $this->empresaRepository->allFiltrado();

        $puntoventaQuery = Puntoventa::query()
            ->where('estado', 'A')
            ->when($empresaId !== null && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderBy('nombre');

        $puntoventa_cae_query = (clone $puntoventaQuery)->whereIn('modofacturacion', ['C', 'E'])->get();
        $puntoventa_caea_query = (clone $puntoventaQuery)->where('modofacturacion', 'A')->get();

        $salida_query = $this->salidaRepository->all()->sortBy('nombre')->values();
        $tipotransaccion_query = $this->tipotransaccionRepository->all(['V', 'C'], ['A']);
        $tipotransaccion_nota_credito_query = $this->tipotransaccionRepository->all(['C'], ['A']);
        $tipotransaccion_caja_query = Tipotransaccion_Caja::query()
            ->where('operacion', 'C')
            ->orderBy('nombre')
            ->get(['id', 'abreviatura', 'nombre']);
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }
    }
}
