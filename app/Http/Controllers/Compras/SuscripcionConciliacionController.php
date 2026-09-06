<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\SuscripcionConciliacionExport;
use App\Http\Controllers\Controller;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Suscripcion_Cargo;
use App\Models\Compras\Suscripcion_Conciliacion;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\SuscripcionConciliacionService;
use App\Services\Compras\SuscripcionImputacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Conciliación mensual: el resumen de la tarjeta contra las OC abiertas de suscripción.
 */
class SuscripcionConciliacionController extends Controller
{
    public function __construct(
        private SuscripcionConciliacionService $conciliacionService,
        private SuscripcionImputacionService $imputacionService,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('conciliar-suscripcion');

        $empresaId = (int) $request->input('empresa_id');

        return view('compras.suscripcion.conciliacion.index', [
            'periodos' => $this->conciliacionService->periodos($empresaId ?: null),
            'empresa_id' => $empresaId,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'periodo_sugerido' => now()->subMonthNoOverflow()->format('Y-m'),
        ]);
    }

    public function abrir(Request $request)
    {
        can('conciliar-suscripcion');

        $request->validate([
            'empresa_id' => 'required|integer|exists:empresa,id',
            'periodo' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ], [
            'periodo.regex' => 'El período va en formato AAAA-MM.',
        ]);

        $conciliacion = $this->conciliacionService->abrirPeriodo(
            (int) $request->input('empresa_id'),
            (string) $request->input('periodo')
        );

        return redirect()
            ->route('ver_conciliacion_suscripcion', $conciliacion->id)
            ->with('mensaje', 'Período '.$conciliacion->periodo.' listo para importar el resumen.');
    }

    public function ver(Request $request, int $id)
    {
        can('conciliar-suscripcion');

        $conciliacion = Suscripcion_Conciliacion::query()->with('empresas')->findOrFail($id);
        $estado = (string) $request->input('estado', '');
        $busqueda = trim((string) $request->input('q', ''));

        $cargos = $conciliacion->suscripcion_cargos()
            ->with(['ordencompras.proveedores', 'suscripcion_tarjetas', 'monedas'])
            ->when($estado !== '', fn ($q) => $q->where('estado', $estado))
            ->when($busqueda !== '', fn ($q) => $q->where(function ($sub) use ($busqueda) {
                $sub->where('comercio', 'like', '%'.$busqueda.'%')
                    ->orWhere('comercio_normalizado', 'like', '%'.$busqueda.'%')
                    ->orWhere('tarjeta_ult4', 'like', '%'.$busqueda.'%');
            }))
            ->orderBy('estado')
            ->orderBy('fecha')
            ->get();

        return view('compras.suscripcion.conciliacion.ver', [
            'conciliacion' => $conciliacion,
            'cargos' => $cargos,
            'resumen' => $this->conciliacionService->resumen($conciliacion),
            'estado' => $estado,
            'busqueda' => $busqueda,
            // Períodos hermanos: cambiar de mes sin volver al listado.
            'periodos_empresa' => Suscripcion_Conciliacion::query()
                ->where('empresa_id', $conciliacion->empresa_id)
                ->orderByDesc('periodo')
                ->limit(12)
                ->get(['id', 'periodo', 'estado']),
            'suscripciones' => Ordencompra::query()
                ->with('proveedores')
                ->where('es_suscripcion', true)
                ->where('suscripcion_borrador', false)
                ->where('empresa_id', $conciliacion->empresa_id)
                ->orderBy('suscripcion_nombre')
                ->get(['id', 'suscripcion_nombre', 'numeroordencompra', 'proveedor_id', 'suscripcion_monto_periodo']),
        ]);
    }

