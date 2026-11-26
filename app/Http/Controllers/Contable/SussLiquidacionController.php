<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Services\Contable\Suss\SussLiquidacionService;
use App\Services\Contable\Suss\SussSolicitudpagoService;
use App\Support\Contable\Suss\SussListadoFiltros;
use App\Support\Contable\Suss\SussLiquidacionTipoSupport;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Liquidación completa (ZIP F2004 + PDF) y solicitud de pago del VEP SUSS.
 */
class SussLiquidacionController extends Controller
{
    public function __construct(
        private readonly SussLiquidacionService $liquidacionService,
        private readonly SussSolicitudpagoService $solicitudpagoService,
    ) {
        $this->middleware('auth');
    }

    /**
     * ZIP con F2004 de la quincena + PDF de liquidación. Corre en iframe (descarga).
     */
    public function liquidacion(Request $request)
    {
        can('exportar-suss');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltros($request);
        $urlSuss = route('suss', array_merge(SussListadoFiltros::paraQueryString($filtros), ['consultar' => 1]));

        if (! SussListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return $this->respuestaEnIframe(route('suss'), 'Indique empresa y período para la liquidación SUSS.');
        }

        try {
            $resultado = $this->liquidacionService->generarCompleta($filtros);
        } catch (\Throwable $e) {
            report($e);

            return $this->respuestaEnIframe($urlSuss, 'No se pudo generar la liquidación: '.$e->getMessage());
        }

        $rutaZip = (string) ($resultado['ruta_zip'] ?? '');
        if ($rutaZip === '' || ! is_file($rutaZip)) {
            return $this->respuestaEnIframe($urlSuss, 'La liquidación no generó el ZIP (F2004 + PDF).');
        }

        return response()->download($rutaZip, (string) $resultado['nombre_zip'])->deleteFileAfterSend(true);
    }

    public function previewSolicitudPago(Request $request)
    {
        if (! can('exportar-suss', false) || ! can('crear-solicitud-pago', false)) {
            return response()->json(['error' => 'Sin permiso.', 'puede_generar' => false], 403);
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        try {
            $filtros = $this->resolverFiltros($request);

            return response()->json($this->solicitudpagoService->preview($filtros));
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage(), 'puede_generar' => false], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'No se pudo armar la vista previa.', 'puede_generar' => false], 500);
        }
    }

    public function generarSolicitudPago(Request $request)
    {
        can('exportar-suss');
        if (! can('crear-solicitud-pago', false)) {
            can('crear-solicitud-pago');
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $validated = $request->validate([
            'empresa_id' => ['required', 'integer'],
            'tipo_liquidacion' => ['required', 'string'],
            'monto_vep' => ['required', 'string', 'max:30'],
            'fecha_vencimiento' => ['required', 'string'],
            'observacion' => ['nullable', 'string', 'max:160'],
            'generar_igual' => ['nullable'],
            'confirmo_diferencia' => ['nullable'],
            'adjunto_vep' => ['required', 'file', 'max:10240'],
            'adjunto_f996' => ['nullable', 'file', 'max:10240'],
            'adjuntos_sire.*' => ['nullable', 'file', 'max:10240'],
        ], [], [
            'monto_vep' => 'monto del VEP',
            'fecha_vencimiento' => 'vencimiento',
            'adjunto_vep' => 'VEP',
        ]);

        $filtros = $this->resolverFiltros($request);
        $redirectQuery = array_merge(SussListadoFiltros::paraQueryString($filtros), ['consultar' => 1]);

        $adjuntos = [
            'vep' => $request->file('adjunto_vep') instanceof UploadedFile ? $request->file('adjunto_vep') : null,
            'f996' => $request->file('adjunto_f996') instanceof UploadedFile ? $request->file('adjunto_f996') : null,
            'sire' => array_values(array_filter(
                (array) $request->file('adjuntos_sire', []),
                static fn ($f) => $f instanceof UploadedFile,
            )),
        ];

        try {
            $resultado = $this->solicitudpagoService->confirmar(
                $filtros,
                SussLiquidacionTipoSupport::parseImporte($validated['monto_vep']),
                (string) $validated['fecha_vencimiento'],
                isset($validated['observacion']) ? (string) $validated['observacion'] : null,
                $request->boolean('generar_igual'),
                $request->boolean('confirmo_diferencia'),
                $adjuntos,
            );

            return redirect()
                ->route('suss', $redirectQuery)
                ->with(
                    'mensaje',
                    'Solicitud de pago #'.(int) $resultado['codigo'].' generada (liquidación SUSS adjunta) y enviada a aprobación.'
                );
        } catch (RuntimeException $e) {
            return redirect()
                ->route('suss', $redirectQuery)
                ->with('mensaje_error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('suss', $redirectQuery)
                ->with('mensaje_error', 'No se pudo generar la solicitud de pago SUSS.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltros(Request $request): array
    {
        $filtros = SussListadoFiltros::resolverDesdeRequest($request);

        $tipoRaw = (string) $request->input('tipo_liquidacion', '');
        if (trim($tipoRaw) === '') {
            $tipoRaw = SussLiquidacionTipoSupport::tipoParaLiquidacion((int) $filtros['liquidacion']);
        }
        $filtros['tipo_liquidacion'] = SussLiquidacionTipoSupport::normalizarTipo($tipoRaw);
        $filtros['saldo_favor_anterior'] = (string) $request->input('saldo_favor_anterior', '');
        $filtros['pago_cuenta'] = (string) $request->input('pago_cuenta', '');
        $filtros['pago_cuenta_fecha'] = (string) $request->input('pago_cuenta_fecha', '');

        return $filtros;
    }

    /**
     * Si la liquidación corre en iframe, un redirect HTML queda invisible: devolvemos una página
     * mínima que lleva el mensaje a la ventana padre (mismo patrón que SICORE).
     */
    private function respuestaEnIframe(string $urlDestino, string $mensajeError)
    {
        session()->flash('mensaje-error', $mensajeError);
        $urlJs = json_encode($urlDestino);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>SUSS</title></head><body>'
            .'<script>try{if(window.parent&&window.parent!==window){window.parent.location='.$urlJs.';}'
            .'else{window.location='.$urlJs.';}}catch(e){window.location='.$urlJs.';}</script>'
            .'Error: '.e($mensajeError)
            .'</body></html>';

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
