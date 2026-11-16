<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

/**
 * Slugs de permiso de Consulta Inteligente.
 */
final class ConsultaInteligentePermisosSupport
{
    /** Catálogo, explorar, tablero, cruce, join, export, NL, snapshots propios. */
    public const LISTAR = 'listar-consulta-inteligente';

    /** Alertas y diccionario NL. */
    public const CONFIGURAR = 'configurar-consulta-inteligente';

    /** Auditoría (propia siempre con LISTAR; global solo con este). */
    public const AUDITAR = 'auditar-consulta-inteligente';

    public static function puedeListar(): bool
    {
        return (bool) can(self::LISTAR, false);
    }

    public static function puedeConfigurar(): bool
    {
        return (bool) can(self::CONFIGURAR, false);
    }

    public static function puedeAuditarGlobal(): bool
    {
        return (bool) can(self::AUDITAR, false);
    }
}
