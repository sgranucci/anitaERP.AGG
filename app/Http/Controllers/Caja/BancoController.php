<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Caja\Banco;
use App\Models\Configuracion\Localidod;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Condicioniva;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionBanco;
use App\Repositories\Caja\BancoRepositoryInterface;

class BancoController extends Controller
{
	private $repository;

    public function __construct(BancoRepositoryInterface $repository)
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
        can('listar-banco');
		$datas = $this->repository->all();

        return view('caja.banco.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-banco');

        $provincias_query = Provincia::orderBy('nombre')->get();
        $condicionesiva_query = Condicioniva::orderBy('nombre')->get();

        return view('caja.banco.crear', compact('provincias_query', 'condicionesiva_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionBanco $request)
    {
		$this->repository->create($request->all());

        return redirect('caja/banco')->with('mensaje', 'Banco creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-banco');
        $data = $this->repository->findOrFail($id);

        $provincias_query = Provincia::orderBy('nombre')->get();
        $condicionesiva_query = Condicioniva::orderBy('nombre')->get();

        return view('caja.banco.editar', compact('data', 'provincias_query', 'condicionesiva_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionBanco $request, $id)
    {
        can('actualizar-banco');
        $this->repository->update($request->all(), $id);

        return redirect('caja/banco')->with('mensaje', 'Banco actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-banco');

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

    public function consultaBanco(Request $request)
    {
        return ($this->repository->leeBanco($request->consulta));
	}

    public function leeBanco($banco_id)
    {
        return ($this->repository->findPorId($banco_id));
	}    

    public function leerBancoPorCodigo($codigobanco)
    {
        return ($this->repository->findPorCodigo($codigobanco));
	}        
}
