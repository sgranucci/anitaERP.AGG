<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\LibroIvaDigitalService;
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
            foreach (['por_fecha_jornada', 'prorrateo_cf_global', 'completar_compras_anita'] as $campo) {
                ReportePreferenciasUsuario::persistirBool(
                    self::PREFERENCIAS_CLAVE,
                    $campo,
                    (bool) $filtros[$campo],
                );
            }
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');
            $resultado = $this->libroIvaDigitalService->generar(
                (int) $filtros['empresa_id'],
                (int) $filtros['anio'],
                (int) $filtros['mes'],
                $this->opcionesDesdeFiltros($filtros),
            );
        }

        return view('contable.libro_iva_digital.index', [
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
            $this->opcionesDesdeFiltros($filtros),
        );

        $zipPath = $this->libroIvaDigitalService->crearZipDescarga($resultado, (int) $filtros['empresa_id']);

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend(false);
    }

    public function exportarIvaSimple(Request $request): BinaryFileResponse
    {
        can('exportar-libro-iva-digital');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->filtrosDesdeRequest($request, $empresaQuery);

        if (! $filtros['empresa_id'] || ! $filtros['periodo']) {
            abort(422, 'Debe indicar empresa y período.');
        }

        $resultado = $this->libroIvaDigitalService->generarIvaSimple(
            (int) $filtros['empresa_id'],
            (int) $filtros['anio'],
            (int) $filtros['mes'],
            $this->opcionesDesdeFiltros($filtros),
        );

        $zipPath = $this->libroIvaDigitalService->crearZipIvaSimple($resultado, (int) $filtros['empresa_id']);

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend(false);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{por_fecha_jornada: bool, prorrateo_cf_global: bool, completar_compras_anita: bool}
     */
    private function opcionesDesdeFiltros(array $filtros): array
    {
        return [
            'por_fecha_jornada' => (bool) ($filtros['por_fecha_jornada'] ?? false),
            'prorrateo_cf_global' => (bool) ($filtros['prorrateo_cf_global'] ?? false),
            'completar_compras_anita' => (bool) ($filtros['completar_compras_anita'] ?? true),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>|null  $empresaQuery
     * @return array{
     *     empresa_id:?int,
     *     periodo:?string,
     *     anio:?int,
     *     mes:?int,
     *     por_fecha_jornada:int,
     *     prorrateo_cf_global:int,
     *     completar_compras_anita:int
     * }
     */
    private function filtrosDesdeRequest(Request $request, $empresaQuery = null): array
    {
        $empresaId = $this->enteroOpcional($request->input('empresa_id'));
        $periodo = trim((string) $request->input('periodo', ''));
        $mesReq = $request->input('mes');
        $anioReq = $request->input('anio');
        $tieneMesAnio = $mesReq !== null && $mesReq !== '' && $anioReq !== null && $anioReq !== '';

        if ($empresaId === null && $periodo === '' && ! $tieneMesAnio && $empresaQuery !== null) {
            $empresaId = ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS_CLAVE);
            $periodo = ReportePreferenciasUsuario::leerPeriodo(self::PREFERENCIAS_CLAVE);
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

        $anio = null;
        $mes = null;

        if ($tieneMesAnio) {
            $mes = max(1, min(12, (int) $mesReq));
            $anio = max(2000, min(2100, (int) $anioReq));
            $periodo = sprintf('%04d-%02d', $anio, $mes);
        } elseif ($periodo !== '') {
            try {
                $periodo = normalizarPeriodoParaUrl($periodo);
            } catch (\InvalidArgumentException) {
                $periodo = '';
            }
        }

        if ($periodo === '') {
            $periodo = date('Y-m', strtotime('first day of last month'));
        }

        if ($anio === null || $mes === null) {
            if (preg_match('/^(\d{4})-(\d{2})$/', $periodo, $m)) {
                $anio = (int) $m[1];
                $mes = (int) $m[2];
            }
        }

        return [
            'empresa_id' => $empresaId,
            'periodo' => $periodo,
            'anio' => $anio,
            'mes' => $mes,
            'por_fecha_jornada' => $this->boolFiltro($request, 'por_fecha_jornada', true) ? 1 : 0,
            'prorrateo_cf_global' => $this->boolFiltro($request, 'prorrateo_cf_global', false) ? 1 : 0,
            'completar_compras_anita' => $this->boolFiltro($request, 'completar_compras_anita', true) ? 1 : 0,
        ];
    }

    private function boolFiltro(Request $request, string $campo, bool $default): bool
    {
        if ($request->exists($campo)) {
            return $request->boolean($campo);
        }

        return ReportePreferenciasUsuario::leerBool(self::PREFERENCIAS_CLAVE, $campo, $default);
    }

    private function enteroOpcional(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (int) $valor;
    }
}
