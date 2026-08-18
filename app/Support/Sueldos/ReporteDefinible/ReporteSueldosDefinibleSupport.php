<?php

namespace App\Support\Sueldos\ReporteDefinible;

/**
 * Constantes y catálogos del listado definible de sueldos (Anita listgen).
 */
final class ReporteSueldosDefinibleSupport
{
    public const TIPO_OSOCIAL = 'osocial';

    public const TIPO_SINDICATO = 'sindicato';

    public const TIPO_GENERICO = 'generico';

    public const CONTENIDO_IMPORTE = 'importe';

    public const CONTENIDO_CANTIDAD = 'cantidad';

    public const CONTENIDO_VALOR = 'valor';

    public const CONTENIDO_CAMPO_EMPLEADO = 'campo_empleado';

    public const CONTENIDO_CONCEPTO_GANANCIAS = 'concepto_ganancias';

    public const CONTENIDO_FORMULA = 'formula';

    public const ORIGEN_LIQUIDACION = 'liquidacion';

    public const ORIGEN_ABM = 'abm';

    public const AGRUPACION_EMPLEADO = 'empleado';

    public const AGRUPACION_CCOSTO = 'centrocosto';

    public const AGRUPACION_LUGAR = 'lugartrabajo';

    public const AGRUPACION_AGRUPAMIENTO = 'agrupamiento';

    /**
     * Anita lism_tipo_list: 1=OS 2=Sindicato 3=Genérico.
     *
     * @return array<string, string>
     */
    public static function tiposListado(): array
    {
        return [
            self::TIPO_OSOCIAL => 'Obra social',
            self::TIPO_SINDICATO => 'Sindicato',
            self::TIPO_GENERICO => 'Genérico',
        ];
    }

    public static function tipoDesdeAnita(int|string $codigo): string
    {
        return match ((int) $codigo) {
            1 => self::TIPO_OSOCIAL,
            2 => self::TIPO_SINDICATO,
            default => self::TIPO_GENERICO,
        };
    }

    public static function tipoHaciaAnita(string $tipo): int
    {
        return match ($tipo) {
            self::TIPO_OSOCIAL => 1,
            self::TIPO_SINDICATO => 2,
            default => 3,
        };
    }

    /**
     * Anita lisc_contenido: 1=Importe 2=Cantidad 3=Valor 4=C.empl. (5=ganancias en runtime).
     *
     * @return array<string, string>
     */
    public static function contenidosColumna(): array
    {
        return [
            self::CONTENIDO_IMPORTE => 'Importe',
            self::CONTENIDO_CANTIDAD => 'Cantidad',
            self::CONTENIDO_VALOR => 'Valor',
            self::CONTENIDO_CAMPO_EMPLEADO => 'Campo empleado',
            self::CONTENIDO_CONCEPTO_GANANCIAS => 'Concepto ganancias',
            self::CONTENIDO_FORMULA => 'Fórmula',
        ];
    }

    public static function contenidoDesdeAnita(int|string $codigo): string
    {
        return match ((int) $codigo) {
            2 => self::CONTENIDO_CANTIDAD,
            3 => self::CONTENIDO_VALOR,
            4 => self::CONTENIDO_CAMPO_EMPLEADO,
            5 => self::CONTENIDO_CONCEPTO_GANANCIAS,
            default => self::CONTENIDO_IMPORTE,
        };
    }

    public static function contenidoHaciaAnita(string $contenido): int
    {
        return match ($contenido) {
            self::CONTENIDO_CANTIDAD => 2,
            self::CONTENIDO_VALOR => 3,
            self::CONTENIDO_CAMPO_EMPLEADO => 4,
            self::CONTENIDO_CONCEPTO_GANANCIAS => 5,
            self::CONTENIDO_FORMULA => 1,
            default => 1,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function agrupaciones(): array
    {
        return [
            self::AGRUPACION_EMPLEADO => 'Por empleado',
            self::AGRUPACION_CCOSTO => 'Por centro de costo',
            self::AGRUPACION_LUGAR => 'Por lugar de trabajo',
            self::AGRUPACION_AGRUPAMIENTO => 'Por agrupamiento',
        ];
    }
}
