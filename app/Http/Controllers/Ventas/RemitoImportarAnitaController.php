<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Services\Ventas\RemitoImportarDesdeAnitaService;
use App\Support\Ventas\ListadoRepartoFechaEntregaSupport;
use Illuminate\Http\Request;

class RemitoImportarAnitaController extends Controller
{
    public function __construct(
        private readonly RemitoImportarDesdeAnitaService $service,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-importar-remito-anita');

        if (! RemitoImportarDesdeAnitaService::esElBierzo()) {
            abort(404);
        }

        $filtros = $this->resolverFiltros($request);
        $consultar = $request->boolean('consultar');
        $filas = [];

        if ($consultar) {
            ini_set('max_execution_time', '300');
            ini_set('memory_limit', '512M');
            $filas = $this->service->listarPreview($filtros);
        }

        return view('ventas.remito_importar_anita.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $this->paraQueryString($filtros),
            'consultar' => $consultar,
            'filas' => $filas,
            'puedeEjecutar' => can('ejecutar-importar-remito-anita', false),
            'etiquetaFuente' => RemitoImportarDesdeAnitaService::etiquetaFuente($filtros['fuente']),
        ]);
    }

    public function importar(Request $request)
    {
        can('ejecutar-importar-remito-anita');

        if (! RemitoImportarDesdeAnitaService::esElBierzo()) {
            abort(404);
        }

        $filtros = $this->resolverFiltros($request);
        $resumen = $this->service->importar($filtros);
        $etiqueta = RemitoImportarDesdeAnitaService::etiquetaFuente($filtros['fuente']);

        $mensaje = sprintf(
            'Importación %s finalizada: %d creados, %d actualizados, %d omitidos (DESPACHO o ya facturados), %d con error (total %d).',
            $etiqueta,
            $resumen['creados'],
            $resumen['actualizados'],
            $resumen['omitidos'] ?? 0,
            $resumen['errores'],
            $resumen['total']
        );

        $query = array_merge(
            $this->paraQueryString($filtros),
            ['consultar' => 1]
        );

        $redirect = redirect()
            ->route('importar_remito_anita', $query)
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
     * Importación rápida desde el index de remitos (modal).
     * Redirige al listado con la misma fecha/reparto filtrados.
     */
    public function importarDesdeIndex(Request $request)
    {
        can('ejecutar-importar-remito-anita');

        if (! RemitoImportarDesdeAnitaService::esElBierzo()) {
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
            'fuente' => $request->input('fuente'),
        ]);

        $filtros = $this->resolverFiltros($request);
        $resumen = $this->service->importar($filtros, (int) (auth()->id() ?: 0));
        $etiqueta = RemitoImportarDesdeAnitaService::etiquetaFuente($filtros['fuente']);

        $mensaje = sprintf(
            'Importación %s: %d creados, %d actualizados, %d omitidos (DESPACHO o ya facturados), %d con error (total %d).',
            $etiqueta,
            $resumen['creados'],
            $resumen['actualizados'],
            $resumen['omitidos'] ?? 0,
            $resumen['errores'],
            $resumen['total']
        );

        $query = ListadoRepartoFechaEntregaSupport::paraQueryString($filtros);
        $redirect = redirect()
            ->route('remito', $query)
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
     * @return array{filtro_reparto: string, fecha_entrega_desde: string, fecha_entrega_hasta: string, fuente: string}
     */
    private function resolverFiltros(Request $request): array
    {
        $base = ListadoRepartoFechaEntregaSupport::resolverDesdeRequest($request);
        $base['fuente'] = RemitoImportarDesdeAnitaService::normalizarFuente(
            (string) $request->input('fuente', RemitoImportarDesdeAnitaService::FUENTE_BIERZO)
        );

        return $base;
    }

    /**
     * @param  array{filtro_reparto: string, fecha_entrega_desde: string, fecha_entrega_hasta: string, fuente: string}  $filtros
     * @return array<string, string>
     */
    private function paraQueryString(array $filtros): array
    {
        $params = ListadoRepartoFechaEntregaSupport::paraQueryString($filtros);
        $fuente = RemitoImportarDesdeAnitaService::normalizarFuente($filtros['fuente'] ?? null);
        if ($fuente !== RemitoImportarDesdeAnitaService::FUENTE_BIERZO) {
            $params['fuente'] = $fuente;
        }

        return $params;
    }
}
