<?php

namespace App\Support\Ventas\Waitry;

/**
 * Circuitos de negocio en la conciliación Waitry ↔ Anita (tesorería).
 */
final class WaitryConciliacionCircuitoSupport
{
    /** Importada en Anita (cuenta Waitry) y cobrada en Waitry. */
    public const CIRCUITO_TOTEM_IMPORTADA_COBRADA = 'totem_importada_cobrada';

    /** Importada en Anita, impaga en Waitry (criterio getOrdersPOS). */
    public const CIRCUITO_IMPORTADA_IMPAGA_WAITRY = 'importada_impaga_waitry';

    /** Importada, impaga en Waitry, cobrada en Anita (revisar medio Waitry vs Anita). */
    public const CIRCUITO_TOTEM_IMPORTADA_IMPAGA_COBRADA_ANITA = 'totem_importada_impaga_cobrada_anita';

    /** Factura generada en Anita y comanda enviada a Waitry (no importación). */
    public const CIRCUITO_ANITA_FACTURA_WAITRY = 'anita_factura_waitry';

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function resolverCircuito(array $fila): ?string
    {
        if (self::esImportadaCobradaWaitry($fila)) {
            return self::CIRCUITO_TOTEM_IMPORTADA_COBRADA;
        }

        if (self::esImportadaImpagaCobradaAnita($fila)) {
            return self::CIRCUITO_TOTEM_IMPORTADA_IMPAGA_COBRADA_ANITA;
        }

        if (self::esImportadaImpagaWaitry($fila)) {
            return self::CIRCUITO_IMPORTADA_IMPAGA_WAITRY;
        }

        if (self::esAnitaFacturaWaitry($fila)) {
            return self::CIRCUITO_ANITA_FACTURA_WAITRY;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function esImportadaCobradaWaitry(array $fila): bool
    {
        if (! self::esImportadaEnAnita($fila)) {
            return false;
        }

        return ($fila['waitry_paid'] ?? null) === true;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function esImportadaImpagaWaitry(array $fila): bool
    {
        if (! self::esImportadaEnAnita($fila)) {
            return false;
        }

        return ($fila['waitry_paid'] ?? null) === false;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function esImportadaImpagaCobradaAnita(array $fila): bool
    {
        if (! self::esImportadaImpagaWaitry($fila)) {
            return false;
        }

        return (int) ($fila['anita_cuentacaja_id'] ?? 0) > 0;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function esAnitaFacturaWaitry(array $fila): bool
    {
        if (self::esImportadaEnAnita($fila)) {
            return false;
        }

        if (empty($fila['anita_venta_id'])) {
            return false;
        }

        return (int) ($fila['waitry_order_id'] ?? 0) > 0;
    }

    /**
     * Cuenta gastronomía Waitry en ERP (importación desde kiosco / getOrdersPOS).
     *
     * @param  array<string, mixed>  $fila
     */
    public static function esImportadaEnAnita(array $fila): bool
    {
        if (! empty($fila['importada_erp'])) {
            return true;
        }

        if (! empty($fila['cuenta_pendiente_id']) || ! empty($fila['cuenta_importada_id'])) {
            return true;
        }

        return ($fila['estado'] ?? '') === 'importada_pendiente';
    }

    public static function etiquetaCircuito(?string $circuito): ?string
    {
        return match ($circuito) {
            self::CIRCUITO_TOTEM_IMPORTADA_COBRADA => 'Importada Anita — cobrada en Waitry',
            self::CIRCUITO_IMPORTADA_IMPAGA_WAITRY => 'Importada Anita — impaga en Waitry',
            self::CIRCUITO_TOTEM_IMPORTADA_IMPAGA_COBRADA_ANITA => 'Importada Anita — impaga Waitry, cobrada Anita',
            self::CIRCUITO_ANITA_FACTURA_WAITRY => 'Anita → Waitry (factura Anita)',
            default => null,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, array{cantidad:int,total_waitry:float,total_anita:float,etiqueta:string}>
     */
    public static function resumenPorCircuito(array $filas): array
    {
        $bloques = [];
        foreach ([
            self::CIRCUITO_TOTEM_IMPORTADA_COBRADA,
            self::CIRCUITO_IMPORTADA_IMPAGA_WAITRY,
            self::CIRCUITO_TOTEM_IMPORTADA_IMPAGA_COBRADA_ANITA,
            self::CIRCUITO_ANITA_FACTURA_WAITRY,
        ] as $clave) {
            $bloques[$clave] = [
                'cantidad' => 0,
                'total_waitry' => 0.0,
                'total_anita' => 0.0,
                'etiqueta' => self::etiquetaCircuito($clave) ?? '',
            ];
        }

        foreach ($filas as $fila) {
            $circuito = (string) ($fila['circuito_conciliacion'] ?? '');
            if ($circuito === '' || ! isset($bloques[$circuito])) {
                continue;
            }

            $bloques[$circuito]['cantidad']++;
            $waitry = self::montoWaitryFila($fila);
            if ($waitry !== null) {
                $bloques[$circuito]['total_waitry'] = round(
                    $bloques[$circuito]['total_waitry'] + $waitry,
                    2,
                );
            }
            if ($fila['anita_total'] !== null) {
                $bloques[$circuito]['total_anita'] = round(
                    $bloques[$circuito]['total_anita'] + (float) $fila['anita_total'],
                    2,
                );
            }
        }

        return $bloques;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function montoWaitryFila(array $fila): ?float
    {
        if ($fila['waitry_total'] !== null && (float) $fila['waitry_total'] > 0.0001) {
            return round((float) $fila['waitry_total'], 2);
        }

        if ($fila['anita_total'] !== null && (float) $fila['anita_total'] > 0.0001) {
            return round((float) $fila['anita_total'], 2);
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public static function enriquecerFilas(array $filas): array
    {
        foreach ($filas as $i => $fila) {
            $circuito = self::resolverCircuito($fila);
            $filas[$i]['circuito_conciliacion'] = $circuito;
            $filas[$i]['circuito_label'] = self::etiquetaCircuito($circuito);
        }

        return $filas;
    }
}
