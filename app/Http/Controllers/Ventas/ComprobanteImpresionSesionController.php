<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Ventas\Pedido;
use App\Models\Ventas\Remito;
use App\Models\Ventas\Venta;
use App\Repositories\Configuracion\SalidaRepositoryInterface;
use App\Services\Ventas\ComprobanteImpresionSesionService;
use App\Support\Ventas\ComprobanteImpresionFormulario;
use App\Support\Ventas\ComprobanteImpresionSalidaUsuarioSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ComprobanteImpresionSesionController extends Controller
{
    public function __construct(
        private ComprobanteImpresionSesionService $sesionService,
        private SalidaRepositoryInterface $salidaRepository,
    ) {
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
        ));
    }

    public function pedido(Request $request, int $id)
    {
        $pedido = Pedido::query()->findOrFail($id);
        $pack = $request->boolean('pack');

        return $this->mostrar($request, $this->sesionService->armarDesdePedido(
            $pedido,
            $this->modo($request),
            $this->soloFormulario($request, $pack, ComprobanteImpresionFormulario::PEDIDO),
            $pack
        ));
    }

    public function remito(Request $request, int $id)
    {
        $remito = Remito::query()->findOrFail($id);
        $pack = $request->boolean('pack');

        return $this->mostrar($request, $this->sesionService->armarDesdeRemito(
            $remito,
            $this->modo($request),
            $this->soloFormulario($request, $pack, ComprobanteImpresionFormulario::REMITO),
            $pack
        ));
    }

    public function ejecutar(Request $request)
    {
        try {
            $sesion = $this->resolverSesionParaEjecutar($request);
        } catch (\InvalidArgumentException $e) {
            return back()->with('errores', [$e->getMessage()]);
        }
        if (! is_array($sesion) || empty($sesion['pack'])) {
            return back()->with('errores', ['No hay una sesión de impresión armada.']);
        }

        try {
            $resultado = $this->sesionService->ejecutar($sesion, $this->soloPackIdxs($request, $sesion));
        } catch (\InvalidArgumentException $e) {
            return $this->redirectSesion($sesion)->with('errores', [$e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('ventas.impresion_sesion.ejecutar', ['error' => $e->getMessage()]);

            return $this->redirectSesion($sesion)
                ->with('errores', ['No se pudo ejecutar la sesión: '.$e->getMessage()]);
        }

        $request->session()->put('comprobante_impresion_sesion', $sesion);
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
    private function mostrar(Request $request, array $sesion)
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
        $faltanteImpresora = ! empty($sesion['faltante_impresora_papel']);
        $autoEjecutar = $forzarRegen && ! empty($sesion['pack']) && ! $faltanteImpresora;
        $impresora = $sesion['impresora_usuario'] ?? [];
        $programaSeteo = ComprobanteImpresionSalidaUsuarioSupport::programaUnificado();

        return view('ventas.programa_impresion.sesion', [
            'sesion' => $sesion,
            'resultado' => $resultado,
            'programaSeteo' => $programaSeteo,
            'autoEjecutar' => $autoEjecutar,
            'volverUrl' => $this->urlVolver($sesion, $request),
            'salidasUsuario' => $this->salidaRepository->paraProgramaSeteo(
                $programaSeteo,
                isset($impresora['salida_id']) ? (int) $impresora['salida_id'] : null
            ),
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
        } elseif (($sesion['origen_tipo'] ?? '') !== 'FACTURA') {
            $params['pack'] = 1;
        }

        return match ($sesion['origen_tipo'] ?? '') {
            'PEDIDO' => redirect()->route('sesion_impresion_pedido', $params),
            'REMITO' => redirect()->route('sesion_impresion_remito', $params),
            default => redirect()->route('sesion_impresion_factura', $params),
        };
    }

    /**
     * @param  array<string, mixed>  $sesion
     * @return list<int>|null
     */
    private function soloPackIdxs(Request $request, array $sesion): ?array
    {
        if (! $request->exists('pack_idx')) {
            return null;
        }
        $raw = $request->input('pack_idx');
        if (! is_array($raw)) {
            if ($raw === null || $raw === '') {
                return null;
            }
            $raw = [$raw];
        }
        $idxs = [];
        foreach ($raw as $valor) {
            if ($valor === '' || $valor === null) {
                continue;
            }
            $idxs[] = (int) $valor;
        }

        return $idxs;
    }

    private function modo(Request $request): string
    {
        return strtoupper((string) $request->query('modo', 'OPERATIVO')) === 'CONSULTA'
            ? 'CONSULTA'
            : 'OPERATIVO';
    }

    private function soloFormulario(Request $request, bool $pack, string $default): ?string
    {
        if ($request->has('solo_formulario')) {
            $solo = trim((string) $request->query('solo_formulario', ''));

            return $solo !== '' ? $solo : null;
        }

        return $pack ? null : $default;
    }

    /**
     * El POST de ejecutar no lleva el id en la URL: si solo se lee la sesión PHP,
     * un tab anterior (u otro GET del listado de pedidos) puede dejar la factura vieja.
     * El formulario manda origen_tipo + origen_id y se rearma el pack desde ahí.
     *
     * @return array<string, mixed>
     */
    private function resolverSesionParaEjecutar(Request $request): array
    {
        $tipo = strtoupper(trim((string) $request->input('origen_tipo', '')));
        $id = (int) $request->input('origen_id', 0);
        $sesionSesion = $request->session()->get('comprobante_impresion_sesion');
        if ($id <= 0 || ! in_array($tipo, ['FACTURA', 'PEDIDO', 'REMITO'], true)) {
            return is_array($sesionSesion) ? $sesionSesion : [];
        }

        $modo = strtoupper((string) $request->input('modo', 'OPERATIVO')) === 'CONSULTA'
            ? 'CONSULTA'
            : 'OPERATIVO';
        $solo = $request->input('solo_formulario');
        $solo = is_string($solo) && $solo !== '' ? $solo : null;

        if (
            is_array($sesionSesion)
            && (($sesionSesion['origen_tipo'] ?? '') !== $tipo
                || (int) ($sesionSesion['origen_id'] ?? 0) !== $id)
        ) {
            Log::warning('ventas.impresion_sesion.sesion_desfasada', [
                'form_tipo' => $tipo,
                'form_id' => $id,
                'sesion_tipo' => $sesionSesion['origen_tipo'] ?? null,
                'sesion_id' => $sesionSesion['origen_id'] ?? null,
            ]);
        }

        $pack = $request->boolean('pack') || $solo === null;
        $sesion = match ($tipo) {
            'PEDIDO' => $this->sesionService->armarDesdePedido(
                Pedido::query()->findOrFail($id),
                $modo,
                $solo,
                $pack
            ),
            'REMITO' => $this->sesionService->armarDesdeRemito(
                Remito::query()->findOrFail($id),
                $modo,
                $solo,
                $pack
            ),
            default => $this->sesionService->armarDesdeVenta(
                Venta::query()->with(['puntoventas', 'pedidos', 'remitos'])->findOrFail($id),
                $modo,
                $solo,
            ),
        };
        $request->session()->put('comprobante_impresion_sesion', $sesion);

        return $sesion;
    }
}
