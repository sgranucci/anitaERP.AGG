<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Configuracion\LibroIvaDigitalService;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LibroIvaDigitalController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'libro_iva_digital';

    public function __construct(
        private readonly LibroIvaDigitalService $libroIvaDigitalService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-libro-iva-digital');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->filtrosDesdeRequest($request, $empresaQuery);
        $consultado = $request->boolean('consultar');
        $resultado = null;

        if ($consultado && $filtros['empresa_id'] && $filtros['periodo']) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_id' => $filtros['empresa_id'],
                'periodo' => $filtros['periodo'],
            ]);
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');
            $resultado = $this->libroIvaDigitalService->generar(
                (int) $filtros['empresa_id'],
                (int) $filtros['anio'],
                (int) $filtros['mes'],
            );
        }

        return view('configuracion.libro_iva_digital.index', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'consultado' => $consultado,
            'resultado' => $resultado,
        ]);
    }

    public function exportar(Request $request): BinaryFileResponse
    {
        can('exportar-libro-iva-digital');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->filtrosDesdeRequest($request, $empresaQuery);

        if (! $filtros['empresa_id'] || ! $filtros['periodo']) {
            abort(422, 'Debe indicar empresa y período.');
        }

        $resultado = $this->libroIvaDigitalService->generar(
            (int) $filtros['empresa_id'],
            (int) $filtros['anio'],
            (int) $filtros['mes'],
        );

        $zipPath = $this->libroIvaDigitalService->crearZipDescarga($resultado, (int) $filtros['empresa_id']);

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend(false);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>|null  $empresaQuery
     * @return array{empresa_id:?int, periodo:?string, anio:?int, mes:?int}
     */
    private function filtrosDesdeRequest(Request $request, $empresaQuery = null): array
    {
        $empresaId = $this->enteroOpcional($request->input('empresa_id'));
        $periodo = trim((string) $request->input('periodo', ''));

        if ($empresaId === null && $periodo === '' && $empresaQuery !== null) {
            $prefs = ReportePreferenciasUsuario::leer(self::PREFERENCIAS_CLAVE);
            $empresaId = $this->enteroOpcional($prefs['empresa_id'] ?? null);
            $periodo = trim((string) ($prefs['periodo'] ?? ''));
        }

        if ($empresaId === null && $empresaQuery !== null && $empresaQuery->count() === 1) {
            $empresaId = (int) $empresaQuery->first()->id;
        }

        if ($empresaId !== null && $empresaQuery !== null) {
            $permitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($permitidos !== [] && ! in_array($empresaId, $permitidos, true)) {
                $empresaId = null;
            }
        }

        if ($periodo === '') {
            $periodo = date('Y-m', strtotime('first day of last month'));
        }

        $anio = null;
        $mes = null;
        if (preg_match('/^(\d{4})-(\d{2})$/', $periodo, $m)) {
            $anio = (int) $m[1];
            $mes = (int) $m[2];
        }

        return [
            'empresa_id' => $empresaId,
            'periodo' => $periodo,
            'anio' => $anio,
            'mes' => $mes,
        ];
    }

    private function enteroOpcional(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (int) $valor;
    }
}
