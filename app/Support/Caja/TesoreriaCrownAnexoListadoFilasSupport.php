<?php

namespace App\Support\Caja;

/**
 * Filas planas Pdf/Excel/Csv de Anexo I y VIII (un día o varias con corte).
 */
final class TesoreriaCrownAnexoListadoFilasSupport
{
    /**
     * @param  array{filas: list<array<string, mixed>>}  $armado
     * @return list<list<mixed>>
     */
    public static function anexo1(array $armado, string $variante): array
    {
        $filas = [];
        if ($variante === 'nuevo') {
            foreach ($armado['filas'] as $fila) {
                if ($fila['seccion']) {
                    $filas[] = [$fila['concepto'], '', '', '', ''];

                    continue;
                }
                $filas[] = [
                    $fila['concepto'],
                    $fila['fecha'] ?? '',
                    $fila['debito'] === null ? '' : (float) $fila['debito'],
                    $fila['credito'] === null ? '' : (float) $fila['credito'],
                    $fila['saldo'] === null ? '' : (float) $fila['saldo'],
                ];
            }

            return $filas;
        }

        foreach ($armado['filas'] as $fila) {
            if ($fila['seccion']) {
                $filas[] = [$fila['concepto'], '', '', ''];

                continue;
            }
            $filas[] = [
                $fila['concepto'],
                $fila['dia'] === null ? '' : (float) $fila['dia'],
                $fila['mes'] === null ? '' : (float) $fila['mes'],
                $fila['anio'] === null ? '' : (float) $fila['anio'],
            ];
        }

        return $filas;
    }

    /**
     * @return list<array{label: string, ancho: int, wrap?: bool, numero?: bool}>
     */
    public static function columnasAnexo1(string $variante): array
    {
        if ($variante === 'nuevo') {
            return [
                ['label' => 'Concepto', 'ancho' => 48, 'wrap' => true],
                ['label' => 'Fecha', 'ancho' => 14],
                ['label' => 'Débito', 'ancho' => 16, 'numero' => true],
                ['label' => 'Crédito', 'ancho' => 16, 'numero' => true],
                ['label' => 'Saldo', 'ancho' => 16, 'numero' => true],
            ];
        }

        return [
            ['label' => 'Concepto', 'ancho' => 70, 'wrap' => true],
            ['label' => 'Día', 'ancho' => 18, 'numero' => true],
            ['label' => 'Mes', 'ancho' => 18, 'numero' => true],
            ['label' => 'Anual', 'ancho' => 18, 'numero' => true],
        ];
    }

    /**
     * @return list<mixed>
     */
    public static function corteDiaAnexo1(string $etiqueta, string $variante): array
    {
        $n = $variante === 'nuevo' ? 5 : 4;
        $fila = array_fill(0, $n, '');
        $fila[0] = $etiqueta;

        return $fila;
    }

    /**
     * @param  array{
     *     lugares: list<array<string, mixed>>,
     *     total: float,
     *     fichas: array<string, mixed>|null,
     *     componentes: array<string, mixed>|null
     * }  $armado
     * @return list<list<mixed>>
     */
    public static function anexo8(array $armado): array
    {
        $filas = [];
        $fichas = $armado['fichas'] ?? null;
        if (is_array($fichas) && ($fichas['filas'] ?? []) !== []) {
            $filas[] = ['Stock de fichas (no entra al total)', '', '', ''];
            foreach ($fichas['filas'] as $fila) {
                $filas[] = [
                    $fila['serie'].' '.$fila['descripcion'],
                    (float) $fila['valor'],
                    (float) $fila['cantidad'],
                    (float) $fila['monto'],
                ];
            }
            $filas[] = ['', 'Total conversión valorizado', '', (float) $fichas['conversion_valorizada']];
            $filas[] = ['', 'Total poder público valorizado', '', (float) $fichas['poder_valorizado']];
            $filas[] = ['', 'Total poder público diario', '', (float) $fichas['diario_valorizado']];
            $filas[] = ['', '', '', ''];
        }
        $c = $armado['componentes'] ?? null;
        if (is_array($c)) {
            $filas[] = ['Componentes', '', '', ''];
            $filas[] = ['', 'Pesos en bóveda', '', (float) ($c['pesos'] ?? 0)];
            $filas[] = ['', 'Slots (recuento)', '', (float) ($c['slots'] ?? 0)];
            $filas[] = ['', 'Drops de mesa', '', (float) ($c['drops_mesa'] ?? 0)];
            // Sin detalle de resumen ni USD/EUR convertidos: se listan abajo en lugares (evita repetir).
            $filas[] = ['', 'Items resumen final', '', (float) ($c['resumen'] ?? 0)];
            $filas[] = ['', 'Otras monedas segun cotiz.', '', (float) ($c['moneda_extranjera'] ?? 0)];
            $filas[] = ['', 'Total Anexo VIII', '', (float) $armado['total']];
            if (($c['saldo_final_anexo1'] ?? null) !== null) {
                $filas[] = ['', 'Saldo final Anexo I (caja por variación)', '', (float) $c['saldo_final_anexo1']];
                $filas[] = ['', 'Dif. final de bóveda', '', (float) ($c['dif_final_boveda'] ?? 0)];
            }
            $filas[] = ['', '', '', ''];
        }
        foreach ($armado['lugares'] as $lugar) {
            $filas[] = [$lugar['lugar'], '', '', ''];
            foreach ($lugar['grupos'] as $grupo) {
                $filas[] = [$grupo['titulo'], '', '', ''];
                foreach ($grupo['filas'] as $fila) {
                    $filas[] = [
                        '',
                        $fila['denominacion'],
                        (float) $fila['cantidad'],
                        (float) $fila['monto'],
                    ];
                }
                $filas[] = ['', 'Subtotal '.$grupo['titulo'], '', (float) $grupo['monto']];
            }
            $filas[] = ['', 'Total '.$lugar['lugar'], '', (float) $lugar['total']];
        }
        if ($armado['lugares'] !== []) {
            $filas[] = ['', 'Total general', '', (float) $armado['total']];
        }

        return $filas;
    }

    /**
     * @return list<array{label: string, ancho: int, wrap?: bool, numero?: bool}>
     */
    public static function columnasAnexo8(): array
    {
        return [
            ['label' => 'Grupo', 'ancho' => 36, 'wrap' => true],
            ['label' => 'Denominación', 'ancho' => 28, 'wrap' => true],
            ['label' => 'Cantidad', 'ancho' => 14, 'numero' => true],
            ['label' => 'Monto', 'ancho' => 16, 'numero' => true],
        ];
    }

    /**
     * @return list<mixed>
     */
    public static function corteDiaAnexo8(string $etiqueta): array
    {
        return [$etiqueta, '', '', ''];
    }
}
