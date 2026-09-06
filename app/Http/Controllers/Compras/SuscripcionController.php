<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\SuscripcionListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Compras\Suscripcion_Tarjeta;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Compras\SuscripcionAprobadorService;
use App\Services\Compras\SuscripcionService;
use App\Support\Compras\SuscripcionListadoFiltros;
use App\Support\Compras\SuscripcionSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SuscripcionController extends Controller
{
    public function __construct(
        private SuscripcionService $suscripcionService,
        private SuscripcionAprobadorService $aprobadorService,
        private EmpresaRepositoryInterface $empresaRepository,
        private MonedaRepositoryInterface $monedaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-suscripcion');

        $filtros = SuscripcionListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = SuscripcionListadoFiltros::paraQueryString($filtros);
        $filas = $this->suscripcionService->listar($filtros);
        $pendientesUsuario = $this->suscripcionService
            ->pendientesAprobacionParaUsuario((int) Auth::id())
            ->count();

        return view('compras.suscripcion.index', [
            'filas' => $filas,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'kpis' => $this->suscripcionService->kpis($filas),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'pendientes_count' => $pendientesUsuario,
            'estados' => SuscripcionSupport::estadosNegocio(),
        ]);
    }

    /**
     * Mismo listado en PDF, XLS o CSV.
     */
    public function exportar(Request $request, string $formato)
    {
        can('listar-suscripcion');

        $filtros = SuscripcionListadoFiltros::resolverDesdeRequest($request);
        $filas = $this->suscripcionService->listar($filtros);
        $kpis = $this->suscripcionService->kpis($filas);

        return match (strtoupper($formato)) {
            'PDF' => $this->descargarPdfListado($filas, $filtros, $kpis),
            'CSV' => (new SuscripcionListadoExport)
                ->parametros($filas, $filtros, $kpis)
                ->download('suscripciones.csv', Excel::CSV),
            default => (new SuscripcionListadoExport)
                ->parametros($filas, $filtros, $kpis)
                ->download('suscripciones.xlsx'),
        };
    }

    public function crear()
    {
        can('crear-suscripcion');

        return view('compras.suscripcion.crear', $this->datosFormulario());
    }

    public function guardar(Request $request)
    {
        can('crear-suscripcion');

        $enviar = $request->input('accion') !== 'borrador';
        $resultado = $this->suscripcionService->guardar($request, $enviar);

        if (($resultado['mensaje'] ?? '') !== 'ok') {
            return back()->withInput()->with('error', $resultado['errores'] ?? 'No se pudo guardar la suscripción.');
        }

        $numero = $resultado['numeroordencompra'] ?? null;
        $msg = $enviar
            ? 'Suscripción enviada a aprobación.'.($numero ? ' OC N° '.$numero.'.' : '')
            : 'Borrador de suscripción guardado.';

        $redirect = redirect()->route('consultar_suscripcion')->with('mensaje', $msg);

        return isset($resultado['advertencia'])
            ? $redirect->with('advertencias', $resultado['advertencia'])
            : $redirect;
    }

    public function ver(int $id)
    {
        can('listar-suscripcion');

        $oc = $this->suscripcionService->find($id);
        if (! $oc) {
            return redirect()->route('consultar_suscripcion')->with('error', 'Suscripción no encontrada.');
        }

        return view('compras.suscripcion.ver', [
            'oc' => $oc,
            'estado' => SuscripcionSupport::estadoNegocio($oc),
            'historia' => $this->suscripcionService->historiaAprobacion($id),
            'gerente_id' => $this->aprobadorService->gerenteDe((int) $oc->empresa_id, (int) $oc->centrocosto_id),
            'impacto' => $this->suscripcionService->impactoPresupuestario($oc),
        ]);
    }

    public function enviarBorrador(int $id)
    {
        can('crear-suscripcion');

        $resultado = $this->suscripcionService->enviarBorradorAAprobacion($id);
        if (($resultado['mensaje'] ?? '') !== 'ok') {
            return back()->with('error', $resultado['errores'] ?? 'No se pudo enviar.');
        }

        return redirect()
            ->route('consultar_suscripcion')
            ->with('mensaje', 'Borrador enviado a aprobación.');
    }

    public function aprobacion()
    {
        can('aprobar-suscripcion');

        $pendientes = $this->suscripcionService->pendientesAprobacionParaUsuario((int) Auth::id());

        return view('compras.suscripcion.aprobacion', [
            'pendientes' => $pendientes,
            'impactos' => $pendientes->mapWithKeys(fn ($mov) => [
                (int) $mov->id => $this->suscripcionService->impactoPresupuestario($mov->ordencompras),
            ]),
        ]);
    }

    public function aprobar(Request $request, int $movimientoId)
    {
        can('aprobar-suscripcion');

        $resultado = $this->suscripcionService->aprobar(
            $movimientoId,
            (int) Auth::id(),
            $request->input('observacion')
        );

        if (! ($resultado['ok'] ?? false)) {
            return back()->with('error', $resultado['mensaje'] ?? 'No se pudo autorizar.');
        }

        return redirect()
            ->route('aprobacion_suscripcion')
            ->with('mensaje', $resultado['mensaje']);
    }

    public function rechazar(Request $request, int $movimientoId)
    {
        can('aprobar-suscripcion');

        $resultado = $this->suscripcionService->rechazar(
            $movimientoId,
            (int) Auth::id(),
            (string) $request->input('observacion', '')
        );

        if (! ($resultado['ok'] ?? false)) {
            return back()->with('error', $resultado['mensaje'] ?? 'No se pudo rechazar.');
        }

        return redirect()
            ->route('aprobacion_suscripcion')
            ->with('mensaje', $resultado['mensaje']);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Compras\Ordencompra>  $filas
     * @param  array<string, mixed>  $filtros
     * @param  array<string, float|int>  $kpis
     */
    private function descargarPdfListado($filas, array $filtros, array $kpis): BinaryFileResponse
    {
        $html = view('exports.compras.suscripcion_listado_pdf', compact('filas', 'filtros', 'kpis'))->render();

        $directorio = storage_path('pdf/listados');
        if (! is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }
        $archivo = $directorio.'/suscripciones_'.now()->format('Ymd_His').'.pdf';

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html)->save($archivo);

        return response()->download($archivo)->deleteFileAfterSend(true);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Suscripcion_Tarjeta>
     */
    private function tarjetasActivas()
    {
        return Suscripcion_Tarjeta::query()
            ->where('activo', true)
            ->orderBy('etiqueta')
            ->get(['id', 'etiqueta', 'ult4', 'empresa_id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(): array
    {
        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaDefault = optional($empresaQuery->first())->id;

        return [
            'empresa_query' => $empresaQuery,
            'empresa_default' => $empresaDefault,
            'moneda_query' => $this->monedaRepository->all(),
            'tarjeta_query' => $this->tarjetasActivas(),
            'tolerancia_default' => SuscripcionSupport::TOLERANCIA_DEFAULT_PCT,
            'aprobadores_mapa' => $this->aprobadorService->listar(null)
                ->filter(fn (array $f) => (int) ($f['usuario_id'] ?? 0) > 0)
                ->mapWithKeys(fn (array $f) => [
                    ((int) $f['empresa_id']).':'.((int) $f['centrocosto_id']) => (string) $f['usuario_nombre'],
                ])
                ->all(),
            'areas' => SuscripcionSupport::areas(),
        ];
    }
}
