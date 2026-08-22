<?php

namespace App\Http\Controllers\Seguridad;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Seguridad\IngresoProveedorControlSupport;
use App\Support\Seguridad\IngresoProveedorListadoFiltros;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class IngresoProveedorControlController extends Controller
{
    public function __construct(private readonly EmpresaRepositoryInterface $empresaRepository)
    {
    }

    public function index(Request $request)
    {
        if (! can('autorizar-ingreso-proveedor', false) && ! can('listar-ingreso-proveedor', false)) {
            can('listar-ingreso-proveedor');
        }
        $filtros = $this->resolverFiltros($request);

        return view('seguridad.ingreso_proveedor.control', [
            'filas' => IngresoProveedorControlSupport::grillaDelDia($filtros),
            'filtros' => $filtros,
            'filtrosQuery' => IngresoProveedorListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => IngresoProveedorListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'empresaFiltroId' => (int) ($filtros['empresa_id'] ?? 0),
            'puedeRegistrarIngresoEgreso' => can('autorizar-ingreso-proveedor', false),
        ]);
    }

    public function buscarDni(Request $request): JsonResponse
    {
        can('autorizar-ingreso-proveedor');
        $dni = IngresoProveedorControlSupport::normalizarDni((string) $request->input('documento', ''));
        if (strlen($dni) < 6) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Ingrese un DNI / CUIL válido (al menos 6 números).',
            ], 422);
        }

        $filtros = $this->resolverFiltros($request);
        $empresaId = ($filtros['empresa_scope'] ?? 'una') === 'una'
            ? (int) ($filtros['empresa_id'] ?? 0)
            : null;

        $persona = IngresoProveedorControlSupport::buscarPorDni($dni, $empresaId ?: null);
        if (! $persona) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'No hay un ticket abierto para el DNI '.$dni
                    .($empresaId ? ' en la empresa seleccionada.' : '.'),
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'persona' => IngresoProveedorControlSupport::payloadPersona($persona),
        ]);
    }

    public function marcarEntro(Request $request): JsonResponse
    {
        return $this->marcar($request, 'entro');
    }

    public function marcarSalio(Request $request): JsonResponse
    {
        return $this->marcar($request, 'salio');
    }

    private function marcar(Request $request, string $accion): JsonResponse
    {
        can('autorizar-ingreso-proveedor');
        $personaId = (int) $request->input('persona_id');
        if ($personaId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Falta la persona del ticket.'], 422);
        }

        try {
            $persona = $accion === 'entro'
                ? IngresoProveedorControlSupport::marcarEntro($personaId)
                : IngresoProveedorControlSupport::marcarSalio($personaId);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        $filtros = $this->resolverFiltros($request);

        return response()->json([
            'ok' => true,
            'mensaje' => $accion === 'entro' ? 'Ingreso registrado.' : 'Egreso registrado.',
            'persona' => IngresoProveedorControlSupport::payloadPersona($persona),
            'filas' => $this->filasJson($filtros),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filasJson(array $filtros): array
    {
        return IngresoProveedorControlSupport::grillaDelDia($filtros)
            ->map(fn ($p) => IngresoProveedorControlSupport::payloadPersona($p))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltros(Request $request): array
    {
        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;

        return IngresoProveedorListadoFiltros::resolverDesdeRequest(
            $request,
            null,
            $empresaDefault ? (int) $empresaDefault : null
        );
    }
}
