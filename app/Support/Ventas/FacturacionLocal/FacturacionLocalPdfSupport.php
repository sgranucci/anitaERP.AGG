<?php

declare(strict_types=1);

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Venta;
use App\Support\Ventas\FacturaPdfMembreteSupport;
use Illuminate\Support\Facades\Schema;

/**
 * PDF de factura del canal Facturación Local (POS): membrete por local y leyendas propias.
 * No altera el membrete de fábrica / mayorista (factura_pdf_parametro por empresa).
 */
final class FacturacionLocalPdfSupport
{
    /**
     * Texto estándar de defensa al consumidor (pie FAC local).
     */
    public const TEXTO_DEFENSA_CONSUMIDOR = 'Defensa de las y los Consumidores. Para reclamos ingrese a www.argentina.gob.ar/produccion/defensadelconsumidor o comuníquese al 0800-666-1518.';

    public static function esVentaLocal(?object $venta): bool
    {
        if ($venta === null) {
            return false;
        }
        $ventaId = (int) ($venta->id ?? 0);
        if ($ventaId <= 0) {
            return false;
        }

        if ($venta instanceof Venta) {
            if ($venta->relationLoaded('facturacionLocalEmision') && $venta->facturacionLocalEmision) {
                return true;
            }
            if ($venta->relationLoaded('facturacionLocalEmisionComoNc') && $venta->facturacionLocalEmisionComoNc) {
                return true;
            }
        }

        return FacturacionLocalEmision::query()
            ->where(function ($q) use ($ventaId) {
                $q->where('venta_id', $ventaId)
                    ->orWhere('venta_nc_id', $ventaId);
            })
            ->exists();
    }

    public static function localVentaDesdeVenta(?object $venta): ?LocalVenta
    {
        if ($venta === null) {
            return null;
        }

        if ($venta instanceof Venta) {
            $emision = null;
            if ($venta->relationLoaded('facturacionLocalEmision') && $venta->facturacionLocalEmision) {
                $emision = $venta->facturacionLocalEmision;
            } elseif ($venta->relationLoaded('facturacionLocalEmisionComoNc') && $venta->facturacionLocalEmisionComoNc) {
                $emision = $venta->facturacionLocalEmisionComoNc;
            }
            if ($emision) {
                if ($emision->relationLoaded('localVenta') && $emision->localVenta) {
                    return $emision->localVenta;
                }
                $localId = (int) ($emision->local_venta_id ?? 0);
                if ($localId > 0) {
                    return LocalVenta::query()->find($localId);
                }
            }
        }

        $ventaId = (int) ($venta->id ?? 0);
        if ($ventaId <= 0) {
            return null;
        }

        $localId = (int) (FacturacionLocalEmision::query()
            ->where(function ($q) use ($ventaId) {
                $q->where('venta_id', $ventaId)
                    ->orWhere('venta_nc_id', $ventaId);
            })
            ->value('local_venta_id') ?? 0);

        return $localId > 0 ? LocalVenta::query()->find($localId) : null;
    }

    /**
     * Membrete para PDF: base empresa + overrides del local (solo si es venta local).
     *
     * @return array<string, string>
     */
    public static function membreteParaVenta(?object $venta, ?int $empresaId): array
    {
        $mapa = FacturaPdfMembreteSupport::paraEmpresa($empresaId);
        if (! self::esVentaLocal($venta)) {
            return $mapa;
        }

        $local = self::localVentaDesdeVenta($venta);
        if ($local === null || ! self::localTieneColumnasPdf()) {
            // Sin fila local o sin columnas: no mostrar membrete de fábrica en campos de sucursal.
            $mapa[FacturaPdfMembreteSupport::CLAVE_SEGURIDAD_HIGIENE] = '';
            $mapa[FacturaPdfMembreteSupport::CLAVE_HABILITACION] = '';
            $mapa[FacturaPdfMembreteSupport::CLAVE_INICIO_FALLBACK] = '';
            $mapa[FacturaPdfMembreteSupport::CLAVE_LEYENDA_MERCADERIA] = '';
            $mapa[FacturaPdfMembreteSupport::CLAVE_LEYENDA_CHEQUES] = '';
            $mapa[FacturaPdfMembreteSupport::CLAVE_CHEQUES] = '';

            return $mapa;
        }

        // Campos de sucursal: solo valor del local (vacío = no imprimir; no heredar fábrica).
        $mapa[FacturaPdfMembreteSupport::CLAVE_SEGURIDAD_HIGIENE] = trim((string) ($local->pdf_seguridad_higiene ?? ''));
        $mapa[FacturaPdfMembreteSupport::CLAVE_HABILITACION] = trim((string) ($local->pdf_habilitacion ?? ''));
        $mapa[FacturaPdfMembreteSupport::CLAVE_INICIO_FALLBACK] = trim((string) ($local->pdf_inicio_actividad ?? ''));

        $webLocal = trim((string) ($local->pdf_web ?? ''));
        if ($webLocal !== '') {
            $mapa[FacturaPdfMembreteSupport::CLAVE_WEB] = $webLocal;
        }

        $impLocal = trim((string) ($local->pdf_imp_internos ?? ''));
        if ($impLocal !== '') {
            $mapa[FacturaPdfMembreteSupport::CLAVE_IMP_INTERNOS] = $impLocal;
        }

        $lugarLocal = trim((string) ($local->pdf_lugar ?? ''));
        if ($lugarLocal !== '') {
            $mapa[FacturaPdfMembreteSupport::CLAVE_LUGAR] = $lugarLocal;
        }

        // Pie local: sin puntos 1 y 2 de fábrica.
        $mapa[FacturaPdfMembreteSupport::CLAVE_LEYENDA_MERCADERIA] = '';
        $mapa[FacturaPdfMembreteSupport::CLAVE_LEYENDA_CHEQUES] = '';
        $mapa[FacturaPdfMembreteSupport::CLAVE_CHEQUES] = '';

        return $mapa;
    }

    /**
     * Inicio de actividades del local: solo el del local (no el de empresa/fábrica).
     */
    public static function inicioActividadesFmt(?object $venta, string $fallbackMembrete = ''): string
    {
        if (! self::esVentaLocal($venta)) {
            return $fallbackMembrete;
        }
        $local = self::localVentaDesdeVenta($venta);
        $desdeLocal = trim((string) ($local->pdf_inicio_actividad ?? ''));
        if ($desdeLocal !== '') {
            return $desdeLocal;
        }

        return $fallbackMembrete !== '' ? $fallbackMembrete : '';
    }

    /**
     * Línea de contacto (web/email) bajo domicilio: local → email PV → membrete.
     */
    public static function lineaContacto(?object $venta, string $webMembrete, ?string $emailPv = null): string
    {
        if (! self::esVentaLocal($venta)) {
            return trim($webMembrete);
        }
        $local = self::localVentaDesdeVenta($venta);
        $webLocal = trim((string) ($local->pdf_web ?? ''));
        if ($webLocal !== '') {
            return $webLocal;
        }
        $email = trim((string) ($emailPv ?? ''));
        if ($email !== '') {
            return $email;
        }

        return trim($webMembrete);
    }

    private static function localTieneColumnasPdf(): bool
    {
        return Schema::hasTable('local_venta')
            && Schema::hasColumn('local_venta', 'pdf_seguridad_higiene');
    }
}
