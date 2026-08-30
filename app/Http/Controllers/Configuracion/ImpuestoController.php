<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionImpuesto;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\ImpuestoRepositoryInterface;
use App\Repositories\Configuracion\Impuesto_CuentacontableRepositoryInterface;
use Carbon\Carbon;
use DB;

class ImpuestoController extends Controller
{
    private $impuestoRepository;
    private $impuesto_cuentacontableRepository;
    private $empresaRepository;

    public function __construct(ImpuestoRepositoryInterface $impuestorepository,
                                EmpresaRepositoryInterface $empresarepository,
                                Impuesto_CuentacontableRepositoryInterface $impuesto_cuentacontableRepository)
    {
        $this->impuestoRepository = $impuestorepository;
        $this->impuesto_cuentacontableRepository = $impuesto_cuentacontableRepository;
        $this->empresaRepository = $empresarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-impuestos');

        $datas = $this->impuestoRepository->all();

        return view('configuracion.impuesto.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-impuestos');

        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('configuracion.impuesto.crear', compact('empresa_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionImpuesto $request)
    {
        try
        {
            DB::beginTransaction();

            $impuesto = $this->impuestoRepository->create($request->all());

            $empresa_ids = $request->input('empresa_ids', []);
            $cuentacontable_ids = $request->input('cuentacontable_ids', []);
            for ($i=0; $i < count($cuentacontable_ids); $i++) {
                if ($cuentacontable_ids[$i] != '') 
                {
                    $this->impuesto_cuentacontableRepository->create([
                                                        'impuesto_id' => $impuesto->id,
                                                        'empresa_id' => $empresa_ids[$i],
                                                        'cuentacontable_id' => $cuentacontable_ids[$i],
                                                        'creousuario_id' => auth()->id()
                                                        ]);
                }
            }
            DB::commit();
        
            return redirect('configuracion/impuesto')->with('mensaje', 'Impuesto creada con éxito');

        } catch (\Exception $exception) {
            DB::rollBack();
            
            return back()
                ->with('mensaje', $exception->getMessage());
        }
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-impuestos');

        $data = $this->impuestoRepository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('configuracion.impuesto.editar', compact('data', 'empresa_query'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionImpuesto $request, $id)
    {
        can('actualizar-impuestos');

        try
        {
            DB::beginTransaction();

            $impuesto = $this->impuestoRepository->update($request->all(), $id);

            $this->impuesto_cuentacontableRepository->deletePorImpuesto($id);

            $empresa_ids = $request->input('empresa_ids', []);
            $cuentacontable_ids = $request->input('cuentacontable_ids', []);
            $creousuario_cuentacontable_ids = $request->input('creousuario_cuentacontable_ids', []);
            for ($i=0; $i < count($cuentacontable_ids); $i++) {
                if ($cuentacontable_ids[$i] != '') 
                {
                    $this->impuesto_cuentacontableRepository->create([
                                                        'impuesto_id' => $id,
                                                        'empresa_id' => $empresa_ids[$i],
                                                        'cuentacontable_id' => $cuentacontable_ids[$i],
                                                        'creousuario_id' => $creousuario_cuentacontable_ids[$i]
                                                        ]);
                }
            }

            DB::commit();
        
            return redirect('configuracion/impuesto')->with('mensaje', 'Impuesto actualizada con exito');

        } catch (\Exception $exception) {
            DB::rollBack();
            
            return back()
                ->with('mensaje', $exception->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-impuestos');

        if ($request->ajax()) {
            try {
                if ($this->impuestoRepository->delete($id)) {
                    return response()->json(['mensaje' => 'ok']);
                }

                return response()->json(['mensaje' => 'ng']);
            } catch (\RuntimeException $e) {
                return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
            }
        } else {
            abort(404);
        }
        return redirect('configuracion/impuesto')->with('mensaje', 'Impuesto eliminado con exito');
    }
}
