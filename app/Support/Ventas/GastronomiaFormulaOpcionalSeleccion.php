<?php

namespace App\Support\Ventas;

use App\Models\Stock\Formula_Articulo_Hijo;

/**
 * Valores de opcionales en cuenta_gastronomia_linea.opcionales_json (orden => elección).
 *
 * - Artículo: entero positivo (legacy) o array con articulo_id.
 * - Subfórmula opcional: string "f:{formula_hija_id}" o array con formula_hija_id.
 */
final class GastronomiaFormulaOpcionalSeleccion
{
    public const PREFIJO_FORMULA_HIJA = 'f:';

    /**
     * @param  array<int|string, mixed>  $raw
     * @return array<string, int|string> orden => valor normalizado para persistir
     */
    public static function normalizarMapaDesdeRequest(array $raw): array
    {
        $out = [];
        foreach ($raw as $k => $v) {
            $norm = self::normalizarValorRequest($v);
            if ($norm !== null) {
                $out[(string) $k] = $norm;
            }
        }

        return $out;
    }

    /**
     * @return int|string|null
     */
    public static function normalizarValorRequest(mixed $valor): int|string|null
    {
        if ($valor === null || $valor === '' || $valor === 0 || $valor === '0') {
            return null;
        }

        if (is_array($valor)) {
            if (isset($valor['formula_hija_id']) && (int) $valor['formula_hija_id'] > 0) {
                return self::codificarFormulaHija((int) $valor['formula_hija_id']);
            }
            if (isset($valor['articulo_id']) && (int) $valor['articulo_id'] > 0) {
                return (int) $valor['articulo_id'];
            }

            return null;
        }

        if (is_string($valor) && str_starts_with($valor, self::PREFIJO_FORMULA_HIJA)) {
            $fid = (int) substr($valor, strlen(self::PREFIJO_FORMULA_HIJA));

            return $fid > 0 ? self::codificarFormulaHija($fid) : null;
        }

        if (is_numeric($valor)) {
            $n = (int) $valor;

            return $n > 0 ? $n : null;
        }

        return null;
    }

    public static function codificarFormulaHija(int $formulaHijaId): string
    {
        return self::PREFIJO_FORMULA_HIJA.$formulaHijaId;
    }

    public static function estaVacio(mixed $valor): bool
    {
        return self::decodificar($valor) === null;
    }

    /**
     * @return array{tipo: 'articulo'|'formula_hija', id: int}|null
     */
    public static function decodificar(mixed $valor): ?array
    {
        if ($valor === null || $valor === '' || $valor === 0 || $valor === '0') {
            return null;
        }

        if (is_string($valor) && str_starts_with($valor, self::PREFIJO_FORMULA_HIJA)) {
            $fid = (int) substr($valor, strlen(self::PREFIJO_FORMULA_HIJA));

            return $fid > 0 ? ['tipo' => 'formula_hija', 'id' => $fid] : null;
        }

        if (is_array($valor)) {
            if (isset($valor['formula_hija_id']) && (int) $valor['formula_hija_id'] > 0) {
                return ['tipo' => 'formula_hija', 'id' => (int) $valor['formula_hija_id']];
            }
            if (isset($valor['articulo_id']) && (int) $valor['articulo_id'] > 0) {
                return ['tipo' => 'articulo', 'id' => (int) $valor['articulo_id']];
            }

            return null;
        }

        if (is_numeric($valor) && (int) $valor > 0) {
            return ['tipo' => 'articulo', 'id' => (int) $valor];
        }

        return null;
    }

    public static function coincideConHijo(Formula_Articulo_Hijo $hijo, array $decoded): bool
    {
        if ($decoded['tipo'] === 'articulo') {
            return (int) ($hijo->articulo_id ?? 0) === $decoded['id'];
        }

        return (int) ($hijo->formula_hija_id ?? 0) === $decoded['id'];
    }

    /**
     * @param  list<array{articulo_id?:int, formula_hija_id?:int, sku?:string, descripcion?:string}>  $opciones
     */
    public static function esOpcionValidaEnGrupo(mixed $valor, array $opciones): bool
    {
        $decoded = self::decodificar($valor);
        if ($decoded === null) {
            return false;
        }

        foreach ($opciones as $op) {
            if ($decoded['tipo'] === 'articulo' && isset($op['articulo_id']) && (int) $op['articulo_id'] === $decoded['id']) {
                return true;
            }
            if ($decoded['tipo'] === 'formula_hija' && isset($op['formula_hija_id']) && (int) $op['formula_hija_id'] === $decoded['id']) {
                return true;
            }
        }

        return false;
    }
}
