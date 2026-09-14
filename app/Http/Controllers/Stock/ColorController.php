<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Color;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionColor;
use DataTables;

class ColorController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-colores');

        $datas = Color::orderBy('id')->get();

		if ($datas->isEmpty())
		{
            if (config('app.empresa') == 'CALZADOS FERLI')
            {
                $Color = new Color();
                $Color->sincronizarConAnita();
            }
        
        	$datas = Color::orderBy('id')->paginate(50);
		}  

        return view('stock.color.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-colores');
        return view('stock.color.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionColor $request)
    {
        $color = Color::create($request->all());

		// Graba anita
        if (config('app.empresa') == 'CALZADOS FERLI')
        {        
            $Color = new Color();
            $Color->guardarAnita($request);
        }

        return redirect('stock/color')->with('mensaje', 'Color creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-colores');
        $data = Color::findOrFail($id);
        return view('stock.color.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionColor $request, $id)
    {
        can('actualizar-colores');
        Color::findOrFail($id)->update($request->all());

		// Actualiza anita
        if (config('app.empresa') == 'CALZADOS FERLI')
        {        
            $Color = new Color();
            $Color->actualizarAnita($request);
        }

        return redirect('stock/color')->with('mensaje', 'Color actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-colores');

		// Elimina anita
        if (config('app.empresa') == 'CALZADOS FERLI')
        {
            $Color = new Color();
            $Color->eliminarAnita($request->codigo);
        }

        if ($request->ajax()) {
            if (Color::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function consultaColor(Request $request)
    {
        $consulta = trim((string) $request->input('consulta', ''));
        $ids = $request->input('ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_filter(array_map('intval', $ids)));

        $q = Color::query()->orderBy('codigo')->orderBy('nombre');
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
                .'<button type="button" class="btn btn-sm btn-primary elige-color">Elegir</button> '
                .'<a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="'.route('editar_color', $f->id).'?origen=modal_consulta&vista=consulta">Consultar</a>'
                .'</td></tr>';
        }

        return response()->json(['data' => $html !== '' ? $html : '<tr><td colspan="4" class="text-muted">Sin resultados</td></tr>']);
    }

    public function resolverColor(Request $request)
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

        $q = Color::query();
        if ($ids !== []) {
            $q->whereIn('id', $ids);
        }
        $color = (clone $q)->where('codigo', $valor)->first()
            ?? (clone $q)->where('id', (int) $valor)->first()
            ?? (clone $q)->where('nombre', $valor)->first();

        if (! $color) {
            return response()->json(['ok' => false, 'error' => 'Color no encontrado']);
        }

        return response()->json([
            'ok' => true,
            'id' => (int) $color->id,
            'codigo' => (string) $color->codigo,
            'nombre' => (string) $color->nombre,
        ]);
    }
}
