<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\SuscripcionAprobadorExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\SuscripcionAprobadorService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * ABM de aprobadores del circuito de suscripciones: un gerente por centro de costo.
 */
class SuscripcionAprobadorController extends Controller
{
    public function __construct(
        private SuscripcionAprobadorService $aprobadorService,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('configurar-suscripcion');

        $empresaId = (int) $request->input('empresa_id');
        $filas = $this->aprobadorService->listar($empresaId ?: null);

        $diagnostico = $empresaId > 0
            ? $this->aprobadorService->diagnostico($empresaId)
            : ['arbol' => null, 'configurados' => $filas->count(), 'sin_gerente' => []];

        return view('compras.suscripcion.aprobador.index', [
            'filas' => $filas,
            'empresa_id' => $empresaId,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'diagnostico' => $diagnostico,
            'filtrosQuery' => $this->filtrosRetorno($request),
        ]);
    }

    /**
     * Mismo listado en PDF, XLS o CSV.
     */
    public function exportar(Request $request, string $formato)
    {
        can('configurar-suscripcion');

        $filtros = $this->filtrosRetorno($request);
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $filas = $this->aprobadorService->listar($empresaId ?: null);

        return match (strtoupper($formato)) {
            'PDF' => $this->descargarPdf($filas, $filtros),
            'CSV' => (new SuscripcionAprobadorExport)
                ->parametros($filas, $filtros)
                ->download('aprobadores_suscripcion.csv', Excel::CSV),
            default => (new SuscripcionAprobadorExport)
                ->parametros($filas, $filtros)
                ->download('aprobadores_suscripcion.xlsx'),
        };
    }

    public function crear(Request $request)
    {
        can('configurar-suscripcion');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtrosQuery = $this->filtrosRetorno($request);
        // Default del combo: filtro del listado si vino; si no, la primera empresa.
        $empresaId = (int) ($filtrosQuery['empresa_id'] ?? optional($empresaQuery->first())->id);

        return view('compras.suscripcion.aprobador.crear', [
            'nivel' => null,
            'empresa_id' => $empresaId,
            'empresa_query' => $empresaQuery,
            'filtrosQuery' => $filtrosQuery,
        ]);
    }

    public function guardar(Request $request)
    {
        can('configurar-suscripcion');

        $data = $this->validar($request);

        try {
            $this->aprobadorService->crear(
                (int) $data['empresa_id'],
                (int) $data['centrocosto_id'],
                (int) $data['usuario_id']
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('aprobadores_suscripcion', $this->filtrosRetorno($request))
            ->with('mensaje', 'Aprobador creado.');
    }

    public function editar(Request $request, int $id)
    {
        can('configurar-suscripcion');

        $nivel = $this->aprobadorService->findNivel($id);

        return view('compras.suscripcion.aprobador.crear', [
            'nivel' => $nivel,
            'empresa_id' => (int) $nivel->arbolaprobaciones->empresa_id,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'filtrosQuery' => $this->filtrosRetorno($request),
        ]);
    }

    public function actualizar(Request $request, int $id)
    {
        can('configurar-suscripcion');

        $data = $this->validar($request, $id);
        $nivel = $this->aprobadorService->findNivel($id);

        try {
            $this->aprobadorService->actualizar(
                $id,
                (int) $data['centrocosto_id'],
                (int) $data['usuario_id']
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('aprobadores_suscripcion', $this->filtrosRetorno($request))
            ->with('mensaje', 'Aprobador actualizado.');
    }

    public function eliminar(Request $request, int $id)
    {
        can('configurar-suscripcion');

        $this->aprobadorService->findNivel($id);
        $filtrosQuery = $this->filtrosRetorno($request);
        $this->aprobadorService->eliminar($id);

        return redirect()
            ->route('aprobadores_suscripcion', $filtrosQuery)
            ->with('mensaje', 'Aprobador eliminado.');
    }

    /**
     * Query del listado a preservar (solo query string: no mezclar con campos del form).
     *
     * @return array<string, int>
     */
    private function filtrosRetorno(Request $request): array
    {
        $empresaId = (int) $request->query('empresa_id', 0);

        return array_filter(['empresa_id' => $empresaId > 0 ? $empresaId : null]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $filas
     * @param  array<string, mixed>  $filtros
     */
    private function descargarPdf($filas, array $filtros): BinaryFileResponse
    {
        $html = view('exports.compras.suscripcion_aprobador_pdf', compact('filas', 'filtros'))->render();

        $directorio = storage_path('pdf/listados');
        if (! is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }
        $archivo = $directorio.'/aprobadores_suscripcion_'.now()->format('Ymd_His').'.pdf';

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html)->save($archivo);

        return response()->download($archivo)->deleteFileAfterSend(true);
    }

    /** @return array{empresa_id: int, centrocosto_id: int, usuario_id: int} */
    private function validar(Request $request, ?int $id = null): array
    {
        $reglas = [
            'centrocosto_id' => 'required|integer|exists:centrocosto,id',
            'usuario_id' => 'required|integer|exists:usuario,id',
        ];

        // En edición la empresa viene del árbol; en alta es obligatoria.
        if ($id === null) {
            $reglas['empresa_id'] = 'required|integer|exists:empresa,id';
        } else {
            $reglas['empresa_id'] = 'nullable|integer|exists:empresa,id';
        }

        return $request->validate($reglas);
    }
}
