<?php

namespace App\Support\Ventas\Waitry;

/**
 * Código alfanumérico del papelito Waitry (display_id, external_reference, etc.).
 */
final class WaitryDisplayIdSupport
{
    /**
     * @param  array<string, mixed>  $orden
     */
    public static function extraerDesdeOrden(array $orden): string
    {
        foreach (['display_id', 'displayId', 'external_reference_id', 'externalDeliveryId'] as $campo) {
            $valor = trim((string) ($orden[$campo] ?? ''));
            if ($valor !== '') {
                return $valor;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $data  Respuesta completa de pushExternalOrder.
     */
    public static function extraerDesdeRespuestaPush(array $data): string
    {
        $response = $data['response'] ?? null;
        if (is_array($response)) {
            $desdeResponse = self::extraerDesdeOrden($response);
            if ($desdeResponse !== '') {
                return $desdeResponse;
            }
        }

        return self::extraerDesdeOrden($data);
    }
}
