<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Configuracion\Empresa;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionEmpresa;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;

class EmpresaController extends Controller
{
    private $empresaRepository;

    public function __construct(EmpresaRepositoryInterface $empresarepository)
    {
        $this->empresaRepository = $empresarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-empresas');

        $datas = $this->empresaRepository->all();

        return view('configuracion.empresa.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-empresas');

        return view('configuracion.empresa.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionEmpresa $request)
    {
        $this->empresaRepository->create($request->all());

        return redirect('configuracion/empresa')->with('mensaje', 'Empresa creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-empresas');

        $data = $this->empresaRepository->findOrFail($id);
        
        return view('configuracion.empresa.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionEmpresa $request, $id)
    {
        can('actualizar-empresas');
        
        $this->empresaRepository->update($request->all(), $id);

        return redirect('configuracion/empresa')->with('mensaje', 'Empresa actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-empresas');

        if ($request->ajax()) {
            if ($this->empresaRepository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
        return redirect('configuracion/empresa')->with('mensaje', 'Empresa eliminada con exito');
    }
}
