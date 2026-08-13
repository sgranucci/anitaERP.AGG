<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ventas\AsignacionRemitoFacturaService;
use App\Support\Ventas\AsignacionRemitoFacturaSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AsignacionRemitoFacturaController extends Controller
{
    public function __construct(
        private AsignacionRemitoFacturaService $service,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-asignacion-remito-factura');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->filtrosConEmpresa($request, $empresaQuery);

        return view('ventas.asignacion_remito_factura.index', [
            'filtros' => $filtros,
            'empresa_query' => $empresaQuery,
            'puedeEjecutar' => can('ejecutar-asignacion-remito-factura', false),
            'puedeEditarRemito' => can('editar-remitos', false),
            'puedeListarRemito' => can('listar-remitos', false),
            'puedeEditarFactura' => can('editar-factura', false),
            'puedeListarFactura' => can('listar-factura', false),
        ]);
    }

    public function consultar(Request $request): JsonResponse
    {
        can('listar-asignacion-remito-factura');

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');

        $filtros = $this->filtrosConEmpresa($request, $this->empresaRepository->allFiltrado());
        if ((int) ($filtros['empresa_id'] ?? 0) <= 0) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Seleccioná una empresa. La división (p. ej. PV 15 Villafranca) no se mezcla con El Bierzo.',
            ], 422);
        }

        $resultado = $this->service->consultar($filtros);

        return response()->json([
            'ok' => true,
            'filtros' => $filtros,
            ...$resultado,
        ]);
    }

    public function confirmar(Request $request): JsonResponse
    {
        can('ejecutar-asignacion-remito-factura');

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '180');

        $filtros = $this->filtrosConEmpresa($request, $this->empresaRepository->allFiltrado());
        if ((int) ($filtros['empresa_id'] ?? 0) <= 0) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Seleccioná una empresa. La división (p. ej. PV 15 Villafranca) no se mezcla con El Bierzo.',
            ], 422);
        }

        $pares = $request->input('pares', []);
        if (! is_array($pares) || $pares === []) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'No hay emparejamientos para confirmar',
            ], 422);
        }

        $normalizados = [];
        foreach ($pares as $par) {
            if (! is_array($par)) {
                continue;
            }
            $normalizados[] = [
                'remito_id' => (int) ($par['remito_id'] ?? 0),
                'venta_id' => (int) ($par['venta_id'] ?? 0),
            ];
        }

        $resultado = $this->service->confirmarPares($normalizados, (int) $filtros['empresa_id']);
        $huboOk = $resultado['ok'] !== [];

        return response()->json([
            'ok' => $huboOk,
            'mensaje' => $this->mensajeResultado($resultado),
            'asignados' => $resultado['ok'],
            'errores' => $resultado['errores'],
        ], $huboOk ? 200 : 422);
    }

    /**
     * @param  array{ok: list<array<string, mixed>>, errores: list<array<string, mixed>>}  $resultado
     */
    private function mensajeResultado(array $resultado): string
    {
        $nOk = count($resultado['ok']);
        $nErr = count($resultado['errores']);
        if ($nOk > 0 && $nErr === 0) {
            return $nOk === 1
                ? 'Se asignó 1 remito a la factura'
                : 'Se asignaron '.$nOk.' remitos a facturas';
        }
        if ($nOk > 0) {
            return 'Se asignaron '.$nOk.' par(es); '.$nErr.' con error';
        }
        $primero = $resultado['errores'][0]['error'] ?? 'No se pudo asignar';

        return (string) $primero;
    }

    /**
     * @param  Collection<int, \App\Models\Configuracion\Empresa>  $empresaQuery
     * @return array<string, mixed>
     */
    private function filtrosConEmpresa(Request $request, Collection $empresaQuery): array
    {
        $filtros = AsignacionRemitoFacturaSupport::resolverFiltros($request);
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId <= 0 && $empresaQuery->count() === 1) {
            $empresaId = (int) $empresaQuery->first()->id;
        }
        if ($empresaId > 0 && ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            $empresaId = 0;
        }
        $filtros['empresa_id'] = $empresaId;

        return $filtros;
    }
}
