<?php

namespace App\Http\Controllers\Stock;

use App\Http\Requests\ValidacionCategoria;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Categoria;
use Illuminate\Support\Facades\Storage;
use App\Models\Stock\Tipoarticulo;

class CategoriaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-categorias');
        $datas = Categoria::with('tipoarticulo:id,nombre')->get();

		if ($datas->isEmpty())
		{
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

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-categorias');
		$tipoarticulos = Tipoarticulo::all();

        return view('stock.categoria.crear', compact('tipoarticulos'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCategoria $request)
    {
        $categoria = Categoria::create($request->all());

		// Graba anita
		$Categoria = new Categoria();
        $Categoria->guardarAnita($request, $categoria->id);

        return redirect('stock/categoria')->with('mensaje', 'Categoria creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-categorias');
		$tipoarticulos = Tipoarticulo::all();

        $data = Categoria::findOrFail($id);

        return view('stock.categoria.editar', compact('data', 'tipoarticulos'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCategoria $request, $id)
    {
        can('actualizar-categorias');
        Categoria::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Categoria = new Categoria();
        $Categoria->actualizarAnita($request, $id);

        return redirect('stock/categoria')->with('mensaje', 'Categoria actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-categorias');

		// Elimina anita
		$Categoria = new Categoria();
        $Categoria->eliminarAnita($id);

        if ($request->ajax()) {
            if (Categoria::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
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
