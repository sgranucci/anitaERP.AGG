<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Contable\Centrocosto;
use App\Models\Ventas\ViandaConsumo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ventas\Vianda\ViandaConsumoDiaService;
use App\Support\Stock\MovimientosArticuloDepositoSupport;
use App\Support\Ventas\Vianda\ViandaConsumoListadoFiltros;
use App\Support\Ventas\Vianda\ViandaEmpresaSupport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Throwable;

/**
 * "Viandas del día": consola operativa (patrón "Facturas del día" de gastronomía).
 * Filtros en la barra del card-header; acciones por fila (ver, reimprimir, borrar).
 * El reporte por período y exportación permanece en ViandaReporteController.
 */
class ViandaDiaController extends Controller
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly ViandaConsumoDiaService $diaService,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-viandas-dia-gastronomia');

        $filtros = $this->resolverFiltros($request);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        $filtrosQuery = ViandaConsumoListadoFiltros::paraQueryString($filtros);
        $filtrosQuery['fecha'] = $filtros['fecha_desde'];

        $perPage = max(10, min(200, (int) $request->input('per_page', 25)));

        $filas = $this->baseQuery($filtros)->paginate($perPage)->appends($filtrosQuery);
        $totales = $this->totales($filtros);

        return view('ventas.vianda.dia.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'fecha' => $filtros['fecha_desde'],
            'filas' => $filas,
            'totales' => $totales,
            'empresa_query' => ViandaEmpresaSupport::empresasSeleccionables(),
            'centrocosto_query' => Centrocosto::query()->orderBy('nombre')->get(['id', 'nombre']),
            'puede_borrar' => can('borrar-consumo-vianda-gastronomia', false),
        ]);
    }

    public function ver(int $consumoId, Request $request)
    {
        can('listar-viandas-dia-gastronomia');

        $consumo = ViandaConsumo::query()
            ->with([
                'lineas.articulo',
                'movimientos.articulos',
                'movimientos.depositos',
                'centrocosto',
                'empresa',
                'terminal.ubicacion',
                'terminal.salidaVoucher',
                'viandaUsuario',
                'tipoMenu',
                'operador',
                'anuladoPor',
                'jornada',
            ])
            ->findOrFail($consumoId);

        $this->assertAccesoEmpresa((int) $consumo->empresa_id);

        $volverUrl = route('viandas_dia_gastronomia', $this->filtrosQueryDesdeRequest($request));

        return view('ventas.vianda.dia.ver', [
            'consumo' => $consumo,
            'filtrosQuery' => $this->filtrosQueryDesdeRequest($request),
            'volver_url' => $volverUrl,
            'deposito_platos_id' => (int) ($consumo->terminal?->deposito_platos_id ?? 0),
            'deposito_insumos_id' => (int) ($consumo->terminal?->deposito_insumos_id ?? 0),
            'puede_borrar' => can('borrar-consumo-vianda-gastronomia', false),
            'puede_ver_articulo' => can('editar-articulos', false),
            'puede_ver_formula' => can('listar-formula-articulo', false) || can('listar-articulos', false),
            'puede_ver_movimientos' => MovimientosArticuloDepositoSupport::puedeConsultar(),
            'mostrar_acciones_articulo' => can('editar-articulos', false)
                || can('listar-formula-articulo', false)
                || can('listar-articulos', false)
                || MovimientosArticuloDepositoSupport::puedeConsultar(),
            'puede_ver_empleado' => can('editar-vianda-usuario-gastronomia', false)
                || can('listar-vianda-usuario-gastronomia', false),
        ]);
    }

    public function reimprimir(int $consumoId): JsonResponse
    {
        if (! can('listar-viandas-dia-gastronomia', false)) {
            return response()->json(['ok' => false, 'error' => 'Sin permiso.'], 403);
        }

        try {
            $voucher = $this->diaService->reimprimir($consumoId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => 'No se pudo reimprimir el voucher: '.$e->getMessage()], 500);
        }

        if (empty($voucher['ok'])) {
            return response()->json([
                'ok' => false,
                'error' => $voucher['mensaje'] ?? 'No se pudo enviar el voucher a la impresora.',
                'texto_preview' => $voucher['texto_preview'] ?? '',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'mensaje' => 'Voucher enviado a la impresora.',
            'texto_preview' => $voucher['texto_preview'] ?? '',
        ]);
    }

    public function borrar(int $consumoId, Request $request): JsonResponse
    {
        if (! can('borrar-consumo-vianda-gastronomia', false)) {
            return response()->json(['ok' => false, 'error' => 'No tiene permiso para borrar viandas.'], 403);
        }

        try {
            $resultado = $this->diaService->anular(
                $consumoId,
                Auth::id(),
                (string) $request->input('motivo', ''),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => 'No se pudo borrar la vianda: '.$e->getMessage()], 500);
        }

        return response()->json($resultado);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltros(Request $request): array
    {
        $filtros = ViandaConsumoListadoFiltros::resolverDesdeRequest($request);
        $fecha = $this->resolverFecha($request);
        $filtros['fecha_desde'] = $fecha;
        $filtros['fecha_hasta'] = $fecha;

        return $filtros;
    }

    private function resolverFecha(Request $request): string
    {
        $fecha = trim((string) $request->input('fecha', ''));
        if ($fecha !== '') {
            try {
                return Carbon::parse($fecha)->format('Y-m-d');
            } catch (Throwable) {
                // fecha inválida: cae en hoy
            }
        }

        return Carbon::today()->format('Y-m-d');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Database\Eloquent\Builder<ViandaConsumo>
     */
    private function baseQuery(array $filtros)
    {
        $query = ViandaConsumo::query()
            ->with(['centrocosto', 'empresa', 'terminal', 'viandaUsuario'])
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        return ViandaConsumoListadoFiltros::aplicar($query, $filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{consumos:int,items:int,costo:float,venta:float,anulados:int}
     */
    private function totales(array $filtros): array
    {
        $query = ViandaConsumo::query();
        ViandaConsumoListadoFiltros::aplicar($query, $filtros);

        $row = $query->selectRaw('count(*) as consumos, sum(cantidad_items) as items, sum(total_costo) as costo, sum(total_venta) as venta, '
            ."sum(case when estado = 'N' then 1 else 0 end) as anulados")
            ->first();

        return [
            'consumos' => (int) ($row->consumos ?? 0),
            'items' => (int) ($row->items ?? 0),
            'costo' => (float) ($row->costo ?? 0),
            'venta' => (float) ($row->venta ?? 0),
            'anulados' => (int) ($row->anulados ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosQueryDesdeRequest(Request $request): array
    {
        $filtros = $this->resolverFiltros($request);
        $filtrosQuery = ViandaConsumoListadoFiltros::paraQueryString($filtros);
        $filtrosQuery['fecha'] = $filtros['fecha_desde'];

        return $filtrosQuery;
    }

    private function assertAccesoEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'No tiene acceso a la empresa seleccionada.');
        }
    }
}
