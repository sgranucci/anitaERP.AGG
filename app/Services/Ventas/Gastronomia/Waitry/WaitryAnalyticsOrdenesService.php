<?php

namespace App\Services\Ventas\Gastronomia\Waitry;

use App\Support\Ventas\Waitry\WaitryOrdenCobroSupport;

/**
 * Órdenes Waitry vía analytics/getordersdetails (reporte por fecha).
 * Usado en cierre de jornada tesorería; el POS en vivo usa getOrdersPOS (pág. 21).
 */
final class WaitryAnalyticsOrdenesService
{
    public function __construct(
        private readonly WaitryHttpClient $httpClient,
        private readonly WaitryAuthService $authService,
    ) {
    }

    /**
     * @return array{ok:bool,ordenes?:list<array<string,mixed>>,error?:string}
     */
    public function ordenesPorRangoFecha(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        if (! config('waitry.habilitado', false)) {
            return ['ok' => false, 'error' => 'Integración Waitry deshabilitada.'];
        }

        if (! $this->authService->credencialesCompletas()) {
            return ['ok' => false, 'error' => 'Waitry: credenciales incompletas.'];
        }

        $placeId = $this->resolverPlaceId($empresaId);
        if ($placeId === null) {
            return ['ok' => false, 'error' => 'Waitry: no hay placeId para la empresa '.$empresaId.'.'];
        }

        $fechaDesde = trim($fechaDesde);
        $fechaHasta = trim($fechaHasta);
        if ($fechaDesde === '' || $fechaHasta === '') {
            return ['ok' => false, 'error' => 'Debe indicar el rango de fechas.'];
        }

        $url = (string) config('waitry.get_orders_details_url');
        $resultado = $this->httpClient->postJson($url, [
            'placeId' => $placeId,
            'from' => $fechaDesde,
            'to' => $fechaHasta,
        ], 'get_orders_details');

        if (! ($resultado['ok'] ?? false)) {
            return [
                'ok' => false,
                'error' => $resultado['error'] ?? 'Error al consultar órdenes Waitry (getordersdetails).',
            ];
        }

        $data = is_array($resultado['data'] ?? null) ? $resultado['data'] : [];
        if (($data['ok'] ?? true) === false) {
            return [
                'ok' => false,
                'error' => $this->mensajeErrorPayload($data) ?: 'Waitry getordersdetails rechazó la consulta.',
            ];
        }

        $ordenes = [];
        foreach ($this->extraerOrdenesDesdePayload($data) as $orden) {
            if (! is_array($orden)) {
                continue;
            }
            $ordenes[] = $this->normalizarOrden($orden);
        }

        return ['ok' => true, 'ordenes' => $ordenes];
    }

    /**
     * @param  array<string, mixed>  $orden
     * @return array<string, mixed>
     */
    public function normalizarOrden(array $orden): array
    {
        $orderId = (int) ($orden['orderId'] ?? $orden['id'] ?? 0);
        $placedAt = $orden['placed_at'] ?? null;
        if (($placedAt === null || $placedAt === '') && isset($orden['timestamp']['date'])) {
            $placedAt = $orden['timestamp']['date'];
        }

        $total = isset($orden['totalAmount']) && is_numeric($orden['totalAmount'])
            ? round((float) $orden['totalAmount'], 2)
            : 0.0;
        if ($total <= 0.0001) {
            $total = WaitryOrdenCobroSupport::montoCobro($orden);
        }

        $paid = $orden['paid'] ?? null;
        if ($paid === null) {
            $paid = WaitryOrdenCobroSupport::cobradaEnTotem($orden);
        }

        return array_merge($orden, [
            'id' => $orderId,
            'orderId' => $orderId,
            'placed_at' => $placedAt,
            'display_id' => $orden['display_id'] ?? $orden['externalDeliveryId'] ?? null,
            'external_reference_id' => $orden['external_reference_id'] ?? $orden['externalId'] ?? null,
            'totalAmount' => $total,
            'paid' => $paid,
        ]);
    }

    /**
     * @param  mixed  $data
     * @return list<array<string, mixed>>
     */
    private function extraerOrdenesDesdePayload($data): array
    {
        if (! is_array($data)) {
            return [];
        }

        $ordenes = $data['orders'] ?? $data['response']['orders'] ?? $data['response'] ?? null;
        if (is_array($ordenes) && isset($ordenes['orderId']) && ! isset($ordenes[0])) {
            $ordenes = [$ordenes];
        }
        if (is_array($ordenes) && isset($ordenes['id']) && ! isset($ordenes[0]) && ! isset($ordenes['orderId'])) {
            $ordenes = [$ordenes];
        }
        if (! is_array($ordenes)) {
            return [];
        }

        $lista = [];
        foreach ($ordenes as $orden) {
            if (is_array($orden)) {
                $lista[] = $orden;
            }
        }

        return $lista;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mensajeErrorPayload(array $data): string
    {
        $partes = [];
        foreach (['message', 'msg', 'error'] as $clave) {
            if (! empty($data[$clave]) && is_string($data[$clave])) {
                $partes[] = trim($data[$clave]);
            }
        }

        return implode(' ', array_unique($partes));
    }

    private function resolverPlaceId(int $empresaId): ?int
    {
        $map = config('waitry.place_id_por_empresa', []);
        if (! is_array($map)) {
            return null;
        }

        $placeId = (int) ($map[$empresaId] ?? 0);

        return $placeId > 0 ? $placeId : null;
    }
}
