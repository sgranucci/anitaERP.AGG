<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Tipoarticulo;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTipoarticulo;

class TipoarticuloController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-tipo-articulo');
        $datas = Tipoarticulo::orderBy('id')->get();

        return view('stock.tipoarticulo.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipo-articulo');

        return view('stock.tipoarticulo.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTipoarticulo $request)
    {
        Tipoarticulo::create($request->validated());

        return redirect('stock/tipoarticulo')->with('mensaje', 'Tipo de articulo creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipo-articulo');
        $data = Tipoarticulo::findOrFail($id);

        return view('stock.tipoarticulo.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTipoarticulo $request, $id)
    {
        can('actualizar-tipo-articulo');
        Tipoarticulo::findOrFail($id)->update($request->validated());

        return redirect('stock/tipoarticulo')->with('mensaje', 'Tipo de articulo actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-articulo');

        if ($request->ajax()) {
            if (Tipoarticulo::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function consultaTipoarticulo(Request $request)
    {
        if (! $this->puedeConsultarTipoarticulo()) {
            abort(403);
        }

        $consulta = trim((string) ($request->get('consulta') ?? ''));
        $query = Tipoarticulo::query()->select('id', 'nombre', 'abreviatura');
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('nombre', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('abreviatura', 'LIKE', '%'.$consulta.'%');
                if (ctype_digit($consulta)) {
                    $q->orWhere('id', (int) $consulta);
                }
            });
        }

        $data = $query->orderBy('nombre')->orderBy('abreviatura')->limit(200)->get();
        $puedeAbrirAbm = can('editar-tipo-articulo', false) || can('listar-tipo-articulo', false);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="4">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="codigo">'.e($row->abreviatura).'</td>';
                $output['data'] .= '<td class="nombre">'.e($row->nombre).'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultatipoarticulo">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_tipoarticulo', [
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

    public function leeUnTipoarticuloPorCodigo(string $codigo)
    {
        if (! $this->puedeConsultarTipoarticulo()) {
            abort(403);
        }

        $codigo = trim($codigo);
        if ($codigo === '') {
            return response()->json(null);
        }

        $fila = Tipoarticulo::query()
            ->select('id', 'nombre', 'abreviatura')
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', [mb_strtoupper($codigo)])
            ->first();
        if (! $fila && ctype_digit($codigo)) {
            $fila = Tipoarticulo::query()->select('id', 'nombre', 'abreviatura')->find((int) $codigo);
        }
        if ($fila === null) {
            return response()->json(null);
        }

        return response()->json([
            'id' => (int) $fila->id,
            'codigo' => (string) ($fila->abreviatura ?? ''),
            'nombre' => (string) ($fila->nombre ?? ''),
        ]);
    }

    private function puedeConsultarTipoarticulo(): bool
    {
        return can('listar-tipo-articulo', false)
            || can('editar-tipo-articulo', false)
            || can('listar-articulos', false)
            || can('listar-reporte-historial-precios-compra', false);
    }
}
