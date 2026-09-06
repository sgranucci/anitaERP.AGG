<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Sueldos\Solicitud_Prenda_Sueldos;
use App\Services\Configuracion\MisAprobacionesArbolService;
use App\Services\Configuracion\UserTaskBandejaService;
use App\Services\Contable\AsientoAprobacionService;
use App\Services\Stock\PrestamoService;
use App\Services\Stock\TransferenciaMercaderiaService;
use App\Services\Sueldos\SolicitudPrendaService;
use App\Support\Seguridad\IngresoProveedorAutorizacionSupport;
use Illuminate\Http\Request;

class MisAprobacionesArbolController extends Controller
{
    public function __construct(
        private MisAprobacionesArbolService $misAprobacionesService,
        private UserTaskBandejaService $userTaskBandejaService,
        private SolicitudPrendaService $solicitudPrendaService,
        private PrestamoService $prestamoService,
        private AsientoAprobacionService $asientoAprobacionService,
        private TransferenciaMercaderiaService $transferenciaService,
    ) {}

    public function index(Request $request)
    {
        $this->asegurarAccesoBandeja();

        $usuarioId = (int) (auth()->id() ?? 0);
        $filtros = $this->filtrosDesdeRequest($request);

        $pendientes = $this->userTaskBandejaService->listarPendientes($usuarioId, $filtros);
        $tieneFiltros = $this->tieneFiltrosActivos($filtros);
        $totalSinFiltro = ! $tieneFiltros
            ? $pendientes->count()
            : $this->userTaskBandejaService->contarPendientes($usuarioId);

        $analyticsBase = $tieneFiltros
            ? $this->userTaskBandejaService->listarPendientes($usuarioId, array_filter([
                'fuente' => $filtros['fuente'] ?? null,
                'tipo' => $filtros['tipo'] ?? null,
            ]))
            : $pendientes;
        $analytics = $this->userTaskBandejaService->resumirCola($analyticsBase);

        $urgentes = $pendientes->where('urgencia', 'urgente')->count();
        $atencion = $pendientes->where('urgencia', 'atencion')->count();
        $huerfanos = $pendientes->where('documento_existe', false)->count();
        $countReemplazos = $pendientes->where('es_reemplazo', true)->count();

        $fuentesDisponibles = [];
        foreach ($this->userTaskBandejaService->fuentesDisponibles() as $codigo) {
            $fuentesDisponibles[] = [
                'valor' => $codigo,
                'nombre' => UserTaskBandejaService::FUENTES[$codigo] ?? $codigo,
            ];
        }

        return view('configuracion.mis_aprobaciones_arbol.bandeja', [
            'pendientes' => $pendientes,
            'totalPendientes' => $totalSinFiltro,
            'filtroTipo' => $filtros['tipo'] ?? '',
            'filtroFuente' => $filtros['fuente'] ?? '',
            'filtroQ' => $filtros['q'] ?? '',
            'filtroUrgencia' => $filtros['urgencia'] ?? '',
            'filtroReemplazo' => ! empty($filtros['reemplazo']),
            'filtroDiasMin' => (int) ($filtros['dias_min'] ?? 0),
            'filtroMontoMin' => (float) ($filtros['monto_min'] ?? 0),
            'tiposArbol' => $this->tiposFiltroBandeja(),
            'fuentesDisponibles' => $fuentesDisponibles,
            'countUrgentes' => $urgentes,
            'countAtencion' => $atencion,
            'countHuerfanos' => $huerfanos,
            'countReemplazos' => $countReemplazos,
            'analytics' => $analytics,
            'urlBandeja' => url('mis-aprobaciones'),
            'urlLimpiarHuerfanos' => url('mis-aprobaciones/limpiar-huerfanos'),
            'urlBulkAprobar' => url('mis-aprobaciones/bulk-aprobar'),
            'bulkMax' => (int) config('arbolaprobacion.bulk.max_items', 20),
            'bulkMontoMaxArbol' => (float) config('arbolaprobacion.bulk.monto_max_arbol', 100000),
            'puedeLimpiarHuerfanos' => can('aprobar-mis-aprobaciones-arbol', false),
        ]);
    }

