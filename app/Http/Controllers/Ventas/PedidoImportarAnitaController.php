<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Services\Ventas\PedidoImportarDesdeAnitaService;
use App\Support\Ventas\ListadoRepartoFechaEntregaSupport;
use Illuminate\Http\Request;

class PedidoImportarAnitaController extends Controller
{
    public function __construct(
        private readonly PedidoImportarDesdeAnitaService $service,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-importar-pedido-anita');

        if (! PedidoImportarDesdeAnitaService::esElBierzo()) {
            abort(404);
        }

        $filtros = ListadoRepartoFechaEntregaSupport::resolverDesdeRequest($request);
        $consultar = $request->boolean('consultar');
        $filas = [];

        if ($consultar) {
            ini_set('max_execution_time', '300');
            ini_set('memory_limit', '512M');
            $filas = $this->service->listarPreview($filtros);
        }

        return view('ventas.pedido_importar_anita.index', [
            'filtros' => $filtros,
            'filtrosQuery' => ListadoRepartoFechaEntregaSupport::paraQueryString($filtros),
            'consultar' => $consultar,
            'filas' => $filas,
            'puedeEjecutar' => can('ejecutar-importar-pedido-anita', false),
        ]);
    }

    public function importar(Request $request)
    {
        can('ejecutar-importar-pedido-anita');

        if (! PedidoImportarDesdeAnitaService::esElBierzo()) {
            abort(404);
        }

        $filtros = ListadoRepartoFechaEntregaSupport::resolverDesdeRequest($request);
        $resumen = $this->service->importar($filtros);

        $mensaje = sprintf(
            'Importación finalizada: %d creados, %d actualizados, %d omitidos (ya facturados/procesados), %d DESPACHO cerrados en Anita (sin importar), %d con error (total %d).',
            $resumen['creados'],
            $resumen['actualizados'],
            $resumen['omitidos'] ?? 0,
            $resumen['cerrados'] ?? 0,
            $resumen['errores'],
            $resumen['total']
        );

        $query = array_merge(
            ListadoRepartoFechaEntregaSupport::paraQueryString($filtros),
            ['consultar' => 1]
        );

        $redirect = redirect()
            ->route('importar_pedido_anita', $query)
            ->with('mensaje', $mensaje);

        if ($resumen['errores'] > 0) {
            $errores = collect($resumen['detalle'])
                ->filter(static fn ($d) => ($d['estado'] ?? '') === 'error')
                ->take(15)
                ->map(static fn ($d) => ($d['codigo'] ?? '').': '.($d['mensaje'] ?? 'error'))
                ->implode(' | ');
            $redirect->with('mensaje_error', $errores !== '' ? $errores : 'Hubo errores en la importación.');
        }

        return $redirect;
    }

    /**
     * Importación rápida desde el index de pedidos (modal).
     * Redirige al listado con la misma fecha/reparto filtrados.
     */
    public function importarDesdeIndex(Request $request)
    {
        can('ejecutar-importar-pedido-anita');

        if (! PedidoImportarDesdeAnitaService::esElBierzo()) {
            abort(404);
        }

        $fecha = trim((string) $request->input('fecha_entrega', ''));
        if ($fecha === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = date('Y-m-d');
        }

        $request->merge([
            'fecha_entrega_desde' => $fecha,
            'fecha_entrega_hasta' => $fecha,
            'filtro_reparto' => trim((string) $request->input('filtro_reparto', '')),
        ]);

        $filtros = ListadoRepartoFechaEntregaSupport::resolverDesdeRequest($request);
        $resumen = $this->service->importar($filtros, (int) (auth()->id() ?: 0));

        $mensaje = sprintf(
            'Importación Anita: %d creados, %d actualizados, %d omitidos (ya facturados/procesados), %d DESPACHO cerrados en Anita (sin importar), %d con error (total %d).',
            $resumen['creados'],
            $resumen['actualizados'],
            $resumen['omitidos'] ?? 0,
            $resumen['cerrados'] ?? 0,
            $resumen['errores'],
            $resumen['total']
        );

        $query = ListadoRepartoFechaEntregaSupport::paraQueryString($filtros);
        $redirect = redirect()
            ->route('pedido', $query)
            ->with('mensaje', $mensaje);

        if ($resumen['errores'] > 0) {
            $errores = collect($resumen['detalle'])
                ->filter(static fn ($d) => ($d['estado'] ?? '') === 'error')
                ->take(15)
                ->map(static fn ($d) => ($d['codigo'] ?? '').': '.($d['mensaje'] ?? 'error'))
                ->implode(' | ');
            $redirect->with('mensaje_error', $errores !== '' ? $errores : 'Hubo errores en la importación.');
        }

        return $redirect;
    }
}
