<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Talle;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTalle;

class TalleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-talles');
        $datas = Talle::orderBy('id')->get();

        return view('stock.talle.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-talles');
        return view('stock.talle.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTalle $request)
    {
        $talle = Talle::create($request->all());

        return redirect('stock/talle')->with('mensaje', 'Talle creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-talles');
        $data = Talle::findOrFail($id);
        return view('stock.talle.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTalle $request, $id)
    {
        can('actualizar-talles');
        Talle::findOrFail($id)->update($request->all());

        return redirect('stock/talle')->with('mensaje', 'Talle actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-talles');

        if ($request->ajax()) {
            if (Talle::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function consultaTalle(Request $request)
    {
        $consulta = trim((string) $request->input('consulta', ''));
        $ids = $request->input('ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_filter(array_map('intval', $ids)));

        $q = Talle::query()->orderBy('codigo')->orderBy('nombre');
        if ($ids !== []) {
            $q->whereIn('id', $ids);
        }
        if ($consulta !== '') {
            $q->where(function ($w) use ($consulta) {
                $w->where('nombre', 'like', '%'.$consulta.'%')
                    ->orWhere('codigo', 'like', '%'.$consulta.'%')
                    ->orWhere('id', $consulta);
            });
        }
        $filas = $q->limit(80)->get(['id', 'codigo', 'nombre']);
        $html = '';
        foreach ($filas as $f) {
            $html .= '<tr data-id="'.$f->id.'" data-codigo="'.e((string) $f->codigo).'" data-nombre="'.e($f->nombre).'">'
                .'<td>'.$f->id.'</td>'
                .'<td>'.e((string) $f->codigo).'</td>'
                .'<td>'.e($f->nombre).'</td>'
                .'<td class="text-nowrap">'
                .'<button type="button" class="btn btn-sm btn-primary elige-talle">Elegir</button> '
                .'<a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="'.route('editar_talle', $f->id).'?origen=modal_consulta&vista=consulta">Consultar</a>'
                .'</td></tr>';
        }

        return response()->json(['data' => $html !== '' ? $html : '<tr><td colspan="4" class="text-muted">Sin resultados</td></tr>']);
    }

    public function resolverTalle(Request $request)
    {
        $valor = trim((string) $request->input('codigo', $request->input('valor', '')));
        $ids = $request->input('ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if ($valor === '') {
            return response()->json(['ok' => false]);
        }

        $q = Talle::query();
        if ($ids !== []) {
            $q->whereIn('id', $ids);
        }
        $talle = (clone $q)->where('codigo', $valor)->first()
            ?? (clone $q)->where('id', (int) $valor)->first()
            ?? (clone $q)->where('nombre', $valor)->first();

        if (! $talle) {
            return response()->json(['ok' => false, 'error' => 'Talle no encontrado']);
        }

        return response()->json([
            'ok' => true,
            'id' => (int) $talle->id,
            'codigo' => (string) $talle->codigo,
            'nombre' => (string) $talle->nombre,
        ]);
    }
}
