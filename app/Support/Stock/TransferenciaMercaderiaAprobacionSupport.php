<?php

namespace App\Support\Stock;

use App\Models\Stock\Tipotransaccion_Stock;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\ReglaNegocio\ReglaNegocioConsultaSupport;

final class TransferenciaMercaderiaAprobacionSupport
{
    public const MODO_INMEDIATA = 'inmediata';

    public const MODO_TIPO_TRANSACCION = 'tipo_transaccion';

    public const MODO_SIEMPRE = 'siempre';

    /** En Ferli solo TRA queda pendiente de recepción; otros tipos T impactan al instante. */
    public const ABREV_APROBACION_FERLI = 'TRA';

    public const PUNTO_REGLA = 'stock.tm.requiere_aprobacion';

    /**
     * ¿La transferencia queda pendiente de aprobación (con aviso)?
     *
     * Para tipos con "aviso_opcional" es el usuario quien decide al grabar
     * ($decisionAvisoUsuario, tomada en el modal). Sin decisión explícita → sin aviso.
     *
     * Si el BRE transversal está ON y hay regla activa que matchea el punto
     * {@see self::PUNTO_REGLA}, manda el dato `requiere_aprobacion` de la decisión.
     * Si no: legacy (config + flags del tipo + gate Ferli).
     */
    public static function requiereAprobacion(?Tipotransaccion_Stock $tipo, ?bool $decisionAvisoUsuario = null): bool
    {
        $d = ReglaNegocioConsultaSupport::decidirSiActivo(
            self::PUNTO_REGLA,
            self::contextoRegla($tipo, $decisionAvisoUsuario)
        );
        if ($d !== null) {
            $bre = ReglaNegocioConsultaSupport::boolDato($d->datos, 'requiere_aprobacion');
            if ($bre !== null) {
                return $bre;
            }
        }

        return self::requiereAprobacionLegacy($tipo, $decisionAvisoUsuario);
    }

    /**
     * Lógica histórica (sin BRE). Pública para tests y para documentar el fallback.
     */
    public static function requiereAprobacionLegacy(?Tipotransaccion_Stock $tipo, ?bool $decisionAvisoUsuario = null): bool
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

    /**
     * Contexto estable para el diseñador IF / simulación.
     *
     * @return array<string, mixed>
     */
    public static function contextoRegla(?Tipotransaccion_Stock $tipo, ?bool $decisionAvisoUsuario = null): array
    {
        $abrev = strtoupper(trim((string) ($tipo->abreviatura ?? '')));
        $codigoEmpresa = EntornoEmpresaSupport::codigo();

        return [
            'tipo' => $abrev !== '' ? $abrev : null,
            'tipo_id' => $tipo?->id !== null ? (int) $tipo->id : null,
            'entorno' => strtolower(str_replace(' ', '_', $codigoEmpresa)),
            'empresa_codigo' => $codigoEmpresa,
            'es_ferli' => EntornoEmpresaSupport::esFerli(),
            'modo' => (string) config('stock.transferencia_modo_aprobacion', self::MODO_TIPO_TRANSACCION),
            'aviso_opcional' => self::avisoOpcional($tipo),
            'requiere_aprobacion_tipo' => (bool) ($tipo->requiere_aprobacion ?? false),
            'decision_aviso_usuario' => $decisionAvisoUsuario,
        ];
    }
}
