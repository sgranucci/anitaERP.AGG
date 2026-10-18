<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente;
use App\Services\Arca\ConstanciaInscripcionService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Valida padrón ARCA en operaciones de venta (pedido / factura clásica).
 * Solo se invoca desde el frontend vía ClienteController@validarPadronOperacion.
 * Clientes regularizados (estado R) pueden operar aunque el padrón muestre problemas.
 * Si el WS del padrón no responde, no bloquea: avisa y permite continuar (mismo criterio que WSAPOC).
 */
final class ArcaPadronClienteOperacionValidacionSupport
{
    public static function mensajeAvisoNoDisponible(): string
    {
        return 'Consulta al padrón ARCA no disponible por el momento. Puede continuar facturando; verifique el cliente cuando el servicio vuelva.';
    }

    /**
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     aviso?: string,
     *     error_servicio?: bool,
     *     validacion?: array<string, mixed>
     * }
     */
    public static function evaluarOperacion(object $cliente, ?int $condicionivaIdOverride = null): array
    {
        if (! filter_var(config('arca.padron_validacion_cliente.habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            return ['ok' => true, 'skipped' => true];
        }

        if ((string) ($cliente->estado ?? '') === Cliente::ESTADO_REGULARIZADO) {
            return ['ok' => true, 'regularizado' => true];
        }

        $condicionivaId = $condicionivaIdOverride ?? (int) ($cliente->condicioniva_id ?? 0);

        if (! ArcaPadronImpuestosClienteValidacion::aplicaParaCondicionIva($condicionivaId)) {
            return ['ok' => true, 'skipped' => true];
        }

        $cuit = preg_replace('/\D+/', '', (string) ($cliente->numerodocumento ?? ''));
        if (strlen($cuit) !== 11) {
            return [
                'ok' => false,
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
                    'ok' => false,
                    'error' => (string) ($validacion['mensaje'] ?? 'Problemas en ARCA: el cliente no cumple la validación del padrón.'),
                    'validacion' => $validacion,
                ];
            }

            return ['ok' => true, 'validacion' => $validacion];
        } catch (Throwable $e) {
            Log::warning('Padrón ARCA operación venta: servicio no disponible (no bloquea)', [
                'cliente_id' => $cliente->id ?? null,
                'cuit' => $cuit,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => true,
                'aviso' => self::mensajeAvisoNoDisponible(),
                'error_servicio' => true,
                'error_detalle' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{error: string, validacion?: array<string, mixed>}|null  null = operación permitida
     */
    public static function bloqueoOperacion(object $cliente, ?int $condicionivaIdOverride = null): ?array
    {
        $resultado = self::evaluarOperacion($cliente, $condicionivaIdOverride);

        if ($resultado['ok'] ?? false) {
            return null;
        }

        return [
            'error' => (string) ($resultado['error'] ?? 'Problemas en ARCA: el cliente no cumple la validación del padrón.'),
            'validacion' => $resultado['validacion'] ?? null,
        ];
    }
}
