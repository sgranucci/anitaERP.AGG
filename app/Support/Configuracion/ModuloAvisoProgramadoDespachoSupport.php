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
        if (! self::entornoCatalogoPermite($clave)) {
            return false;
        }

        return self::configPermite($clave);
    }

    /**
     * Visibilidad en catálogo / Programador / preferencias: solo el gate de EMPRESA
     * (programados_entorno). Sin entrada = visible en todos los clientes.
     */
    public static function entornoCatalogoPermite(string $clave): bool
    {
        $mapa = config('modulo_aviso.programados_entorno', []);
        if (! is_array($mapa) || ! isset($mapa[$clave])) {
            return true;
        }

        $entorno = strtolower(trim((string) $mapa[$clave]));

        return match ($entorno) {
            'agg' => EntornoEmpresaSupport::esAgg(),
            'crown' => EntornoEmpresaSupport::esCrown(),
            'ferli' => EntornoEmpresaSupport::esFerli(),
            'elbierzo', 'el_bierzo' => EntornoEmpresaSupport::esElBierzo(),
            'interforming' => EntornoEmpresaSupport::esInterforming(),
            default => true,
        };
    }

    /**
     * @param  iterable<int, ModuloAvisoTipo>  $tipos
     * @return \Illuminate\Support\Collection<int, ModuloAvisoTipo>
     */
    public static function filtrarTiposVisiblesEnCatalogo(iterable $tipos): \Illuminate\Support\Collection
    {
        return collect($tipos)->values()->filter(
            static fn (ModuloAvisoTipo $t) => self::entornoCatalogoPermite($t->claveCompleta())
        )->values();
    }

    /**
     * @param  list<string>  $claves
     * @return list<string>
     */
    public static function filtrarClavesVisiblesEnCatalogo(array $claves): array
    {
        $out = [];
        foreach ($claves as $clave) {
            $k = trim((string) $clave);
            if ($k !== '' && self::entornoCatalogoPermite($k)) {
                $out[] = $k;
            }
        }

        return array_values(array_unique($out));
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
