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
use App\Support\Ventas\ComprobanteImpresionSesionUrlSupport;
use App\Support\Ventas\FacturaListadoFiltros;
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

    public function reparto(Request $request, int $transporteId)
    {
        can('listar-factura');

        $filtros = FacturaListadoFiltros::resolverDesdeRequest($request);

        try {
            $sesion = $this->sesionService->armarDesdeRepartoPorFiltros(
                $filtros,
                $transporteId,
                $this->modo($request)
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('factura', FacturaListadoFiltros::paraQueryString($filtros))
                ->with('errores', [$e->getMessage()]);
        }

        return $this->mostrar($request, $sesion);
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

    public function cot(Request $request, int $id)
    {
        can('procesar-cot-electronico');

        $remitoEnvioId = $request->integer('remito_envio_id') ?: null;

        try {
            $sesion = $this->sesionService->armarDesdeCotSesion(
                $id,
                $remitoEnvioId,
                $this->modo($request)
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('cot_electronico', array_filter(['sesion_id' => $id]))
                ->with('errores', [$e->getMessage()]);
        }

        return $this->mostrar($request, $sesion);
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

        if (! $request->boolean('enviar_impresora') && ! $request->boolean('solo_copia')) {
            try {
                $this->sesionService->asegurarPdfSesionPapel(
                    $sesion,
                    $this->soloPackIdxs($request, $sesion)
                );
            } catch (\Throwable $e) {
                Log::error('ventas.impresion_sesion.pdf', ['error' => $e->getMessage()]);

                return $this->redirectSesion($sesion)
                    ->with('errores', ['No se pudo armar el PDF: '.$e->getMessage()]);
            }

            $request->session()->put('comprobante_impresion_sesion', $sesion);
            $request->session()->forget('comprobante_impresion_sesion_resultado');

            return $this->redirectSesion($sesion)
                ->with('mensaje', 'PDF listo. No se envió a la impresora.');
        }

        try {
            $resultado = $this->sesionService->ejecutar(
                $sesion,
                $this->soloPackIdxs($request, $sesion),
                $request->boolean('solo_copia')
            );
        } catch (\InvalidArgumentException $e) {
            return $this->redirectSesion($sesion)->with('errores', [$e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('ventas.impresion_sesion.ejecutar', ['error' => $e->getMessage()]);

            return $this->redirectSesion($sesion)
                ->with('errores', ['No se pudo ejecutar la sesión: '.$e->getMessage()]);
        }

        $request->session()->put('comprobante_impresion_sesion', $sesion);
        $request->session()->put('comprobante_impresion_sesion_resultado', $resultado);

        return $this->redirectSesion($sesion)->with('mensaje', 'Papel y NAS se envían en segundo plano. La pantalla ya puede usarse.');
    }

    public function descargar(Request $request)
    {
        $sesion = $request->session()->get('comprobante_impresion_sesion');
        if (! is_array($sesion) || empty($sesion['pack'])) {
            return back()->with('errores', ['No hay una sesión de impresión para descargar.']);
        }

        try {
            $ruta = $this->sesionService->asegurarPdfSesionPapel(
                $sesion,
                $this->soloPackIdxs($request, $sesion)
            );
        } catch (\Throwable $e) {
            Log::error('ventas.impresion_sesion.descargar', ['error' => $e->getMessage()]);

            return back()->with('errores', ['No se pudo armar el PDF: '.$e->getMessage()]);
        }
        if (! $ruta || ! is_file($ruta)) {
            return back()->with('errores', ['No hay copias de papel para armar el PDF.']);
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
        $retorno = $this->resolverRetornoPath($request, $sesion);
        if ($retorno !== '') {
            $sesion['retorno'] = $retorno;
        }
        $request->session()->put('comprobante_impresion_sesion', $sesion);
        $autoPdf = $request->boolean('pdf');
        $esLote = ($sesion['origen_tipo'] ?? '') === 'REPARTO';
        $enviarImpresora = $esLote ? true : $this->enviarImpresoraDesdeRequest($request);
        $forzarRegen = $request->boolean('auto')
            || $autoPdf
            || $request->boolean('elegir');
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
        $autoEjecutar = ! $esLote
            && $request->boolean('auto')
            && $enviarImpresora
            && ! empty($sesion['pack'])
            && ! $faltanteImpresora;
        $impresora = $sesion['impresora_usuario'] ?? [];
        $programaSeteo = ComprobanteImpresionSalidaUsuarioSupport::programaUnificado();

        if (! $autoEjecutar && ! empty($sesion['pack'])) {
            $sesionPdf = $sesion;
            dispatch(function () use ($sesionPdf) {
                app(ComprobanteImpresionSesionService::class)->asegurarPdfSesionPapel($sesionPdf);
            })->afterResponse();
        }

        return view('ventas.programa_impresion.sesion', [
            'sesion' => $sesion,
            'resultado' => $resultado,
            'programaSeteo' => $programaSeteo,
            'autoEjecutar' => $autoEjecutar,
            'enviarImpresora' => $enviarImpresora,
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
        $retorno = $this->resolverRetornoPath($request, $sesion);
        if ($retorno !== '') {
            return url($retorno);
        }

        if (($sesion['origen_tipo'] ?? '') === 'REPARTO') {
            $retorno = is_array($sesion['lote_retorno'] ?? null) ? $sesion['lote_retorno'] : [];

            return route('factura', $retorno);
        }

        return match ($sesion['origen_tipo'] ?? '') {
            'PEDIDO' => route('pedido'),
            'REMITO' => route('remito'),
            'COT' => route('cot_electronico', array_filter([
                'sesion_id' => (int) ($sesion['origen_id'] ?? 0) ?: null,
            ])),
            default => route('factura'),
        };
    }

    /**
     * @param  array<string, mixed>  $sesion
     */
    private function redirectSesion(array $sesion)
    {
        $params = ['id' => (int) ($sesion['origen_id'] ?? 0)];
        if (! empty($sesion['retorno'])) {
            $params['retorno'] = $sesion['retorno'];
        }
        if (($sesion['modo'] ?? '') === 'CONSULTA') {
            $params['modo'] = 'CONSULTA';
        }
        if (! empty($sesion['solo_formulario'])) {
            $params['solo_formulario'] = $sesion['solo_formulario'];
        } elseif (($sesion['origen_tipo'] ?? '') !== 'FACTURA') {
            $params['pack'] = 1;
        }

        if (($sesion['origen_tipo'] ?? '') === 'REPARTO') {
            $retorno = is_array($sesion['lote_retorno'] ?? null) ? $sesion['lote_retorno'] : [];

            return redirect()->route(
                'sesion_impresion_reparto',
                ['transporteId' => (int) ($sesion['origen_id'] ?? 0)] + $retorno
            );
        }

        if (($sesion['origen_tipo'] ?? '') === 'COT') {
            $paramsCot = ['id' => (int) ($sesion['origen_id'] ?? 0)];
            if (! empty($sesion['retorno'])) {
                $paramsCot['retorno'] = $sesion['retorno'];
            }
            $remitoEnvioId = (int) (($sesion['pack'][0]['remito_envio_id'] ?? 0));
            if ($remitoEnvioId > 0) {
                $paramsCot['remito_envio_id'] = $remitoEnvioId;
            }

            return redirect()->route('sesion_impresion_cot', $paramsCot);
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

    private function enviarImpresoraDesdeRequest(Request $request): bool
    {
        if ($request->boolean('pdf')) {
            return false;
        }
        if ($request->exists('enviar_impresora')) {
            return $request->boolean('enviar_impresora');
        }

        return true;
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
        if (! in_array($tipo, ['FACTURA', 'PEDIDO', 'REMITO', 'REPARTO', 'COT'], true)) {
            return is_array($sesionSesion) ? $sesionSesion : [];
        }
        if ($tipo !== 'REPARTO' && $id <= 0) {
            return is_array($sesionSesion) ? $sesionSesion : [];
        }

        $modo = strtoupper((string) $request->input('modo', 'OPERATIVO')) === 'CONSULTA'
            ? 'CONSULTA'
            : 'OPERATIVO';
        $solo = $request->input('solo_formulario');
        $solo = is_string($solo) && $solo !== '' ? $solo : null;

        if ($tipo === 'REPARTO') {
            $ids = is_array($sesionSesion) ? ($sesionSesion['lote_venta_ids'] ?? []) : [];
            if ($ids === []) {
                return is_array($sesionSesion) ? $sesionSesion : [];
            }
            $sesion = $this->sesionService->armarDesdeReparto(
                $ids,
                $modo,
                (string) ($sesionSesion['lote_etiqueta'] ?? ''),
                is_array($sesionSesion['lote_retorno'] ?? null) ? $sesionSesion['lote_retorno'] : []
            );
            $request->session()->put('comprobante_impresion_sesion', $sesion);

            return $sesion;
        }

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

        if ($tipo === 'COT') {
            $remitoEnvioId = $request->integer('remito_envio_id') ?: null;
            if ($remitoEnvioId === null && is_array($sesionSesion)) {
                $remitoEnvioId = (int) ($sesionSesion['pack'][0]['remito_envio_id'] ?? 0) ?: null;
            }
            $sesion = $this->sesionService->armarDesdeCotSesion($id, $remitoEnvioId, $modo);
            $retorno = $this->resolverRetornoPath($request, is_array($sesionSesion) ? $sesionSesion : []);
            if ($retorno !== '') {
                $sesion['retorno'] = $retorno;
            }
            $request->session()->put('comprobante_impresion_sesion', $sesion);

            return $sesion;
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
        $retorno = $this->resolverRetornoPath($request, is_array($sesionSesion) ? $sesionSesion : []);
        if ($retorno !== '') {
            $sesion['retorno'] = $retorno;
        }
        $request->session()->put('comprobante_impresion_sesion', $sesion);

        return $sesion;
    }

    /**
     * @param  array<string, mixed>  $sesion
     */
    private function resolverRetornoPath(Request $request, array $sesion): string
    {
        foreach ([$request->query('retorno', ''), $request->input('retorno', ''), $sesion['retorno'] ?? ''] as $candidato) {
            $path = ComprobanteImpresionSesionUrlSupport::sanitizarRetornoPath((string) $candidato);
            if ($path !== '') {
                return $path;
            }
        }

        return '';
    }
}
