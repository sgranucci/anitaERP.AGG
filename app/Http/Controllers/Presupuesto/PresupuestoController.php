<?php

namespace App\Http\Controllers\Presupuesto;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionPresupuesto;
use App\Repositories\Presupuesto\PresupuestoRepositoryInterface;
use App\Repositories\Presupuesto\Presupuesto_EscenarioRepositoryInterface;
use App\Models\Presupuesto\Presupuesto;
use App\Models\Presupuesto\Presupuesto_Escenario;
use Illuminate\Support\Facades\Auth;
use DB;

class PresupuestoController extends Controller
{
    private $presupuestoRepository;
    private $presupuesto_escenarioRepository;

    public function __construct(PresupuestoRepositoryInterface $presupuestorepository,
                                Presupuesto_EscenarioRepositoryInterface $presupuesto_escenariorepository)
    {
        $this->presupuestoRepository = $presupuestorepository;
        $this->presupuesto_escenarioRepository = $presupuesto_escenariorepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-presupuesto');

        $datas = $this->presupuestoRepository->all();

        return view('presupuesto.presupuesto.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-presupuesto');

        $estado_enum = Presupuesto::$enumEstado;
        $tipo_enum = Presupuesto_Escenario::$enumTipo;

        return view('presupuesto.presupuesto.crear', compact('estado_enum', 'tipo_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPresupuesto $request)
    {
        $data = $request->all();
        $data['creousuario_id'] = auth()->id();

        return $this->presupuestoRepository->create($data);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-presupuesto');

        $estado_enum = Presupuesto::$enumEstado;
        $tipo_enum = Presupuesto_Escenario::$enumTipo;

        $data = $this->presupuestoRepository->findOrFail($id);

        return view('presupuesto.presupuesto.editar', compact('data', 'estado_enum', 'tipo_enum'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPresupuesto $request, $id)
    {
        can('actualizar-presupuesto');
        
        $data = $request->all();

        return $this->presupuestoRepository->update($data, $id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-presupuesto');

        if ($request->ajax()) {
            if ($this->presupuestoRepository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
        return redirect('presupuesto/presupuesto')->with('mensaje', 'Presupuesto eliminado con éxito');
    }
    
    public function leerEscenario($presupuesto_id)
    {
        $data = $this->presupuestoRepository->findOrFail($presupuesto_id);

        return $data->presupuesto_escenarios;
    }
}
