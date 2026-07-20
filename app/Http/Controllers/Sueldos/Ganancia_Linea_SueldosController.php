<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionGanancia_Linea_Sueldos;
use App\Models\Sueldos\Ganancia_Deduccion_Sueldos;
use App\Repositories\Sueldos\Ganancia_Linea_SueldosRepositoryInterface;
use App\Support\Sueldos\GananciaLineaSueldosListadoFiltros;
use Illuminate\Http\Request;

class Ganancia_Linea_SueldosController extends Controller
{
    private Ganancia_Linea_SueldosRepositoryInterface $repository;

    public function __construct(Ganancia_Linea_SueldosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        can('listar-ganancia-linea-sueldos');

        $filtros = GananciaLineaSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeGananciaLinea($filtros, true);

        return view('sueldos.ganancia_linea.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => GananciaLineaSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => GananciaLineaSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function crear()
    {
        can('crear-ganancia-linea-sueldos');

        return view('sueldos.ganancia_linea.crear', [
            'deduccionesCatalogo' => $this->deduccionesCatalogo(),
        ]);
    }

    public function guardar(ValidacionGanancia_Linea_Sueldos $request)
    {
        can('crear-ganancia-linea-sueldos');
        $this->repository->create($request->validated());

        return redirect('sueldos/ganancia-linea')
            ->with('mensaje', 'Línea de planilla creada con éxito');
    }

    public function editar($id)
    {
        can('editar-ganancia-linea-sueldos');
        $data = $this->repository->findOrFail($id);

        return view('sueldos.ganancia_linea.editar', [
            'data' => $data,
            'deduccionesCatalogo' => $this->deduccionesCatalogo(),
        ]);
    }

    public function actualizar(ValidacionGanancia_Linea_Sueldos $request, $id)
    {
        can('actualizar-ganancia-linea-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/ganancia-linea')
            ->with('mensaje', 'Línea de planilla actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-ganancia-linea-sueldos');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Sueldos\Ganancia_Deduccion_Sueldos>
     */
    private function deduccionesCatalogo()
    {
        return Ganancia_Deduccion_Sueldos::query()
            ->where('activo', true)
            ->orderBy('codigo')
            ->get(['codigo', 'descripcion']);
    }
}
