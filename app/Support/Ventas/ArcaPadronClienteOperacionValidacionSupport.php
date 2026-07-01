<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente;
use App\Services\Arca\ConstanciaInscripcionService;
use Throwable;

/**
 * Valida padrón ARCA en operaciones de venta (pedido / factura clásica).
 * Solo se invoca desde el frontend vía ClienteController@validarPadronOperacion.
 * Clientes regularizados (estado R) pueden operar aunque el padrón muestre problemas.
 */
final class ArcaPadronClienteOperacionValidacionSupport
{
    /**
     * @return array{error: string, validacion?: array<string, mixed>}|null  null = operación permitida
     */
    public static function bloqueoOperacion(object $cliente, ?int $condicionivaIdOverride = null): ?array
    {
        if (! filter_var(config('arca.padron_validacion_cliente.habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        if ((string) ($cliente->estado ?? '') === Cliente::ESTADO_REGULARIZADO) {
            return null;
        }

        $condicionivaId = $condicionivaIdOverride ?? (int) ($cliente->condicioniva_id ?? 0);

        if (! ArcaPadronImpuestosClienteValidacion::aplicaParaCondicionIva($condicionivaId)) {
            return null;
        }

        $cuit = preg_replace('/\D+/', '', (string) ($cliente->numerodocumento ?? ''));
        if (strlen($cuit) !== 11) {
            return [
                'error' => 'El cliente no tiene CUIT válida (11 dígitos) para consultar el padrón ARCA.',
            ];
        }

        try {
            $padronData = app(ConstanciaInscripcionService::class)->getPersonaV2($cuit);
            $validacion = ArcaPadronImpuestosClienteValidacion::validar(
                $condicionivaId > 0 ? $condicionivaId : null,
                $padronData
            );

            if (($validacion['aplica'] ?? false) && ! ($validacion['ok'] ?? false)) {
                return [
                    'error' => (string) ($validacion['mensaje'] ?? 'Problemas en ARCA: el cliente no cumple la validación del padrón.'),
                    'validacion' => $validacion,
                ];
            }

            return null;
        } catch (Throwable $e) {
            return [
                'error' => 'No se pudo consultar el padrón ARCA: '.$e->getMessage(),
            ];
        }
    }
}
