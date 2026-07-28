<?php

namespace App\Support\Ai;

use App\Models\Ai\AiDecision;
use App\Services\Compras\RequisicionService;
use App\Services\Sala\RequisicionSalaService;
use Auth;
use Illuminate\Http\Request;

/**
 * Confirma borradores de pedido por consumo (HITL) creando RQ compra o sala.
 */
final class PedidoConsumoSectorConfirmacionSupport
{
    public function __construct(
        private RequisicionService $requisicionService,
        private RequisicionSalaService $requisicionSalaService,
    ) {}

    /**
     * @param  array<string,mixed>  $borrador
     * @return array{ok: bool, message: string, documento?: string, id?: int, numero?: mixed, error?: string}
     */
    public function confirmar(string $tipo, array $borrador, ?int $decisionId = null): array
    {
        $tipo = strtolower(trim($tipo));
        if ($tipo === PedidoConsumoSectorProyeccionSupport::DOCUMENTO_COMPRA) {
            return $this->confirmarCompra($borrador, $decisionId);
        }
        if ($tipo === PedidoConsumoSectorProyeccionSupport::DOCUMENTO_SALA) {
            return $this->confirmarSala($borrador, $decisionId);
        }

        return ['ok' => false, 'message' => 'Tipo de documento inválido (compra|sala).', 'error' => 'tipo'];
    }

    /**
     * @param  array<string,mixed>  $borrador
     * @return array{ok: bool, message: string, documento?: string, id?: int, numero?: mixed, error?: string}
     */
    private function confirmarCompra(array $borrador, ?int $decisionId): array
    {
        if (! can('crear-requisicion', false)) {
            return ['ok' => false, 'message' => 'Sin permiso para crear requisición de compra.', 'error' => 'permiso'];
        }

        $articuloIds = $borrador['articulo_ids'] ?? [];
        $cantidades = $borrador['cantidades'] ?? [];
        if (! is_array($articuloIds) || $articuloIds === [] || ! is_array($cantidades)) {
            return ['ok' => false, 'message' => 'Borrador de compra sin líneas.', 'error' => 'borrador'];
        }

        $payload = [
            'fecha' => $borrador['fecha'] ?? now()->toDateString(),
            'fechaentrega' => $borrador['fechaentrega'] ?? now()->addDays(7)->toDateString(),
            'empresa_id' => (int) ($borrador['empresa_id'] ?? 0),
            'centrocosto_id' => (int) ($borrador['centrocosto_id'] ?? 0),
            'comentario' => (string) ($borrador['comentario'] ?? 'Sugerido por IA'),
            'detalle' => (string) ($borrador['detalle'] ?? ''),
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'centrocostodestino_ids' => $borrador['centrocostodestino_ids']
                ?? array_fill(0, count($articuloIds), (int) ($borrador['centrocosto_id'] ?? 0)),
            'proveedor_ids' => $borrador['proveedor_ids'] ?? array_fill(0, count($articuloIds), ''),
            'detalles' => $borrador['detalles'] ?? array_fill(0, count($articuloIds), ''),
            'colores_id' => $borrador['colores_id'] ?? array_fill(0, count($articuloIds), ''),
            'talles_id' => $borrador['talles_id'] ?? array_fill(0, count($articuloIds), ''),
            'precios' => array_fill(0, count($articuloIds), 0),
            'moneda_ids' => array_fill(0, count($articuloIds), ''),
            'partidagasto_ids' => array_fill(0, count($articuloIds), ''),
            'capex_ids' => array_fill(0, count($articuloIds), ''),
        ];

        if ($payload['empresa_id'] <= 0 || $payload['centrocosto_id'] <= 0) {
            return ['ok' => false, 'message' => 'Borrador incompleto (empresa / centro de costo).', 'error' => 'borrador'];
        }

        $request = Request::create('/compras/requisicion', 'POST', $payload);
        $request->setUserResolver(static fn () => Auth::user());

        $resultado = $this->requisicionService->guardaRequisicion($request);
        if (($resultado['mensaje'] ?? '') !== 'ok') {
            return [
                'ok' => false,
                'message' => (string) ($resultado['errores'] ?? 'No se pudo crear la requisición de compra.'),
                'error' => 'servicio',
            ];
        }

        $id = (int) ($resultado['requisicion_id'] ?? 0);
        $this->cerrarDecision($decisionId, AiDecision::ACCION_CONFIRMADA, [
            'documento' => 'compra',
            'requisicion_id' => $id,
        ]);

        return [
            'ok' => true,
            'message' => 'Requisición de compra creada desde sugerencia IA.',
            'documento' => 'compra',
            'id' => $id,
        ];
    }

