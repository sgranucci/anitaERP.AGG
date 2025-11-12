<?php

namespace App\Http\Controllers\Produccion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionSectorsellado;
use App\Repositories\Produccion\SectorselladoRepositoryInterface;

class SectorselladoController extends Controller
{
	private $repository;

    public function __construct(SectorselladoRepositoryInterface $repository)
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
        can('listar-sector-sellado');
		$datas = $this->repository->all();

        return view('produccion.sectorsellado.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-sector-sellado');

        return view('produccion.sectorsellado.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionSectorsellado $request)
    {
		$this->repository->create($request->all());

        return redirect('produccion/sectorsellado')->with('mensaje', 'Sector de sellado creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-sector-sellado');
        $data = $this->repository->findOrFail($id);

        return view('produccion.sectorsellado.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionSectorsellado $request, $id)
    {
        can('actualizar-sector-sellado');

        $this->repository->update($request->all(), $id);

        return redirect('produccion/sectorsellado')->with('mensaje', 'Sector de sellado actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-sector-sellado');

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

}
