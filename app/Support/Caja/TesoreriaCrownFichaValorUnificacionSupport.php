<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownTipoValor;
use App\Models\Caja\TesoreriaCrownValor;
use Illuminate\Support\Facades\Schema;

/**
 * Unifica filas de fichas (legado Anita: negociable / no negociable / color)
 * contra el catálogo canónico de Valores FVAL, por denominación.
 *
 * Misma regla que el sync de maestros: series distintas con el mismo valor
 * (p. ej. ROJ y NNR a 500.000) se muestran como una sola ficha de valor.
 */
final class TesoreriaCrownFichaValorUnificacionSupport
{
    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<string>  $columnasSumar
     * @return list<array<string, mixed>>
     */
    public static function unificarFilas(int $salaId, array $filas, array $columnasSumar): array
    {
        if ($filas === [] || $salaId <= 0) {
            return $filas;
        }
        $canon = self::catalogoFvalPorDenominacion($salaId);
        if ($canon === []) {
            return $filas;
        }

        $grupos = [];
        foreach ($filas as $fila) {
            $valor = (float) ($fila['valor'] ?? 0);
            $clave = self::claveDenominacion($valor);
            if (! isset($grupos[$clave])) {
                $meta = $canon[$clave] ?? null;
                $base = $fila;
                $base['serie'] = $meta['serie'] ?? (string) ($fila['serie'] ?? '');
                $base['descripcion'] = $meta['descripcion'] ?? (string) ($fila['descripcion'] ?? '');
                $base['valor'] = $meta['valor'] ?? $valor;
                $base['_orden'] = $meta['orden'] ?? 9999;
                foreach ($columnasSumar as $columna) {
                    $base[$columna] = 0;
                }
                if (array_key_exists('canje', $fila)) {
                    $base['canje'] = false;
                }
                $grupos[$clave] = $base;
            }
            foreach ($columnasSumar as $columna) {
                $grupos[$clave][$columna] = self::sumarCelda(
                    $grupos[$clave][$columna] ?? 0,
                    $fila[$columna] ?? 0
                );
            }
            if (! empty($fila['canje'])) {
                $grupos[$clave]['canje'] = true;
            }
        }

        $out = array_values($grupos);
        usort($out, static function (array $a, array $b): int {
            $oa = (int) ($a['_orden'] ?? 9999);
            $ob = (int) ($b['_orden'] ?? 9999);
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }

            return ((float) ($b['valor'] ?? 0)) <=> ((float) ($a['valor'] ?? 0));
        });

        foreach ($out as &$fila) {
            unset($fila['_orden']);
        }
        unset($fila);

        return $out;
    }

    /**
     * @return array<string, array{serie: string, descripcion: string, valor: float, orden: int}>
     */
    public static function catalogoFvalPorDenominacion(int $salaId): array
    {
        if ($salaId <= 0
            || ! Schema::hasTable('tesoreria_crown_valor')
            || ! Schema::hasTable('tesoreria_crown_tipo_valor')) {
            return [];
        }
        $tipoId = TesoreriaCrownTipoValor::query()
            ->where('sala_id', $salaId)
            ->where('codigo', 'FVAL')
            ->value('id');
        if (! $tipoId) {
            return [];
        }

        $out = [];
        $valores = TesoreriaCrownValor::query()
            ->where('sala_id', $salaId)
            ->where('tipo_valor_id', $tipoId)
            ->where('activo', true)
            ->orderBy('orden')
            ->orderByDesc('denominacion')
            ->get(['serie', 'descripcion', 'denominacion', 'orden']);
        foreach ($valores as $valor) {
            $clave = self::claveDenominacion((float) $valor->denominacion);
            if (isset($out[$clave])) {
                continue;
            }
            $out[$clave] = [
                'serie' => strtoupper(trim((string) $valor->serie)),
                'descripcion' => trim((string) $valor->descripcion),
                'valor' => (float) $valor->denominacion,
                'orden' => (int) $valor->orden,
            ];
        }

        return $out;
    }

    public static function claveDenominacion(float $valor): string
    {
        return number_format($valor, 2, '.', '');
    }

    private static function sumarCelda(mixed $actual, mixed $suma): int|float
    {
        if (is_int($actual) && is_int($suma)) {
            return $actual + $suma;
        }

        return round((float) $actual + (float) $suma, 2);
    }
}
