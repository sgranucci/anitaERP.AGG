<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionWaitryMesaGastronomia;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\WaitryMesaGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Http\Request;

class WaitryMesaGastronomiaController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index()
    {
        can('listar-configuracion-puntoventa-gastronomia');

        $query = WaitryMesaGastronomia::query()->with('empresa');
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);
        $datas = $query->orderBy('empresa_id')->orderBy('servicio')->orderBy('nombre')->get();

        return view('ventas.waitry_mesa_gastronomia.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-configuracion-puntoventa-gastronomia');

        $data = new WaitryMesaGastronomia(['activo' => true, 'nombre' => 'Mesa 1']);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('ventas.waitry_mesa_gastronomia.crear', compact('data', 'empresa_query'));
    }

    public function guardar(ValidacionWaitryMesaGastronomia $request)
    {
        can('crear-configuracion-puntoventa-gastronomia');
        $this->assertEmpresa((int) $request->input('empresa_id'));

        WaitryMesaGastronomia::query()->create($request->validated());

        return redirect()->route('consultar_waitry_mesa_gastronomia')
            ->with('mensaje', 'Mesa Waitry creada con éxito');
    }

    public function editar($id)
    {
        can('editar-configuracion-puntoventa-gastronomia');

        $data = WaitryMesaGastronomia::query()->findOrFail($id);
        $this->assertEmpresa((int) $data->empresa_id);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('ventas.waitry_mesa_gastronomia.editar', compact('data', 'empresa_query'));
    }

    public function actualizar(ValidacionWaitryMesaGastronomia $request, $id)
    {
        can('actualizar-configuracion-puntoventa-gastronomia');
        $this->assertEmpresa((int) $request->input('empresa_id'));

        $data = WaitryMesaGastronomia::query()->findOrFail($id);
        $this->assertEmpresa((int) $data->empresa_id);
        $data->update($request->validated());

        return redirect()->route('consultar_waitry_mesa_gastronomia')
            ->with('mensaje', 'Mesa Waitry actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-configuracion-puntoventa-gastronomia');

        if (! $request->ajax()) {
            abort(404);
        }

        $data = WaitryMesaGastronomia::query()->find($id);
        if ($data === null) {
            return response()->json(['mensaje' => 'ng']);
        }

        $this->assertEmpresa((int) $data->empresa_id);

        $enUso = ConfiguracionPuntoventaGastronomia::query()
            ->where('waitry_mesa_gastronomia_id', $data->id)
            ->exists();
        if ($enUso) {
            return response()->json([
                'mensaje' => 'ng',
                'error' => 'Hay PCs asignadas a esta mesa. Cambiá la asignación antes de borrarla.',
            ]);
        }

        $data->delete();

        return response()->json(['mensaje' => 'ok']);
    }

    private function assertEmpresa(int $empresaId): void
    {
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }
    }
}
