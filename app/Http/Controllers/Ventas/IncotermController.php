<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Incoterm;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionIncoterm;
use App\Repositories\Ventas\IncotermRepositoryInterface;

class IncotermController extends Controller
{
	private $repository;

    public function __construct(IncotermRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-incoterms');
		$datas = $this->repository->all();

        return view('ventas.incoterm.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-incoterms');

        return view('ventas.incoterm.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionIncoterm $request)
    {
		$this->repository->create($request->all());

        return redirect('ventas/incoterm')->with('mensaje', 'Incoterm creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-incoterms');
        $data = $this->repository->findOrFail($id);

        return view('ventas.incoterm.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionIncoterm $request, $id)
    {
        can('actualizar-incoterms');

        $this->repository->update($request->all(), $id);

        return redirect('ventas/incoterm')->with('mensaje', 'Incoterm actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-incoterms');

        if ($request->ajax()) {
        	if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function consultaIncoterm(Request $request)
    {
        $consulta = trim((string) $request->input('consulta', ''));
        $q = Incoterm::query()->orderBy('abreviatura');
        if ($consulta !== '') {
            $q->where(function ($w) use ($consulta) {
                $w->where('abreviatura', 'like', '%'.$consulta.'%')
                    ->orWhere('nombre', 'like', '%'.$consulta.'%');
            });
        }
        $html = '';
        foreach ($q->limit(80)->get() as $row) {
            $html .= '<tr data-id="'.(int) $row->id.'" data-abreviatura="'.e($row->abreviatura).'" data-nombre="'.e($row->nombre).'">';
            $html .= '<td class="d-none">'.$row->id.'</td>';
            $html .= '<td>'.e($row->abreviatura).'</td>';
            $html .= '<td>'.e($row->nombre).'</td>';
            $html .= '<td class="text-nowrap">';
            $html .= '<a class="btn btn-warning btn-sm eligeconsultaincoterm">Elegir</a>';
            if (can('editar-incoterms', false) || can('listar-incoterms', false)) {
                $url = route('editar_incoterm', [
                    'id' => $row->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]);
                $html .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
            }
            $html .= '</td></tr>';
        }

        return response()->json(['data' => $html !== '' ? $html : '<tr><td colspan="4" class="text-muted">Sin resultados</td></tr>']);
    }

    public function leerPorAbreviatura(string $abreviatura)
    {
        $abrev = trim($abreviatura);
        $row = Incoterm::query()
            ->whereRaw('UPPER(abreviatura) = ?', [strtoupper($abrev)])
            ->first();

        if (! $row) {
            return response()->json([]);
        }

        return response()->json([
            'id' => $row->id,
            'abreviatura' => $row->abreviatura,
            'nombre' => $row->nombre,
        ]);
    }
}
