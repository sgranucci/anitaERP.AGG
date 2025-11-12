<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Zonavta;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionZonavta;
use App\ApiAnita;

class ZonavtaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-zonas-de-venta');
        $datas = Zonavta::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Zonavta = new Zonavta();
        	$Zonavta->sincronizarConAnita();
	
        	$datas = Zonavta::orderBy('id')->get();
		}

        return view('ventas.zonavta.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-zonas-de-venta');
        return view('ventas.zonavta.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionZonavta $request)
    {
        $codigo = '';
		self::ultimoCodigo($codigo);

        $data = $request->all();
        $data['codigo'] = $codigo;

        $zonavta = Zonavta::create($data);

		// Graba anita
		$Zonavta = new Zonavta();
        $Zonavta->guardarAnita($request, $codigo);

        return redirect('ventas/zonavta')->with('mensaje', 'Zonavta creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-zonas-de-venta');
        $data = Zonavta::findOrFail($id);
        return view('ventas.zonavta.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionZonavta $request, $id)
    {
        can('actualizar-zonas-de-venta');
        Zonavta::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Zonavta = new Zonavta();
        $Zonavta->actualizarAnita($request, $request->codigo);

        return redirect('ventas/zonavta')->with('mensaje', 'Zonavta actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-zonas-de-venta');
        
        $zonavta = Zonavta::findOrFail($id);

        $codigo = $zonavta->codigo;

		// Elimina anita
		$Zonavta = new Zonavta();
        $Zonavta->eliminarAnita($codigo);

        if ($request->ajax()) {
            if (Zonavta::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    // Devuelve ultimo codigo de zonavta + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
				'tabla' => 'zonavta', 
				'sistema' => 'ventas',
				'campos' => " max(zonv_codigo) as codigo "
				);
        $dataAnita = json_decode($apiAnita->apiCall($data));

		if ($dataAnita[0]->codigo != '')
		{
			$numero = filter_var($dataAnita[0]->codigo, FILTER_SANITIZE_NUMBER_INT);
			$numero = $numero + 1;

			$codigo = $numero;
		}
		else
			$codigo = 1;
	}

}
