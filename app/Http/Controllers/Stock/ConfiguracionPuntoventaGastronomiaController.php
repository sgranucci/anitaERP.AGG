<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConfiguracionPuntoventaGastronomia;
use App\Models\Configuracion\Empresa;
use App\Models\Stock\ConfiguracionPuntoventaGastronomia;
use App\Models\Stock\Listaprecio;
use App\Models\Ventas\Puntoventa;
use App\Repositories\Configuracion\SalidaRepositoryInterface;
use App\Repositories\Stock\ConfiguracionPuntoventaGastronomiaRepositoryInterface;
use App\Repositories\Stock\UbicacionGastronomiaRepositoryInterface;
use Illuminate\Http\Request;

class ConfiguracionPuntoventaGastronomiaController extends Controller
{
    public function __construct(
        private ConfiguracionPuntoventaGastronomiaRepositoryInterface $repository,
        private UbicacionGastronomiaRepositoryInterface $ubicacionGastronomiaRepository,
        private SalidaRepositoryInterface $salidaRepository,
    ) {
    }

    public function index()
    {
        can('listar-configuracion-puntoventa-gastronomia');

        $datas = $this->repository->all();

        return view('stock.configuracion_puntoventa_gastronomia.index', compact('datas'));
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
            $empresaId
        );

        return view('stock.configuracion_puntoventa_gastronomia.crear', compact(
            'data',
            'empresa_query',
            'puntoventa_cae_query',
            'puntoventa_caea_query',
            'ubicacion_query',
            'salida_query',
            'listaprecio_query',
        ));
    }

    public function guardar(ValidacionConfiguracionPuntoventaGastronomia $request)
    {
        $this->repository->create($request->all());

        return redirect('stock/configuracion-puntoventa-gastronomia')
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
            (int) $data->empresa_id
        );

        return view('stock.configuracion_puntoventa_gastronomia.editar', compact(
            'data',
            'empresa_query',
            'puntoventa_cae_query',
            'puntoventa_caea_query',
            'ubicacion_query',
            'salida_query',
            'listaprecio_query',
        ));
    }

    public function actualizar(ValidacionConfiguracionPuntoventaGastronomia $request, $id)
    {
        can('actualizar-configuracion-puntoventa-gastronomia');
        $this->repository->update($request->all(), $id);

        return redirect('stock/configuracion-puntoventa-gastronomia')
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

    private function cargarSelects(
        &$empresa_query,
        &$puntoventa_cae_query,
        &$puntoventa_caea_query,
        &$ubicacion_query,
        &$salida_query,
        &$listaprecio_query,
        ?int $empresaId = null,
    ): void {
        $empresa_query = Empresa::orderBy('nombre')->get();

        $puntoventaQuery = Puntoventa::query()
            ->where('estado', 'A')
            ->when($empresaId !== null && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderBy('nombre');

        $puntoventa_cae_query = (clone $puntoventaQuery)->where('modofacturacion', 'C')->get();
        $puntoventa_caea_query = (clone $puntoventaQuery)->where('modofacturacion', 'A')->get();

        $ubicacion_query = $this->ubicacionGastronomiaRepository->listarParaSelect($empresaId);
        $salida_query = $this->salidaRepository->all()->sortBy('nombre')->values();
        $listaprecio_query = Listaprecio::query()->orderBy('nombre')->get();
    }
}
