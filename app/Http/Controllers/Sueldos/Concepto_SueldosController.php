<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\ConceptoSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConcepto_Sueldos;
use App\Models\Sueldos\Acumulador_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Sueldos\Concepto_SueldosRepositoryInterface;
use App\Services\Sueldos\ConceptoPapoReclasificarService;
use App\Services\Sueldos\LiquidacionCalculadorService;
use App\Support\Sueldos\ConceptoSueldosListadoFiltros;
use App\Support\Sueldos\ConceptoTipo;
use App\Support\Sueldos\Formula\FormulaException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class Concepto_SueldosController extends Controller
{
    private Concepto_SueldosRepositoryInterface $repository;

    private EmpresaRepositoryInterface $empresaRepository;

    public function __construct(
        Concepto_SueldosRepositoryInterface $repository,
        EmpresaRepositoryInterface $empresaRepository
    ) {
        $this->repository = $repository;
        $this->empresaRepository = $empresaRepository;
    }

    /** Permisos de ABM o de pantallas operativas que eligen concepto. */
    private function puedeConsultarConceptoOperativo(): bool
    {
        return can('listar-concepto-sueldos', false)
            || can('editar-concepto-sueldos', false)
            || can('crear-concepto-sueldos', false)
            || can('editar-empleado-sueldos', false)
            || can('actualizar-empleado-sueldos', false)
            || can('crear-tipo-ausencia-sueldos', false)
            || can('editar-tipo-ausencia-sueldos', false)
            || can('actualizar-tipo-ausencia-sueldos', false)
            || can('listar-tipo-ausencia-sueldos', false);
    }

    public function index(Request $request)
    {
        can('listar-concepto-sueldos');

        $filtros = ConceptoSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeConcepto($filtros, true);

        return view('sueldos.concepto.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ConceptoSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ConceptoSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-concepto-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ConceptoSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeConcepto($filtros, false);

                $view = \View::make('sueldos.concepto.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_concepto_sueldos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(ConceptoSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('concepto_sueldos.xlsx');

            case 'CSV':
                return app(ConceptoSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('concepto_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_concepto_sueldos', ConceptoSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-concepto-sueldos');

        return view('sueldos.concepto.crear', [
            'acumuladores' => $this->acumuladores(),
            'overridesMap' => [],
        ]);
    }

    public function guardar(ValidacionConcepto_Sueldos $request)
    {
        can('crear-concepto-sueldos');
        $this->repository->create($request->validated());

        return redirect('sueldos/concepto')
            ->with('mensaje', 'Concepto creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        $modoConsulta = $request->query('origen') === 'modal_consulta'
            || $request->query('vista') === 'consulta';
        if ($modoConsulta) {
            if (! $this->puedeConsultarConceptoOperativo()) {
                abort(403);
            }
        } else {
            can('editar-concepto-sueldos');
        }

        $data = $this->repository->findOrFail($id);
        $data->load('acumuladoresOverride');

        $overridesMap = [];
        foreach ($data->acumuladoresOverride as $ov) {
            $overridesMap[$ov->acumulador_id] = [
                'accion' => $ov->excluir ? 'excluir' : 'incluir',
                'signo' => (int) $ov->signo,
            ];
        }

        $puedeActualizar = can('actualizar-concepto-sueldos', false);

        return view('sueldos.concepto.editar', [
            'data' => $data,
            'acumuladores' => $this->acumuladores(),
            'overridesMap' => $overridesMap,
            'modoConsulta' => $modoConsulta,
            'soloConsulta' => $modoConsulta && ! $puedeActualizar,
            'empresas' => $this->empresaRepository->allFiltrado(),
            'tiposLiquidacion' => Liquidacion_Sueldos::TIPOS,
        ]);
    }

    public function consultaConcepto(Request $request)
    {
        if (! $this->puedeConsultarConceptoOperativo()) {
            abort(403);
        }

        $consulta = (string) ($request->input('consulta') ?? '');
        $data = $this->repository->listadoParaConsulta($consulta);
        $puedeAbrirAbm = can('editar-concepto-sueldos', false) || can('listar-concepto-sueldos', false);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="5">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $tipoLabel = ConceptoTipo::TIPOS[$row->tipo] ?? (string) $row->tipo;
                $codigoFmt = str_pad((string) $row->codigo, 4, '0', STR_PAD_LEFT);
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="concepto_id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="codigoconcepto">'.e($codigoFmt).'</td>';
                $output['data'] .= '<td class="descripcionconcepto">'.e($row->descripcion).'</td>';
                $output['data'] .= '<td class="tipoconcepto">'.e($tipoLabel).'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultaconcepto_sueldos">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_concepto_sueldos', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td>';
                $output['data'] .= '</tr>';
            }
        }

        return response()->json($output);
    }

    public function leeUnConceptoPorCodigo($codigo)
    {
        if (! $this->puedeConsultarConceptoOperativo()) {
            abort(403);
        }

        $codigoInt = (int) preg_replace('/\D+/', '', (string) $codigo);
        $concepto = $this->repository->findActivoPorCodigo($codigoInt);
        if ($concepto === null) {
            return response()->json(['error' => 'Concepto no encontrado'], 404);
        }

        return response()->json($this->payloadConceptoOperativo($concepto));
    }

    public function leeConcepto($id)
    {
        if (! $this->puedeConsultarConceptoOperativo()) {
            abort(403);
        }

        try {
            $concepto = $this->repository->find((int) $id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Concepto no encontrado'], 404);
        }

        if (! $concepto->activo) {
            return response()->json(['error' => 'Concepto inactivo'], 404);
        }

        return response()->json($this->payloadConceptoOperativo($concepto));
    }

    /**
     * @return array{id:int,codigo:int,descripcion:string,tipo:?string,tipo_label:string}
     */
    private function payloadConceptoOperativo($concepto): array
    {
        return [
            'id' => (int) $concepto->id,
            'codigo' => (int) $concepto->codigo,
            'descripcion' => (string) $concepto->descripcion,
            'tipo' => $concepto->tipo,
            'tipo_label' => ConceptoTipo::TIPOS[$concepto->tipo] ?? (string) ($concepto->tipo ?? ''),
        ];
    }

    private function acumuladores()
    {
        return Acumulador_Sueldos::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'descripcion', 'tipos_incluye', 'signo']);
    }

    public function actualizar(ValidacionConcepto_Sueldos $request, $id)
    {
        can('actualizar-concepto-sueldos');
        $this->repository->update($request->validated(), $id);

        if ($request->query('origen') === 'modal_consulta' || $request->query('vista') === 'consulta') {
            return redirect()->route('editar_concepto_sueldos', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])->with('mensaje', 'Concepto actualizado con éxito');
        }

        return redirect('sueldos/concepto')
            ->with('mensaje', 'Concepto actualizado con éxito');
    }

    public function sincronizarAnita(Request $request)
    {
        can('actualizar-concepto-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $resultado = $this->repository->sincronizarConAnita();

        if (! empty($resultado['errores'])) {
            return redirect()->route('consultar_concepto_sueldos')
                ->with('error', 'No se pudo sincronizar con Anita: '.implode(' | ', $resultado['errores']));
        }

        return redirect()->route('consultar_concepto_sueldos')->with(
            'mensaje',
            'Sincronización Anita: '.$resultado['importados'].' importados, '
                .$resultado['actualizados'].' actualizados (seeds), '
                .$resultado['omitidos'].' ya existentes, '
                .$resultado['con_formula'].' con fórmula, '
                .$resultado['traducidos'].' traducidos OK, '
                .$resultado['pendientes_traduccion'].' pendientes de revisión '
                .'(de '.$resultado['en_anita'].' en Anita).'
        );
    }

    public function retraducirFormulas(Request $request)
    {
        can('actualizar-concepto-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $resultado = $this->repository->retraducirFormulasDesdeLineas();

        $msg = 'Retraducción: '.$resultado['traducidos'].' OK, '
            .$resultado['pendientes_traduccion'].' pendientes/revisión, '
            .$resultado['sin_lineas'].' sin líneas Anita '
            .'(procesados '.$resultado['procesados'].').';

        if (! empty($resultado['errores'])) {
            $msg .= ' Errores: '.implode(' | ', array_slice($resultado['errores'], 0, 5));
        }

        return redirect()->route('consultar_concepto_sueldos')->with('mensaje', $msg);
    }

    /**
     * Reclasifica papo–uapo: contribución (va recibo) vs informativo (solo reportes),
     * y corrige va_recibo según Anita hab_va_recibo (0=va, 1=no).
     */
    public function reclasificarPapo(ConceptoPapoReclasificarService $svc)
    {
        can('actualizar-concepto-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '120');

        $r = $svc->reclasificarDesdeAnita();
        $u = $svc->precargarUnidadesMedida();
        $msg = 'Reclasificación papo: Anita '.$r['leidos_anita']
            .' · actualizados '.$r['actualizados']
            .' · → contribución '.$r['a_contribucion']
            .' · → informativo '.$r['a_informativo']
            .' · va_recibo corregidos '.$r['va_recibo_corregidos']
            .' · unidades medida '.$r['unidad_medida_asignadas']
            .' (+precarga '.$u['asignadas'].')';

        if ($r['errores'] !== []) {
            return redirect()->route('consultar_concepto_sueldos')
                ->with('mensaje', $msg)
                ->with('error', implode(' | ', $r['errores']));
        }

        return redirect()->route('consultar_concepto_sueldos')->with('mensaje', $msg);
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-concepto-sueldos');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    /**
     * Valida sintaxis de la fórmula del textarea (sin legajo).
     */
    public function validarFormula(Request $request, LiquidacionCalculadorService $calculador)
    {
        if (! $this->puedeConsultarConceptoOperativo()) {
            can('editar-concepto-sueldos');
        }

        $formula = (string) $request->input('formula', '');

        return response()->json($calculador->validarFormula($formula));
    }

    /**
     * Debugger sandbox: evalúa el concepto (o fórmula override) contra un legajo/período.
     */
    public function depurarFormula(Request $request, LiquidacionCalculadorService $calculador, $id)
    {
        can('editar-concepto-sueldos');
        $concepto = $this->repository->findOrFail($id);

        $datos = $request->validate([
            'empleado_id' => ['nullable', 'integer', 'min:1'],
            'empresa_id' => ['nullable', 'integer', 'min:1'],
            'legajo' => ['nullable', 'integer', 'min:1'],
            'periodo' => ['required', 'string'],
            'tipo' => ['nullable', 'string'],
            'usar_texto_formulario' => ['nullable', 'boolean'],
            'formula' => ['nullable', 'string'],
            'formula_cantidad' => ['nullable', 'string'],
            'formula_valor' => ['nullable', 'string'],
        ]);

        $emp = null;
        if (! empty($datos['empleado_id'])) {
            $emp = Empleado_Sueldos::find((int) $datos['empleado_id']);
        } elseif (! empty($datos['legajo'])) {
            $q = Empleado_Sueldos::query()->where('legajo', (int) $datos['legajo']);
            if (! empty($datos['empresa_id'])) {
                $q->where('empresa_id', (int) $datos['empresa_id']);
            }
            $emp = $q->orderBy('id')->first();
        }

        if (! $emp) {
            return response()->json([
                'message' => 'Indique empleado (ID) o empresa + legajo existentes.',
            ], 422);
        }

        $overrides = null;
        if ($request->boolean('usar_texto_formulario', true)) {
            $overrides = [
                'formula' => array_key_exists('formula', $datos) ? (string) ($datos['formula'] ?? '') : (string) ($concepto->formula ?? ''),
                'formula_cantidad' => array_key_exists('formula_cantidad', $datos)
                    ? (string) ($datos['formula_cantidad'] ?? '')
                    : (string) ($concepto->formula_cantidad ?? ''),
                'formula_valor' => array_key_exists('formula_valor', $datos)
                    ? (string) ($datos['formula_valor'] ?? '')
                    : (string) ($concepto->formula_valor ?? ''),
            ];
        }

        try {
            $resultado = $calculador->depurarEmpleado(
                $emp,
                (string) $datos['periodo'],
                (string) ($datos['tipo'] ?? 'mensual'),
                (int) $concepto->codigo,
                $overrides
            );

            return response()->json($resultado);
        } catch (FormulaException $e) {
            return response()->json(['message' => $e->getMessage(), 'pasos' => []], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'No se pudo depurar: '.$e->getMessage(), 'pasos' => []], 500);
        }
    }
}
