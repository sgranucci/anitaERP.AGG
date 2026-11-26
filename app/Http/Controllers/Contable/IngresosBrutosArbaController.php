<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Contable\Iibb_Presentacion_Config;
use App\Repositories\Contable\Iibb_Presentacion_ConfigRepositoryInterface;
use App\Services\Contable\IngresosBrutos\IngresosBrutosLiquidacionArbaService;
use App\Services\Contable\IngresosBrutos\IngresosBrutosReporteService;
use App\Services\Contable\IngresosBrutos\IngresosBrutosSolicitudpagoArbaService;
use App\Support\Contable\IngresosBrutos\IngresosBrutosListadoFiltros;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Acciones de la liquidación ARBA quincenal: recalcular con decisiones del operador,
 * generar liquidación (ZIP) y solicitud de pago del VEP.
 */
class IngresosBrutosArbaController extends Controller
{
    public function __construct(
        private readonly IngresosBrutosReporteService $reporteService,
        private readonly IngresosBrutosLiquidacionArbaService $liquidacionService,
        private readonly IngresosBrutosSolicitudpagoArbaService $solicitudService,
        private readonly Iibb_Presentacion_ConfigRepositoryInterface $configRepository,
    ) {
        $this->middleware('auth');
    }

    /**
     * Guarda las decisiones en sesión y vuelve a la consulta, que recalcula el neteo.
     */
    public function recalcular(Request $request)
    {
        can('listar-ingresos-brutos');

        $filtros = $this->filtrosDesdeRequest($request);
        if (! IngresosBrutosLiquidacionArbaService::esArbaLiquidable($filtros)) {
            return $this->volver($filtros, 'mensaje_error', 'La liquidación ARBA solo aplica a retenciones por quincena.');
        }

        session()->put(
            IngresosBrutosLiquidacionArbaService::claveDecisiones($filtros),
            $this->decisionesDesdeRequest($request),
        );

        return $this->volver($filtros, 'mensaje', 'Neteo recalculado.');
    }

    /**
     * Persiste devoluciones, neteos y la liquidación; devuelve el ZIP (TXT + PDF).
     */
    public function liquidar(Request $request)
    {
        can('exportar-ingresos-brutos');

        $filtros = $this->filtrosDesdeRequest($request);
        if (! IngresosBrutosLiquidacionArbaService::esArbaLiquidable($filtros)) {
            return $this->volver($filtros, 'mensaje_error', 'La liquidación ARBA solo aplica a retenciones por quincena.');
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        try {
            $reporte = $this->reporteService->generar($filtros);
            $res = $this->liquidacionService->generar(
                $filtros,
                $reporte,
                $this->decisionesDesdeRequest($request),
                auth()->id(),
            );
        } catch (RuntimeException $e) {
            return $this->volver($filtros, 'mensaje_error', $e->getMessage());
        }

        session()->put(
            IngresosBrutosLiquidacionArbaService::claveDecisiones($filtros),
            $this->decisionesDesdeRequest($request),
        );

        return response()->download($res['zip_path'], $res['nombre_zip'])->deleteFileAfterSend(true);
    }

    /**
     * Solicitud de pago del VEP con la liquidación vigente de la quincena.
     */
    public function solicitudPago(Request $request)
    {
        can('exportar-ingresos-brutos');
        can('crear-solicitud-pago');

        $validated = $request->validate([
            'fecha_vencimiento' => ['required', 'string'],
            'observacion' => ['nullable', 'string', 'max:160'],
            'generar_igual' => ['nullable'],
            'monto_vep' => ['required', 'numeric', 'min:0.01'],
            'confirmar_diferencia_vep' => ['nullable'],
            'adjuntos.*' => ['nullable', 'file', 'max:10240'],
        ], [], [
            'fecha_vencimiento' => 'vencimiento',
            'monto_vep' => 'VEP',
        ]);

        $filtros = $this->filtrosDesdeRequest($request);
        if (! IngresosBrutosLiquidacionArbaService::esArbaLiquidable($filtros)) {
            return $this->volver($filtros, 'mensaje_error', 'La solicitud ARBA solo aplica a retenciones por quincena.');
        }

        $config = $this->configArba($filtros);
        if (! $config instanceof Iibb_Presentacion_Config) {
            return $this->volver($filtros, 'mensaje_error', 'No hay configuración IIBB activa para ARBA.');
        }

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $periodo = substr((string) ($filtros['periodo'] ?? ''), 0, 6);
        $quincena = IngresosBrutosListadoFiltros::quincenaLote((int) ($filtros['liquidacion'] ?? 0));
        $liq = $this->liquidacionService->liquidacionVigente($empresaId, (int) $config->id, $periodo, $quincena);

        $adjuntos = [];
        foreach ((array) $request->file('adjuntos', []) as $file) {
            if ($file instanceof UploadedFile) {
                $adjuntos[] = $file;
            }
        }

        try {
            $resultado = $this->solicitudService->confirmar(
                $empresaId,
                $periodo,
                $quincena,
                $liq,
                (string) $validated['fecha_vencimiento'],
                isset($validated['observacion']) ? (string) $validated['observacion'] : null,
                $request->boolean('generar_igual'),
                (float) $validated['monto_vep'],
                $request->boolean('confirmar_diferencia_vep'),
                $adjuntos,
            );

            return $this->volver($filtros, 'mensaje', 'Solicitud de pago #'.(int) $resultado['codigo'].' generada y enviada a aprobación.');
        } catch (RuntimeException $e) {
            return $this->volver($filtros, 'mensaje_error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return $this->volver($filtros, 'mensaje_error', 'No se pudo generar la solicitud de pago ARBA.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosDesdeRequest(Request $request): array
    {
        return IngresosBrutosListadoFiltros::alinearProvincia(
            IngresosBrutosListadoFiltros::resolverDesdeRequest($request),
        );
    }

    private function configArba(array $filtros): ?Iibb_Presentacion_Config
    {
        return $this->configRepository->findActivoPorProvinciaTipo(
            (int) ($filtros['provincia_id'] ?? 0),
            IngresosBrutosListadoFiltros::tipoConfig((string) ($filtros['tipo'] ?? '')),
        );
    }

    /**
     * Decisiones del operador por devolución: proveedor (código), certificado, pendiente, confirmación.
     *
     * @return array<string, array<string, mixed>>
     */
    private function decisionesDesdeRequest(Request $request): array
    {
        $out = [];
        foreach ((array) $request->input('dec', []) as $movId => $dec) {
            if (! ctype_digit((string) $movId) || ! is_array($dec)) {
                continue;
            }
            $out[(string) $movId] = [
                'proveedor_id' => (int) ($dec['proveedor_id'] ?? 0),
                'cert' => preg_replace('/\D+/', '', (string) ($dec['cert'] ?? '')) ?? '',
                'pendiente' => ! empty($dec['pendiente']),
                'confirmo' => ! empty($dec['confirmo']),
            ];
        }

        return $out;
    }

    private function volver(array $filtros, string $clave, string $mensaje)
    {
        return redirect()
            ->route('ingresos_brutos', array_merge(IngresosBrutosListadoFiltros::paraQueryString($filtros), ['consultar' => 1]))
            ->with($clave, $mensaje);
    }
}
