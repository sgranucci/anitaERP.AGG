<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Configuracion\Moneda;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaPdfIaMonedaDestinoSupport;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionMoneda;

class MonedaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-monedas');
        $datas = Moneda::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Moneda = new Moneda();
        	$Moneda->sincronizarConAnita();
	
        	$datas = Moneda::orderBy('id')->get();
		}

        return view('configuracion.moneda.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-monedas');
        return view('configuracion.moneda.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionMoneda $request)
    {
        $moneda = Moneda::create($request->all());

		// Graba anita
		$Moneda = new Moneda();
        $Moneda->guardarAnita($request, $moneda->id);

        return redirect('configuracion/moneda')->with('mensaje', 'Moneda creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-monedas');
        $data = Moneda::findOrFail($id);
        return view('configuracion.moneda.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionMoneda $request, $id)
    {
        can('actualizar-monedas');
        Moneda::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Moneda = new Moneda();
        $Moneda->actualizarAnita($request, $id);

        return redirect('configuracion/moneda')->with('mensaje', 'Moneda actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-monedas');

		// Elimina anita
		$Moneda = new Moneda();
        $Moneda->eliminarAnita($id);

        if ($request->ajax()) {
            if (Moneda::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function leerMoneda()
    {
        return Moneda::orderBy('id')->get();
    }

    public function consultaMoneda(Request $request)
    {
        if (! $this->puedeConsultarMoneda()) {
            abort(403);
        }

        $filas = FacturaPdfIaMonedaDestinoSupport::listarParaConsulta($request->input('consulta'));
        $puedeAbrirAbm = can('editar-monedas', false) || can('listar-monedas', false);

        $output = ['data' => ''];
        if ($filas->isEmpty()) {
            $output['data'] = '<tr><td colspan="5">Sin resultados</td></tr>';
        } else {
            foreach ($filas as $row) {
                $codigo = FacturaPdfIaMonedaDestinoSupport::codigoVisible($row);
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="id d-none">'.e($row->id).'</td>';
                $output['data'] .= '<td class="codigo">'.e($codigo).'</td>';
                $output['data'] .= '<td class="abreviatura">'.e((string) ($row->abreviatura ?? '')).'</td>';
                $output['data'] .= '<td class="nombre">'.e((string) ($row->nombre ?? '')).'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultamoneda">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $urlConsulta = route('editar_moneda', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($urlConsulta).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td>';
                $output['data'] .= '</tr>';
            }
        }

        return response()->json($output, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function leeUnaMonedaPorCodigo(string $codigo)
    {
        if (! $this->puedeConsultarMoneda()) {
            abort(403);
        }

        $moneda = FacturaPdfIaMonedaDestinoSupport::buscar($codigo);
        if ($moneda === null) {
            return response()->json(['id' => null]);
        }

        return response()->json([
            'id' => (int) $moneda->id,
            'codigo' => FacturaPdfIaMonedaDestinoSupport::codigoVisible($moneda),
            'abreviatura' => (string) ($moneda->abreviatura ?? ''),
            'nombre' => (string) ($moneda->nombre ?? ''),
        ]);
    }

    private function puedeConsultarMoneda(): bool
    {
        return can('listar-monedas', false)
            || can('editar-monedas', false)
            || can('crear-monedas', false)
            || can('crear-precarga-proveedores', false)
            || can('editar-precarga-proveedores', false)
            || can('listar-precarga-proveedores', false)
            || can('crear-comprobante-proveedor', false)
            || can('editar-comprobante-proveedor', false)
            || can('actualizar-comprobante-proveedor', false)
            || can('listar-comprobante-proveedor', false)
            || can('cargar-portal-proveedores', false);
    }
}
