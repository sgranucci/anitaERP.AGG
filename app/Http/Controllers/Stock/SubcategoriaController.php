<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Subcategoria;
use App\Models\Ventas\AreaComandaGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Ventas\FacturacionLocal\ArticuloCanalSupport;
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

    public function consultaSubcategoria(Request $request)
    {
        if (! $this->puedeConsultarSubcategoria()) {
            abort(403);
        }

        $consulta = trim((string) ($request->get('consulta') ?? ''));
        $categoriaId = (int) $request->input('categoria_id', 0);
        $query = Subcategoria::query()->select('id', 'nombre', 'codigo');
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('nombre', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('codigo', 'LIKE', '%'.$consulta.'%');
                if (ctype_digit($consulta)) {
                    $q->orWhere('id', (int) $consulta);
                }
            });
        }
        if ($categoriaId > 0) {
            $canalId = ArticuloCanalSupport::canalLocalId();
            $query->whereExists(function ($q) use ($categoriaId, $canalId) {
                $q->select(DB::raw(1))
                    ->from('articulo')
                    ->whereColumn('articulo.subcategoria_id', 'subcategoria.id')
                    ->where('articulo.categoria_id', $categoriaId);
                if ($canalId) {
                    $q->whereExists(function ($c) use ($canalId) {
                        $c->select(DB::raw(1))
                            ->from('articulo_canal')
                            ->whereColumn('articulo_canal.articulo_id', 'articulo.id')
                            ->where('articulo_canal.canal_id', $canalId);
                    });
                }
            });
        }

        $data = $query->orderBy('codigo')->orderBy('nombre')->limit(200)->get();
        $puedeAbrirAbm = can('editar-subcategorias', false) || can('listar-subcategorias', false);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="4">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="codigo">'.e($row->codigo).'</td>';
                $output['data'] .= '<td class="nombre">'.e($row->nombre).'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultasubcategoria">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_subcategoria', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td></tr>';
            }
        }

        return response()->json($output);
    }

    public function leeUnaSubcategoriaPorCodigo(string $codigo)
    {
        if (! $this->puedeConsultarSubcategoria()) {
            abort(403);
        }

        $fila = $this->findSubcategoriaPorCodigo($codigo);
        if ($fila === null) {
            return response()->json(null);
        }

        return response()->json([
            'id' => (int) $fila->id,
            'codigo' => (string) $fila->codigo,
            'nombre' => (string) $fila->nombre,
        ]);
    }

    private function puedeConsultarSubcategoria(): bool
    {
        return can('listar-subcategorias', false)
            || can('editar-subcategorias', false)
            || can('listar-articulos', false)
            || can('editar-articulos', false)
            || can('listar-informe-stock-local', false);
    }

    private function findSubcategoriaPorCodigo(string $codigo): ?Subcategoria
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $base = Subcategoria::query()->select('id', 'nombre', 'codigo');
        $fila = (clone $base)->where('codigo', $codigo)->first();
        if ($fila) {
            return $fila;
        }

        $alt = ltrim($codigo, '0');
        if ($alt !== '' && $alt !== $codigo) {
            $fila = (clone $base)->where('codigo', $alt)->first();
            if ($fila) {
                return $fila;
            }
        }

        if (ctype_digit($codigo)) {
            return (clone $base)->whereKey((int) $codigo)->first();
        }

        return null;
    }
}