    public function contador()
    {
        $this->asegurarAccesoBandeja();
        $usuarioId = (int) (auth()->id() ?? 0);

        return response()->json([
            'ok' => true,
            'count' => $this->userTaskBandejaService->contarPendientes($usuarioId),
        ]);
    }

    public function bulkAprobar(Request $request)
    {
        $this->asegurarAccesoBandeja();

        $fuente = strtolower(trim((string) $request->input('fuente', '')));
        $tipo = strtoupper(trim((string) $request->input('tipo', '')));
        $ids = $request->input('ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));

        $maxItems = max(1, (int) config('arbolaprobacion.bulk.max_items', 20));
        $fuentesOk = config('arbolaprobacion.bulk.fuentes', [
            'arbol', 'indumentaria', 'salida_bienes', 'asiento', 'transferencia', 'ingreso_proveedor',
        ]);
        $montoMaxArbol = (float) config('arbolaprobacion.bulk.monto_max_arbol', 100000);

        if (! in_array($fuente, $fuentesOk, true)) {
            return redirect()->to($this->urlRedirect($request))
                ->with('error', 'Fuente no permitida para aprobación masiva.');
        }
        if ($ids === []) {
            return redirect()->to($this->urlRedirect($request))
                ->with('error', 'Seleccioná al menos un pendiente.');
        }
        if (count($ids) > $maxItems) {
            return redirect()->to($this->urlRedirect($request))
                ->with('error', "Máximo {$maxItems} ítems por lote.");
        }
        if ($fuente === UserTaskBandejaService::FUENTE_ARBOL && $tipo === '') {
            return redirect()->to($this->urlRedirect($request))
                ->with('error', 'Para el árbol, la aprobación masiva exige un mismo tipo (RE, OC, …).');
        }

        $usuarioId = (int) (auth()->id() ?? 0);
        $pendientes = $this->userTaskBandejaService->listarPendientes($usuarioId, [
            'fuente' => $fuente,
            'tipo' => $fuente === UserTaskBandejaService::FUENTE_ARBOL ? $tipo : null,
        ])->keyBy(function (array $row) use ($fuente) {
            return $fuente === UserTaskBandejaService::FUENTE_ARBOL
                ? (int) ($row['movimiento_id'] ?? 0)
                : (int) ($row['comprobante_id'] ?? 0);
        });

        $ok = 0;
        $fallidos = [];

        foreach ($ids as $id) {
            $item = $pendientes->get($id);
            if (! $item) {
                $fallidos[] = "#{$id}: no está en tu cola";

                continue;
            }
            if (empty($item['puede_aprobar']) || empty($item['documento_existe']) || ! empty($item['es_aviso_pago'])) {
                $fallidos[] = ($item['numero'] ?? '#'.$id).': no aprobable';

                continue;
            }
            if (! empty($item['es_reemplazo'])) {
                $fallidos[] = ($item['numero'] ?? '#'.$id).': reemplazo (aprobar individual)';

                continue;
            }
            if ($fuente === UserTaskBandejaService::FUENTE_ARBOL) {
                if (strtoupper((string) ($item['tipo'] ?? '')) !== $tipo) {
                    $fallidos[] = ($item['numero'] ?? '#'.$id).': tipo distinto';

                    continue;
                }
                if ((float) ($item['monto'] ?? 0) > $montoMaxArbol) {
                    $fallidos[] = ($item['numero'] ?? '#'.$id).': monto supera el tope masivo';

                    continue;
                }
            }

            try {
                $this->aprobarItemBulk($fuente, $id, $usuarioId);
                $ok++;
            } catch (\Throwable $e) {
                $fallidos[] = ($item['numero'] ?? '#'.$id).': '.$e->getMessage();
            }
        }

        $this->userTaskBandejaService->invalidarContador();

        if ($ok > 0 && $fallidos === []) {
            return $this->redirectTrasAccion($request, $ok === 1
                ? '1 pendiente aprobado.'
                : "{$ok} pendientes aprobados.");
        }

        $msg = $ok > 0
            ? "{$ok} aprobados. Fallaron: ".implode(' · ', array_slice($fallidos, 0, 5))
            : 'No se pudo aprobar ninguno. '.implode(' · ', array_slice($fallidos, 0, 5));

        return redirect()
            ->to($this->urlRedirect($request))
            ->with($ok > 0 ? 'mensaje' : 'error', $msg);
    }

    private function aprobarItemBulk(string $fuente, int $id, int $usuarioId): void
    {
        match ($fuente) {
            UserTaskBandejaService::FUENTE_ARBOL => (function () use ($id, $usuarioId) {
                can('aprobar-mis-aprobaciones-arbol');
                $resultado = $this->misAprobacionesService->aprobar($id, $usuarioId, null);
                if (empty($resultado['aprobado_ok'])) {
                    throw new \RuntimeException('ya no estaba pendiente');
                }
            })(),
            UserTaskBandejaService::FUENTE_INDUMENTARIA => (function () use ($id, $usuarioId) {
                can('aprobar-solicitud-indumentaria');
                $solicitud = Solicitud_Prenda_Sueldos::findOrFail($id);
                $this->solicitudPrendaService->aprobar($solicitud, $usuarioId, null);
            })(),
            UserTaskBandejaService::FUENTE_SALIDA_BIENES => (function () use ($id, $usuarioId) {
                can('aprobar-recepcion-salida-bienes');
                $prestamo = $this->prestamoService->buscar($id);
                if (! $this->prestamoService->usuarioPuedeAprobarSalida($prestamo, $usuarioId)) {
                    throw new \RuntimeException('sin permiso de aprobador');
                }
                $this->prestamoService->aprobarRecepcion($id, $usuarioId, null);
            })(),
            UserTaskBandejaService::FUENTE_ASIENTO => (function () use ($id, $usuarioId) {
                can('aprobar-asiento-pendiente');
                $this->asientoAprobacionService->aprobar($id, $usuarioId, null);
            })(),
            UserTaskBandejaService::FUENTE_TRANSFERENCIA => (function () use ($id, $usuarioId) {
                can('aprobar-transferencia-mercaderia');
                $tm = $this->transferenciaService->buscar($id);
                if (! $this->transferenciaService->usuarioPuedeAprobarEnBandeja($tm, $usuarioId)) {
                    throw new \RuntimeException('sin permiso de aprobador');
                }
                $this->transferenciaService->aprobarRecepcion($id, $usuarioId, null);
            })(),
            UserTaskBandejaService::FUENTE_INGRESO_PROVEEDOR => (function () use ($id) {
                can('autorizar-ingreso-proveedor');
                IngresoProveedorAutorizacionSupport::autorizar($id);
            })(),
            default => throw new \InvalidArgumentException('Fuente no soportada'),
        };
    }

    public function detalle(Request $request, string $fuente, $id)
    {
        $this->asegurarAccesoBandeja();

        try {
            $detalle = $this->userTaskBandejaService->detalle(
                strtolower(trim($fuente)),
                (int) $id,
                (int) (auth()->id() ?? 0)
            );
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 404);
        }

        return response()->json(['ok' => true, 'detalle' => $detalle]);
    }

