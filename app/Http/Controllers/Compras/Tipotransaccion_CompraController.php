<?php

namespace App\Http\Controllers\Compras;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Compras\Tipotransaccion_Compra;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTipotransaccion_Compra;
use App\Repositories\Compras\Tipotransaccion_CompraRepositoryInterface;
use App\Repositories\Compras\Tipotransaccion_Compra_CentrocostoRepositoryInterface;
use App\Repositories\Compras\Tipotransaccion_Compra_Concepto_IvacompraRepositoryInterface;
use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use DB;

class Tipotransaccion_CompraController extends Controller
{
	private $repository;
    private $tipotransaccion_compra_centrocostoRepository;
    private $tipotransaccion_concepto_ivacompraRepository;
    private $concepto_ivacompraRepository;
	private $centrocostoRepository;

    public function __construct(Tipotransaccion_CompraRepositoryInterface $repository,
                                Concepto_IvacompraRepositoryInterface $concepto_ivacomprarepository,
                                CentrocostoRepositoryInterface $centrocostorepository,
                                Tipotransaccion_Compra_CentrocostoRepositoryInterface $tipotransaccion_compra_centrocostorepository,
                                Tipotransaccion_Compra_Concepto_IvacompraRepositoryInterface $tipotransaccion_compra_concepto_ivacomprarepository
                                )
    {
        $this->repository = $repository;
        $this->concepto_ivacompraRepository = $concepto_ivacomprarepository;
		$this->centrocostoRepository = $centrocostorepository;
        $this->tipotransaccion_compra_centrocostoRepository = $tipotransaccion_compra_centrocostorepository;
        $this->tipotransaccion_concepto_ivacompraRepository = $tipotransaccion_compra_concepto_ivacomprarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-tipo-transaccion-compra');

        $datas = $this->repository->all('*');

        return view('compras.tipotransaccion_compra.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipo-transaccion-compra');
        $operacionEnum = Tipotransaccion_Compra::$enumOperacion;
        $signoEnum = Tipotransaccion_Compra::$enumSigno;
        $subdiarioEnum = Tipotransaccion_Compra::$enumSubdiario;
        $asientocontableEnum = Tipotransaccion_Compra::$enumAsientoContable;
        $estadoEnum = Tipotransaccion_Compra::$enumEstado;
        $retieneEnum = Tipotransaccion_Compra::$enumRetiene;
        $centrocosto_query = $this->centrocostoRepository->all();
        $concepto_ivacompra_query = $this->concepto_ivacompraRepository->all();

        return view('compras.tipotransaccion_compra.crear', compact('operacionEnum', 'signoEnum', 'subdiarioEnum',
                                                                    'asientocontableEnum', 'estadoEnum', 'retieneEnum',
                                                                    'centrocosto_query', 'concepto_ivacompra_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTipotransaccion_Compra $request)
    {
        DB::beginTransaction();
        try
        {
            $tipotransaccion = $this->repository->create($request->all());

            // Guarda tablas asociadas
            if ($tipotransaccion)
            {
                $tipotransaccion_centrocosto = $this->tipotransaccion_compra_centrocostoRepository->create($request->all(), $tipotransaccion->id);
                $tipotransaccion_concepto = $this->tipotransaccion_concepto_ivacompraRepository->create($request->all(), $tipotransaccion->id);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return ['errores' => $e->getMessage()];
        }
        return redirect('compras/tipotransaccion_compra')->with('mensaje', 'Tipo de transacción creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipo-transaccion-compra');
        $data = $this->repository->findOrFail($id);
        $operacionEnum = Tipotransaccion_Compra::$enumOperacion;
        $signoEnum = Tipotransaccion_Compra::$enumSigno;
        $subdiarioEnum = Tipotransaccion_Compra::$enumSubdiario;
        $asientocontableEnum = Tipotransaccion_Compra::$enumAsientoContable;
        $estadoEnum = Tipotransaccion_Compra::$enumEstado;
        $retieneEnum = Tipotransaccion_Compra::$enumRetiene;
        $centrocosto_query = $this->centrocostoRepository->all();
        $concepto_ivacompra_query = $this->concepto_ivacompraRepository->all();

        return view('compras.tipotransaccion_compra.editar', compact('data', 'operacionEnum', 'signoEnum', 'subdiarioEnum',
                                                                    'asientocontableEnum', 'estadoEnum', 'retieneEnum',
                                                                    'centrocosto_query', 'concepto_ivacompra_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTipotransaccion_Compra $request, $id)
    {
        can('actualizar-tipo-transaccion-compra');

        DB::beginTransaction();
        try
        {
            // Graba proveedor
            $this->repository->update($request->all(), $id);

            // Graba centros de costos
            $this->tipotransaccion_compra_centrocostoRepository->update($request->all(), $id);

            // Graba conceptos de compra
            $this->tipotransaccion_concepto_ivacompraRepository->update($request->all(), $id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            dd($e->getMessage());
            return ['errores' => $e->getMessage()];
        }

        return redirect('compras/tipotransaccion_compra')->with('mensaje', 'Tipo de transacción actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-transaccion-compra');

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