    /**
     * El papel de trabajo del período: los cargos con la orden que los explica.
     */
    public function exportar(Request $request, int $id, string $formato)
    {
        can('conciliar-suscripcion');

        $conciliacion = Suscripcion_Conciliacion::query()->with('empresas')->findOrFail($id);
        $estado = (string) $request->input('estado', '');
        $busqueda = trim((string) $request->input('q', ''));

        // Se exporta lo que el usuario está viendo, filtros incluidos.
        $cargos = $conciliacion->suscripcion_cargos()
            ->with(['ordencompras', 'suscripcion_tarjetas', 'monedas'])
            ->when($estado !== '', fn ($q) => $q->where('estado', $estado))
            ->when($busqueda !== '', fn ($q) => $q->where(function ($sub) use ($busqueda) {
                $sub->where('comercio', 'like', '%'.$busqueda.'%')
                    ->orWhere('comercio_normalizado', 'like', '%'.$busqueda.'%')
                    ->orWhere('tarjeta_ult4', 'like', '%'.$busqueda.'%');
            }))
            ->orderBy('estado')
            ->orderBy('fecha')
            ->get();

        $resumen = $this->conciliacionService->resumen($conciliacion);
        $nombre = 'conciliacion_suscripciones_'.$conciliacion->periodo;

        return match (strtoupper($formato)) {
            'PDF' => $this->descargarPdf($conciliacion, $cargos, $resumen, $nombre),
            'CSV' => (new SuscripcionConciliacionExport)
                ->parametros($conciliacion, $cargos, $resumen)
                ->download($nombre.'.csv', Excel::CSV),
            default => (new SuscripcionConciliacionExport)
                ->parametros($conciliacion, $cargos, $resumen)
                ->download($nombre.'.xlsx'),
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Suscripcion_Cargo>  $cargos
     * @param  array<string, mixed>  $resumen
     */
    private function descargarPdf(
        Suscripcion_Conciliacion $conciliacion,
        $cargos,
        array $resumen,
        string $nombre
    ): BinaryFileResponse {
        $html = view('exports.compras.suscripcion_conciliacion_pdf', compact('conciliacion', 'cargos', 'resumen'))->render();

        $directorio = storage_path('pdf/listados');
        if (! is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }
        $archivo = $directorio.'/'.$nombre.'_'.now()->format('Ymd_His').'.pdf';

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html)->save($archivo);

        return response()->download($archivo)->deleteFileAfterSend(true);
    }

    public function importar(Request $request, int $id)
    {
        can('conciliar-suscripcion');

        $request->validate([
            'archivo' => 'required|file|max:20480',
        ], [
            'archivo.max' => 'El resumen no puede superar los 20 MB.',
        ]);

        $conciliacion = Suscripcion_Conciliacion::query()->findOrFail($id);
        $resultado = $this->conciliacionService->importarResumen($conciliacion, $request->file('archivo'));

        return back()->with($resultado['ok'] ? 'mensaje' : 'error', $resultado['mensaje']);
    }

    public function rematchear(int $id)
    {
        can('conciliar-suscripcion');

        $conciliacion = Suscripcion_Conciliacion::query()->findOrFail($id);
        $r = $this->conciliacionService->matchearAutomatico($conciliacion);

        return back()->with(
            'mensaje',
            "Cruce automático: {$r['asociados']} de {$r['evaluados']} cargos sin identificar quedaron asociados."
        );
    }

    public function asociar(Request $request, int $cargoId)
    {
        can('conciliar-suscripcion');

        $request->validate([
            'ordencompra_id' => 'required|integer|exists:ordencompra,id',
        ]);

        $cargo = Suscripcion_Cargo::query()->with('suscripcion_conciliaciones')->findOrFail($cargoId);
        $resultado = $this->conciliacionService->asociarManual(
            $cargo,
            (int) $request->input('ordencompra_id'),
            (int) Auth::id(),
            $request->boolean('aprender_alias', true)
        );

        return back()->with($resultado['ok'] ? 'mensaje' : 'error', $resultado['mensaje']);
    }

    public function desasociar(int $cargoId)
    {
        can('conciliar-suscripcion');

        $cargo = Suscripcion_Cargo::query()->with('suscripcion_conciliaciones')->findOrFail($cargoId);
        if (! $cargo->suscripcion_conciliaciones?->abierta()) {
            return back()->with('error', 'El período está cerrado.');
        }

        $this->conciliacionService->desasociar($cargo);

        return back()->with('mensaje', 'Se quitó la asociación del cargo.');
    }

    public function marcar(Request $request, int $cargoId)
    {
        can('conciliar-suscripcion');

        $request->validate([
            'estado' => 'required|string|in:REGULARIZAR,DESCARTADO',
            'observacion' => 'nullable|string|max:255',
        ]);

        $cargo = Suscripcion_Cargo::query()->with('suscripcion_conciliaciones')->findOrFail($cargoId);
        $resultado = $this->conciliacionService->marcarEstado(
            $cargo,
            (string) $request->input('estado'),
            $request->input('observacion')
        );

        return back()->with($resultado['ok'] ? 'mensaje' : 'error', $resultado['mensaje']);
    }

    public function revalidar(int $cargoId)
    {
        can('conciliar-suscripcion');

        $cargo = Suscripcion_Cargo::query()->with('suscripcion_conciliaciones')->findOrFail($cargoId);
        $resultado = $this->conciliacionService->enviarDesvioAReaprobacion($cargo, (int) Auth::id());

        return back()->with($resultado['ok'] ? 'mensaje' : 'error', $resultado['mensaje']);
    }

    public function imputar(int $id)
    {
        can('imputar-suscripcion');

        $conciliacion = Suscripcion_Conciliacion::query()->findOrFail($id);
        $resultado = $this->imputacionService->imputarPeriodo($conciliacion, (int) Auth::id());

        return back()->with($resultado['ok'] ? 'mensaje' : 'error', $resultado['mensaje']);
    }

    public function cerrar(int $id)
    {
        can('conciliar-suscripcion');

        $conciliacion = Suscripcion_Conciliacion::query()->findOrFail($id);
        $resultado = $this->conciliacionService->cerrarPeriodo($conciliacion, (int) Auth::id());

        return back()->with($resultado['ok'] ? 'mensaje' : 'error', $resultado['mensaje']);
    }

    /** Sugerencias de suscripción para un cargo, para el modal de asociación. */
    public function sugerencias(int $cargoId)
    {
        can('conciliar-suscripcion');

        $cargo = Suscripcion_Cargo::query()->with('suscripcion_conciliaciones')->findOrFail($cargoId);

        $items = array_map(
            fn (array $s) => [
                'id' => (int) $s['ordencompra']->id,
                'nombre' => (string) $s['ordencompra']->suscripcion_nombre,
                'proveedor' => (string) optional($s['ordencompra']->proveedores)->nombre,
                'numero_oc' => (string) $s['ordencompra']->numeroordencompra,
                'monto' => (float) $s['ordencompra']->suscripcion_monto_periodo,
                'puntaje' => $s['puntaje'],
                'motivo' => $s['motivo'],
            ],
            $this->conciliacionService->sugerenciasPara($cargo)
        );

        return response()->json(['cargo_id' => $cargo->id, 'sugerencias' => $items]);
    }
}