    public function aprobar(Request $request, $id)
    {
        can('aprobar-mis-aprobaciones-arbol');

        try {
            $resultado = $this->misAprobacionesService->aprobar(
                (int) $id,
                (int) (auth()->id() ?? 0),
                $request->input('observacion')
            );
        } catch (\Throwable $e) {
            return redirect()
                ->to($this->urlRedirect($request))
                ->with('error', $e->getMessage());
        }

        $tipo = (string) ($resultado['tipo'] ?? '');
        $numero = (string) ($resultado['numero'] ?? $id);
        $ok = ! empty($resultado['aprobado_ok']);
        $mensaje = $ok
            ? "Aprobación registrada: {$tipo} {$numero}. El circuito del árbol avanzó."
            : "No se pudo confirmar la aprobación de {$tipo} {$numero} (el pendiente ya no estaba disponible).";

        $this->userTaskBandejaService->invalidarContador();

        return redirect()
            ->to($this->urlRedirect($request))
            ->with($ok ? 'mensaje' : 'error', $mensaje);
    }

    public function rechazar(Request $request, $id)
    {
        can('aprobar-mis-aprobaciones-arbol');

        try {
            $this->misAprobacionesService->rechazar(
                (int) $id,
                (int) (auth()->id() ?? 0),
                $request->input('observacion')
            );
        } catch (\Throwable $e) {
            return redirect()
                ->to($this->urlRedirect($request))
                ->with('error', $e->getMessage());
        }

        return $this->redirectTrasAccion($request, 'Rechazo registrado.');
    }

