<?php

namespace App\Support\Contable\ReporteDefinible;

/**
 * Constantes y etiquetas del diseñador / listador de reportes definibles.
 */
class ReporteDefinibleSupport
{
    public const TIPO_REPORTE_BALANCE = 'balance';

    public const TIPO_REPORTE_RESULTADO = 'resultado';

    public const TIPO_REPORTE_OTRO = 'otro';

    public const RUBRO_CUENTAS = 'cuentas';

    public const RUBRO_TOTAL = 'total';

    public const RUBRO_FORMULA = 'formula';

    public const RUBRO_TEXTO = 'texto';

    public const ORIGEN_REAL = 'R';

    public const ORIGEN_PRESUPUESTO = 'P';

    public const CCOSTO_SIN = 'S';

    public const CCOSTO_RANGO = 'R';

    public const CCOSTO_PART = 'P';

    public const BASE_SALDO_PERIODO = 'periodo';

    public const BASE_SALDO_EJERCICIO = 'ejercicio';

    public const LAYOUT_PERIODOS = 'periodos';

    public const LAYOUT_COMPARATIVO = 'comparativo';

    public const LAYOUT_CCOSTO = 'ccosto';

    /**
     * @return array<string, string>
     */
    public static function layoutsColumnas(): array
    {
        return [
            self::LAYOUT_PERIODOS => 'Por período / rango',
            self::LAYOUT_COMPARATIVO => 'Actual / Plan / Var / %',
            self::LAYOUT_CCOSTO => 'Centros de costo en columnas',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tiposReporte(): array
    {
        return [
            self::TIPO_REPORTE_BALANCE => 'Balance / estado patrimonial',
            self::TIPO_REPORTE_RESULTADO => 'Estado de resultados',
            self::TIPO_REPORTE_OTRO => 'Otro / gerencial',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tiposRubro(): array
    {
        return [
            self::RUBRO_CUENTAS => 'Suma de cuentas',
            self::RUBRO_TOTAL => 'Total de hijos',
            self::RUBRO_FORMULA => 'Fórmula entre rubros',
            self::RUBRO_TEXTO => 'Texto / título',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tiposRubroAyuda(): array
    {
        return [
            self::RUBRO_CUENTAS => 'Acumula los saldos de las cuentas contables asignadas a este rubro.',
            self::RUBRO_TOTAL => 'Suma automática de los rubros hijos (como un subtotal de sección).',
            self::RUBRO_FORMULA => 'Calcula con referencias a otros rubros, ej. R01-R02. (fase premium)',
            self::RUBRO_TEXTO => 'Solo muestra la etiqueta, sin importe (separador visual).',
        ];
    }

    public static function etiquetaTipoRubro(string $tipo): string
    {
        return self::tiposRubro()[$tipo] ?? $tipo;
    }

    public static function etiquetaTipoReporte(string $tipo): string
    {
        return self::tiposReporte()[$tipo] ?? $tipo;
    }

    /**
     * Anita: 1=sin, 2=rango, 3=particular — o chars S/R/P.
     */
    public static function normalizarCargaCcosto(string|int $valor): string
    {
        $v = is_int($valor) ? (string) $valor : trim($valor);
        return match ($v) {
            '1', 'S', 's' => self::CCOSTO_SIN,
            '2', 'R', 'r' => self::CCOSTO_RANGO,
            '3', 'P', 'p' => self::CCOSTO_PART,
            default => self::CCOSTO_SIN,
        };
    }

    /**
     * Anita real_presup char.
     */
    public static function normalizarOrigen(string $valor): string
    {
        $v = strtoupper(trim($valor));

        return $v === self::ORIGEN_PRESUPUESTO ? self::ORIGEN_PRESUPUESTO : self::ORIGEN_REAL;
    }
}
