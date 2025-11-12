<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Transporte;
use App\Models\Configuracion\Localidod;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Condicioniva;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTransporte;
use App\Repositories\Ventas\TransporteRepositoryInterface;

class TransporteController extends Controller
{
	private $repository;

    public function __construct(TransporteRepositoryInterface $repository)
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
        can('listar-transportes');
		$datas = $this->repository->all();

        return view('ventas.transporte.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-transportes');

        $provincias_query = Provincia::orderBy('nombre')->get();
        $condicionesiva_query = Condicioniva::orderBy('nombre')->get();
        $tipoexpresoEnum = Transporte::$enumTipoExpreso;

        return view('ventas.transporte.crear', compact('provincias_query', 'condicionesiva_query', 'tipoexpresoEnum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTransporte $request)
    {
		$this->repository->create($request->all());

        return redirect('ventas/transporte')->with('mensaje', 'Transporte creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-transportes');
        $data = $this->repository->findOrFail($id);

        $provincias_query = Provincia::orderBy('nombre')->get();
        $condicionesiva_query = Condicioniva::orderBy('nombre')->get();
        $tipoexpresoEnum = Transporte::$enumTipoExpreso;

        return view('ventas.transporte.editar', compact('data', 'provincias_query', 'condicionesiva_query', 'tipoexpresoEnum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTransporte $request, $id)
    {
        can('actualizar-transportes');
        $this->repository->update($request->all(), $id);

        return redirect('ventas/transporte')->with('mensaje', 'Transporte actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-transportes');

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

    public function consultaTransporte(Request $request)
    {
        return ($this->repository->leeTransporte($request->consulta));
	}

    public function leeTransporte($codigoTransporte)
    {
        return ($this->repository->findPorCodigo($codigoTransporte));
	}    
}
