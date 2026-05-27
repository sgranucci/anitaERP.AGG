<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Subcategoria;
use App\Models\Ventas\AreaComandaGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\ValidacionSubcategoria;

class SubcategoriaController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index()
    {
        can('listar-subcategorias');
        $datas = Subcategoria::orderBy('id')->get();

        return view('stock.subcategoria.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-subcategorias');

        $data = new Subcategoria();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $area_query = $this->cargarAreasPorEmpresa($empresa_query->pluck('id')->all());

        return view('stock.subcategoria.crear', compact('data', 'empresa_query', 'area_query'));
    }

    public function guardar(ValidacionSubcategoria $request)
    {
        $subcategoria = DB::transaction(function () use ($request) {
            $subcategoria = Subcategoria::create($request->all());
            $this->sincronizarAreasComanda($subcategoria, $request->input('area_comanda_ids', []));

            return $subcategoria;
        });

        $Subcategoria = new Subcategoria();
        $Subcategoria->guardarAnita($request, $subcategoria->id);

        return redirect('stock/subcategoria')->with('mensaje', 'Subcategoria creado con exito');
    }

    public function editar($id)
    {
        can('editar-subcategorias');
        $data = Subcategoria::with(['subcategoriaAreasComanda.areaComanda.empresa'])->findOrFail($id);

        $empresa_query = $this->empresaRepository->allFiltrado();
        $area_query = $this->cargarAreasPorEmpresa($empresa_query->pluck('id')->all());

        return view('stock.subcategoria.editar', compact('data', 'empresa_query', 'area_query'));
    }

    public function actualizar(ValidacionSubcategoria $request, $id)
    {
        can('actualizar-subcategorias');

        DB::transaction(function () use ($request, $id) {
            $subcategoria = Subcategoria::findOrFail($id);
            $subcategoria->update($request->all());
            $this->sincronizarAreasComanda($subcategoria, $request->input('area_comanda_ids', []));
        });

        $Subcategoria = new Subcategoria();
        $Subcategoria->actualizarAnita($request, $id);

        return redirect('stock/subcategoria')->with('mensaje', 'Subcategoria actualizado con exito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-subcategorias');

        $Subcategoria = new Subcategoria();
        $Subcategoria->eliminarAnita($request->codigo);

        if ($request->ajax()) {
            if (Subcategoria::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    /**
     * Sincroniza las áreas de comanda asignadas a la subcategoría.
     * Evita duplicados y descarta valores vacíos o repetidos del request.
     */
    private function sincronizarAreasComanda(Subcategoria $subcategoria, $areaIds): void
    {
        $ids = collect((array) $areaIds)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        $subcategoria->areasComanda()->sync($ids);
    }

    /**
     * Devuelve las áreas de comanda agrupadas por empresa_id, listas para usar en los selects.
     */
    private function cargarAreasPorEmpresa(array $empresaIds): array
    {
        $query = AreaComandaGastronomia::orderBy('empresa_id')->orderBy('nombre');

        if (count($empresaIds) > 1) {
            $query->whereIn('empresa_id', $empresaIds);
        }

        return $query->get()
            ->groupBy('empresa_id')
            ->map(fn ($coll) => $coll->values())
            ->toArray();
    }
}
