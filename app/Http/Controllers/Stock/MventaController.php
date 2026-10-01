<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Mventa;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionMventa;

class MventaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-marcas-de-venta');
        $datas = Mventa::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Mventa = new Mventa();
        	$Mventa->sincronizarConAnita();
	
        	$datas = Mventa::orderBy('id')->get();
		}

        return view('stock.mventa.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-marcas-de-venta');
        return view('stock.mventa.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionMventa $request)
    {
        $mventa = Mventa::create($request->all());

		// Graba anita
		$Mventa = new Mventa();
        $Mventa->guardarAnita($request, $mventa->id);

        return redirect('stock/mventa')->with('mensaje', 'Marca creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-marcas-de-venta');
        $data = Mventa::findOrFail($id);
        return view('stock.mventa.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionMventa $request, $id)
    {
        can('actualizar-marcas-de-venta');
        Mventa::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Mventa = new Mventa();
        $Mventa->actualizarAnita($request, $id);

        return redirect('stock/mventa')->with('mensaje', 'Marca actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-marcas-de-venta');

		// Elimina anita
		$Mventa = new Mventa();
        $Mventa->eliminarAnita($request->codigo);

        if ($request->ajax()) {
            if (Mventa::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function consultaMventa(Request $request)
    {
        if (! $this->puedeConsultarMventa()) {
            abort(403);
        }

        $consulta = trim((string) ($request->get('consulta') ?? ''));
        $query = Mventa::query()->select('id', 'nombre', 'codigo');
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
        $puedeAbrirAbm = can('editar-marcas-de-venta', false) || can('listar-marcas-de-venta', false);

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
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultamventa">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_mventa', [
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

    public function leeUnaMventaPorCodigo(string $codigo)
    {
        if (! $this->puedeConsultarMventa()) {
            abort(403);
        }

        $fila = $this->findMventaPorCodigo($codigo);
        if ($fila === null) {
            return response()->json(null);
        }

        return response()->json([
            'id' => (int) $fila->id,
            'codigo' => (string) $fila->codigo,
            'nombre' => (string) $fila->nombre,
        ]);
    }

    private function puedeConsultarMventa(): bool
    {
        return can('listar-marcas-de-venta', false)
            || can('editar-marcas-de-venta', false)
            || can('listar-precios', false)
            || can('crear-precios', false)
            || can('editar-precios', false)
            || can('actualizar-precios', false)
            || can('listar-articulos', false);
    }

    private function findMventaPorCodigo(string $codigo): ?Mventa
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $base = Mventa::query()->select('id', 'nombre', 'codigo');
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
