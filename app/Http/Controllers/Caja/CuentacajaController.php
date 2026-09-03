<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionCuentacaja;
use App\Models\Caja\Cuentacaja;
use App\Support\Ventas\GastronomiaCuentacajaSoloAutomaticaSupport;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Caja\UsocuentacajaRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Support\Caja\CuentacajaListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use App\Exports\Caja\CuentacajaListadoExport;

class CuentacajaController extends Controller
{
	private $repository;
    private $cuentacontableRepository;
    private $empresaRepository;
    private $monedaRepository;
    private $usocuentacajaRepository;

    public function __construct(CuentacajaRepositoryInterface $repository,
                                CuentacontableRepositoryInterface $cuentacontablerepository,
                                EmpresaRepositoryInterface $empresarepository,
                                MonedaRepositoryInterface $monedarepository,
                                UsocuentacajaRepositoryInterface $usocuentacajarepository)
    {
        $this->repository = $repository;
        $this->cuentacontableRepository = $cuentacontablerepository;
        $this->empresaRepository = $empresarepository;
        $this->monedaRepository = $monedarepository;
        $this->usocuentacajaRepository = $usocuentacajarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-cuentas-de-caja');

        $filtros = $this->resolverFiltrosListado($request);
        $datas = $this->repository->leeCuentacaja($filtros, true);
        $tipocuenta_enum = Cuentacaja::$enumTipocuenta;

        return view('caja.cuentacaja.index', [
            'datas' => $datas,
            'tipocuenta_enum' => $tipocuenta_enum,
            'filtros' => $filtros,
            'filtrosQuery' => CuentacajaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => CuentacajaListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-cuentas-de-caja');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);
        $tipocuenta_enum = Cuentacaja::$enumTipocuenta;

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeCuentacaja($filtros, false);

                $view = \View::make('caja.cuentacaja.listado', compact('datas', 'tipocuenta_enum'))
                    ->render();
                $path = storage_path('pdf/listados');
                $nombre_pdf = 'listado_cuentacaja';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new CuentacajaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('cuentacaja.xlsx');

            case 'CSV':
                return (new CuentacajaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('cuentacaja.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('cuentacaja', CuentacajaListadoFiltros::paraQueryString($filtros));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear(Request $request)
    {
        can('crear-cuentas-de-caja');
        $data = new Cuentacaja();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $moneda_query = $this->monedaRepository->all();
        $usocuentacaja_query = $this->usocuentacajaRepository->all();
        $tipocuenta_enum = Cuentacaja::$enumTipocuenta;
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, CuentacajaListadoFiltros::class);

        return view('caja.cuentacaja.crear', compact('data', 'empresa_query',
                                                    'tipocuenta_enum', 'moneda_query', 'usocuentacaja_query', 'filtrosQuery'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCuentacaja $request)
    {
		$this->repository->create($request->all());

        return redirect()->route('cuentacaja', QueryRetornoListado::desdeRequest($request, CuentacajaListadoFiltros::class))
            ->with('mensaje', 'Cuenta de caja creada con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar(Request $request, $id)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            can('listar-cuentas-de-caja');
        } else {
            can('editar-cuentas-de-caja');
        }

        $data = $this->repository->findOrFail($id);
        $data->loadMissing('bancos');
        $empresa_query = $this->empresaRepository->allFiltrado();
        $moneda_query = $this->monedaRepository->all();
        $usocuentacaja_query = $this->usocuentacajaRepository->all();
        $tipocuenta_enum = Cuentacaja::$enumTipocuenta;

        $filtrosQuery = QueryRetornoListado::desdeRequest($request, CuentacajaListadoFiltros::class);

        return view('caja.cuentacaja.editar', compact('data', 'empresa_query',
                                                    'tipocuenta_enum', 'moneda_query', 'usocuentacaja_query', 'soloConsulta', 'filtrosQuery'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCuentacaja $request, $id)
    {
        if ($request->input('origen') === 'modal_consulta') {
            abort(403);
        }

        can('actualizar-cuentas-de-caja');

        $this->repository->update($request->all(), $id);

        return redirect()->route('cuentacaja', QueryRetornoListado::desdeRequest($request, CuentacajaListadoFiltros::class))
            ->with('mensaje', 'Cuenta de caja actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-cuentas-de-caja');

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

    public function consultaCuentaCaja(Request $request)
    {
		$columns = ['cuentacaja.id', 'cuentacaja.codigo', 'cuentacaja.nombre', 'empresa.nombre', 'cuentacontable.codigo',
                    'cuentacontable.nombre', 'cuentacaja.moneda_id', 'moneda.nombre', 'cuentacaja.cbu'];
		$columnsOut = ['cuentacaja_id', 'codigo', 'nombre', 'nombreempresa', 'codigocuentacontable', 'nombrecuentacontable',
                        'moneda_id', 'nombremoneda', 'cbu'];

        $empresaId = $request->input('empresa_id');
        $consulta = $request->consulta;
        $usoCuentacajaId = (int) $request->get('usocuentacaja_id');
        $count = count($columns);

        $query = CuentaCaja::select('cuentacaja.id as cuentacaja_id', 'cuentacaja.codigo',
                'cuentacaja.nombre', 'cuentacaja.empresa_id as empresa_id', 'empresa.nombre as nombreempresa',
                'cuentacaja.tipocuenta', 'cuentacontable.codigo as codigocuentacontable', 'cuentacontable.nombre as nombrecuentacontable',
                'cuentacaja.moneda_id', 'moneda.nombre as nombremoneda', 'cuentacaja.cbu')
				->leftJoin('empresa','cuentacaja.empresa_id','=','empresa.id')
                ->leftJoin('cuentacontable','cuentacaja.cuentacontable_id','=','cuentacontable.id')
                ->leftJoin('moneda','cuentacaja.moneda_id','=','moneda.id');

        if ($usoCuentacajaId > 0) {
            $query->whereHas('usocuentacajas', fn ($r) => $r->whereKey($usoCuentacajaId));
        }

        $empresaIdInt = (int) $empresaId;
        if ($empresaIdInt > 0) {
            $query->where(function ($q) use ($empresaIdInt) {
                $q->where('cuentacaja.empresa_id', $empresaIdInt)->orWhereNull('cuentacaja.empresa_id');
            });
        }

        if ($consulta) {
            $query->where(function ($q) use ($count, $consulta, $columns) {
                for ($i = 0; $i < $count; $i++) {
                    $q->orWhere($columns[$i], 'LIKE', '%'.$consulta.'%');
                }
            });
        }

        $excluirSoloAutomaticas = filter_var(
            $request->get('excluir_cuentas_solo_automaticas'),
            FILTER_VALIDATE_BOOLEAN,
        );
        if ($excluirSoloAutomaticas && $empresaIdInt > 0) {
            GastronomiaCuentacajaSoloAutomaticaSupport::aplicarExclusionEnQuery($query, $empresaIdInt);
        }

        if (filter_var($request->get('solo_con_interbanking'), FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNotNull('cuentacaja.cuenta_interbanking')
                ->where('cuentacaja.cuenta_interbanking', '!=', '');
        }

        $query = $query->orderBy('cuentacaja.nombre')->get();

        $output = [];
		$output['data'] = '';	
        $flSinDatos = true;
		if (count($query) > 0)
		{
			foreach ($query as $row)
			{
                    $flSinDatos = false;
                    $output['data'] .= '<tr>';
                    for ($i = 0; $i < $count; $i++)
                        $output['data'] .= '<td class="'.$columnsOut[$i].'">' . $row[$columnsOut[$i]] . '</td>';	
                    $output['data'] .= '<td class="text-nowrap"><a class="btn btn-warning btn-sm eligeconsultacuentacaja">Elegir</a>';
                    if (can('editar-cuentas-de-caja', false) || can('listar-cuentas-de-caja', false)) {
                        $urlAbm = route('editar_cuentacaja', [
                            'id' => (int) $row['cuentacaja_id'],
                            'origen' => 'modal_consulta',
                            'vista' => 'consulta',
                        ]);
                        $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($urlAbm).'" target="_blank" rel="noopener">Consultar</a>';
                    }
                    $output['data'] .= '</td>';
                    $output['data'] .= '</tr>';
			}
		}

        if ($flSinDatos)
		{
			$output['data'] .= '<tr>';
			$output['data'] .= '<td>Sin resultados</td>';
			$output['data'] .= '</tr>';
		}
        return response()->json($output);
	}

    public function leerCuentaCajaPorCodigo(Request $request, $codigo)
    {
        $query = Cuentacaja::query()->where('codigo', $codigo);
        $empresaId = (int) $request->query('empresa_id');
        if ($empresaId > 0) {
            $query->paraEmpresa($empresaId);
        }

        $cuenta = $query->first();
        if ($cuenta === null) {
            return response()->json([
                'id' => 0,
                'error' => $empresaId > 0
                    ? 'No se encontró cuenta de caja para esa empresa.'
                    : 'No se encontró cuenta de caja.',
            ], 404);
        }

        return $cuenta;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;

        return CuentacajaListadoFiltros::resolverDesdeRequest(
            $request,
            $busquedaRuta,
            $empresaDefault ? (int) $empresaDefault : null
        );
    }

}