    public function limpiarHuerfanos(Request $request)
    {
        can('aprobar-mis-aprobaciones-arbol');

        try {
            $stats = $this->misAprobacionesService->limpiarHuerfanos((int) (auth()->id() ?? 0));
        } catch (\Throwable $e) {
            return redirect()
                ->to($this->urlRedirect($request))
                ->with('error', $e->getMessage());
        }

        $n = (int) ($stats['limpiados'] ?? 0);
        $mensaje = $n === 0
            ? 'No había pendientes huérfanos para limpiar.'
            : ($n === 1
                ? 'Se descartó 1 pendiente sin comprobante.'
                : "Se descartaron {$n} pendientes sin comprobante.");

        return $this->redirectTrasAccion($request, $mensaje);
    }

    public function descartarHuerfano(Request $request, $id)
    {
        can('aprobar-mis-aprobaciones-arbol');

        try {
            $this->misAprobacionesService->descartarHuerfano((int) $id, (int) (auth()->id() ?? 0));
        } catch (\Throwable $e) {
            return redirect()
                ->to($this->urlRedirect($request))
                ->with('error', $e->getMessage());
        }

        return $this->redirectTrasAccion($request, 'Pendiente huérfano descartado.');
    }

    public function reenviar(Request $request, $id)
    {
        can('aprobar-mis-aprobaciones-arbol');

        try {
            $this->misAprobacionesService->reenviarCorreoDesdeBandeja(
                (int) $id,
                (int) (auth()->id() ?? 0)
            );
        } catch (\Throwable $e) {
            return redirect()
                ->to($this->urlRedirect($request))
                ->with('error', $e->getMessage());
        }

        return $this->redirectTrasAccion($request, 'Correo de aprobación reenviado.');
    }

    public function aprobarIndumentaria(Request $request, $id)
    {
        can('aprobar-solicitud-indumentaria');
        $solicitud = Solicitud_Prenda_Sueldos::findOrFail($id);

        try {
            $this->solicitudPrendaService->aprobar($solicitud, (int) (auth()->id() ?? 0), $request->input('observacion'));
        } catch (\Throwable $e) {
            return redirect()->to($this->urlRedirect($request))->with('error', $e->getMessage());
        }

        return $this->redirectTrasAccion($request, 'Solicitud de indumentaria #'.$solicitud->id.' aprobada.');
    }

    public function rechazarIndumentaria(Request $request, $id)
    {
        can('aprobar-solicitud-indumentaria');
        $solicitud = Solicitud_Prenda_Sueldos::findOrFail($id);

        try {
            $this->solicitudPrendaService->rechazar($solicitud, (int) (auth()->id() ?? 0), $request->input('observacion'));
        } catch (\Throwable $e) {
            return redirect()->to($this->urlRedirect($request))->with('error', $e->getMessage());
        }

        return $this->redirectTrasAccion($request, 'Solicitud de indumentaria #'.$solicitud->id.' rechazada.');
    }

