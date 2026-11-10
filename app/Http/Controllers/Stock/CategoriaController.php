<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCategoria;
use App\Models\Stock\Categoria;
use App\Models\Stock\Tipoarticulo;
use App\Models\Ventas\AreaComandaGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoriaController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index()
    {
        can('listar-categorias');
        $datas = Categoria::with('tipoarticulo:id,nombre')->get();

        if ($datas->isEmpty()) {
            try {
                $Categoria = new Categoria();
                $Categoria->sincronizarConAnita();
            } catch (\Throwable $e) {
                return redirect()->route('categoria')->with('errores', [
                    'No se pudo importar categorías desde Anita (stkagr): '.$e->getMessage(),
                ]);
            }

            $datas = Categoria::with('tipoarticulo:id,nombre')->get();
        }

        return view('stock.categoria.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-categorias');
        $tipoarticulos = Tipoarticulo::all();
        $usaAreasCrown = EntornoEmpresaSupport::esCrown();
        $empresa_query = $usaAreasCrown ? $this->empresaRepository->allFiltrado() : collect();
        $area_query = $usaAreasCrown
            ? $this->cargarAreasPorEmpresa($empresa_query->pluck('id')->all())
            : [];

        return view('stock.categoria.crear', compact(
            'tipoarticulos',
            'usaAreasCrown',
            'empresa_query',
            'area_query'
        ));
    }

    public function guardar(ValidacionCategoria $request)
    {
        $categoria = DB::transaction(function () use ($request) {
            $categoria = Categoria::create($request->all());
            if (EntornoEmpresaSupport::esCrown()) {
                $this->sincronizarAreasComanda($categoria, $request->input('area_comanda_ids', []));
            }

            return $categoria;
        });

        $Categoria = new Categoria();
        $Categoria->guardarAnita($request, $categoria->id);

        return redirect('stock/categoria')->with('mensaje', 'Categoria creada con exito');
    }

    public function editar($id)
    {
        can('editar-categorias');
        $tipoarticulos = Tipoarticulo::all();
        $usaAreasCrown = EntornoEmpresaSupport::esCrown();
        $data = Categoria::query()
            ->when($usaAreasCrown, fn ($q) => $q->with(['categoriaAreasComanda.areaComanda.empresa']))
            ->findOrFail($id);
        $empresa_query = $usaAreasCrown ? $this->empresaRepository->allFiltrado() : collect();
        $area_query = $usaAreasCrown
            ? $this->cargarAreasPorEmpresa($empresa_query->pluck('id')->all())
            : [];

        return view('stock.categoria.editar', compact(
            'data',
            'tipoarticulos',
            'usaAreasCrown',
            'empresa_query',
            'area_query'
        ));
    }

    public function actualizar(ValidacionCategoria $request, $id)
    {
        can('actualizar-categorias');

        DB::transaction(function () use ($request, $id) {
            $categoria = Categoria::findOrFail($id);
            $categoria->update($request->all());
            if (EntornoEmpresaSupport::esCrown()) {
                $this->sincronizarAreasComanda($categoria, $request->input('area_comanda_ids', []));
            }
        });

        $Categoria = new Categoria();
        $Categoria->actualizarAnita($request, $id);

        return redirect('stock/categoria')->with('mensaje', 'Categoria actualizada con exito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-categorias');

        $Categoria = new Categoria();
        $Categoria->eliminarAnita($id);

        if ($request->ajax()) {
            if (Categoria::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    public function consultaCategoria(Request $request)
    {
        if (! $this->puedeConsultarCategoria()) {
            abort(403);
        }

        $consulta = trim((string) ($request->get('consulta') ?? ''));
        $query = Categoria::query()->select('id', 'nombre', 'codigo');
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('nombre', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('codigo', 'LIKE', '%'.$consulta.'%');
                if (ctype_digit($consulta)) {
                    $q->orWhere('id', (int) $consulta);
                }
            });
        }

        $data = $query->orderBy('nombre')->orderBy('codigo')->limit(200)->get();
        $puedeAbrirAbm = can('editar-categorias', false) || can('listar-categorias', false);

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
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultacategoria">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_categoria', [
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

    public function leeUnaCategoriaPorCodigo(string $codigo)
    {
        if (! $this->puedeConsultarCategoria()) {
            abort(403);
        }

        $fila = $this->findCategoriaPorCodigo($codigo);
        if ($fila === null) {
            return response()->json(null);
        }

        return response()->json([
            'id' => (int) $fila->id,
            'codigo' => (string) $fila->codigo,
            'nombre' => (string) $fila->nombre,
        ]);
    }

    private function sincronizarAreasComanda(Categoria $categoria, $areaIds): void
    {
        $ids = collect((array) $areaIds)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        $categoria->areasComanda()->sync($ids);
    }

    /**
     * @param  list<int|string>  $empresaIds
     * @return array<int|string, mixed>
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

    private function puedeConsultarCategoria(): bool
    {
        return can('listar-categorias', false)
            || can('editar-categorias', false)
            || can('listar-precios', false)
            || can('crear-precios', false)
            || can('editar-precios', false)
            || can('actualizar-precios', false)
            || can('listar-articulos', false)
            || can('listar-informe-stock-local', false);
    }

    private function findCategoriaPorCodigo(string $codigo): ?Categoria
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $base = Categoria::query()->select('id', 'nombre', 'codigo');
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
