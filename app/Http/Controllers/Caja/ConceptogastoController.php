<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Caja\Conceptogasto;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionConceptogasto;
use App\Repositories\Caja\ConceptogastoRepositoryInterface;
use App\Repositories\Caja\Conceptogasto_CuentacontableRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use DB;

class ConceptogastoController extends Controller
{
	private $repository;
    private $conceptogasto_cuentacontableRepository;
    private $empresaRepository;

    public function __construct(ConceptogastoRepositoryInterface $repository,
                                Conceptogasto_CuentacontableRepositoryInterface $conceptogasto_cuentacontablerepository,
                                EmpresaRepositoryInterface $empresarepository)
    {
        $this->repository = $repository;
        $this->conceptogasto_cuentacontableRepository = $conceptogasto_cuentacontablerepository;
        $this->empresaRepository = $empresarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-conceptos-de-gastos');
		$datas = $this->repository->all();

        return view('caja.conceptogasto.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-conceptos-de-gastos');

        $empresa_query = $this->empresaRepository->all();

        return view('caja.conceptogasto.crear', compact('empresa_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionConceptogasto $request)
    {
        DB::beginTransaction();
        try
        {        
            $conceptogasto = $this->repository->create($request->all());

            $cuentacontable_ids = $request->input('cuentacontable_ids', []);
            for ($i_cuenta=0; $i_cuenta < count($cuentacontable_ids); $i_cuenta++) {
                if ($cuentacontable_ids[$i_cuenta] != '') 
                {
                    $conceptogasto_cuentacontable = $this->conceptogasto_cuentacontableRepository->create([
                                                        'conceptogasto_id' => $conceptogasto->id,
                                                        'cuentacontable_id' => $cuentacontable_ids[$i_cuenta], 
                                                        ]);
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return ['errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
        //return redirect('caja/conceptogasto')->with('mensaje', 'Concepto de gasto creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-conceptos-de-gastos');
        $data = $this->repository->findOrFail($id);
        $empresa_query = $this->empresaRepository->all();

        return view('caja.conceptogasto.editar', compact('data', 'empresa_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionConceptogasto $request, $id)
    {
        can('actualizar-conceptos-de-gastos');

        DB::beginTransaction();
        try
        {
            $this->repository->update($request->all(), $id);

            $conceptogasto_cuentacontable = $this->conceptogasto_cuentacontableRepository->deletePorConceptogasto($request->id);

            $cuentacontable_ids = $request->input('cuentacontable_ids', []);
            for ($i_cuenta=0; $i_cuenta < count($cuentacontable_ids); $i_cuenta++) {
                if ($cuentacontable_ids[$i_cuenta] != '') 
                {
                    $conceptogasto_cuentacontable = $this->conceptogasto_cuentacontableRepository->create([
                                                        'conceptogasto_id' => $request->id,
                                                        'cuentacontable_id' => $cuentacontable_ids[$i_cuenta], 
                                                        ]);
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return ['errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
        //return redirect('caja/conceptogasto')->with('mensaje', 'Concepto de gasto actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-conceptos-de-gastos');

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

    public function consultaConceptogasto(Request $request)
    {
        return ($this->repository->leeConceptogasto($request->consulta));
	}

    public function leeConceptogasto($codigoconceptogasto)
    {
        return ($this->repository->findPorId($codigoconceptogasto));
	}
}
