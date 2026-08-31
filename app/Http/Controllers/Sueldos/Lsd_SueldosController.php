<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\LsdPresentacionListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Models\Sueldos\Lsd_Presentacion_Registro_Sueldos;
use App\Models\Sueldos\Lsd_Presentacion_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Sueldos\LsdExportadorConceptosService;
use App\Services\Sueldos\LsdGeneradorPresentacionService;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Sueldos\Lsd\LsdConceptoAfipCatalogo;
use App\Support\Sueldos\Lsd\LsdConceptoCoberturaSupport;
use App\Support\Sueldos\Lsd\LsdPeriodoWizardSupport;
use App\Support\Sueldos\Lsd\LsdTipoLiquidacionSupport;
use App\Support\Sueldos\LsdPresentacionListadoFiltros;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class Lsd_SueldosController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private LsdGeneradorPresentacionService $generador,
        private LsdExportadorConceptosService $exportadorConceptos,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-lsd-sueldos');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = LsdPresentacionListadoFiltros::resolverDesdeRequest(
            $request,
            null,
            $empresaDefault ? (int) $empresaDefault : null
        );

        $query = Lsd_Presentacion_Sueldos::query()->with(['empresa', 'liquidacion', 'usuario']);
        if (($filtros['empresa_scope'] ?? 'una') === 'todas' || empty($filtros['empresa_id'])) {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'lsd_presentacion_sueldos.empresa_id');
        }
        LsdPresentacionListadoFiltros::aplicar($query, $filtros);

        $datas = $query->orderByDesc('periodo')->orderByDesc('nro_liquidacion_afip')->paginate(15);

        return view('sueldos.lsd.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => LsdPresentacionListadoFiltros::paraQueryString($filtros),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'cobertura' => LsdConceptoCoberturaSupport::resumen(),
            'wizard' => LsdPeriodoWizardSupport::para(
                (int) ($filtros['empresa_id'] ?? $empresaDefault ?: 0),
                (int) ($filtros['periodo'] ?? date('Ym'))
            ),
            'periodos' => $this->periodosDisponibles(),
            'proximoNro' => $this->proximoNroSugerido($filtros),
            'puedeGenerar' => can('generar-lsd-sueldos', false),
            'puedeExportarConceptos' => can('exportar-conceptos-lsd-sueldos', false),
            'puedeVer' => can('ver-lsd-sueldos', false),
            'puedePresentar' => can('presentar-lsd-sueldos', false),
            'puedeBorrar' => can('borrar-lsd-sueldos', false),
            'puedeRectificar' => can('rectificar-lsd-sueldos', false),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-lsd-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = LsdPresentacionListadoFiltros::resolverDesdeRequest(
            $request,
            $busqueda,
            $empresaDefault ? (int) $empresaDefault : null
        );

        switch ($formato) {
            case 'PDF':
                $datas = $this->queryListado($filtros)->get();
                $view = \View::make('sueldos.lsd.listado', compact('datas', 'filtros'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/listado_lsd_sueldos.pdf');

                return response()->download($path.'/listado_lsd_sueldos.pdf');

            case 'EXCEL':
                return app(LsdPresentacionListadoExport::class)->parametros($filtros)->download('lsd_presentaciones.xlsx');

            case 'CSV':
                return app(LsdPresentacionListadoExport::class)->parametros($filtros)
                    ->download('lsd_presentaciones.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_lsd_sueldos', LsdPresentacionListadoFiltros::paraQueryString($filtros));
    }

    public function cobertura()
    {
        can('listar-lsd-sueldos');

        return view('sueldos.lsd.cobertura', [
            'resumen' => LsdConceptoCoberturaSupport::resumen(),
            'sinMapeo' => LsdConceptoCoberturaSupport::sinMapeo(),
            'puedeEditarConcepto' => can('editar-concepto-sueldos', false),
        ]);
    }

    public function exportarConceptos()
    {
        can('exportar-conceptos-lsd-sueldos');
        $out = $this->exportadorConceptos->generar();
        if ($out['cantidad'] === 0) {
            return redirect()->route('consultar_lsd_sueldos')
                ->with('error', 'No hay conceptos activos con mapeo AFIP para exportar.');
        }

        return response($out['contenido'], 200, [
            'Content-Type' => 'text/plain; charset=windows-1252',
            'Content-Disposition' => 'attachment; filename="'.$out['nombre'].'"',
        ]);
    }

    public function catalogoJson()
    {
        if (! can('listar-concepto-sueldos', false) && ! can('editar-concepto-sueldos', false)
            && ! can('crear-concepto-sueldos', false) && ! can('listar-lsd-sueldos', false)) {
            abort(403);
        }

        return response()->json(LsdConceptoAfipCatalogo::paraSelector());
    }

    public function liquidacionesPeriodo(Request $request)
    {
        can('generar-lsd-sueldos');
        $empresaId = (int) $request->input('empresa_id');
        $periodo = (int) $request->input('periodo');
        if ($empresaId <= 0 || $periodo < 200001) {
            return response()->json([]);
        }
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }
        $anio = intdiv($periodo, 100);
        $mes = $periodo % 100;
        $filas = Liquidacion_Sueldos::query()
            ->where('empresa_id', $empresaId)
            ->where('periodo_anio', $anio)
            ->where('periodo_mes', $mes)
            ->whereIn('estado', ['cerrada', 'contabilizada', 'pagada'])
            ->orderBy('numero')
            ->get(['id', 'numero', 'descripcion', 'tipo', 'estado', 'fecha_pago', 'cantidad_recibos']);

        $wizard = LsdPeriodoWizardSupport::para($empresaId, $periodo);
        $porId = collect($wizard['liquidaciones'])->keyBy('id');

        return response()->json($filas->map(function ($l) use ($porId) {
            $w = $porId->get($l->id);

            return [
                'id' => $l->id,
                'numero' => $l->numero,
                'descripcion' => $l->descripcion,
                'tipo' => $l->tipoLabel(),
                'tipo_afip' => LsdTipoLiquidacionSupport::desdeTipoErp($l->tipo),
                'orden' => LsdTipoLiquidacionSupport::pesoOrden($l->tipo),
                'estado' => $l->estadoLabel(),
                'fecha_pago' => optional($l->fecha_pago)->format('Y-m-d'),
                'recibos' => $l->cantidad_recibos,
                'presentada' => (bool) ($w['presentada'] ?? false),
                'generada' => (bool) ($w['generada'] ?? false),
            ];
        })->sortBy('orden')->values());
    }

    public function generar(Request $request)
    {
        can('generar-lsd-sueldos');
        $request->validate([
            'empresa_id' => 'required|integer|exists:empresa,id',
            'liquidacion_id' => 'required|integer|exists:liquidacion_sueldos,id',
            'nro_liquidacion_afip' => 'nullable|integer|min:1|max:99999',
            'identificacion' => 'nullable|in:SJ,RE',
            'fecha_pago' => 'nullable|date',
            'fecha_rubrica' => 'nullable|date',
            'incluir_licencias_sin_recibo' => 'nullable|boolean',
            'presentacion_orig_id' => 'nullable|integer|exists:lsd_presentacion_sueldos,id',
        ]);

        if (! $this->empresaRepository->empresaIdPermitida((int) $request->input('empresa_id'))) {
            return back()->with('error', 'No tiene acceso a la empresa seleccionada.');
        }
        $liqEmpresa = (int) Liquidacion_Sueldos::query()->where('id', (int) $request->input('liquidacion_id'))->value('empresa_id');
        if ($liqEmpresa !== (int) $request->input('empresa_id')) {
            return back()->with('error', 'La liquidación no pertenece a la empresa seleccionada.')->withInput();
        }

        try {
            $p = $this->generador->generar($request->all(), Auth::id());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('ver_lsd_sueldos', ['id' => $p->id])
            ->with('mensaje', 'Presentación LSD generada: nro. AFIP '.$p->nro_liquidacion_afip
                .' ('.$p->cantidad_registros_04.' trabajadores en F.931).');
    }

    public function ver($id)
    {
        can('ver-lsd-sueldos');
        $p = Lsd_Presentacion_Sueldos::query()
            ->with(['empresa', 'liquidacion', 'usuario', 'registros', 'origen'])
            ->findOrFail($id);
        $this->assertEmpresa($p);

        $porTipo = $p->registros->groupBy('tipo_registro');

        return view('sueldos.lsd.ver', [
            'p' => $p,
            'porTipo' => $porTipo,
            'puedePresentar' => can('presentar-lsd-sueldos', false),
            'puedeBorrar' => can('borrar-lsd-sueldos', false) && $p->estado !== 'presentada',
            'puedeRectificar' => can('rectificar-lsd-sueldos', false),
            'puedeGenerar' => can('generar-lsd-sueldos', false),
        ]);
    }

    public function descargar($id)
    {
        can('ver-lsd-sueldos');
        $p = Lsd_Presentacion_Sueldos::query()->with('registros')->findOrFail($id);
        $this->assertEmpresa($p);
        $txt = $this->generador->contenidoDesdePresentacion($p);
        $nombre = $p->archivo_nombre ?: sprintf('LSD_%s_%05d.txt', $p->periodo, $p->nro_liquidacion_afip);

        return response($txt, 200, [
            'Content-Type' => 'text/plain; charset=windows-1252',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    public function marcarPresentada(Request $request, $id)
    {
        can('presentar-lsd-sueldos');
        $p = Lsd_Presentacion_Sueldos::findOrFail($id);
        $this->assertEmpresa($p);
        $p->update([
            'estado' => 'presentada',
            'presentado_at' => now(),
        ]);

        return redirect()->route('ver_lsd_sueldos', ['id' => $p->id])
            ->with('mensaje', 'Marcada como presentada en ARCA.');
    }

    public function marcarRechazada(Request $request, $id)
    {
        can('presentar-lsd-sueldos');
        $p = Lsd_Presentacion_Sueldos::findOrFail($id);
        $this->assertEmpresa($p);
        $p->update(['estado' => 'rechazada']);

        return redirect()->route('ver_lsd_sueldos', ['id' => $p->id])
            ->with('mensaje', 'Marcada como rechazada por ARCA. Puede regenerar o rectificar.');
    }

    public function rectificar(Request $request, $id)
    {
        can('rectificar-lsd-sueldos');
        $orig = Lsd_Presentacion_Sueldos::findOrFail($id);
        $this->assertEmpresa($orig);
        if (! $orig->liquidacion_id) {
            return back()->with('error', 'La presentación no tiene liquidación asociada.');
        }

        try {
            $p = $this->generador->generar([
                'liquidacion_id' => $orig->liquidacion_id,
                'nro_liquidacion_afip' => $orig->nro_liquidacion_afip,
                'identificacion' => 'RE',
                'es_rectificativa' => true,
                'presentacion_orig_id' => $orig->id,
                'fecha_pago' => optional($orig->fecha_pago)->format('Y-m-d'),
                'fecha_rubrica' => optional($orig->fecha_rubrica)->format('Y-m-d'),
                'incluir_licencias_sin_recibo' => true,
            ], Auth::id());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('ver_lsd_sueldos', ['id' => $p->id])
            ->with('mensaje', 'Rectificativa RE generada (omite registros 02 y 03).');
    }

    public function guardarOverride(Request $request, $id, $registroId)
    {
        can('generar-lsd-sueldos');
        $p = Lsd_Presentacion_Sueldos::findOrFail($id);
        $this->assertEmpresa($p);
        if ($p->estado === 'presentada') {
            return back()->with('error', 'No se puede editar una presentación ya marcada como presentada.');
        }
        $reg = Lsd_Presentacion_Registro_Sueldos::query()
            ->where('presentacion_id', $p->id)
            ->where('id', $registroId)
            ->firstOrFail();
        $linea = rtrim((string) $request->input('contenido_override', ''));
        $reg->update([
            'contenido_override' => $linea === '' || $linea === $reg->contenido ? null : $linea,
            'estado_linea' => $linea !== '' && $linea !== $reg->contenido ? 'editado' : $reg->estado_linea,
        ]);

        return redirect()->route('ver_lsd_sueldos', ['id' => $p->id])
            ->with('mensaje', 'Línea '.$reg->nro_linea.' actualizada.');
    }

    public function eliminar($id)
    {
        can('borrar-lsd-sueldos');
        $p = Lsd_Presentacion_Sueldos::findOrFail($id);
        $this->assertEmpresa($p);
        if ($p->estado === 'presentada') {
            return back()->with('error', 'No se puede borrar una presentación marcada como presentada.');
        }
        EloquentAuditDeleteSupport::each(
            Lsd_Presentacion_Registro_Sueldos::query()->where('presentacion_id', $p->id)
        );
        $p->delete();

        return redirect()->route('consultar_lsd_sueldos')
            ->with('mensaje', 'Presentación LSD eliminada.');
    }

    private function assertEmpresa(Lsd_Presentacion_Sueldos $p): void
    {
        if (! $this->empresaRepository->empresaIdPermitida((int) $p->empresa_id)) {
            abort(403, 'No tiene acceso a la empresa de esta presentación.');
        }
    }

    /** @return \Illuminate\Database\Eloquent\Builder */
    private function queryListado(array $filtros)
    {
        $query = Lsd_Presentacion_Sueldos::query()->with(['empresa', 'liquidacion']);
        if (($filtros['empresa_scope'] ?? 'una') === 'todas' || empty($filtros['empresa_id'])) {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'lsd_presentacion_sueldos.empresa_id');
        }
        LsdPresentacionListadoFiltros::aplicar($query, $filtros);

        return $query->orderByDesc('periodo')->orderByDesc('nro_liquidacion_afip');
    }

    /** @return list<int> */
    private function periodosDisponibles(): array
    {
        return Lsd_Presentacion_Sueldos::query()
            ->select('periodo')
            ->distinct()
            ->orderByDesc('periodo')
            ->limit(36)
            ->pluck('periodo')
            ->map(fn ($p) => (int) $p)
            ->all();
    }

    private function proximoNroSugerido(array $filtros): int
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $periodo = (int) ($filtros['periodo'] ?? 0);
        if ($empresaId <= 0) {
            $empresaId = (int) optional($this->empresaRepository->allFiltrado()->first())->id;
        }
        if ($periodo <= 0) {
            $periodo = (int) date('Ym');
        }
        if ($empresaId <= 0) {
            return 1;
        }

        return $this->generador->proximoNroAfip($empresaId, $periodo);
    }
}
