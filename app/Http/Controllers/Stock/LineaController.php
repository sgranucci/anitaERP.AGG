<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\EliminarMasivoLineaRequest;
use App\Http\Requests\GuardarLineaRequest;
use App\Http\Requests\ActualizarLineaRequest;
use App\Models\Stock\Articulo;
use App\Models\Stock\Linea;
use App\Models\Stock\Modulo;
use App\Models\Stock\Tiponumeracion;
use App\Models\Stock\Numeracion;
use App\Models\Stock\Listaprecio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LineaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-lineas');
        $lineas = Linea::with('modulos')->with('tiponumeraciones:id,nombre')->with('numeraciones:id,nombre')->with('listaprecios:id,nombre')->get();
		$modulo_query = Modulo::all();

		if ($lineas->isEmpty())
		{
			$Linea = new Linea();
        	$Linea->sincronizarConAnita();
	
        	$lineas = Linea::with('modulos')->with('tiponumeraciones:id,nombre')->with('numeraciones:id,nombre')->with('listaprecios:id,nombre')->get();
		}

        return view('stock.linea.index', compact('lineas', 'modulo_query'));
    }

	public function leerModulos($articulo_id, $modulo_id = null)
    {
		$articulo = Articulo::select('linea_id')->where('id',$articulo_id)->first();

		$linea = NULL;
		$modulos = NULL;
		if ($articulo)
		{
			$linea = Linea::with('modulos')->where('id',$articulo->linea_id)->first();

			if ($linea)
			{
				$mod = $linea->modulos->pluck('id')->toArray();
				if ($modulo_id != null && $modulo_id != 0)
					$modulos = Modulo::select('id','nombre')->whereIn('id',$mod)->orWhere('id','=',$modulo_id)->get()->toArray();
				else
					$modulos = Modulo::select('id','nombre')->whereIn('id',$mod)->get()->toArray();
			}
		}

		return $modulos;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-lineas');
		$modulo_query = Modulo::all();
		$tiponumeracion_query = Tiponumeracion::all();
		$numeracion_query = Numeracion::all();
		$listaprecio_query = Listaprecio::all();

        return view('stock.linea.crear', compact('modulo_query', 'tiponumeracion_query', 'numeracion_query', 'listaprecio_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(GuardarLineaRequest $request)
    {
        $linea = Linea::create($request->all());

    	$modulos = $request->input('modulos', []);
    	for ($i_modulo=0; $i_modulo < count($modulos); $i_modulo++) {
        	if ($modulos[$i_modulo] != '') 
			{
				/* A anita envia descripcion del modulo */
				$modulo = Modulo::find($modulos[$i_modulo]);
            	$linea->modulos()->attach($modulos[$i_modulo]);
        	}
    	}

		// Graba anita
		$Linea = new Linea();
        $Linea->guardarAnita($request, $linea->id, $modulos);

    	return redirect('stock/linea')->with('mensaje', 'Linea creada con exito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-lineas');
		$modulo_query = Modulo::all();
		$tiponumeracion_query = Tiponumeracion::all();
		$numeracion_query = Numeracion::all();
		$listaprecio_query = Listaprecio::all();
		$linea = Linea::where('id', $id)->with('tiponumeraciones:id,nombre')->first();
        //$lineas = Linea::with('modulos')->with('tiponumeraciones:id,nombre')->with('numeraciones:id,nombre')->with('listaprecios:id,nombre')->get();

        return view('stock.linea.editar', compact('modulo_query', 'linea', 'tiponumeracion_query', 'numeracion_query', 'listaprecio_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ActualizarLineaRequest $request, $id)
    {
        can('actualizar-lineas');

        Linea::findOrFail($id)->update($request->all());

		$linea = Linea::findOrFail($id);
		$linea->modulos()->detach();
        $modulos = $request->input('modulos', []);
        for ($i_modulo=0; $i_modulo < count($modulos); $i_modulo++) 
		{
            if ($modulos[$i_modulo] != '') 
			{
				/* A anita envia descripcion del modulo */
				$modulo = Modulo::find($modulos[$i_modulo]);
                $linea->modulos()->attach($modulos[$i_modulo]);
            }
        }

		// Actualiza anita
		$Linea = new Linea();
        $Linea->actualizarAnita($request, $id, $modulos);

        return redirect('stock/linea')->with('mensaje', 'Linea actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-lineas');

        if ($request->ajax()) 
		{
			$linea = Linea::findOrFail($id);

			// Elimina anita
			$Linea = new Linea();
        	$Linea->eliminarAnita($linea->codigo);

			$fl_borro = false;
			$linea->modulos()->detach();
            if (($linea = Linea::destroy($id)))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function eliminarcompleto(EliminarMasivoLineaRequest $request)
    {
        can('borrar-lineas');

        if ($request->ajax()) {
            if (Linea::whereIn('id', request('ids'))->delete()) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function consultaLinea(Request $request)
    {
        if (! $this->puedeConsultarLinea()) {
            abort(403);
        }

        $consulta = trim((string) ($request->get('consulta') ?? ''));
        $query = Linea::query()->select('id', 'nombre', 'codigo');
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
        $puedeAbrirAbm = can('editar-lineas', false) || can('listar-lineas', false);

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
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultalinea">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_linea', [
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

    public function leeUnaLineaPorCodigo(string $codigo)
    {
        if (! $this->puedeConsultarLinea()) {
            abort(403);
        }

        $fila = $this->findLineaPorCodigo($codigo);
        if ($fila === null) {
            return response()->json(null);
        }

        return response()->json([
            'id' => (int) $fila->id,
            'codigo' => (string) $fila->codigo,
            'nombre' => (string) $fila->nombre,
        ]);
    }

    private function puedeConsultarLinea(): bool
    {
        return can('listar-lineas', false)
            || can('editar-lineas', false)
            || can('listar-articulos', false)
            || can('editar-articulos', false)
            || can('listar-movimientos-de-stock', false)
            || can('crear-movimientos-de-stock', false)
            || can('editar-movimientos-de-stock', false)
            || can('listar-ordenes-de-trabajo', false)
            || can('crear-ordenes-de-trabajo', false)
            || can('editar-ordenes-de-trabajo', false);
    }

    private function findLineaPorCodigo(string $codigo): ?Linea
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $base = Linea::query()->select('id', 'nombre', 'codigo');
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
