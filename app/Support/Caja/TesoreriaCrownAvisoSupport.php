<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownAviso;
use App\Support\Configuracion\ParametroSistemaSupport;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de reglas, estados y parámetros de la bandeja de avisos de auditoría Crown.
 */
final class TesoreriaCrownAvisoSupport
{
    public const ABIERTO = 'A';

    public const REVISADO = 'R';

    public const DESCARTADO = 'D';

    /** Cerrado por el sistema: la condición dejó de cumplirse (p. ej. el no conforme se resolvió). */
    public const SUPERADO = 'S';

    /** @var array<string, string> */
    public const ESTADOS = [
        self::ABIERTO => 'Abierto',
        self::REVISADO => 'Revisado',
        self::DESCARTADO => 'Descartado',
        self::SUPERADO => 'Superado',
    ];

    public const ALTA = 'A';

    public const MEDIA = 'M';

    public const BAJA = 'B';

    /** @var array<string, string> */
    public const SEVERIDADES = [
        self::ALTA => 'Alta',
        self::MEDIA => 'Media',
        self::BAJA => 'Baja',
    ];

    public const DIFERENCIA = 'diferencia';

    public const NO_CONFORME = 'no_conforme';

    public const SIN_HABILITACION = 'sin_habilitacion';

    public const REVERSA = 'reversa';

    public const REAPERTURA = 'reapertura';

    public const EDICION_POST_CIERRE = 'edicion_post_cierre';

    public const MISMA_PERSONA = 'misma_persona';

    public const CAJERO_DOS_CAJAS = 'cajero_dos_cajas';

    public const GASTRO_X_SIN_CIERRE = 'gastro_x_sin_cierre';

    public const FALLOS_REPETIDOS = 'fallos_repetidos';

    public const JORNADA_ABIERTA = 'jornada_abierta';

    public const RECALCULO_PENDIENTE = 'recalculo_pendiente';

    public const ANITA = 'anita';

    public const VIII_VARIACION = 'viii_variacion';

    public const COTIZACION_OTRA_FECHA = 'cotizacion_otra_fecha';

    /** @var array<string, array{etiqueta: string, severidad: string}> */
    public const REGLAS = [
        self::DIFERENCIA => ['etiqueta' => 'Diferencia de cierre sobre la tolerancia', 'severidad' => self::ALTA],
        self::NO_CONFORME => ['etiqueta' => 'Comprobante no conforme sin resolver', 'severidad' => self::ALTA],
        self::SIN_HABILITACION => ['etiqueta' => 'Rendición sin habilitación conforme', 'severidad' => self::ALTA],
        self::EDICION_POST_CIERRE => ['etiqueta' => 'Comprobante modificado después del cierre', 'severidad' => self::ALTA],
        self::MISMA_PERSONA => ['etiqueta' => 'Misma persona firma las dos partes', 'severidad' => self::ALTA],
        self::REVERSA => ['etiqueta' => 'Reversa de comprobante', 'severidad' => self::MEDIA],
        self::REAPERTURA => ['etiqueta' => 'Caja reabierta', 'severidad' => self::MEDIA],
        self::CAJERO_DOS_CAJAS => ['etiqueta' => 'Cajero en dos cajas el mismo día', 'severidad' => self::MEDIA],
        self::GASTRO_X_SIN_CIERRE => ['etiqueta' => 'Rendición gastro/hotel con X sin cierre de ventas', 'severidad' => self::MEDIA],
        self::FALLOS_REPETIDOS => ['etiqueta' => 'Fallos repetidos del cajero', 'severidad' => self::MEDIA],
        self::JORNADA_ABIERTA => ['etiqueta' => 'Jornada abierta hace varios días', 'severidad' => self::MEDIA],
        self::ANITA => ['etiqueta' => 'Diferencia contra Anita', 'severidad' => self::MEDIA],
        self::VIII_VARIACION => ['etiqueta' => 'Anexo VIII distinto de la variación del Anexo I', 'severidad' => self::MEDIA],
        self::COTIZACION_OTRA_FECHA => ['etiqueta' => 'Moneda extranjera con cotización de otra fecha', 'severidad' => self::BAJA],
        self::RECALCULO_PENDIENTE => ['etiqueta' => 'Recálculo hacia adelante pendiente', 'severidad' => self::BAJA],
    ];

    private static ?bool $tieneTabla = null;

    public static function disponible(): bool
    {
        return self::$tieneTabla ??= Schema::hasTable('tesoreria_crown_aviso');
    }

    public static function regla(string $codigo): string
    {
        return self::REGLAS[$codigo]['etiqueta'] ?? $codigo;
    }

    public static function estado(string $codigo): string
    {
        return self::ESTADOS[$codigo] ?? $codigo;
    }

    public static function severidad(string $codigo): string
    {
        return self::SEVERIDADES[$codigo] ?? $codigo;
    }

    /**
     * @return list<string>
     */
    public static function mails(): array
    {
        return ParametroSistemaSupport::separarMails(
            ParametroSistemaSupport::texto(ParametroSistemaSupport::CLAVE_TESORERIA_CROWN_AVISOS_MAILS, '')
        );
    }

    public static function hora(): string
    {
        $hora = ParametroSistemaSupport::texto(ParametroSistemaSupport::CLAVE_TESORERIA_CROWN_AVISOS_HORA, '07:30');

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora) === 1 ? $hora : '07:30';
    }

    public static function fallosCantidad(): int
    {
        return max(2, (int) ParametroSistemaSupport::decimal(ParametroSistemaSupport::CLAVE_TESORERIA_CROWN_AVISOS_FALLOS_CANTIDAD, 4));
    }

    public static function fallosDias(): int
    {
        return max(1, (int) ParametroSistemaSupport::decimal(ParametroSistemaSupport::CLAVE_TESORERIA_CROWN_AVISOS_FALLOS_DIAS, 30));
    }

    public static function falloMinimo(): float
    {
        return max(0.01, ParametroSistemaSupport::decimal(ParametroSistemaSupport::CLAVE_TESORERIA_CROWN_AVISOS_FALLO_MINIMO, 5000));
    }

    public static function jornadaAbiertaDias(): int
    {
        return max(1, (int) ParametroSistemaSupport::decimal(ParametroSistemaSupport::CLAVE_TESORERIA_CROWN_AVISOS_JORNADA_ABIERTA_DIAS, 2));
    }

    /**
     * Link al origen del aviso en modo consulta (solapa sin menú).
     */
    public static function link(TesoreriaCrownAviso $aviso): ?string
    {
        $consulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
        if ($aviso->comprobante_id) {
            return route('editar_tesoreria_crown_movimiento', $aviso->comprobante_id).'?'.http_build_query($consulta);
        }
        if ($aviso->jornada_id && $aviso->cajero_crown_id) {
            return route('ver_tesoreria_crown_cierre', [
                'jornada' => $aviso->jornada_id,
                'cajero' => $aviso->cajero_crown_id,
            ]).'?'.http_build_query($consulta);
        }
        if ($aviso->jornada_id) {
            return route('ver_tesoreria_crown_jornada', $aviso->jornada_id).'?'.http_build_query($consulta);
        }

        return null;
    }
}
