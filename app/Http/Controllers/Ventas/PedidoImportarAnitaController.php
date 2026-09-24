<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Services\Ventas\PedidoImportarDesdeAnitaInterformingService;
use App\Services\Ventas\PedidoImportarDesdeAnitaService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\ListadoRepartoFechaEntregaSupport;
use App\Support\Ventas\PedidoInterformingSupport;
use Illuminate\Http\Request;

class PedidoImportarAnitaController extends Controller
{
    public function __construct(
        private readonly PedidoImportarDesdeAnitaService $service,
        private readonly PedidoImportarDesdeAnitaInterformingService $serviceInterforming,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-importar-pedido-anita');

        if (EntornoEmpresaSupport::esInterforming()) {
            return $this->indexInterforming($request);
        }

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

        if (EntornoEmpresaSupport::esInterforming()) {
            return $this->importarInterforming($request);
        }

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
     * Bierzo: fecha entrega + reparto. Interforming: fecha (penm_fecha) + tipo PED/PEX.
     */
    public function importarDesdeIndex(Request $request)
    {
        can('ejecutar-importar-pedido-anita');

        if (EntornoEmpresaSupport::esInterforming()) {
            return $this->importarDesdeIndexInterforming($request);
        }

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
        // Botón del index: pisa cabecera/líneas existentes (incl. facturados) con Anita.
        $resumen = $this->service->importar($filtros, (int) (auth()->id() ?: 0), false, true);

        $mensaje = sprintf(
            'Importación Anita (forzada desde index): %d creados, %d actualizados, %d omitidos, %d DESPACHO cerrados en Anita (sin importar), %d con error (total %d).',
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

    private function indexInterforming(Request $request)
    {
        PedidoInterformingSupport::abortSiNoInterforming();

        $filtros = $this->filtrosInterformingDesdeRequest($request);
        $consultar = $request->boolean('consultar');
        $filas = [];

        if ($consultar) {
            ini_set('max_execution_time', '300');
            ini_set('memory_limit', '512M');
            $filas = $this->serviceInterforming->listarPreview($filtros);
        }

        return view(PedidoInterformingSupport::vista('importar_anita.index'), [
            'filtros' => $filtros,
            'filtrosQuery' => $this->filtrosInterformingQueryString($filtros),
            'consultar' => $consultar,
            'filas' => $filas,
            'puedeEjecutar' => can('ejecutar-importar-pedido-anita', false),
        ]);
    }

    private function importarInterforming(Request $request)
    {
        PedidoInterformingSupport::abortSiNoInterforming();

        $filtros = $this->filtrosInterformingDesdeRequest($request);
        $resumen = $this->serviceInterforming->importar($filtros, (int) (auth()->id() ?: 0));

        $mensaje = sprintf(
            'Importación Interforming finalizada: %d creados, %d actualizados, %d omitidos, %d con error (total %d).',
            $resumen['creados'],
            $resumen['actualizados'],
            $resumen['omitidos'],
            $resumen['errores'],
            $resumen['total']
        );

        $query = array_merge($this->filtrosInterformingQueryString($filtros), ['consultar' => 1]);
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

    private function importarDesdeIndexInterforming(Request $request)
    {
        PedidoInterformingSupport::abortSiNoInterforming();

        $fecha = trim((string) $request->input('fecha', $request->input('fecha_desde', '')));
        if ($fecha === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = date('Y-m-d');
        }

        $tipo = strtoupper(trim((string) $request->input('tipo', 'TODOS')));
        if (! in_array($tipo, ['PED', 'PEX', 'TODOS'], true)) {
            $tipo = 'TODOS';
        }

        $filtros = [
            'fecha_desde' => $fecha,
            'fecha_hasta' => $fecha,
            'tipo' => $tipo,
        ];

        $resumen = $this->serviceInterforming->importar($filtros, (int) (auth()->id() ?: 0), true);

        $mensaje = sprintf(
            'Importación Anita Interforming (forzada desde index): %d creados, %d actualizados, %d omitidos, %d con error (total %d).',
            $resumen['creados'],
            $resumen['actualizados'],
            $resumen['omitidos'],
            $resumen['errores'],
            $resumen['total']
        );

        $redirect = redirect()
            ->route('pedido')
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
     * @return array{fecha_desde: string, fecha_hasta: string, tipo: string}
     */
    private function filtrosInterformingDesdeRequest(Request $request): array
    {
        $hoy = date('Y-m-d');
        $desde = trim((string) $request->input('fecha_desde', $hoy));
        $hasta = trim((string) $request->input('fecha_hasta', $desde));
        if ($desde === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $desde = $hoy;
        }
        if ($hasta === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $hasta = $desde;
        }

        $tipo = strtoupper(trim((string) $request->input('tipo', 'TODOS')));
        if (! in_array($tipo, ['PED', 'PEX', 'TODOS'], true)) {
            $tipo = 'TODOS';
        }

        return [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'tipo' => $tipo,
        ];
    }

    /**
     * @param  array{fecha_desde: string, fecha_hasta: string, tipo: string}  $filtros
     * @return array{fecha_desde: string, fecha_hasta: string, tipo: string}
     */
    private function filtrosInterformingQueryString(array $filtros): array
    {
        return [
            'fecha_desde' => $filtros['fecha_desde'],
            'fecha_hasta' => $filtros['fecha_hasta'],
            'tipo' => $filtros['tipo'],
        ];
    }
}