    /**
     * @param  array<string,mixed>  $borrador
     * @return array{ok: bool, message: string, documento?: string, id?: int, numero?: mixed, error?: string}
     */
    private function confirmarSala(array $borrador, ?int $decisionId): array
    {
        if (! can('crear-requisicion-sala', false)) {
            return ['ok' => false, 'message' => 'Sin permiso para crear requisición de sala.', 'error' => 'permiso'];
        }

        $articuloIds = $borrador['articulo_ids'] ?? [];
        $cantidades = $borrador['cantidades'] ?? [];
        if (! is_array($articuloIds) || $articuloIds === [] || ! is_array($cantidades)) {
            return ['ok' => false, 'message' => 'Borrador de sala sin líneas.', 'error' => 'borrador'];
        }

        $payload = [
            'fecha' => $borrador['fecha'] ?? now()->toDateString(),
            'fecha_entrega' => $borrador['fecha_entrega'] ?? now()->addDays(7)->toDateString(),
            'empresa_id' => (int) ($borrador['empresa_id'] ?? 0),
            'centrocosto_id' => (int) ($borrador['centrocosto_id'] ?? 0),
            'deposito_id' => (int) ($borrador['deposito_id'] ?? 0),
            'comentario' => (string) ($borrador['comentario'] ?? 'Sugerido por IA'),
            'detalle' => (string) ($borrador['detalle'] ?? ''),
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'detalles' => $borrador['detalles'] ?? array_fill(0, count($articuloIds), ''),
            'colores_id' => $borrador['colores_id'] ?? array_fill(0, count($articuloIds), ''),
            'talles_id' => $borrador['talles_id'] ?? array_fill(0, count($articuloIds), ''),
        ];

        if ($payload['empresa_id'] <= 0 || $payload['centrocosto_id'] <= 0 || $payload['deposito_id'] <= 0) {
            return ['ok' => false, 'message' => 'Borrador incompleto (empresa / CC / depósito).', 'error' => 'borrador'];
        }

        $request = Request::create('/sala/requisicion-sala', 'POST', $payload);
        $request->setUserResolver(static fn () => Auth::user());

        $resultado = $this->requisicionSalaService->guardaRequisicionSala($request);
        if (($resultado['mensaje'] ?? '') !== 'ok') {
            return [
                'ok' => false,
                'message' => (string) ($resultado['errores'] ?? 'No se pudo crear la requisición de sala.'),
                'error' => 'servicio',
            ];
        }

        $this->cerrarDecision($decisionId, AiDecision::ACCION_CONFIRMADA, [
            'documento' => 'sala',
            'requisicion_sala' => true,
        ]);

        return [
            'ok' => true,
            'message' => 'Requisición de sala creada desde sugerencia IA.',
            'documento' => 'sala',
        ];
    }

    /** @param  array<string,mixed>  $extra */
    private function cerrarDecision(?int $decisionId, string $accion, array $extra = []): void
    {
        if ($decisionId === null || $decisionId <= 0) {
            return;
        }

        try {
            $decision = AiDecision::query()->find($decisionId);
            if (! $decision) {
                return;
            }
            $payload = is_array($decision->payload) ? $decision->payload : [];
            $payload['confirmacion'] = $extra;
            $decision->accion = $accion;
            $decision->payload = $payload;
            $decision->resuelto_por = Auth::id();
            $decision->resuelto_at = now();
            $decision->save();
        } catch (\Throwable) {
            // no romper el flujo de negocio
        }
    }
}
