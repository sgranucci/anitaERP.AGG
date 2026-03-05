<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Caja\Tipotransaccion_Caja;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTipotransaccion_Caja;
use App\Repositories\Caja\Tipotransaccion_CajaRepositoryInterface;
use DB;

class Tipotransaccion_CajaController extends Controller
{
	private $repository;

    public function __construct(Tipotransaccion_CajaRepositoryInterface $repository)
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
        can('listar-tipo-transaccion-caja');

        $datas = $this->repository->all();
        $operacionEnum = Tipotransaccion_Caja::$enumOperacion;
        $signoEnum = Tipotransaccion_Caja::$enumSigno;
        $estadoEnum = Tipotransaccion_Caja::$enumEstado;

        return view('caja.tipotransaccion_caja.index', compact('datas','operacionEnum', 'signoEnum', 'estadoEnum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipo-transaccion-caja');
        $operacionEnum = Tipotransaccion_Caja::$enumOperacion;
        $signoEnum = Tipotransaccion_Caja::$enumSigno;
        $estadoEnum = Tipotransaccion_Caja::$enumEstado;

        return view('caja.tipotransaccion_caja.crear', compact('operacionEnum', 'signoEnum', 'estadoEnum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTipotransaccion_Caja $request)
    {
        DB::beginTransaction();
        try
        {
            $tipotransaccion = $this->repository->create($request->all());
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return ['errores' => $e->getMessage()];
        }
        return redirect('caja/tipotransaccion_caja')->with('mensaje', 'Tipo de transacción creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipo-transaccion-caja');
        $data = $this->repository->findOrFail($id);
        $operacionEnum = Tipotransaccion_Caja::$enumOperacion;
        $signoEnum = Tipotransaccion_Caja::$enumSigno;
        $estadoEnum = Tipotransaccion_Caja::$enumEstado;

        return view('caja.tipotransaccion_caja.editar', compact('data', 'operacionEnum', 'signoEnum', 'estadoEnum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTipotransaccion_Caja $request, $id)
    {
        can('actualizar-tipo-transaccion-caja');

        DB::beginTransaction();
        try
        {
            // Graba tipo de transaccion
            $this->repository->update($request->all(), $id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            dd($e->getMessage());
            return ['errores' => $e->getMessage()];
        }

        return redirect('caja/tipotransaccion_caja')->with('mensaje', 'Tipo de transacción actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-transaccion-caja');

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

    public function leeTipotransaccion_caja($id)
    {
        return $this->repository->find($id);
    }
}
