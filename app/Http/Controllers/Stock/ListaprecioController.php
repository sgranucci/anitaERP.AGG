<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Tiponumeracion;
use Illuminate\Support\Facades\Storage;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Tipoarticulo;
use App\Http\Requests\ValidacionListaprecio;
use Auth;

class ListaprecioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-listaprecio');
        $datas = Listaprecio::with('usuario:id,nombre')->with('tiposnumeracion')->get();
        
		if ($datas->isEmpty())
		{
			$Listaprecio = new Listaprecio();
        	$Listaprecio->sincronizarConAnita();
	
        	$datas = Listaprecio::with('usuario:id,nombre')->get();
		}

        return view('stock.listaprecio.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-listaprecio');
        $tiponumeracion_query = Tiponumeracion::all();

        return view('stock.listaprecio.crear', compact('tiponumeracion_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionListaprecio $request)
    {
		$listaprecio = Listaprecio::create([
			"nombre" => $request->nombre,
			"formula" => $request->formula,
			"incluyeimpuesto" => $request->incluyeimpuesto,
			"codigo" => $request->codigo,
            "desdetalle" => $request->desdetalle,
            "hastatalle" => $request->hastatalle,
            "tiponumeracion_id" => $request->tiponumeracion_id,
			"usuarioultcambio_id" => Auth::user()->id,
				]);

		// Graba anita
		$Listaprecio = new Listaprecio();
        $Listaprecio->guardarAnita($request, $listaprecio->id);

        return redirect('stock/listaprecio')->with('mensaje', 'Lista de precio creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-listaprecio');

        $data = Listaprecio::findOrFail($id);
        $tiponumeracion_query = Tiponumeracion::all();

        return view('stock.listaprecio.editar', compact('data', 'tiponumeracion_query'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionListaprecio $request, $id)
    {
        can('actualizar-listaprecio');

        $listaprecio = Listaprecio::findOrFail($id);

		$listaprecio->nombre = $request->nombre;
		$listaprecio->formula = $request->formula;
		$listaprecio->incluyeimpuesto = $request->incluyeimpuesto;
		$listaprecio->codigo = $request->codigo;
        $listaprecio->desdetalle = $request->desdetalle;
        $listaprecio->hastatalle = $request->hastatalle;
        $listaprecio->tiponumeracion_id = $request->tiponumeracion_id;
		$listaprecio->usuarioultcambio_id = Auth::user()->id;

		$listaprecio->save();

		// Actualiza anita
		$Listaprecio = new Listaprecio();
        $Listaprecio->actualizarAnita($request, $id);

        return redirect('stock/listaprecio')->with('mensaje', 'Lista de precio actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-listaprecio');

        $listaprecio = Listaprecio::findOrFail($id);

		// Elimina anita
		$Listaprecio = new Listaprecio();
        $Listaprecio->eliminarAnita($listaprecio->codigo);

        if ($request->ajax()) {
            if (Listaprecio::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function consultaListaprecio(Request $request)
    {
        if (! $this->puedeConsultarListaprecio()) {
            abort(403);
        }

        ini_set('max_execution_time', '300');
        ini_set('memory_limit', '512M');

        $consulta = strtoupper(trim((string) ($request->get('consulta') ?? '')));
        $query = Listaprecio::query()->select('id', 'nombre', 'codigo');
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('id', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('nombre', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('codigo', 'LIKE', '%'.$consulta.'%');
            });
        }

        $data = $query->orderBy('nombre')->orderBy('codigo')->limit(200)->get();
        $puedeAbrirAbm = can('editar-listaprecio', false) || can('listar-listaprecio', false);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="4">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="nombre">'.e($row->nombre).'</td>';
                $output['data'] .= '<td class="codigo">'.e($row->codigo).'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultalistaprecio">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_listaprecio', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td></tr>';
            }
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE);
    }

    public function leeUnListaprecioPorCodigo(string $codigo)
    {
        if (! $this->puedeConsultarListaprecio()) {
            abort(403);
        }

        return $this->findListaprecioPorCodigo($codigo);
    }

    private function puedeConsultarListaprecio(): bool
    {
        return can('listar-listaprecio', false)
            || can('listar-clientes', false)
            || can('editar-clientes', false)
            || can('crear-clientes', false)
            || can('listar-precios', false)
            || can('crear-precios', false)
            || can('editar-precios', false)
            || can('actualizar-precios', false)
            || can('listar-articulos', false);
    }

    private function findListaprecioPorCodigo(string $codigo): ?Listaprecio
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $listaprecio = Listaprecio::query()
            ->select('id', 'nombre', 'codigo')
            ->where('codigo', $codigo)
            ->first();
        if ($listaprecio) {
            return $listaprecio;
        }

        $alt = ltrim($codigo, '0');
        if ($alt !== '' && $alt !== $codigo) {
            $listaprecio = Listaprecio::query()
                ->select('id', 'nombre', 'codigo')
                ->where('codigo', $alt)
                ->first();
            if ($listaprecio) {
                return $listaprecio;
            }
        }

        if (ctype_digit($codigo)) {
            return Listaprecio::query()
                ->select('id', 'nombre', 'codigo')
                ->whereKey((int) $codigo)
                ->first();
        }

        return null;
    }
}
