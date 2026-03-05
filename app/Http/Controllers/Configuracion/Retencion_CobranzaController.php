<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionRetencion_Cobranza;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\Retencion_CobranzaRepositoryInterface;
use App\Repositories\Configuracion\Retencion_Cobranza_CuentacontableRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Models\Configuracion\Retencion_Cobranza;
use Illuminate\Support\Facades\Auth;
use DB;

class Retencion_CobranzaController extends Controller
{
    private $empresaRepository;
    private $retencion_cobranzaRepository;
    private $retencion_cobranza_cuentacontableRepository;
    private $cuentacontableRepository;

    public function __construct(Retencion_CobranzaRepositoryInterface $retencionrepository,
                                Retencion_Cobranza_CuentacontableRepositoryInterface $retencion_cobranza_cuentacontablerepository,
                                EmpresaRepositoryInterface $empresarepository,
                                CuentacontableRepositoryInterface $cuentacontablerepository)
    {
        $this->retencion_cobranzaRepository = $retencionrepository;
        $this->retencion_cobranza_cuentacontableRepository = $retencion_cobranza_cuentacontablerepository;
        $this->empresaRepository = $empresarepository;
        $this->cuentacontableRepository = $cuentacontablerepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-retencion-cobranza');

        $datas = $this->retencion_cobranzaRepository->all();

        return view('configuracion.retencion_cobranza.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-retencion-cobranza');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $tiporetencion_enum = Retencion_Cobranza::$enumTipoRetencion;

        return view('configuracion.retencion_cobranza.crear', compact('empresa_query', 'tiporetencion_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionRetencion_Cobranza $request)
    {
        try
        {
            DB::beginTransaction();

            $retencion = $this->retencion_cobranzaRepository->create($request->all());
            
            $empresa_ids = $request->input('empresa_ids', []);
            $cuentacontable_ids = $request->input('cuentacontable_ids', []);
            for ($i=0; $i < count($cuentacontable_ids); $i++) {
                if ($cuentacontable_ids[$i] != '') 
                {
                    $this->retencion_cobranza_cuentacontableRepository->create([
                                                        'retencion_cobranza_id' => $retencion->id,
                                                        'empresa_id' => $empresa_ids[$i],
                                                        'cuentacontable_id' => $cuentacontable_ids[$i],
                                                        'creousuario_id' => auth()->id()
                                                        ]);
                }
            }

            DB::commit();
        
            return redirect('configuracion/retencion_cobranza')->with('mensaje', 'Retención de Cobranza creada con éxito');

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
        can('editar-retencion-cobranza');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $tiporetencion_enum = Retencion_Cobranza::$enumTipoRetencion;

        $data = $this->retencion_cobranzaRepository->findOrFail($id);

        return view('configuracion.retencion_cobranza.editar', compact('data', 'empresa_query', 'tiporetencion_enum'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionRetencion_Cobranza $request, $id)
    {
        can('actualizar-retencion-cobranza');

        try
        {
            DB::beginTransaction();

            $retencion = $this->retencion_cobranzaRepository->update($request->all(), $id);

            $this->retencion_cobranza_cuentacontableRepository->deletePorRetencion_Cobranza($id);

            $empresa_ids = $request->input('empresa_ids', []);
            $cuentacontable_ids = $request->input('cuentacontable_ids', []);
            $creousuario_cuentacontable_ids = $request->input('creousuario_cuentacontable_ids', []);
            for ($i=0; $i < count($cuentacontable_ids); $i++) {
                if ($cuentacontable_ids[$i] != '') 
                {
                    $this->retencion_cobranza_cuentacontableRepository->create([
                                                        'retencion_cobranza_id' => $id,
                                                        'empresa_id' => $empresa_ids[$i],
                                                        'cuentacontable_id' => $cuentacontable_ids[$i],
                                                        'creousuario_id' => $creousuario_cuentacontable_ids[$i]
                                                        ]);
                }
            }

            DB::commit();
        
            return redirect('configuracion/retencion_cobranza')->with('mensaje', 'Retención de Cobranza actualizada con éxito');

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
        can('borrar-retencion-cobranza');

        if ($request->ajax()) {
            if ($this->retencion_cobranzaRepository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
        return redirect('configuracion/retencion_cobranza')->with('mensaje', 'Retención de Cobranza eliminada con éxito');
    }
    
}
