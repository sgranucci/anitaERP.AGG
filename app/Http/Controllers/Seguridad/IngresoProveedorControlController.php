<?php

namespace App\Http\Controllers\Seguridad;

use App\Exports\Seguridad\IngresoProveedorControlListadoExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Seguridad\IngresoProveedorAutorizacionSupport;
use App\Support\Seguridad\IngresoProveedorControlSupport;
use App\Support\Seguridad\IngresoProveedorListadoFiltros;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
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
            'puedeAutorizarPuerta' => can('autorizar-puerta-ingreso-proveedor', false),
        ]);
    }

    public function listar(Request $request, $formato = null)
    {
        if (! can('autorizar-ingreso-proveedor', false) && ! can('listar-ingreso-proveedor', false)) {
            can('listar-ingreso-proveedor');
        }
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltros($request);
        $filtrosQuery = IngresoProveedorListadoFiltros::paraQueryString($filtros);

        switch ($formato) {
            case 'PDF':
                $filas = IngresoProveedorControlSupport::grillaDelDia($filtros)
                    ->map(fn ($p) => IngresoProveedorControlSupport::payloadPersona($p))
                    ->values()
                    ->all();
                $view = \View::make('seguridad.ingreso_proveedor.control_listado', compact('filas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $pdf = \PDF::loadHTML($view)->setPaper('legal', 'landscape');
                $pdf->save($path.'/listado_control_ingreso.pdf');

                return $pdf->download('listado_control_ingreso.pdf');
            case 'EXCEL':
                return Excel::download(
                    (new IngresoProveedorControlListadoExport)->parametros($filtros),
                    'listado_control_ingreso.xlsx'
                );
            case 'CSV':
                return Excel::download(
                    (new IngresoProveedorControlListadoExport)->parametros($filtros),
                    'listado_control_ingreso.csv',
                    \Maatwebsite\Excel\Excel::CSV
                );
            default:
                return redirect()->route('control_ingreso_proveedor', $filtrosQuery);
        }
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
                'mensaje' => IngresoProveedorControlSupport::mensajeDniNoEncontrado($dni, $empresaId ?: null),
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'persona' => IngresoProveedorControlSupport::payloadPersona($persona),
        ]);
    }

    public function detallePendiente(Request $request): JsonResponse
    {
        can('autorizar-puerta-ingreso-proveedor');
        $personaId = (int) $request->input('persona_id');
        if ($personaId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Falta la persona del ticket.'], 422);
        }

        try {
            $detalle = IngresoProveedorControlSupport::detallePendiente($personaId);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'detalle' => $detalle,
        ]);
    }

    public function autorizarPuerta(Request $request): JsonResponse
    {
        can('autorizar-puerta-ingreso-proveedor');
        $personaId = (int) $request->input('persona_id');
        if ($personaId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Falta la persona del ticket.'], 422);
        }

        try {
            $persona = IngresoProveedorControlSupport::personaConRelaciones($personaId);
            $ticketId = (int) $persona->ingreso_proveedor_id;
            IngresoProveedorAutorizacionSupport::autorizar($ticketId);
            $persona = IngresoProveedorControlSupport::personaConRelaciones($personaId);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        $filtros = $this->resolverFiltros($request);

        return response()->json([
            'ok' => true,
            'mensaje' => 'Ticket #'.$persona->ingreso_proveedor_id.' autorizado. Ya puede registrar ENTRO.',
            'persona' => IngresoProveedorControlSupport::payloadPersona($persona),
            'filas' => $this->filasJson($filtros),
        ]);
    }

    public function autorizarPuertaEIngresar(Request $request): JsonResponse
    {
        can('autorizar-puerta-ingreso-proveedor');
        $personaId = (int) $request->input('persona_id');
        if ($personaId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Falta la persona del ticket.'], 422);
        }

        $ticketId = 0;
        try {
            $persona = IngresoProveedorControlSupport::personaConRelaciones($personaId);
            $ticketId = (int) $persona->ingreso_proveedor_id;
            IngresoProveedorAutorizacionSupport::autorizar($ticketId);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        try {
            $persona = IngresoProveedorControlSupport::marcarEntro($personaId);
            $persona = IngresoProveedorControlSupport::personaConRelaciones($personaId);
        } catch (RuntimeException $e) {
            $persona = IngresoProveedorControlSupport::personaConRelaciones($personaId);
            $filtros = $this->resolverFiltros($request);

            return response()->json([
                'ok' => false,
                'autorizado' => true,
                'mensaje' => 'Ticket #'.$ticketId.' autorizado, pero no se pudo registrar el ingreso: '.$e->getMessage(),
                'persona' => IngresoProveedorControlSupport::payloadPersona($persona),
                'filas' => $this->filasJson($filtros),
            ], 422);
        }

        $filtros = $this->resolverFiltros($request);

        return response()->json([
            'ok' => true,
            'mensaje' => 'Ticket #'.$ticketId.' autorizado e ingreso registrado.',
            'persona' => IngresoProveedorControlSupport::payloadPersona($persona),
            'filas' => $this->filasJson($filtros),
        ]);
    }

    public function rechazarPuerta(Request $request): JsonResponse
    {
        can('autorizar-puerta-ingreso-proveedor');
        $personaId = (int) $request->input('persona_id');
        $motivo = trim((string) $request->input('motivo_rechazo', ''));
        if ($personaId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Falta la persona del ticket.'], 422);
        }

        try {
            $persona = IngresoProveedorControlSupport::personaConRelaciones($personaId);
            $ticketId = (int) $persona->ingreso_proveedor_id;
            IngresoProveedorAutorizacionSupport::rechazar($ticketId, $motivo);
            $persona = IngresoProveedorControlSupport::personaConRelaciones($personaId);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        $filtros = $this->resolverFiltros($request);

        return response()->json([
            'ok' => true,
            'mensaje' => 'Ticket #'.$persona->ingreso_proveedor_id.' rechazado.',
            'persona' => IngresoProveedorControlSupport::payloadPersona($persona),
            'filas' => $this->filasJson($filtros),
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
            $persona = IngresoProveedorControlSupport::personaConRelaciones($personaId);
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
