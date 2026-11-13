<?php

namespace App\Support\Caja;

use App\Models\Configuracion\Parametro_Sistema;
use App\Support\Configuracion\ParametroSistemaSupport;

/**
 * Parámetros editables de la asignación automática de CHT en programa de pagos.
 * Default solo si aún no hay fila en parametro_sistema; la pantalla los muestra y permite cambiarlos.
 */
final class ProgramaPagoAsignarChequesConfigSupport
{
    /** Tope de sobrante (anticipo a proveedor) al asignar cheques. */
    public const CLAVE_ANTICIPO_MAXIMO = 'programa_pago_cheque_anticipo_maximo';

    /** % de faltante aceptable respecto del programado neto (cheques por debajo del objetivo). */
    public const CLAVE_FALTANTE_TOLERANCIA_PCT = 'programa_pago_cheque_faltante_tolerancia_pct';

    public const ANTICIPO_MAXIMO_DEFAULT = 15000.0;

    /** Porcentaje entero (10 = 10%). */
    public const FALTANTE_TOLERANCIA_PCT_DEFAULT = 10.0;

    public static function anticipoMaximo(): float
    {
        return max(0.0, ParametroSistemaSupport::decimal(
            self::CLAVE_ANTICIPO_MAXIMO,
            self::ANTICIPO_MAXIMO_DEFAULT
        ));
    }

    /**
     * Ratio 0–1 (ej. 0.10).
     */
    public static function faltanteToleranciaRatio(): float
    {
        return max(0.0, self::faltanteToleranciaPct() / 100.0);
    }

    /**
     * Porcentaje entero/decimal editable (ej. 10).
     */
    public static function faltanteToleranciaPct(): float
    {
        return max(0.0, ParametroSistemaSupport::decimal(
            self::CLAVE_FALTANTE_TOLERANCIA_PCT,
            self::FALTANTE_TOLERANCIA_PCT_DEFAULT
        ));
    }

    public static function guardarAnticipoMaximo(float $importe): void
    {
        Parametro_Sistema::query()->updateOrCreate(
            ['clave' => self::CLAVE_ANTICIPO_MAXIMO],
            [
                'grupo' => 'Compras',
                'etiqueta' => 'Programa de pagos: tope de anticipo al asignar cheques',
                'ayuda' => 'Importe máximo en que la suma de cheques puede superar el programado neto. El exceso queda como anticipo a proveedores.',
                'tipo' => 'decimal',
                'valor' => (string) max(0.0, round($importe, 2)),
                'orden' => 72,
            ]
        );
        ParametroSistemaSupport::olvidarCache();
    }

    public static function guardarFaltanteToleranciaPct(float $porcentaje): void
    {
        Parametro_Sistema::query()->updateOrCreate(
            ['clave' => self::CLAVE_FALTANTE_TOLERANCIA_PCT],
            [
                'grupo' => 'Compras',
                'etiqueta' => 'Programa de pagos: tolerancia de faltante al asignar cheques (%)',
                'ayuda' => 'Porcentaje máximo en que la suma de cheques puede quedar por debajo del programado neto.',
                'tipo' => 'decimal',
                'valor' => (string) max(0.0, round($porcentaje, 2)),
                'orden' => 73,
            ]
        );
        ParametroSistemaSupport::olvidarCache();
    }
}
