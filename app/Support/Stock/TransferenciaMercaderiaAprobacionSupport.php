<?php

namespace App\Support\Stock;

use App\Models\Stock\Tipotransaccion_Stock;
use App\Support\Configuracion\EntornoEmpresaSupport;

final class TransferenciaMercaderiaAprobacionSupport
{
    public const MODO_INMEDIATA = 'inmediata';

    public const MODO_TIPO_TRANSACCION = 'tipo_transaccion';

    public const MODO_SIEMPRE = 'siempre';

    /** En Ferli solo TRA queda pendiente de recepción; otros tipos T impactan al instante. */
    public const ABREV_APROBACION_FERLI = 'TRA';

    /**
     * ¿La transferencia queda pendiente de aprobación (con aviso)?
     *
     * Para tipos con "aviso_opcional" es el usuario quien decide al grabar
     * ($decisionAvisoUsuario, tomada en el modal). Sin decisión explícita → sin aviso.
     */
    public static function requiereAprobacion(?Tipotransaccion_Stock $tipo, ?bool $decisionAvisoUsuario = null): bool
    {
        if (EntornoEmpresaSupport::esFerli() && ! self::esTipoAprobableFerli($tipo)) {
            return false;
        }

        $modo = (string) config('stock.transferencia_modo_aprobacion', self::MODO_TIPO_TRANSACCION);

        return match ($modo) {
            self::MODO_INMEDIATA => false,
            self::MODO_SIEMPRE => true,
            default => self::requiereAprobacionPorTipo($tipo, $decisionAvisoUsuario),
        };
    }

    public static function esTipoAprobableFerli(?Tipotransaccion_Stock $tipo): bool
    {
        if ($tipo === null) {
            return false;
        }

        return strtoupper(trim((string) ($tipo->abreviatura ?? ''))) === self::ABREV_APROBACION_FERLI;
    }

    /**
     * ¿El tipo permite elegir aviso al grabar? (modal Sí/No en las pantallas de transferencia).
     */
    public static function avisoOpcional(?Tipotransaccion_Stock $tipo): bool
    {
        return (bool) ($tipo?->aviso_opcional ?? false);
    }

    private static function requiereAprobacionPorTipo(?Tipotransaccion_Stock $tipo, ?bool $decisionAvisoUsuario): bool
    {
        if ($tipo === null) {
            return false;
        }

        if (self::avisoOpcional($tipo)) {
            return $decisionAvisoUsuario === true;
        }

        return (bool) ($tipo->requiere_aprobacion ?? false);
    }

    public static function manejaContabilidad(?Tipotransaccion_Stock $tipo): bool
    {
        return (bool) ($tipo?->maneja_contabilidad ?? false);
    }
}
