<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Listado\ListadoMedidaSupport;
use App\Support\Listado\ListadoQbeFormulaSupport;
use App\Support\Listado\ListadoVisualSupport;

/**
 * Gráfico, formato condicional y columnas calculadas del listado de pagos.
 * Viajan en la consulta y en filtros_json de la vista. No son un modelo de Power BI.
 */
final class PagoproveedorListadoAnalisisSupport
{
    /** @var list<string> */
    public const TIPOS_GRAFICO = ['barras', 'linea', 'torta'];

    /** @var list<string> */
    public const TONOS = ['danger', 'warning', 'success'];

    /**
     * @param  array<string, mixed>  $raw
     * @return array{tipo: string, dimension: string, medida: string}
     */
    public static function normalizarGrafico(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $tipo = (string) ($raw['tipo'] ?? '');
        $dimension = (string) ($raw['dimension'] ?? '');
        $medida = (string) ($raw['medida'] ?? 'conteo');
        $ordenables = PagoproveedorListadoFiltros::camposOrdenables();
        unset($ordenables['monto']);
        if (! in_array($tipo, self::TIPOS_GRAFICO, true) || ! isset($ordenables[$dimension])) {
            return self::graficoVacio();
        }

        return [
            'tipo' => $tipo,
            'dimension' => $dimension,
            'medida' => ListadoMedidaSupport::normalizarClave($medida, PagoproveedorListadoFiltros::camposOrdenables()),
        ];
    }

    /**
     * @return array{tipo: string, dimension: string, medida: string}
     */
    public static function graficoVacio(): array
    {
        return ['tipo' => '', 'dimension' => '', 'medida' => 'conteo'];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        $graficos = is_array($filtros['graficos'] ?? null) ? $filtros['graficos'] : [];
        $grafico = self::normalizarGrafico($filtros['grafico'] ?? []);
        if (! empty($filtros['grafico_off'])) {
            $out['grafico_off'] = 1;
        } elseif ($graficos !== []) {
            $out['graficos'] = $graficos;
            $out['grafico'] = $graficos[0];
        } elseif ($grafico['tipo'] !== '') {
            $out['grafico'] = $grafico;
        }
        if (trim((string) ($filtros['grafico_click'] ?? '')) !== '') {
            $out['grafico_click'] = $filtros['grafico_click'];
        }
        if (trim((string) ($filtros['grafico_click_dimension'] ?? '')) !== '') {
            $out['grafico_click_dimension'] = $filtros['grafico_click_dimension'];
        }
        foreach (self::normalizarFormato($filtros['formato'] ?? []) as $i => $fila) {
            $out['formato'][$i] = $fila;
        }
        foreach (self::normalizarCalculadas($filtros['calculadas'] ?? []) as $i => $fila) {
            $out['calculadas'][$i] = [
                'etiqueta' => $fila['etiqueta'],
                'formula' => $fila['formula'],
            ];
        }

        return $out;
    }

    /**
     * @return list<array{campo: string, op: string, valor: string, tono: string}>
     */
    public static function normalizarFormato(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $campos = PagoproveedorListadoFiltros::camposOrdenables();
        $out = [];
        foreach ($raw as $fila) {
            if (count($out) >= 4 || ! is_array($fila)) {
                continue;
            }
            $campo = (string) ($fila['campo'] ?? '');
            $op = (string) ($fila['op'] ?? 'igual');
            $valor = trim((string) ($fila['valor'] ?? ''));
            $tono = (string) ($fila['tono'] ?? 'warning');
            if ($valor === '' || ! isset($campos[$campo]) || ! in_array($tono, self::TONOS, true)) {
                continue;
            }
            $out[] = [
                'campo' => $campo,
                'op' => $op === 'contiene' ? 'contiene' : 'igual',
                'valor' => mb_substr($valor, 0, 80),
                'tono' => $tono,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{etiqueta: string, formula: string, valida: bool}>
     */
    public static function normalizarCalculadas(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $campos = self::camposFormula();
        $out = [];
        foreach ($raw as $fila) {
            if (count($out) >= 2 || ! is_array($fila)) {
                continue;
            }
            $etiqueta = trim((string) ($fila['etiqueta'] ?? ''));
            $formula = trim((string) ($fila['formula'] ?? ''));
            if ($etiqueta === '' || $formula === '') {
                continue;
            }
            $out[] = [
                'etiqueta' => mb_substr($etiqueta, 0, 40),
                'formula' => mb_substr($formula, 0, ListadoQbeFormulaSupport::MAX_LEN),
                'valida' => ListadoQbeFormulaSupport::compilar($formula, $campos) !== null,
            ];
        }

        return $out;
    }

    /**
     * Campos que una fórmula puede mostrar. La factura aplicada no está en el union.
     *
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposFormula(): array
    {
        $campos = PagoproveedorListadoFiltros::camposQbeDisponibles();
        foreach (array_keys($campos) as $key) {
            if (str_starts_with($key, 'factura_')) {
                unset($campos[$key]);
            }
        }

        return $campos;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{alias: string, sql: string, bindings: list<mixed>, etiqueta: string}>
     */
    public static function sqlCalculadas(array $filtros): array
    {
        $out = [];
        foreach (self::normalizarCalculadas($filtros['calculadas'] ?? []) as $i => $calc) {
            if (! $calc['valida']) {
                continue;
            }
            $compiled = ListadoQbeFormulaSupport::compilar($calc['formula'], self::camposFormula());
            if ($compiled === null || ! preg_match('/^calc_[0-9]+$/', 'calc_'.$i)) {
                continue;
            }
            $out[] = [
                'alias' => 'calc_'.$i,
                'sql' => $compiled['sql'],
                'bindings' => $compiled['bindings'],
                'etiqueta' => $calc['etiqueta'],
            ];
        }

        return $out;
    }

    public static function tonoFila(object $fila, array $reglas): ?string
    {
        foreach (self::normalizarFormato($reglas) as $regla) {
            $actual = mb_strtolower(trim(PagoproveedorListadoColumnas::valorCelda($fila, $regla['campo'])));
            $esperado = mb_strtolower($regla['valor']);
            $coincide = $regla['op'] === 'contiene'
                ? ($esperado !== '' && str_contains($actual, $esperado))
                : $actual === $esperado;
            if ($coincide) {
                return $regla['tono'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $cortes
     * @return array{tipo: string, dimension: string, medida: string, labels: list<string>, valores: list<float>, titulo: string}
     */
    public static function serie(array $grafico, array $cortes, array $etiquetas): array
    {
        $grafico = self::normalizarGrafico($grafico);

        return ListadoVisualSupport::serie($grafico, $cortes, $etiquetas);
    }
}
