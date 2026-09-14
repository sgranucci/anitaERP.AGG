<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Factura_Pdf_Parametro;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Membrete / leyendas del PDF de factura por empresa.
 * Prioridad: fila empresa → fila global (empresa_id null) → config/env → default.
 */
final class FacturaPdfMembreteSupport
{
    public const CLAVE_WEB = 'pdf_web';

    public const CLAVE_IMP_INTERNOS = 'pdf_imp_internos';

    public const CLAVE_SEGURIDAD_HIGIENE = 'pdf_seguridad_higiene';

    public const CLAVE_HABILITACION = 'pdf_habilitacion';

    public const CLAVE_CHEQUES = 'pdf_cheques_a_la_orden';

    public const CLAVE_LUGAR = 'pdf_lugar';

    public const CLAVE_LEYENDA_IVA = 'pdf_leyenda_iva';

    public const CLAVE_INICIO_FALLBACK = 'pdf_inicio_actividad_fallback';

    public const CLAVE_LEYENDA_MERCADERIA = 'pdf_leyenda_mercaderia';

    public const CLAVE_LEYENDA_CHEQUES = 'pdf_leyenda_cheques';

    private const CACHE_PREFIX = 'factura_pdf_membrete.';

    public static function paraEmpresa(?int $empresaId): array
    {
        $empresaId = $empresaId && $empresaId > 0 ? $empresaId : null;
        $cacheKey = self::CACHE_PREFIX.($empresaId ?? '0');

        return Cache::remember($cacheKey, 300, function () use ($empresaId) {
            $mapa = self::defaultsDesdeConfig();
            if (! Schema::hasTable('factura_pdf_parametro')) {
                return $mapa;
            }
            foreach (Factura_Pdf_Parametro::query()->whereNull('empresa_id')->get(['clave', 'valor']) as $fila) {
                $mapa[(string) $fila->clave] = (string) ($fila->valor ?? '');
            }
            if ($empresaId) {
                foreach (Factura_Pdf_Parametro::query()->where('empresa_id', $empresaId)->get(['clave', 'valor']) as $fila) {
                    $mapa[(string) $fila->clave] = (string) ($fila->valor ?? '');
                }
            }

            return $mapa;
        });
    }

    public static function valor(?int $empresaId, string $clave, string $default = ''): string
    {
        $mapa = self::paraEmpresa($empresaId);

        return trim((string) ($mapa[$clave] ?? $default));
    }

    public static function forget(?int $empresaId = null): void
    {
        Cache::forget(self::CACHE_PREFIX.($empresaId && $empresaId > 0 ? $empresaId : '0'));
        Cache::forget(self::CACHE_PREFIX.'0');
    }

    /**
     * @return array<string, string>
     */
    private static function defaultsDesdeConfig(): array
    {
        return [
            self::CLAVE_WEB => (string) config('facturacion.PDF_WEB', ''),
            self::CLAVE_IMP_INTERNOS => (string) config('facturacion.PDF_IMP_INTERNOS', ''),
            self::CLAVE_SEGURIDAD_HIGIENE => (string) config('facturacion.PDF_SEGURIDAD_HIGIENE', ''),
            self::CLAVE_HABILITACION => (string) config('facturacion.PDF_HABILITACION', ''),
            self::CLAVE_CHEQUES => (string) config('facturacion.PDF_CHEQUES_A_LA_ORDEN', ''),
            self::CLAVE_LUGAR => '',
            self::CLAVE_LEYENDA_IVA => 'I.V.A. RESPONSABLE INSCRIPTO',
            self::CLAVE_INICIO_FALLBACK => '',
            self::CLAVE_LEYENDA_MERCADERIA => '1. La mercaderia viaja por cuenta y riesgo del comprador.',
            self::CLAVE_LEYENDA_CHEQUES => '2. Rogamos extender los cheques a la orden de',
        ];
    }
}
