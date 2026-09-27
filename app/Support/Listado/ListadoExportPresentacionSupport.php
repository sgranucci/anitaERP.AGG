<?php

declare(strict_types=1);

namespace App\Support\Listado;

/**
 * Cabecera compartida de PDF/Excel de listados workbench: subtítulo de QBE, orden y grupos.
 */
final class ListadoExportPresentacionSupport
{
    public static function columnaLetra(int $indiceBaseCero): string
    {
        $n = $indiceBaseCero + 1;
        $letra = '';
        while ($n > 0) {
            $n--;
            $letra = chr(65 + ($n % 26)).$letra;
            $n = intdiv($n, 26);
        }

        return $letra;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, string>  $etiquetas
     * @param  array<string, array{column?: string, label?: string}>  $camposOrdenables
     */
    public static function subtitulo(array $filtros, array $etiquetas, array $camposOrdenables): string
    {
        $partes = [];

        $qbe = $filtros['qbe'] ?? [];
        if (is_array($qbe) && ListadoQbeSupport::tieneCriterios($qbe)) {
            $texto = self::textoQbe(ListadoQbeSupport::paraUi($qbe), $etiquetas);
            if ($texto !== '') {
                $partes[] = 'Filtro: '.$texto;
            }
        } elseif (trim((string) ($filtros['valor'] ?? '')) !== '') {
            $partes[] = 'Texto: '.trim((string) $filtros['valor']);
        }

        $orden = ListadoOrdenamientoSupport::normalizar($filtros['orden'] ?? [], $camposOrdenables);
        if ($orden !== []) {
            $bits = [];
            foreach ($orden as $oc) {
                $bits[] = ($etiquetas[$oc['campo']] ?? $oc['campo']).' '.($oc['dir'] === 'desc' ? 'desc' : 'asc');
            }
            $partes[] = 'Orden: '.implode(', ', $bits);
        }

        $agrupar = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $camposOrdenables);
        if ($agrupar !== []) {
            $bits = array_map(
                static fn (string $k): string => $etiquetas[$k] ?? $k,
                $agrupar
            );
            $partes[] = 'Agrupado: '.implode(' → ', $bits);
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  array{entre_grupos?: string, grupos?: list}  $qbe
     * @param  array<string, string>  $etiquetas
     */
    private static function textoQbe(array $qbe, array $etiquetas): string
    {
        $bits = [];
        foreach ($qbe['grupos'] ?? [] as $gi => $grupo) {
            if (! is_array($grupo)) {
                continue;
            }
            $criterios = [];
            foreach ($grupo['criterios'] ?? [] as $c) {
                if (! is_array($c)) {
                    continue;
                }
                $op = (string) ($c['op'] ?? 'contiene');
                $valor = trim((string) ($c['valor'] ?? ''));
                $hasta = trim((string) ($c['valor_hasta'] ?? ''));
                $formula = trim((string) ($c['formula'] ?? ''));
                if ($op !== 'vacio' && $op !== 'entre' && $valor === '' && $formula === '') {
                    continue;
                }
                if ($op === 'entre' && $valor === '' && $hasta === '') {
                    continue;
                }
                $campo = $formula !== ''
                    ? $formula
                    : ($etiquetas[$c['campo'] ?? ''] ?? (string) ($c['campo'] ?? ''));
                $txt = $campo.' '.$op;
                if ($op === 'entre') {
                    $txt .= ' '.$valor.'…'.$hasta;
                } elseif ($op !== 'vacio') {
                    $txt .= ' «'.$valor.'»';
                }
                $criterios[] = $txt;
            }
            if ($criterios === []) {
                continue;
            }
            if ($gi > 0 && $bits !== []) {
                $bits[] = (($qbe['entre_grupos'] ?? 'and') === 'or') ? 'O' : 'Y';
            }
            $pref = ! empty($grupo['not']) ? 'NOT ' : '';
            $sep = (($grupo['logic'] ?? 'and') === 'or') ? ' O ' : ' Y ';
            $bits[] = $pref.'('.implode($sep, $criterios).')';
        }

        return implode(' ', $bits);
    }
}
