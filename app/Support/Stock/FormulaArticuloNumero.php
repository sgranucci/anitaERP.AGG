<?php

namespace App\Support\Stock;

/**
 * Resuelve el "número de fórmula" visible al usuario en el CRUD.
 * Por defecto se muestra el id interno; si está activo el flag de configuración
 * (FORMULA_ARTICULO_MOSTRAR_CODIGO_COMO_NUMERO) se muestra el código cargado en la fórmula.
 * Si el código está vacío, se cae al "#<id>" como fallback para no romper la identificación.
 */
final class FormulaArticuloNumero
{
    public static function mostrarCodigo(): bool
    {
        return (bool) config('formula_articulo.mostrar_codigo_como_numero', false);
    }

    /**
     * Texto que identifica a la fórmula a partir del id y código.
     */
    public static function paraIdYCodigo($id, $codigo): string
    {
        $codigo = trim((string) ($codigo ?? ''));

        if (self::mostrarCodigo() && $codigo !== '') {
            return $codigo;
        }

        if (self::mostrarCodigo()) {
            return $id !== null && $id !== '' ? '#'.$id : '';
        }

        return $id !== null ? (string) $id : '';
    }

    /**
     * @param  object|null  $formula  Modelo o stdClass con propiedades id/codigo.
     */
    public static function paraFormula($formula): string
    {
        if (! $formula) {
            return '';
        }

        return self::paraIdYCodigo($formula->id ?? null, $formula->codigo ?? null);
    }

    /**
     * Etiqueta corta tipo "Fórmula 123" o "Fórmula ABC-1".
     *
     * @param  object|null  $formula
     */
    public static function etiqueta($formula, string $prefijo = 'Fórmula '): string
    {
        $num = self::paraFormula($formula);

        return $num === '' ? rtrim($prefijo) : $prefijo.$num;
    }

    /**
     * Cabecera de la columna primaria del listado/modal:
     * "Cód. fórmula" cuando se muestra el código, "ID" cuando se muestra el id.
     */
    public static function etiquetaColumnaPrimaria(): string
    {
        return self::mostrarCodigo() ? 'Cód. fórmula' : 'ID';
    }
}
