<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Depmae;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Stock\DepmaeRepositoryInterface;
use App\Exports\Stock\DepmaeListadoExport;
use App\Http\Requests\ValidacionDepmae;
use App\Support\Stock\DepmaeListadoFiltros;
use App\Support\Stock\UsuarioDepositoAutorizado;

class DepmaeController extends Controller
{
    public function __construct(
        private readonly DepmaeRepositoryInterface $depmaeRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
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
            || can('confirmar-recepcion-proveedor', false);
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

        return view('stock.depmae.index', [
            'datas' => $datas,
            'tipodeposito_enum' => $tipodeposito_enum,
            'filtros' => $filtros,
            'filtrosQuery' => DepmaeListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => DepmaeListadoFiltros::CAMPOS,
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
    public function crear()
    {
        can('crear-depositos');

        $tipodeposito_enum = Depmae::$enumTipoDeposito;
        $data = new Depmae();
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('stock.depmae.crear', compact('tipodeposito_enum', 'data', 'empresa_query'));
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

        Depmae::create($request->only(['codigo', 'nombre', 'tipodeposito', 'empresa_id']));

        $Depmae = new Depmae();
        $Depmae->guardarAnita($request, $request->codigo);

        return redirect('stock/depmae')->with('mensaje', 'Deposito creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        $soloConsulta = request()->query('origen') === 'modal_consulta';
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
        $ocultarVolver = $soloConsulta;
        $puedeActualizarDeposito = can('actualizar-depositos', false);

        return view('stock.depmae.editar', compact('data', 'tipodeposito_enum', 'empresa_query', 'soloConsulta', 'ocultarVolver', 'puedeActualizarDeposito'));
    }

    private function urlEditarDepositoConsulta(int $id): string
    {
        return route('editar_depmae', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }

    public function consultaDeposito(Request $request)
    {
        if (! $this->puedeConsultarDeposito()) {
            abort(403);
        }

        $consulta = strtoupper(trim((string) ($request->get('consulta') ?? '')));
        $omitirFiltroUsuario = $request->boolean('omitir_filtro_usuario');
        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $empresaId = (int) $request->input('empresa_id', 0);

        $query = Depmae::query()->select('id', 'codigo', 'nombre', 'tipodeposito', 'empresa_id')
            ->with('empresas:id,nombre');

        if ($empresaIds !== []) {
            $query->whereIn('empresa_id', $empresaIds);
        } elseif ($empresaId > 0) {
            $query->paraEmpresa($empresaId);
        } else {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);
        }

        if (! $omitirFiltroUsuario) {
            UsuarioDepositoAutorizado::aplicarFiltroQuery($query);
        }

        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('codigo', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('nombre', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('tipodeposito', 'LIKE', '%'.$consulta.'%');
            });
        }

        $data = $query->orderBy('nombre')->limit(200)->get();
        $puedeAbrirAbmDeposito = can('editar-depositos', false) || can('listar-depositos', false);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="5">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="codigo">'.e($row->codigo).'</td>';
                $output['data'] .= '<td class="descripcion">'.e($row->nombre).'</td>';
                $output['data'] .= '<td class="tipodeposito">'.e($row->tipodeposito).'</td>';
                $output['data'] .= '<td class="empresa-id d-none">'.e($row->empresa_id).'</td>';
                $output['data'] .= '<td class="empresa-nombre d-none">'.e(optional($row->empresas)->nombre ?? '').'</td>';
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
        $omitirFiltroUsuario = $request->boolean('omitir_filtro_usuario');

        $query = Depmae::query()->where('codigo', trim($codigo))->with('empresas:id,nombre');

        if ($empresaIds !== []) {
            $query->whereIn('empresa_id', $empresaIds);
        } elseif ($empresaId > 0) {
            $query->paraEmpresa($empresaId);
        } else {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);
        }

        if (! $omitirFiltroUsuario) {
            UsuarioDepositoAutorizado::aplicarFiltroQuery($query);
        }

        $deposito = $query->first();

        if (! $deposito) {
            return response()->json(['error' => 'Depósito no encontrado'], 404);
        }

        return response()->json([
            'id' => $deposito->id,
            'codigo' => $deposito->codigo,
            'descripcion' => $deposito->nombre,
            'tipodeposito' => $deposito->tipodeposito,
            'empresa_id' => $deposito->empresa_id,
            'empresa_nombre' => optional($deposito->empresas)->nombre,
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
        Depmae::findOrFail($id)->update($request->only(['codigo', 'nombre', 'tipodeposito', 'empresa_id']));

        // Actualiza anita
        $Depmae = new Depmae();
        $Depmae->actualizarAnita($request, $id);

        if ($request->input('origen') === 'modal_consulta') {
            return redirect()
                ->route('editar_depmae', [
                    'id' => $id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ])
                ->with('mensaje', 'Deposito actualizado con exito');
        }

        return redirect('stock/depmae')->with('mensaje', 'Deposito actualizado con exito');
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

        $depmae = Depmae::findOrFail($id);

        $Depmae = new Depmae();
        if (config('app.empresa') == 'Calzados Ferli') {
            $Depmae->eliminarAnita($id, (int) $depmae->empresa_id);
        } else {
            $Depmae->eliminarAnita($request->codigo, (int) $depmae->empresa_id);
        }

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