    public function aprobarSalidaBienes(Request $request, $id)
    {
        can('aprobar-recepcion-salida-bienes');
        $usuarioId = (int) (auth()->id() ?? 0);
        $prestamo = $this->prestamoService->buscar((int) $id);

        if (! $this->prestamoService->usuarioPuedeAprobarSalida($prestamo, $usuarioId)) {
            return redirect()->to($this->urlRedirect($request))
                ->with('error', 'No estás asignado como aprobador de esta salida de bienes.');
        }

        try {
            $this->prestamoService->aprobarRecepcion((int) $id, $usuarioId, $request->input('observacion'));
        } catch (\Throwable $e) {
            return redirect()->to($this->urlRedirect($request))->with('error', $e->getMessage());
        }

        return $this->redirectTrasAccion($request, 'Salida de bienes aprobada.');
    }

    public function rechazarSalidaBienes(Request $request, $id)
    {
        can('rechazar-recepcion-salida-bienes');
        $usuarioId = (int) (auth()->id() ?? 0);
        $prestamo = $this->prestamoService->buscar((int) $id);

        if (! $this->prestamoService->usuarioPuedeAprobarSalida($prestamo, $usuarioId)) {
            return redirect()->to($this->urlRedirect($request))
                ->with('error', 'No estás asignado como aprobador de esta salida de bienes.');
        }

        try {
            $this->prestamoService->rechazarRecepcion((int) $id, $usuarioId, $request->input('observacion'));
        } catch (\Throwable $e) {
            return redirect()->to($this->urlRedirect($request))->with('error', $e->getMessage());
        }

        return $this->redirectTrasAccion($request, 'Salida de bienes rechazada.');
    }

    public function aprobarAsiento(Request $request, $id)
    {
        can('aprobar-asiento-pendiente');

        try {
            $this->asientoAprobacionService->aprobar((int) $id, (int) (auth()->id() ?? 0), $request->input('observacion'));
        } catch (\Throwable $e) {
            return redirect()->to($this->urlRedirect($request))->with('error', $e->getMessage());
        }

        return $this->redirectTrasAccion($request, 'Asiento aprobado.');
    }

    public function rechazarAsiento(Request $request, $id)
    {
        can('rechazar-asiento-pendiente');

        try {
            $this->asientoAprobacionService->rechazar((int) $id, (int) (auth()->id() ?? 0), $request->input('observacion'));
        } catch (\Throwable $e) {
            return redirect()->to($this->urlRedirect($request))->with('error', $e->getMessage());
        }

        return $this->redirectTrasAccion($request, 'Asiento rechazado.');
    }

    public function aprobarTransferencia(Request $request, $id)
    {
        can('aprobar-transferencia-mercaderia');
        $usuarioId = (int) (auth()->id() ?? 0);
        $tm = $this->transferenciaService->buscar((int) $id);

        if (! $this->transferenciaService->usuarioPuedeAprobarEnBandeja($tm, $usuarioId)) {
            return redirect()->to($this->urlRedirect($request))
                ->with('error', 'No estás asignado como aprobador de esta transferencia.');
        }

        try {
            $this->transferenciaService->aprobarRecepcion((int) $id, $usuarioId, $request->input('observacion'));
        } catch (\Throwable $e) {
            return redirect()->to($this->urlRedirect($request))->with('error', $e->getMessage());
        }

        return $this->redirectTrasAccion($request, 'Transferencia aprobada.');
    }

    public function rechazarTransferencia(Request $request, $id)
    {
        can('aprobar-transferencia-mercaderia');
        $usuarioId = (int) (auth()->id() ?? 0);
        $tm = $this->transferenciaService->buscar((int) $id);

        if (! $this->transferenciaService->usuarioPuedeAprobarEnBandeja($tm, $usuarioId)) {
            return redirect()->to($this->urlRedirect($request))
                ->with('error', 'No estás asignado como aprobador de esta transferencia.');
        }

        try {
            $this->transferenciaService->rechazarRecepcion((int) $id, $usuarioId, $request->input('observacion'));
        } catch (\Throwable $e) {
            return redirect()->to($this->urlRedirect($request))->with('error', $e->getMessage());
        }

        return $this->redirectTrasAccion($request, 'Transferencia rechazada.');
    }

