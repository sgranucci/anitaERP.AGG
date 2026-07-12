<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente;
use Throwable;

/**
 * Bloqueo WSAPOC en operaciones de venta administrativas (pedido / factura clásica).
 * No aplica bypass por cliente regularizado: APOC es bloqueo absoluto.
 */
final class ArcaApocClienteOperacionValidacionSupport
{
    /**
     * @return array{error: string, validacion?: array<string, mixed>}|null  null = operación permitida
     */
    public static function bloqueoOperacion(object $cliente, bool $consultarWs = true): ?array
    {
        $support = app(ClienteFacturasApocrifasSupport::class);
        if (! $support->habilitadoParaFactura()) {
            return null;
        }

        if ((bool) ($cliente->facturas_apocrifas ?? false)) {
            $detalle = self::mensajeDesdeDetalleAlmacenado($cliente);

            return [
                'error' => $detalle ?: 'El cliente figura en la base de facturas apócrifas de ARCA (WSAPOC). No puede facturarse.',
                'validacion' => [
                    'aplica' => true,
                    'ok' => false,
                    'es_apocrifo' => true,
                    'mensaje' => $detalle,
                    'detalles' => [],
                ],
            ];
        }

        $cuit = preg_replace('/\D+/', '', (string) ($cliente->numerodocumento ?? ''));
        if (strlen($cuit) !== 11) {
            return [
                'error' => 'El cliente no tiene CUIT válida (11 dígitos) para consultar facturas apócrifas en ARCA.',
            ];
        }

        if (! $consultarWs) {
            return null;
        }

        try {
            $validacion = $support->evaluarCliente($cliente instanceof Cliente ? $cliente : Cliente::query()->find($cliente->id), suspenderSiApocrifo: true);

            if (($validacion['es_apocrifo'] ?? false) || (($validacion['aplica'] ?? false) && ! ($validacion['ok'] ?? false))) {
                return [
                    'error' => (string) ($validacion['mensaje'] ?? 'El cliente figura en la base de facturas apócrifas de ARCA (WSAPOC).'),
                    'validacion' => $validacion,
                ];
            }

            return null;
        } catch (Throwable $e) {
            return [
                'error' => 'No se pudo consultar facturas apócrifas en ARCA: '.$e->getMessage(),
            ];
        }
    }

    private static function mensajeDesdeDetalleAlmacenado(object $cliente): string
    {
        $raw = $cliente->facturas_apocrifas_detalle ?? null;
        if (! is_string($raw) || trim($raw) === '') {
            return '';
        }

        $json = json_decode($raw, true);
        if (! is_array($json)) {
            return '';
        }

        return trim((string) ($json['mensaje'] ?? ''));
    }
}
