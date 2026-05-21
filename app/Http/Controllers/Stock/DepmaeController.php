<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Depmae;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionDepmae;

class DepmaeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-depositos');
        $datas = Depmae::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Depmae = new Depmae();
        	$Depmae->sincronizarConAnita();
	
        	$datas = Depmae::orderBy('id')->get();
		}

        return view('stock.depmae.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-depositos');

        $tipodeposito_enum = Depmae::$enumTipoDeposito;
        $data = new Depmae();

        return view('stock.depmae.crear', compact('tipodeposito_enum', 'data'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionDepmae $request)
    {
        can('crear-depositos');

        $depmae = Depmae::create($request->only(['codigo', 'nombre', 'tipodeposito']));

        $Depmae = new Depmae();
        $Depmae->guardarAnita($request, $request->codigo);

        return redirect('stock/depmae')->with('mensaje', 'Deposito creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-depositos');
        $data = Depmae::findOrFail($id);
        $tipodeposito_enum = Depmae::$enumTipoDeposito;

        return view('stock.depmae.editar', compact('data', 'tipodeposito_enum'));
    }

    public function consultaDeposito(Request $request)
    {
        if (! can('listar-depositos', false) && ! can('crear-transferencia-mercaderia', false)) {
            abort(403);
        }

        $consulta = strtoupper(trim((string) ($request->get('consulta') ?? '')));

        $query = Depmae::query()->select('id', 'codigo', 'nombre', 'tipodeposito');
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('codigo', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('nombre', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('tipodeposito', 'LIKE', '%'.$consulta.'%');
            });
        }

        $data = $query->orderBy('nombre')->limit(200)->get();

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="5">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="codigo">'.e($row->codigo).'</td>';
                $output['data'] .= '<td class="descripcion">'.e($row->nombre).'</td>';
                $output['data'] .= '<td class="tipodeposito">'.e($row->tipodeposito).'</td>';
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultadeposito">Elegir</a></td>';
                $output['data'] .= '</tr>';
            }
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE);
    }

    public function leeUnDepositoPorCodigo(string $codigo)
    {
        if (! can('listar-depositos', false) && ! can('crear-transferencia-mercaderia', false)) {
            abort(403);
        }

        $deposito = Depmae::query()
            ->where('codigo', trim($codigo))
            ->first();

        if (! $deposito) {
            return response()->json(['error' => 'Depósito no encontrado'], 404);
        }

        return response()->json([
            'id' => $deposito->id,
            'codigo' => $deposito->codigo,
            'descripcion' => $deposito->nombre,
            'tipodeposito' => $deposito->tipodeposito,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionDepmae $request, $id)
    {
        can('actualizar-depositos');
        Depmae::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Depmae = new Depmae();
        $Depmae->actualizarAnita($request, $id);

        return redirect('stock/depmae')->with('mensaje', 'Deposito actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-depositos');

		// Elimina anita
        $Depmae = new Depmae();
        if (config('app.empresa') == 'Calzados Ferli')
            $Depmae->eliminarAnita($id);
        else
            $Depmae->eliminarAnita($request->codigo);

        if ($request->ajax()) {
            if (Depmae::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
