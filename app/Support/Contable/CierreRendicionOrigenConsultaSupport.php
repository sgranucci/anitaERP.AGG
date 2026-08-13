<?php

declare(strict_types=1);

namespace App\Support\Contable;

use App\Support\Caja\RendicionEstacionamientoPdfPermiso;
use App\Support\Caja\RendicionGastronomiaPdfPermiso;

/**
 * Permisos para consultar rendiciones / cierres de turno que alimentan un cierre contable.
 * Quien lista el cierre puede ver los documentos origen (ABM consulta y PDF).
 */
final class CierreRendicionOrigenConsultaSupport
{
    public static function puedeListarCierreEstacionamiento(): bool
    {
        return can('listar-cierre-rendicion-estacionamiento-contable', false);
    }

    public static function puedeConsultarRendicionEstacionamiento(): bool
    {
        return can('listar-rendicion-estacionamiento-caja', false)
            || can('editar-rendicion-estacionamiento-caja', false)
            || self::puedeListarCierreEstacionamiento();
    }

    public static function puedeVerPdfRendicionEstacionamiento(): bool
    {
        return RendicionEstacionamientoPdfPermiso::puedeVerPdfRendicion()
            || can('listar-rendicion-estacionamiento-caja', false)
            || self::puedeListarCierreEstacionamiento();
    }

    public static function puedeConsultarCierreTurnoEstacionamiento(): bool
    {
        return can('listar-cierres-turno-estacionamiento', false)
            || self::puedeListarCierreEstacionamiento();
    }

    public static function puedeVerPdfCierreTurnoEstacionamiento(): bool
    {
        return can('ver-comprobante-cierre-turno-estacionamiento', false)
            || self::puedeListarCierreEstacionamiento();
    }

    public static function puedeListarCierreBingo(): bool
    {
        return can('listar-cierre-rendicion-bingo-contable', false);
    }

    public static function puedeVerPdfRendicionBingo(): bool
    {
        return can('imprimir-rendicion-bingo-caja', false)
            || can('listar-rendicion-bingo-caja', false)
            || self::puedeListarCierreBingo();
    }

    public static function puedeVerPdfCierreTurnoBingo(): bool
    {
        return can('ver-comprobante-cierre-turno-bingo', false)
            || can('listar-cierres-turno-bingo', false)
            || self::puedeListarCierreBingo();
    }

    public static function puedeListarCierreMaquinavending(): bool
    {
        return can('listar-cierre-rendicion-maquinavending-contable', false);
    }

    public static function puedeConsultarRendicionMaquinavending(): bool
    {
        return can('listar-rendicion-maquinavending-caja', false)
            || can('editar-rendicion-maquinavending-caja', false)
            || self::puedeListarCierreMaquinavending();
    }

    public static function puedeVerPdfRendicionMaquinavending(): bool
    {
        return can('listar-rendicion-maquinavending-caja', false)
            || self::puedeListarCierreMaquinavending();
    }

    public static function puedeVerPdfRendicionVentasMaquinavending(): bool
    {
        return can('ver-comprobante-maquinavending-rendicion-gastronomia', false)
            || self::puedeListarCierreMaquinavending();
    }

    public static function puedeListarCierreMaquina(): bool
    {
        return can('listar-cierre-rendicion-maquina-contable', false);
    }

    public static function puedeConsultarRendicionMaquina(): bool
    {
        return can('listar-rendicion-maquina', false)
            || can('editar-rendicion-maquina', false)
            || self::puedeListarCierreMaquina();
    }

    public static function puedeVerPdfRendicionMaquina(): bool
    {
        return can('imprimir-rendicion-maquina', false)
            || can('listar-rendicion-maquina', false)
            || self::puedeListarCierreMaquina();
    }

    public static function puedeListarCierresTurnoGastronomiaContable(): bool
    {
        return can('listar-cierres-turno-gastronomia-contable', false);
    }

    public static function puedeConsultarCierreTurnoGastronomia(): bool
    {
        return can('listar-cierres-turno-gastronomia', false)
            || self::puedeListarCierresTurnoGastronomiaContable();
    }

    public static function puedeVerPdfCierreTurnoGastronomia(): bool
    {
        return can('ver-comprobante-cierre-turno-gastronomia', false)
            || self::puedeConsultarCierreTurnoGastronomia();
    }

    public static function puedeConsultarRendicionGastronomia(): bool
    {
        return can('listar-rendicion-gastronomia-caja', false)
            || can('editar-rendicion-gastronomia-caja', false)
            || self::puedeListarCierresTurnoGastronomiaContable();
    }

    public static function puedeVerPdfRendicionGastronomia(): bool
    {
        return RendicionGastronomiaPdfPermiso::puedeVerPdfRendicion()
            || can('listar-rendicion-gastronomia-caja', false)
            || self::puedeListarCierresTurnoGastronomiaContable();
    }

    public static function exigir(bool $ok, string $mensaje = 'No tiene permiso para consultar el documento origen.'): void
    {
        if (! $ok) {
            abort(403, $mensaje);
        }
    }
}
