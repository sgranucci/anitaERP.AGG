<?php

declare(strict_types=1);

namespace App\Support\Caja;

/**
 * Bloques del informe de cierre de caja (Anita l-ciecaja).
 * PDF, Excel y el armado de filas planas de pantalla usan la misma lista.
 */
final class CierreCajaReporteSecciones
{
    /**
     * @return list<array{
     *     clave: string,
     *     titulo: string,
     *     columnas: list<string>,
     *     anchos_pdf: list<string>,
     *     cols_numericas: list<string>,
     *     col_ultima: string
     * }>
     */
    public static function definiciones(): array
    {
        return [
            [
                'clave' => 'saldos',
                'titulo' => 'Resumen de movimientos por cuenta',
                'columnas' => ['Código', 'Cuenta', 'Saldo anterior', 'Ingresos', 'Egresos', 'Total mov.', 'Saldo actual'],
                'anchos_pdf' => ['8%', '32%', '12%', '12%', '12%', '12%', '12%'],
                'cols_numericas' => ['C', 'D', 'E', 'F', 'G'],
                'col_ultima' => 'G',
            ],
            [
                'clave' => 'cheques_emitidos',
                'titulo' => 'Cheques propios emitidos',
                'columnas' => ['Código', 'Cuenta', 'Cantidad', 'Importe'],
                'anchos_pdf' => ['12%', '52%', '16%', '20%'],
                'cols_numericas' => ['C', 'D'],
                'col_ultima' => 'D',
            ],
            [
                'clave' => 'depositos',
                'titulo' => 'Depósitos',
                'columnas' => ['Fecha', 'Cheque', 'Banco / Cuenta', 'Boleta', 'Importe'],
                'anchos_pdf' => ['12%', '14%', '42%', '16%', '16%'],
                'cols_numericas' => ['E'],
                'col_ultima' => 'E',
            ],
            [
                'clave' => 'cobro_pago',
                'titulo' => 'Cobranzas / Pagos',
                'columnas' => ['Valores', 'Cobro', 'Pago'],
                'anchos_pdf' => ['50%', '25%', '25%'],
                'cols_numericas' => ['B', 'C'],
                'col_ultima' => 'C',
            ],
            [
                'clave' => 'cheques_recibidos',
                'titulo' => 'Cheques de terceros recibidos',
                'columnas' => ['N.Int.', 'N.Cli.', 'Fec.Che.', 'N.Cheque', 'Banco', 'Importe'],
                'anchos_pdf' => ['10%', '10%', '12%', '14%', '40%', '14%'],
                'cols_numericas' => ['F'],
                'col_ultima' => 'F',
            ],
            [
                'clave' => 'cheques_rechazados',
                'titulo' => 'Cheques de terceros rechazados',
                'columnas' => ['N.Int.', 'N.Cli.', 'Fec.Che.', 'N.Cheque', 'Banco', 'Importe'],
                'anchos_pdf' => ['10%', '10%', '12%', '14%', '40%', '14%'],
                'cols_numericas' => ['F'],
                'col_ultima' => 'F',
            ],
            [
                'clave' => 'cheques_caucion',
                'titulo' => 'Cheques entregados en caución',
                'columnas' => ['N.Int.', 'N.Cli.', 'Fec.Che.', 'N.Cheque', 'Banco', 'Importe'],
                'anchos_pdf' => ['10%', '10%', '12%', '14%', '40%', '14%'],
                'cols_numericas' => ['F'],
                'col_ultima' => 'F',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return list<array<string, mixed>>
     */
    public static function filasDe(array $resultado, string $clave): array
    {
        if ($clave === 'cobro_pago') {
            $filas = $resultado['cobro_pago']['filas'] ?? [];

            return is_array($filas) ? $filas : [];
        }

        $filas = $resultado[$clave] ?? [];

        return is_array($filas) ? $filas : [];
    }

    /**
     * Bloques con datos, en orden de impresión.
     *
     * @param  array<string, mixed>  $resultado
     * @return list<array{
     *     clave: string,
     *     titulo: string,
     *     columnas: list<string>,
     *     anchos_pdf: list<string>,
     *     cols_numericas: list<string>,
     *     col_ultima: string,
     *     filas: list<array<string, mixed>>
     * }>
     */
    public static function bloques(array $resultado): array
    {
        $out = [];
        foreach (self::definiciones() as $def) {
            $filas = self::filasDe($resultado, $def['clave']);
            if ($filas === []) {
                continue;
            }
            $def['filas'] = $filas;
            $out[] = $def;
        }

        return $out;
    }
}
