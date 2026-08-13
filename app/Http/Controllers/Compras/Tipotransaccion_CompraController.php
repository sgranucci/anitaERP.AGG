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
use App\Support\Compras\ConceptoIvacompraConsultaSupport;
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

    public function consultaTipotransaccionCompra(Request $request)
    {
        if (! $this->puedeConsultarTipotransaccionCompra()) {
            abort(403);
        }

        $consulta = strtoupper(trim((string) ($request->get('consulta') ?? '')));
        $centrocostoId = (int) ($request->input('centrocosto_id') ?: 0) ?: null;

        $data = $this->repository->listarParaConsulta($consulta !== '' ? $consulta : null, $centrocostoId);
        $puedeAbrirAbm = can('editar-tipo-transaccion-compra', false) || can('listar-tipo-transaccion-compra', false);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="4">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="abreviatura">'.e($row->abreviatura).'</td>';
                $output['data'] .= '<td class="nombre">'.e($row->nombre).'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultatipotransaccioncompra">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $urlConsulta = route('editar_tipotransaccion_compra', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($urlConsulta).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td>';
                $output['data'] .= '</tr>';
            }
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE);
    }

    public function leeUnTipotransaccionPorAbreviatura(Request $request, string $abreviatura)
    {
        if (! $this->puedeConsultarTipotransaccionCompra()) {
            abort(403);
        }

        $centrocostoId = (int) ($request->input('centrocosto_id') ?: 0) ?: null;
        $abrev = strtoupper(trim($abreviatura));
        $tipo = $this->repository->findPorAbreviaturaFiltrado($abrev, $centrocostoId);

        if (! $tipo) {
            return response()->json(['id' => null]);
        }

        return response()->json([
            'id' => (int) $tipo->id,
            'abreviatura' => (string) $tipo->abreviatura,
            'nombre' => (string) $tipo->nombre,
        ]);
    }

    public function conceptosIvaPorTipo(int $id)
    {
        if (! $this->puedeConsultarTipotransaccionCompra()) {
            abort(403);
        }

        $lista = ConceptoIvacompraConsultaSupport::listarPorTipoTransaccion($id);

        return response()->json([
            'ok' => true,
            'conceptos' => $lista->map(fn ($c) => [
                'id' => (int) $c->id,
                'codigo' => (string) ($c->codigo ?? ''),
                'nombre' => (string) ($c->nombre ?? ''),
                'tipoconcepto' => (string) ($c->tipoconcepto ?? ''),
                'cuentacontable_id' => $c->cuentacontable_id ? (int) $c->cuentacontable_id : null,
            ])->values()->all(),
        ]);
    }

    private function puedeConsultarTipotransaccionCompra(): bool
    {
        return can('listar-tipo-transaccion-compra', false)
            || can('crear-comprobante-proveedor', false)
            || can('editar-comprobante-proveedor', false)
            || can('actualizar-comprobante-proveedor', false)
            || can('listar-comprobante-proveedor', false)
            || can('crear-precarga-proveedores', false)
            || can('editar-precarga-proveedores', false)
            || can('listar-precarga-proveedores', false);
    }
}
