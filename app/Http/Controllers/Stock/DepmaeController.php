<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Depmae;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\SalaRepositoryInterface;
use App\Repositories\Stock\DepmaeRepositoryInterface;
use App\Exports\Stock\DepmaeListadoExport;
use App\Http\Requests\ValidacionDepmae;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\SqlDialectSupport;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Stock\DepmaeListadoFiltros;
use App\Support\Stock\MovimientosArticuloDepositoSupport;
use App\Support\Stock\RecepcionProveedorIntercompanySupport;
use App\Support\Stock\TransferenciaMercaderiaIntercompanySupport;
use App\Support\Stock\ReporteStockOtDepositoAmbitoSupport;
use App\Support\Stock\UsuarioDepositoAutorizado;
use App\Support\Ventas\GastronomiaSalaSupport;

class DepmaeController extends Controller
{
    public function __construct(
        private readonly DepmaeRepositoryInterface $depmaeRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly SalaRepositoryInterface $salaRepository,
    ) {}

    private function puedeConsultarDeposito(): bool
    {
        return can('listar-depositos', false)
            || can('crear-transferencia-mercaderia', false)
            || can('editar-usuarios', false)
            || can('crear-usuarios', false)
            || can('actualizar-usuarios', false)
            || can('crear-recuento', false)
            || can('editar-recuento', false)
            || can('actualizar-recuento', false)
            || can('ver-recuento', false)
            || can('crear-recepcion-proveedor', false)
            || can('editar-recepcion-proveedor', false)
            || can('actualizar-recepcion-proveedor', false)
            || can('listar-recepcion-proveedor', false)
            || can('confirmar-recepcion-proveedor', false)
            || can('crear-prestamo', false)
            || can('editar-prestamo', false)
            || can('actualizar-prestamo', false)
            || can('listar-prestamo', false)
            || can('crear-salida-bienes', false)
            || can('editar-salida-bienes', false)
            || can('actualizar-salida-bienes', false)
            || can('listar-salida-bienes', false)
            || can('crear-movimientos-de-stock', false)
            || can('editar-movimientos-de-stock', false)
            || can('listar-movimientos-de-stock', false)
            || can('crear-movimiento-surmar', false)
            || can('editar-movimiento-surmar', false)
            || can('listar-movimiento-surmar', false)
            || can('actualizar-movimiento-surmar', false)
            || can('crear-recepcion-proveedor-surmar', false)
            || can('editar-recepcion-proveedor-surmar', false)
            || can('listar-recepcion-proveedor-surmar', false)
            || can('actualizar-recepcion-proveedor-surmar', false)
            || can('ver-configuracion-indumentaria', false)
            || can('editar-configuracion-indumentaria', false)
            || can('crear-requisicion-sala', false)
            || can('editar-requisicion-sala', false)
            || can('actualizar-requisicion-sala', false)
            || can('listar-requisicion-sala', false)
            || can('cumplir-requisicion-sala', false)
            || can('cumplir-requisicion-compra', false)
            || can('crear-factura', false)
            || can('editar-factura', false)
            || can('actualizar-factura', false)
            || can('listar-factura', false)
            || can('generar-nota-de-credito', false)
            || can('listar-transportes', false)
            || can('crear-transportes', false)
            || can('editar-transportes', false)
            || can('actualizar-transportes', false)
            || can('listar-local-venta', false)
            || can('crear-local-venta', false)
            || can('editar-local-venta', false)
            || can('actualizar-local-venta', false)
            || can('consultar-stock-local', false)
            || can('consultar-precios-local', false)
            || can('usar-facturacion-local', false)
            || can('gestionar-logistica-solicitud', false);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-depositos');

        $filtros = DepmaeListadoFiltros::resolverDesdeRequest($request);

        if (! DepmaeListadoFiltros::tieneCriteriosAplicados($filtros)
            && $this->depmaeRepository->leeDepmae(DepmaeListadoFiltros::filtrosVacios(), false)->isEmpty()) {
            $Depmae = new Depmae();
            $Depmae->sincronizarConAnita();
        }

        $datas = $this->depmaeRepository->leeDepmae($filtros, true);
        $tipodeposito_enum = Depmae::$enumTipoDeposito;
        $esCrown = EntornoEmpresaSupport::esCrown();

        return view('stock.depmae.index', [
            'datas' => $datas,
            'tipodeposito_enum' => $tipodeposito_enum,
            'filtros' => $filtros,
            'filtrosQuery' => DepmaeListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => DepmaeListadoFiltros::campos(),
            'esCrown' => $esCrown,
            'sala_query' => $esCrown ? $this->salaRepository->allFiltrado() : collect(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-depositos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = DepmaeListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $tipodeposito_enum = Depmae::$enumTipoDeposito;

        switch ($formato) {
            case 'PDF':
                $datas = $this->depmaeRepository->leeDepmae($filtros, false);
                $view = \View::make('stock.depmae.listado', compact('datas', 'tipodeposito_enum'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_depmae';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new DepmaeListadoExport($this->depmaeRepository))
                    ->parametros($filtros)
                    ->download('depositos.xlsx');

            case 'CSV':
                return (new DepmaeListadoExport($this->depmaeRepository))
                    ->parametros($filtros)
                    ->download('depositos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('depmae', DepmaeListadoFiltros::paraQueryString($filtros));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear(Request $request)
    {
        can('crear-depositos');

        $tipodeposito_enum = Depmae::$enumTipoDeposito;
        $data = new Depmae();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $esCrown = EntornoEmpresaSupport::esCrown();
        $sala_query = $esCrown ? $this->salaRepository->allFiltrado() : collect();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, DepmaeListadoFiltros::class);

        return view('stock.depmae.crear', compact(
            'tipodeposito_enum',
            'data',
            'empresa_query',
            'sala_query',
            'esCrown',
            'filtrosQuery'
        ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionDepmae $request)
    {
        can('crear-depositos');

        $campos = ['codigo', 'nombre', 'tipodeposito', 'empresa_id'];
        if (EntornoEmpresaSupport::esCrown()) {
            $campos[] = 'sala_id';
        }
        if (EntornoEmpresaSupport::esFerli()) {
            $campos[] = 'canal';
        }
        Depmae::create($request->only($campos));

        return redirect()->route('depmae', QueryRetornoListado::desdeRequest($request, DepmaeListadoFiltros::class))
            ->with('mensaje', 'Deposito creado con exito');
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
            if (! $this->puedeConsultarDeposito()) {
                abort(403);
            }
        } else {
            can('editar-depositos');
        }

        $data = Depmae::findOrFail($id);
        $tipodeposito_enum = Depmae::$enumTipoDeposito;
        $empresa_query = $this->empresaRepository->allFiltrado();
        $esCrown = EntornoEmpresaSupport::esCrown();
        $sala_query = $esCrown ? $this->salaRepository->allFiltrado() : collect();
        $ocultarVolver = $soloConsulta;
        $puedeActualizarDeposito = can('actualizar-depositos', false);

        $filtrosQuery = QueryRetornoListado::desdeRequest($request, DepmaeListadoFiltros::class);

        return view('stock.depmae.editar', compact(
            'data',
            'tipodeposito_enum',
            'empresa_query',
            'sala_query',
            'esCrown',
            'soloConsulta',
            'ocultarVolver',
            'puedeActualizarDeposito',
            'filtrosQuery',
        ));
    }

    private function urlEditarDepositoConsulta(int $id): string
    {
        return route('editar_depmae', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }

    private function consultaDepositoIntercompany(Request $request): bool
    {
        if (! $request->boolean('intercompany')) {
            return false;
        }

        if (! TransferenciaMercaderiaIntercompanySupport::puedeUsar()
            && ! RecepcionProveedorIntercompanySupport::puedeUsar()) {
            abort(403);
        }

        return true;
    }

    private function aplicarAmbitoDepositoReporte(Request $request, $query): void
    {
        $ambito = trim((string) $request->input('ambito_deposito', ''));
        if ($ambito === '') {
            return;
        }

        ReporteStockOtDepositoAmbitoSupport::aplicarSobreMaestro($query, $ambito);
    }

    public function consultaDeposito(Request $request)
    {
        if (! $this->puedeConsultarDeposito()) {
            abort(403);
        }

        $consulta = strtoupper(trim((string) ($request->get('consulta') ?? '')));
        $omitirFiltroUsuario = $request->boolean('omitir_filtro_usuario');
        $intercompany = $this->consultaDepositoIntercompany($request);
        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $empresaId = (int) $request->input('empresa_id', 0);

        $salaId = (int) $request->input('sala_id', 0);
        $esCrown = EntornoEmpresaSupport::esCrown();
        $mostrarSala = $esCrown;

        $query = Depmae::query()->select('id', 'codigo', 'nombre', 'tipodeposito', 'empresa_id', 'sala_id')
            ->with(['empresas:id,nombre', 'salas:id,codigo,nombre']);

        if (! $intercompany) {
            if ($empresaIds !== []) {
                $query->whereIn('empresa_id', $empresaIds);
            } elseif ($empresaId > 0) {
                $query->paraEmpresa($empresaId);
            } else {
                $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);
            }
        }

        if ($esCrown) {
            GastronomiaSalaSupport::aplicarFiltroSiCorresponde($query, $salaId > 0 ? $salaId : null);
        }

        if (! $omitirFiltroUsuario) {
            UsuarioDepositoAutorizado::aplicarFiltroQuery($query, $intercompany ? null : ($empresaId > 0 ? $empresaId : null));
        }

        $this->aplicarAmbitoDepositoReporte($request, $query);

        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('codigo', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('nombre', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('tipodeposito', 'LIKE', '%'.$consulta.'%')
                    ->orWhereHas('empresas', function ($eq) use ($consulta) {
                        $eq->where('nombre', 'LIKE', '%'.$consulta.'%');
                    })
                    ->orWhereHas('salas', function ($sq) use ($consulta) {
                        $sq->where('codigo', 'LIKE', '%'.$consulta.'%')
                            ->orWhere('nombre', 'LIKE', '%'.$consulta.'%');
                    });
            });
        }

        if ($intercompany || $empresaIds !== [] || $empresaId > 0 || $salaId > 0) {
            $query->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))->orderBy('nombre');
        } else {
            $query->orderBy('nombre');
        }

        $data = $query->limit(200)->get();
        $puedeAbrirAbmDeposito = can('editar-depositos', false) || can('listar-depositos', false);
        $mostrarEmpresa = MovimientosArticuloDepositoSupport::mostrarEmpresaConsultaDeposito();
        $colspanVacio = 5 + ($mostrarEmpresa ? 1 : 0) + ($mostrarSala ? 1 : 0);

        $output = [
            'data' => '',
            'mostrar_empresa' => $mostrarEmpresa,
            'mostrar_sala' => $mostrarSala,
        ];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="'.$colspanVacio.'">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $salaEtiqueta = '';
                if ($row->salas) {
                    $salaEtiqueta = trim(($row->salas->codigo ?? '').' — '.($row->salas->nombre ?? ''), ' —');
                }
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="codigo">'.e($row->codigo).'</td>';
                $output['data'] .= '<td class="descripcion">'.e($row->nombre).'</td>';
                $output['data'] .= '<td class="tipodeposito">'.e($row->tipodeposito).'</td>';
                if ($mostrarSala) {
                    $output['data'] .= '<td class="sala-nombre">'.e($salaEtiqueta).'</td>';
                }
                $output['data'] .= '<td class="sala-id d-none">'.e($row->sala_id ?? '').'</td>';
                if ($mostrarEmpresa) {
                    $output['data'] .= '<td class="empresa-nombre">'.e(optional($row->empresas)->nombre ?? '').'</td>';
                }
                $output['data'] .= '<td class="empresa-id d-none">'.e($row->empresa_id).'</td>';
                if (! $mostrarEmpresa) {
                    $output['data'] .= '<td class="empresa-nombre d-none">'.e(optional($row->empresas)->nombre ?? '').'</td>';
                }
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultadeposito">Elegir</a>';
                if ($puedeAbrirAbmDeposito) {
                    $urlConsulta = $this->urlEditarDepositoConsulta((int) $row->id);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($urlConsulta).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td>';
                $output['data'] .= '</tr>';
            }
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE);
    }

    public function leeUnDepositoPorCodigo(Request $request, string $codigo)
    {
        if (! $this->puedeConsultarDeposito()) {
            abort(403);
        }

        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $empresaId = (int) $request->input('empresa_id', 0);
        $salaId = (int) $request->input('sala_id', 0);
        $omitirFiltroUsuario = $request->boolean('omitir_filtro_usuario');
        $intercompany = $this->consultaDepositoIntercompany($request);
        $esCrown = EntornoEmpresaSupport::esCrown();

        $query = Depmae::query()
            ->where('codigo', trim($codigo))
            ->with(['empresas:id,nombre', 'salas:id,codigo,nombre']);

        if (! $intercompany) {
            if ($empresaIds !== []) {
                $query->whereIn('empresa_id', $empresaIds);
            } elseif ($empresaId > 0) {
                $query->paraEmpresa($empresaId);
            } else {
                $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);
            }
        }

        if ($esCrown) {
            GastronomiaSalaSupport::aplicarFiltroSiCorresponde($query, $salaId > 0 ? $salaId : null);
        }

        if (! $omitirFiltroUsuario) {
            UsuarioDepositoAutorizado::aplicarFiltroQuery($query, $intercompany ? null : ($empresaId > 0 ? $empresaId : null));
        }

        $this->aplicarAmbitoDepositoReporte($request, $query);

        $matches = $query->limit(5)->get();
        if ($matches->isEmpty()) {
            $ambitoPedido = trim((string) $request->input('ambito_deposito', ''));
            $ambito = ReporteStockOtDepositoAmbitoSupport::normalizar($ambitoPedido);
            $mensaje = ($ambitoPedido !== '' && $ambito !== ReporteStockOtDepositoAmbitoSupport::TODOS)
                ? 'Ese depósito no está en '.ReporteStockOtDepositoAmbitoSupport::etiqueta($ambito).'.'
                : 'Depósito no encontrado';

            return response()->json(['error' => $mensaje], 404);
        }

        if ($esCrown && $salaId <= 0 && $matches->count() > 1) {
            return response()->json([
                'error' => 'Hay varios depósitos con ese código en distintas salas. Indicá la sala o elegí desde el modal.',
                'ambiguo' => true,
            ], 409);
        }

        $deposito = $matches->first();

        return response()->json([
            'id' => $deposito->id,
            'codigo' => $deposito->codigo,
            'descripcion' => $deposito->nombre,
            'tipodeposito' => $deposito->tipodeposito,
            'empresa_id' => $deposito->empresa_id,
            'empresa_nombre' => optional($deposito->empresas)->nombre,
            'sala_id' => $deposito->sala_id,
            'sala_nombre' => optional($deposito->salas)->nombre,
            'sala_codigo' => optional($deposito->salas)->codigo,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionDepmae $request, $id)
    {
        can('actualizar-depositos');
        $campos = ['codigo', 'nombre', 'tipodeposito', 'empresa_id'];
        if (EntornoEmpresaSupport::esCrown()) {
            $campos[] = 'sala_id';
        }
        if (EntornoEmpresaSupport::esFerli()) {
            $campos[] = 'canal';
        }
        Depmae::findOrFail($id)->update($request->only($campos));

        if ($request->input('origen') === 'modal_consulta') {
            return redirect()
                ->route('editar_depmae', [
                    'id' => $id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ])
                ->with('mensaje', 'Deposito actualizado con exito');
        }

        return redirect()->route('depmae', QueryRetornoListado::desdeRequest($request, DepmaeListadoFiltros::class))
            ->with('mensaje', 'Deposito actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-depositos');

        if ($request->ajax()) {
            if (Depmae::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
