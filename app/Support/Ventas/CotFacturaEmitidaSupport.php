<?php

namespace App\Support\Ventas;

/**
 * Identidad de factura en filas COT (grilla / persistencia) y prioridad al fusionar.
 * Una misma FAC no debe generar dos COT aunque el remito se regeneró con otro número.
 */
final class CotFacturaEmitidaSupport
{
    /**
     * @param  array{tipo: string, letra: string, sucursal: int, numero: int}|null  $identidad
     */
    public static function clave(?array $identidad): string
    {
        if ($identidad === null) {
            return '';
        }

        $tipo = strtoupper(trim((string) ($identidad['tipo'] ?? '')));
        $letra = strtoupper(trim((string) ($identidad['letra'] ?? '')));
        $sucursal = (int) ($identidad['sucursal'] ?? 0);
        $numero = (int) ($identidad['numero'] ?? 0);

        if ($tipo === '' || $letra === '' || $numero < 1) {
            return '';
        }

        if (CotGuiaFacturaIdentidadSupport::pareceRemito($tipo, $letra)) {
            return '';
        }

        return implode('|', [$tipo, $letra, $sucursal, $numero]);
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{tipo: string, letra: string, sucursal: int, numero: int}|null
     */
    public static function identidadDesdeFila(array $fila): ?array
    {
        $directa = $fila['factura_identidad'] ?? null;
        if (is_array($directa)) {
            $norm = CotGuiaFacturaIdentidadSupport::normalizar(
                (string) ($directa['tipo'] ?? ''),
                (string) ($directa['letra'] ?? ''),
                (int) ($directa['sucursal'] ?? 0),
                (int) ($directa['numero'] ?? 0),
            );
            if (self::clave($norm) !== '') {
                return $norm;
            }
        }

        $anita = $fila['anita'] ?? null;
        if (is_array($anita)) {
            $norm = CotGuiaFacturaIdentidadSupport::normalizar(
                (string) ($anita['tipo'] ?? ''),
                (string) ($anita['letra'] ?? ''),
                (int) ($anita['sucursal_factura'] ?? $anita['sucursal'] ?? 0),
                (int) ($anita['nro_fact'] ?? $anita['numero'] ?? 0),
            );
            if (self::clave($norm) !== '') {
                return $norm;
            }
        }

        foreach (['factura_tipo', 'tipo_factura'] as $tipoKey) {
            if (! empty($fila[$tipoKey]) && isset($fila['factura_numero'])) {
                $norm = CotGuiaFacturaIdentidadSupport::normalizar(
                    (string) $fila[$tipoKey],
                    (string) ($fila['factura_letra'] ?? 'A'),
                    (int) ($fila['factura_sucursal'] ?? 0),
                    (int) ($fila['factura_numero'] ?? 0),
                );
                if (self::clave($norm) !== '') {
                    return $norm;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function claveDesdeFila(array $fila): string
    {
        return self::clave(self::identidadDesdeFila($fila));
    }

    /**
     * Menor = preferido al fusionar remitos de la misma factura.
     * ERP gana sobre Anita (remito regenerado tras problemas).
     *
     * @param  array<string, mixed>  $fila
     */
    public static function prioridadOrigen(array $fila): int
    {
        return match ((string) ($fila['origen'] ?? '')) {
            'erp' => 1,
            'anita_pendmae' => 2,
            'anita_comprob' => 3,
            default => 9,
        };
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, numero: int}  $identidad
     */
    public static function etiqueta(array $identidad): string
    {
        return sprintf(
            '%s %s-%04d-%08d',
            $identidad['tipo'],
            $identidad['letra'],
            $identidad['sucursal'],
            $identidad['numero']
        );
    }
}
