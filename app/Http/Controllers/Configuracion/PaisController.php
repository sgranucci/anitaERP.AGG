<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPais;
use App\Models\Configuracion\Pais;
use App\Support\Http\EliminacionRegistroSupport;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class PaisController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-paises');
        $datas = Pais::orderBy('id')->get();

		$pais = new Pais();
		if ($datas->isEmpty()) {
			$pais->sincronizarConAnita();
			$datas = Pais::orderBy('id')->get();
		} elseif ($datas->contains(fn ($row) => trim((string) ($row->codigo ?? '')) === '')) {
			$pais->sincronizarCodigosDesdeAnita();
			$datas = Pais::orderBy('id')->get();
		}

        return view('configuracion.pais.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-paises');
        return view('configuracion.pais.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPais $request)
    {
        $pais = Pais::create($request->all());

		$modeloPais = new Pais();
        $modeloPais->guardarAnita($request, $request->codigo);

        return redirect('configuracion/pais')->with('mensaje', 'Pais creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-paises');
        $data = Pais::findOrFail($id);
        return view('configuracion.pais.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPais $request, $id)
    {
        can('actualizar-paises');
        $pais = Pais::findOrFail($id);
        $pais->update($request->all());

		$modeloPais = new Pais();
        $modeloPais->actualizarAnita($request, $pais->codigo);

        return redirect('configuracion/pais')->with('mensaje', 'Pais actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-paises');

        if (! $request->ajax()) {
            abort(404);
        }

        try {
            $pais = Pais::findOrFail($id);
            $codigoAnita = $pais->codigoAnita() ?? (string) $id;

            $bloqueo = EliminacionRegistroSupport::mensajeSiReferenciado('pais_id', (int) $id, null, 'el país');
            if ($bloqueo !== null) {
                return EliminacionRegistroSupport::respuestaJsonError($bloqueo);
            }

            if (! Pais::destroy($id)) {
                return EliminacionRegistroSupport::respuestaJsonError('No se pudo eliminar el país.');
            }

            (new Pais())->eliminarAnita($codigoAnita);

            return EliminacionRegistroSupport::respuestaJsonOk();
        } catch (QueryException $e) {
            return EliminacionRegistroSupport::respuestaJsonError(
                EliminacionRegistroSupport::mensajeDesdeQueryException($e, 'el país')
            );
        } catch (\Throwable $e) {
            return EliminacionRegistroSupport::respuestaJsonError(
                EliminacionRegistroSupport::mensajeDesdeExcepcion($e, 'el país')
            );
        }
    }

    public function consultaPais(Request $request)
    {
        $consulta = trim((string) $request->input('consulta', ''));
        $q = Pais::query()->orderBy('nombre');
        if ($consulta !== '') {
            $q->where(function ($w) use ($consulta) {
                $w->where('codigo', 'like', '%'.$consulta.'%')
                    ->orWhere('nombre', 'like', '%'.$consulta.'%')
                    ->orWhere('id', 'like', '%'.$consulta.'%');
            });
        }
        $html = '';
        foreach ($q->limit(80)->get() as $row) {
            $html .= '<tr data-id="'.(int) $row->id.'" data-codigo="'.e((string) $row->codigo).'" data-nombre="'.e((string) $row->nombre).'">';
            $html .= '<td class="d-none">'.$row->id.'</td>';
            $html .= '<td>'.e((string) $row->codigo).'</td>';
            $html .= '<td>'.e((string) $row->nombre).'</td>';
            $html .= '<td class="text-nowrap"><a class="btn btn-warning btn-sm eligeconsultapais">Elegir</a>';
            if (can('editar-paises', false) || can('listar-paises', false)) {
                $url = route('editar_pais', [
                    'id' => $row->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]);
                $html .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
            }
            $html .= '</td></tr>';
        }

        return response()->json([
            'data' => $html !== '' ? $html : '<tr><td colspan="4" class="text-muted">Sin resultados</td></tr>',
        ]);
    }

    public function leerPorCodigo(string $codigo)
    {
        $cod = trim($codigo);
        $row = Pais::query()
            ->whereRaw('UPPER(codigo) = ?', [strtoupper($cod)])
            ->orWhere('id', ctype_digit($cod) ? (int) $cod : 0)
            ->first();

        if (! $row) {
            return response()->json([]);
        }

        return response()->json([
            'id' => $row->id,
            'codigo' => $row->codigo,
            'nombre' => $row->nombre,
        ]);
    }
}
