<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\ModuloAvisoTipo;

/**
 * Metadatos de despacho: frecuencia, entorno y opciones Artisan por clave.
 */
final class ModuloAvisoProgramadoDespachoSupport
{
    public const FRECUENCIA_DIARIA = 'diaria';

    public const FRECUENCIA_CADA_PASADA = 'cada_pasada';

    public static function frecuencia(string $clave): string
    {
        $mapa = config('modulo_aviso.programados_frecuencia', []);
        $valor = is_array($mapa) ? ($mapa[$clave] ?? self::FRECUENCIA_DIARIA) : self::FRECUENCIA_DIARIA;

        return $valor === self::FRECUENCIA_CADA_PASADA
            ? self::FRECUENCIA_CADA_PASADA
            : self::FRECUENCIA_DIARIA;
    }

    public static function entornoPermite(string $clave): bool
    {
        $mapa = config('modulo_aviso.programados_entorno', []);
        if (is_array($mapa) && isset($mapa[$clave])) {
            $entorno = strtolower(trim((string) $mapa[$clave]));
            $ok = match ($entorno) {
                'agg' => EntornoEmpresaSupport::esAgg(),
                'crown' => EntornoEmpresaSupport::esCrown(),
                'ferli' => EntornoEmpresaSupport::esFerli(),
                'elbierzo', 'el_bierzo' => EntornoEmpresaSupport::esElBierzo(),
                'interforming' => EntornoEmpresaSupport::esInterforming(),
                default => true,
            };
            if (! $ok) {
                return false;
            }
        }

        return self::configPermite($clave);
    }

    /** Flags de negocio que el Kernel tenía en ->when(...). */
    public static function configPermite(string $clave): bool
    {
        return match ($clave) {
            'contable.reportes_definibles' => (bool) config('contable.reporte_definible.distribucion.habilitada', true),
            'sueldos.reportes_definibles' => (bool) config('sueldos.reporte_definible.distribucion.habilitada', true),
            'flash.distribuir_reportes' => (bool) config('caja.flash_reporte_agg.distribucion_habilitada', true),
            'flash_crown.distribuir_reportes' => (bool) config('caja.flash_reporte_crown.distribucion_habilitada', true),
            'arbolaprobacion.digest_pendientes' => (bool) config('arbolaprobacion.digest.habilitado', true),
            'ai.digest_brief' => (bool) config('ai.habilitado', false) && (bool) config('ai.digest.habilitado', true),
            'ai.alerta_umbral' => (bool) config('ai.habilitado', false) && (bool) config('ai.alerta_umbral.habilitado', true),
            'tesoreria_crown.avisos' => class_exists(\App\Support\Caja\TesoreriaCrownAvisoSupport::class)
                && \App\Support\Caja\TesoreriaCrownAvisoSupport::disponible(),
            default => true,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function opcionesArtisan(string $clave): array
    {
        $mapa = config('modulo_aviso.programados_opciones', []);
        $opts = is_array($mapa) ? ($mapa[$clave] ?? []) : [];

        return is_array($opts) ? $opts : [];
    }

    public static function esCadaPasada(ModuloAvisoTipo $tipo): bool
    {
        return self::frecuencia($tipo->claveCompleta()) === self::FRECUENCIA_CADA_PASADA;
    }
}
