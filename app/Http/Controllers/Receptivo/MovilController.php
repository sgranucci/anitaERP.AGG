<?php

namespace App\Http\Controllers\Receptivo;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Receptivo\Movil;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionMovil;
use App\Repositories\Receptivo\MovilRepositoryInterface;

class MovilController extends Controller
{
	private $movilRepository;

    public function __construct(MovilRepositoryInterface $repository)
    {
        $this->movilRepository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-movil');
		$datas = $this->movilRepository->all();
        $tipomovil_enum = Movil::$enumTipoMovil;

        return view('receptivo.movil.index', compact('datas', 'tipomovil_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-movil');

        $tipomovil_enum = Movil::$enumTipoMovil;

        return view('receptivo.movil.crear', compact('tipomovil_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionMovil $request)
    {
		$this->movilRepository->create($request->all());

        return redirect('receptivo/movil')->with('mensaje', 'Móvil creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-movil');
        $data = $this->movilRepository->findOrFail($id);
        $tipomovil_enum = Movil::$enumTipoMovil;

        return view('receptivo.movil.editar', compact('data', 'tipomovil_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(Validacionmovil $request, $id)
    {
        can('actualizar-movil');

        $this->movilRepository->update($request->all(), $id);

        return redirect('receptivo/movil')->with('mensaje', 'Móvil actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-movil');

        if ($request->ajax()) {
        	if ($this->movilRepository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function consultaMovil(Request $request)
    {
        return ($this->movilRepository->leeMovil($request->consulta));
	}

    public function leeMovil($codigoMovil)
    {
        return ($this->movilRepository->findPorCodigo($codigoMovil));
	}
}