    public function aprobarIngresoProveedor(Request $request, $id)
    {
        can('autorizar-ingreso-proveedor');

        try {
            IngresoProveedorAutorizacionSupport::autorizar((int) $id);
        } catch (\Throwable $e) {
            return redirect()->to($this->urlRedirect($request))->with('error', $e->getMessage());
        }

        return $this->redirectTrasAccion($request, 'Ingreso de proveedor autorizado.');
    }

    public function rechazarIngresoProveedor(Request $request, $id)
    {
        can('autorizar-ingreso-proveedor');

        try {
            IngresoProveedorAutorizacionSupport::rechazar(
                (int) $id,
                (string) ($request->input('observacion') ?? '')
            );
        } catch (\Throwable $e) {
            return redirect()->to($this->urlRedirect($request))->with('error', $e->getMessage());
        }

        return $this->redirectTrasAccion($request, 'Ingreso de proveedor rechazado.');
    }

    private function redirectTrasAccion(Request $request, string $mensaje, string $flash = 'mensaje')
    {
        $this->userTaskBandejaService->invalidarContador();

        return redirect()
            ->to($this->urlRedirect($request))
            ->with($flash, $mensaje);
    }

    private function asegurarAccesoBandeja(): void
    {
        if ($this->userTaskBandejaService->puedeAcceder()) {
            return;
        }

        // Fuerza el redirect estándar de can() cuando no tiene ninguna fuente.
        can('aprobar-mis-aprobaciones-arbol');
    }

    private function urlRedirect(Request $request): string
    {
        $url = url('mis-aprobaciones');
        $qs = [];
        foreach ($this->filtrosDesdeRequest($request) as $key => $value) {
            if ($value === null || $value === '' || $value === false || $value === 0 || $value === 0.0) {
                continue;
            }
            if ($key === 'reemplazo') {
                $qs['reemplazo'] = '1';

                continue;
            }
            $qs[$key] = $value;
        }
        if ($qs !== []) {
            $url .= '?'.http_build_query($qs);
        }

        return $url;
    }

    /**
     * Tipos del filtro de bandeja (incluye Suscripciones / SU del enum).
     *
     * @return list<array{id: string, valor: string, nombre: string}>
     */
    private function tiposFiltroBandeja(): array
    {
        return array_values(Arbolaprobacion::$enumTipoArbol);
    }

    /**
     * @return array{fuente?: string, tipo?: string, q?: string, urgencia?: string, reemplazo?: bool, dias_min?: int, monto_min?: float}
     */
    private function filtrosDesdeRequest(Request $request): array
    {
        $tipo = strtoupper(trim((string) $request->input('tipo', '')));
        $fuente = strtolower(trim((string) $request->input('fuente', '')));
        $q = trim((string) $request->input('q', ''));
        $urgencia = strtolower(trim((string) $request->input('urgencia', '')));
        $diasMin = max(0, (int) $request->input('dias_min', 0));
        $montoMin = max(0.0, (float) str_replace(',', '.', (string) $request->input('monto_min', '0')));
        $reemplazo = in_array((string) $request->input('reemplazo', ''), ['1', 'true', 'on', 'si'], true);

        return array_filter([
            'tipo' => $tipo !== '' ? $tipo : null,
            'fuente' => $fuente !== '' ? $fuente : null,
            'q' => $q !== '' ? $q : null,
            'urgencia' => in_array($urgencia, ['urgente', 'atencion', 'normal', 'vencido'], true) ? $urgencia : null,
            'reemplazo' => $reemplazo ?: null,
            'dias_min' => $diasMin > 0 ? $diasMin : null,
            'monto_min' => $montoMin > 0 ? $montoMin : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function tieneFiltrosActivos(array $filtros): bool
    {
        return $filtros !== [];
    }
}
