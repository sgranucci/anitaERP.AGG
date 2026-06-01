<?php

namespace App\Support\Ventas\Waitry;

/**
 * Filas del comprobante de cierre: solo casos que requieren revisión en caja/auditoría.
 */
final class WaitryCierreJornadaDiscrepanciaSupport
{
    /**
     * @param  array<string, mixed>  $ln  Línea armada por GastronomiaCierreTotemJornadaService
     */
    public static function esDiscrepancia(array $ln): bool
    {
        $motivo = self::motivoDiscrepancia($ln);

        return $motivo !== null;
    }

    /**
     * @param  array<string, mixed>  $ln
     */
    public static function motivoDiscrepancia(array $ln): ?string
    {
        if (! empty($ln['discrepancia_gap'])) {
            return 'Hueco en secuencia Waitry (pendiente auditoría del día)';
        }

        if (($ln['fuente_listado'] ?? '') === 'erp') {
            return 'Presente en ERP, ausente en Waitry del rango consultado';
        }

        if (($ln['paid_waitry'] ?? null) === false) {
            return 'Impaga en Waitry';
        }

        if (($ln['paid_waitry'] ?? null) === true && empty($ln['importada_erp'])) {
            return 'Cobrada en tótem, no importada en ERP';
        }

        if (($ln['paid_waitry'] ?? null) === true
            && ! empty($ln['importada_erp'])
            && empty($ln['facturada_erp'])
            && ! empty($ln['waitry_cobro_totem'])) {
            return 'Cobrada en tótem, sin facturar en ERP';
        }

        if (($ln['paid_waitry'] ?? null) === true
            && ! empty($ln['importada_erp'])
            && empty($ln['facturada_erp'])
            && empty($ln['waitry_cobro_totem'])) {
            return 'Pagada en Waitry, cuenta sin facturar';
        }

        if (($ln['paid_waitry'] ?? null) === null && empty($ln['importada_erp'])) {
            return 'Estado de pago Waitry desconocido, no importada';
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    public static function filtrar(array $lineas): array
    {
        $out = [];
        foreach ($lineas as $ln) {
            $motivo = self::motivoDiscrepancia($ln);
            if ($motivo === null) {
                continue;
            }
            $ln['motivo_discrepancia'] = $motivo;
            $out[] = $ln;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $auditoria
     */
    public static function hayDiscrepanciasAuditoria(array $auditoria, int $cantidadLineasDiscrepancia): bool
    {
        if ($cantidadLineasDiscrepancia > 0) {
            return true;
        }

        if (count($auditoria['ids_huecos_secuencia'] ?? $auditoria['ids_gap_sin_recuperar'] ?? []) > 0) {
            return true;
        }

        return false;
    }
}
