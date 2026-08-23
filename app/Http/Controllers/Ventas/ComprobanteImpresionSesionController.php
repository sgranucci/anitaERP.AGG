<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Ventas\Pedido;
use App\Models\Ventas\Remito;
use App\Models\Ventas\Venta;
use App\Services\Ventas\ComprobanteImpresionSesionService;
use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use App\Support\Ventas\ComprobanteImpresionFormulario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ComprobanteImpresionSesionController extends Controller
{
    public function __construct(private ComprobanteImpresionSesionService $sesionService)
    {
        $this->middleware('auth');
    }

    public function factura(Request $request, int $id)
    {
        $venta = Venta::query()->with(['puntoventas', 'gastronomiaEmision', 'estacionamientoEmision'])->findOrFail($id);
        if ($venta->gastronomiaEmision || $venta->estacionamientoEmision) {
            return redirect()->route('lista_una_factura', $id);
        }

        return $this->mostrar($request, $this->sesionService->armarDesdeVenta(
            $venta,
            $this->modo($request),
            $request->query('solo_formulario')
        ), SeteoSalidaProgramaSupport::VENTAS_FACTURA);
    }

    public function pedido(Request $request, int $id)
    {
        $pedido = Pedido::query()->findOrFail($id);

        return $this->mostrar($request, $this->sesionService->armarDesdePedido(
            $pedido,
            $this->modo($request),
            $request->query('solo_formulario', ComprobanteImpresionFormulario::PEDIDO),
            $request->boolean('pack')
        ), SeteoSalidaProgramaSupport::VENTAS_PEDIDO);
    }

    public function remito(Request $request, int $id)
    {
        $remito = Remito::query()->findOrFail($id);

        return $this->mostrar($request, $this->sesionService->armarDesdeRemito(
            $remito,
            $this->modo($request),
            $request->query('solo_formulario', ComprobanteImpresionFormulario::REMITO),
            $request->boolean('pack')
        ), SeteoSalidaProgramaSupport::VENTAS_REMITO);
    }

    public function ejecutar(Request $request)
    {
        $sesion = $request->session()->get('comprobante_impresion_sesion');
        if (! is_array($sesion) || empty($sesion['pack'])) {
            return back()->with('errores', ['No hay una sesión de impresión armada.']);
        }

        try {
            $resultado = $this->sesionService->ejecutar($sesion);
        } catch (\Throwable $e) {
            Log::error('ventas.impresion_sesion.ejecutar', ['error' => $e->getMessage()]);

            return $this->redirectSesion($sesion)
                ->with('errores', ['No se pudo ejecutar la sesión: '.$e->getMessage()]);
        }

        $request->session()->put('comprobante_impresion_sesion_resultado', $resultado);

        return $this->redirectSesion($sesion)->with('mensaje', 'Sesión de impresión ejecutada.');
    }

    public function descargar(Request $request)
    {
        $resultado = $request->session()->get('comprobante_impresion_sesion_resultado');
        $ruta = is_array($resultado) ? ($resultado['pdf_sesion'] ?? null) : null;
        if (! $ruta || ! is_file($ruta)) {
            return back()->with('errores', ['No hay PDF de sesión para descargar. Ejecute primero la impresión.']);
        }

        return response()->download($ruta, basename($ruta), [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * @param  array<string, mixed>  $sesion
     */
    private function mostrar(Request $request, array $sesion, string $programaSeteo)
    {
        $request->session()->put('comprobante_impresion_sesion', $sesion);
        $forzarRegen = $request->boolean('auto');
        $resultado = $forzarRegen ? null : $request->session()->get('comprobante_impresion_sesion_resultado');
        if ($forzarRegen) {
            $request->session()->forget('comprobante_impresion_sesion_resultado');
        } elseif (
            is_array($resultado)
            && (
                ($resultado['origen_tipo'] ?? null) !== ($sesion['origen_tipo'] ?? null)
                || (int) ($resultado['origen_id'] ?? 0) !== (int) ($sesion['origen_id'] ?? 0)
            )
        ) {
            $resultado = null;
            $request->session()->forget('comprobante_impresion_sesion_resultado');
        }
        $autoEjecutar = $forzarRegen && ! empty($sesion['pack']);

        return view('ventas.programa_impresion.sesion', [
            'sesion' => $sesion,
            'resultado' => $resultado,
            'programaSeteo' => $programaSeteo,
            'autoEjecutar' => $autoEjecutar,
            'volverUrl' => $this->urlVolver($sesion, $request),
        ]);
    }

    /**
     * @param  array<string, mixed>  $sesion
     */
    private function urlVolver(array $sesion, Request $request): string
    {
        $retorno = trim((string) $request->query('retorno', ''));
        if ($retorno !== '' && str_starts_with($retorno, '/') && ! str_starts_with($retorno, '//')) {
            return url($retorno);
        }

        return match ($sesion['origen_tipo'] ?? '') {
            'PEDIDO' => route('pedido'),
            'REMITO' => route('remito'),
            default => route('factura'),
        };
    }

    /**
     * @param  array<string, mixed>  $sesion
     */
    private function redirectSesion(array $sesion)
    {
        $params = ['id' => (int) ($sesion['origen_id'] ?? 0)];
        if (($sesion['modo'] ?? '') === 'CONSULTA') {
            $params['modo'] = 'CONSULTA';
        }
        if (! empty($sesion['solo_formulario'])) {
            $params['solo_formulario'] = $sesion['solo_formulario'];
        }

        return match ($sesion['origen_tipo'] ?? '') {
            'PEDIDO' => redirect()->route('sesion_impresion_pedido', $params),
            'REMITO' => redirect()->route('sesion_impresion_remito', $params),
            default => redirect()->route('sesion_impresion_factura', $params),
        };
    }

    private function modo(Request $request): string
    {
        return strtoupper((string) $request->query('modo', 'OPERATIVO')) === 'CONSULTA'
            ? 'CONSULTA'
            : 'OPERATIVO';
    }
}
