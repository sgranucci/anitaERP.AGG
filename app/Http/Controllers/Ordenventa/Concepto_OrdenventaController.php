<?php

namespace App\Http\Controllers\Ordenventa;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionConcepto_Ordenventa;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ordenventa\Concepto_OrdenventaRepositoryInterface;
use App\Repositories\Ordenventa\Concepto_Cuentacontable_OrdenventaRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use DB;

class Concepto_OrdenventaController extends Controller
{
    private $empresaRepository;
    private $concepto_ordenventaRepository;
    private $concepto_ordenventa_cuentacontableRepository;
    private $cuentacontableRepository;

    public function __construct(Concepto_OrdenventaRepositoryInterface $concepto_ordenventaRepository,
                                Concepto_Cuentacontable_OrdenventaRepositoryInterface $concepto_ordenventa_cuentacontableRepository,
                                EmpresaRepositoryInterface $empresarepository,
                                CuentacontableRepositoryInterface $cuentacontablerepository)
    {
        $this->concepto_ordenventaRepository = $concepto_ordenventaRepository;
        $this->concepto_ordenventa_cuentacontableRepository = $concepto_ordenventa_cuentacontableRepository;
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
        can('listar-concepto-de-orden-de-venta');

        $datas = $this->concepto_ordenventaRepository->all();

        return view('ordenventa.concepto_ordenventa.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-concepto-de-orden-de-venta');

        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('ordenventa.concepto_ordenventa.crear', compact('empresa_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionConcepto_Ordenventa $request)
    {
        try
        {
            DB::beginTransaction();

            $concepto_ordenventa = $this->concepto_ordenventaRepository->create($request->all());

            $empresa_ids = $request->input('empresa_ids', []);
            $cuentacontable_ids = $request->input('cuentacontable_ids', []);
            for ($i=0; $i < count($cuentacontable_ids); $i++) {
                if ($cuentacontable_ids[$i] != '') 
                {
                    $this->concepto_ordenventa_cuentacontableRepository->create([
                                                        'concepto_ordenventa_id' => $concepto_ordenventa->id,
                                                        'empresa_id' => $empresa_ids[$i],
                                                        'cuentacontable_id' => $cuentacontable_ids[$i],
                                                        'creousuario_id' => auth()->id()
                                                        ]);
                }
            }

            DB::commit();
        
            return redirect('ordenventa/concepto_ordenventa')->with('mensaje', 'Concepto de Ordenes de Venta creado con éxito');

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
        can('editar-concepto-de-orden-de-venta');
        $empresa_query = $this->empresaRepository->allFiltrado();

        $data = $this->concepto_ordenventaRepository->findOrFail($id);

        return view('ordenventa.concepto_ordenventa.editar', compact('data', 'empresa_query'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionConcepto_Ordenventa $request, $id)
    {
        can('actualizar-concepto-de-orden-de-venta');

        try
        {
            DB::beginTransaction();

            $concepto_ordenventa = $this->concepto_ordenventaRepository->update($request->all(), $id);

            $this->concepto_ordenventa_cuentacontableRepository->deletePorConceptoOrdenventa($id);

            $empresa_ids = $request->input('empresa_ids', []);
            $cuentacontable_ids = $request->input('cuentacontable_ids', []);
            $creousuario_cuentacontable_ids = $request->input('creousuario_cuentacontable_ids', []);
            for ($i=0; $i < count($cuentacontable_ids); $i++) {
                if ($cuentacontable_ids[$i] != '') 
                {
                    $this->concepto_ordenventa_cuentacontableRepository->create([
                                                        'concepto_ordenventa_id' => $id,
                                                        'empresa_id' => $empresa_ids[$i],
                                                        'cuentacontable_id' => $cuentacontable_ids[$i],
                                                        'creousuario_id' => $creousuario_cuentacontable_ids[$i]
                                                        ]);
                }
            }

            DB::commit();
        
            return redirect('ordenventa/concepto_ordenventa')->with('mensaje', 'Provincia actualizada con exito');

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
        can('borrar-concepto-de-orden-de-venta');

        if ($request->ajax()) {
            if ($this->concepto_ordenventaRepository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
        return redirect('ordenventa/concepto_ordenventa')->with('mensaje', 'Provincia eliminada con exito');
    }
}
