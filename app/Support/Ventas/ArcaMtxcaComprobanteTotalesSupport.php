<?php

declare(strict_types=1);

namespace App\Support\Ventas;

/**
 * Coherencia entre el detalle de ítems y los totales de cabecera en WSMTXCA.
 *
 * Validaciones de autorizarComprobante (manual ARCA Web-Service-MTXCA v25):
 *
 *  110  importeGravado   = Σ (importeItem − importeIVA) de los ítems con condición 3, 4, 5 ó 6
 *  111  importeNoGravado = Σ importeItem de los ítems con condición 1
 *  112  importeExento    = Σ importeItem de los ítems con condición 2
 *  113  importeSubtotal  = importeGravado + importeNoGravado + importeExento
 *  114  importeOtrosTributos = Σ importe de arrayOtrosTributos
 *  115  importeTotal     = importeSubtotal + importeOtrosTributos + Σ subtotalIVA
 *  116  importeTotal     = importeOtrosTributos + Σ importeItem
 *  401  subtotalIVA      = Σ importeIVA de los ítems de esa alícuota
 *  403  cada alícuota presente en los ítems debe tener su subtotalIVA (y viceversa)
 *
 * Margen aceptado por ARCA: error relativo <= 0,01 % o error absoluto <= 0,01 × cantidad de ítems.
 */
final class ArcaMtxcaComprobanteTotalesSupport
{
    public const CONDICION_NO_GRAVADO = 1;

    public const CONDICION_EXENTO = 2;

    /** Códigos de condición IVA gravada (consultarCondicionesIVA) y su alícuota. */
    private const ALICUOTA_POR_CODIGO = [
        3 => 0.0,
        4 => 10.5,
        5 => 21.0,
        6 => 27.0,
        8 => 5.0,
        9 => 2.5,
    ];

    public static function esCondicionGravada(int $codigo): bool
    {
        return array_key_exists($codigo, self::ALICUOTA_POR_CODIGO);
    }

    public static function alicuotaPorCodigo(int $codigo): ?float
    {
        return self::ALICUOTA_POR_CODIGO[$codigo] ?? null;
    }

    public static function codigoPorTasa(float $tasa): ?int
    {
        foreach (self::ALICUOTA_POR_CODIGO as $codigo => $alicuota) {
            if (abs($alicuota - $tasa) < 0.001) {
                return $codigo;
            }
        }

        return null;
    }

    /**
     * La alícuota real de la línea manda sobre `impuesto.codigoarca`: un ítem marcado 21 %
     * con tasa 0 hace fallar las validaciones 110 y 515.
     */
    public static function resolverCodigoCondicion(int|string|null $codigoImpuesto, float $tasa): int
    {
        $codigo = is_numeric($codigoImpuesto) ? (int) $codigoImpuesto : 0;

        if ($tasa > 0) {
            return self::codigoPorTasa($tasa)
                ?? (self::esCondicionGravada($codigo) ? $codigo : 5);
        }

        // Sin alícuota el ERP totaliza la línea como exenta: informarla como "gravada al 0 %"
        // la haría entrar en importeGravado (validación 110) y vaciaría importeExento (112).
        return $codigo === self::CONDICION_NO_GRAVADO
            ? self::CONDICION_NO_GRAVADO
            : self::CONDICION_EXENTO;
    }

    public static function tolerancia(float $objetivo, int $cantidadItems): float
    {
        return max(0.01 * max(1, $cantidadItems), abs($objetivo) * 0.0001);
    }

    /**
     * Ajusta el detalle para que reproduzca los totales de cabecera.
     *
     * Cada fila es un array con al menos: codigo_condicion_iva, alicuota, neto, iva.
     * Los objetivos por alícuota salen de `impuestos` (arraySubtotalesIVA), que ya incluye
     * conceptos que no son línea del pedido (logística, descuento general prorrateado).
     *
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $cabecera
     * @return list<array<string, mixed>>
     */
    public static function conciliar(array $filas, array $cabecera): array
    {
        $filas = self::conciliarGravados($filas, $cabecera);
        $filas = self::conciliarSinIva($filas, self::CONDICION_EXENTO, (float) ($cabecera['exento'] ?? 0));
        $filas = self::conciliarSinIva($filas, self::CONDICION_NO_GRAVADO, (float) ($cabecera['nogravado'] ?? 0));

        return array_values($filas);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $cabecera
     * @return list<array<string, mixed>>
     */
    private static function conciliarGravados(array $filas, array $cabecera): array
    {
        $objetivos = self::objetivosPorAlicuota($cabecera, $filas);

        foreach ($objetivos as $codigo => $objetivo) {
            $indices = self::indicesPorCondicion($filas, $codigo);
            $netoActual = 0.0;
            foreach ($indices as $i) {
                $netoActual += (float) $filas[$i]['neto'];
            }

            $difNeto = round($objetivo['neto'] - $netoActual, 2);
            if (abs($difNeto) >= 0.01) {
                if ($indices !== [] && abs($difNeto) <= self::tolerancia($objetivo['neto'], count($indices))) {
                    $ultimo = $indices[array_key_last($indices)];
                    $filas[$ultimo]['neto'] = round((float) $filas[$ultimo]['neto'] + $difNeto, 2);
                } else {
                    $filas[] = self::filaAjuste($codigo, $objetivo['alicuota'], $difNeto);
                    $indices = self::indicesPorCondicion($filas, $codigo);
                }
            }

            foreach ($indices as $i) {
                $filas[$i]['iva'] = round((float) $filas[$i]['neto'] * $objetivo['alicuota'] / 100, 2);
            }

            if ($indices === []) {
                continue;
            }

            $ivaActual = 0.0;
            foreach ($indices as $i) {
                $ivaActual += (float) $filas[$i]['iva'];
            }
            $difIva = round($objetivo['iva'] - $ivaActual, 2);
            if (abs($difIva) >= 0.01 && abs($difIva) <= self::tolerancia($objetivo['iva'], count($indices))) {
                $ultimo = $indices[array_key_last($indices)];
                $filas[$ultimo]['iva'] = round((float) $filas[$ultimo]['iva'] + $difIva, 2);
            }
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private static function conciliarSinIva(array $filas, int $codigo, float $objetivo): array
    {
        $indices = self::indicesPorCondicion($filas, $codigo);
        $actual = 0.0;
        foreach ($indices as $i) {
            $actual += (float) $filas[$i]['neto'];
        }

        $dif = round($objetivo - $actual, 2);
        if (abs($dif) < 0.01) {
            return $filas;
        }

        if ($indices !== [] && abs($dif) <= self::tolerancia($objetivo, count($indices))) {
            $ultimo = $indices[array_key_last($indices)];
            $filas[$ultimo]['neto'] = round((float) $filas[$ultimo]['neto'] + $dif, 2);

            return $filas;
        }

        $filas[] = self::filaAjuste($codigo, 0.0, $dif);

        return $filas;
    }

    /**
     * Objetivo de neto e IVA por código de condición, tomado de arraySubtotalesIVA.
     *
     * @param  array<string, mixed>  $cabecera
     * @param  list<array<string, mixed>>  $filas
     * @return array<int, array{alicuota: float, neto: float, iva: float}>
     */
    private static function objetivosPorAlicuota(array $cabecera, array $filas): array
    {
        $objetivos = [];

        foreach ((array) ($cabecera['impuestos'] ?? []) as $subtotal) {
            if (! is_array($subtotal)) {
                continue;
            }
            $codigo = (int) ($subtotal['id'] ?? 0);
            $alicuota = self::alicuotaPorCodigo($codigo);
            if ($alicuota === null) {
                continue;
            }

            $iva = round((float) ($subtotal['importe'] ?? 0), 2);
            $neto = round((float) ($subtotal['base_imp'] ?? 0), 2);
            if ($neto <= 0 && $alicuota > 0) {
                $neto = round($iva * 100 / $alicuota, 2);
            }

            if (isset($objetivos[$codigo])) {
                $objetivos[$codigo]['neto'] = round($objetivos[$codigo]['neto'] + $neto, 2);
                $objetivos[$codigo]['iva'] = round($objetivos[$codigo]['iva'] + $iva, 2);

                continue;
            }

            $objetivos[$codigo] = ['alicuota' => $alicuota, 'neto' => $neto, 'iva' => $iva];
        }

        if ($objetivos !== []) {
            return $objetivos;
        }

        // Sin subtotales de IVA: el gravado de cabecera se reparte sobre la alícuota de las filas.
        $gravado = round((float) ($cabecera['gravado'] ?? 0), 2);
        if ($gravado == 0.0) {
            return [];
        }

        foreach ($filas as $fila) {
            $codigo = (int) $fila['codigo_condicion_iva'];
            if (self::esCondicionGravada($codigo)) {
                $alicuota = (float) $fila['alicuota'];

                return [$codigo => [
                    'alicuota' => $alicuota,
                    'neto' => $gravado,
                    'iva' => round($gravado * $alicuota / 100, 2),
                ]];
            }
        }

        $iva = round((float) ($cabecera['iva'] ?? 0), 2);
        $alicuota = $gravado != 0.0 ? round($iva * 100 / $gravado, 1) : 0.0;
        $codigo = self::codigoPorTasa($alicuota) ?? 5;

        return [$codigo => [
            'alicuota' => self::alicuotaPorCodigo($codigo) ?? 21.0,
            'neto' => $gravado,
            'iva' => $iva,
        ]];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<int>
     */
    private static function indicesPorCondicion(array $filas, int $codigo): array
    {
        $indices = [];
        foreach ($filas as $i => $fila) {
            if ((int) ($fila['codigo_condicion_iva'] ?? 0) === $codigo) {
                $indices[] = $i;
            }
        }

        return $indices;
    }

    /**
     * @return array<string, mixed>
     */
    private static function filaAjuste(int $codigo, float $alicuota, float $neto): array
    {
        return [
            'codigo' => '',
            'descripcion' => $neto < 0 ? 'Bonificación' : 'Conceptos facturados',
            'cantidad' => 1.0,
            'codigo_unidad_medida' => 7,
            'precio_lista' => round($neto, 2),
            'bonificacion' => 0.0,
            'codigo_condicion_iva' => $codigo,
            'alicuota' => $alicuota,
            'neto' => round($neto, 2),
            'iva' => round($neto * $alicuota / 100, 2),
        ];
    }

    /**
     * Verifica el request armado contra las validaciones de totales del manual.
     *
     * @param  array<string, mixed>  $req
     * @return list<string>
     */
    public static function inconsistencias(array $req): array
    {
        $items = self::itemsDelRequest($req);
        if ($items === []) {
            return [];
        }

        $cantidad = count($items);
        $gravado = 0.0;
        $nogravado = 0.0;
        $exento = 0.0;
        $sumaItems = 0.0;
        $ivaPorCodigo = [];

        foreach ($items as $item) {
            $codigo = (int) ($item['codigoCondicionIVA'] ?? 0);
            $importe = round((float) ($item['importeItem'] ?? 0), 2);
            $sumaItems += $importe;

            // Clase B no informa importeIVA: el IVA sale del importeItem y la alícuota (110 y 401).
            $alicuota = self::alicuotaPorCodigo($codigo) ?? 0.0;
            $iva = array_key_exists('importeIVA', $item)
                ? round((float) $item['importeIVA'], 2)
                : round($importe - $importe / (1 + $alicuota / 100), 2);

            if (self::esCondicionGravada($codigo)) {
                $gravado += $importe - $iva;
                $ivaPorCodigo[$codigo] = round(($ivaPorCodigo[$codigo] ?? 0) + $iva, 2);

                continue;
            }
            if ($codigo === self::CONDICION_NO_GRAVADO) {
                $nogravado += $importe;

                continue;
            }
            if ($codigo === self::CONDICION_EXENTO) {
                $exento += $importe;
            }
        }

        $errores = [];
        self::comparar($errores, 110, 'importeGravado', (float) ($req['importeGravado'] ?? 0), $gravado, $cantidad);
        self::comparar($errores, 111, 'importeNoGravado', (float) ($req['importeNoGravado'] ?? 0), $nogravado, $cantidad);
        self::comparar($errores, 112, 'importeExento', (float) ($req['importeExento'] ?? 0), $exento, $cantidad);

        $subtotal = (float) ($req['importeSubtotal'] ?? 0);
        self::comparar(
            $errores,
            113,
            'importeSubtotal',
            $subtotal,
            (float) ($req['importeGravado'] ?? 0) + (float) ($req['importeNoGravado'] ?? 0) + (float) ($req['importeExento'] ?? 0),
            $cantidad,
        );

        $tributos = (float) ($req['importeOtrosTributos'] ?? 0);
        $total = (float) ($req['importeTotal'] ?? 0);
        $sumaSubtotalesIva = self::sumaSubtotalesIva($req);

        self::comparar($errores, 115, 'importeTotal', $total, $subtotal + $tributos + $sumaSubtotalesIva, $cantidad);
        self::comparar($errores, 116, 'importeTotal', $total, $tributos + $sumaItems, $cantidad);

        foreach (self::subtotalesIvaPorCodigo($req) as $codigo => $importe) {
            self::comparar(
                $errores,
                401,
                'subtotalIVA código '.$codigo,
                $importe,
                $ivaPorCodigo[$codigo] ?? 0.0,
                $cantidad,
            );
            unset($ivaPorCodigo[$codigo]);
        }

        foreach (array_keys($ivaPorCodigo) as $codigo) {
            $errores[] = sprintf(
                '[403] falta el subtotal de IVA de la alícuota %s, presente en los ítems.',
                $codigo,
            );
        }

        return $errores;
    }

    /**
     * @param  list<string>  $errores
     */
    private static function comparar(array &$errores, int $codigo, string $campo, float $declarado, float $calculado, int $cantidadItems): void
    {
        $dif = round($declarado - $calculado, 2);
        if (abs($dif) <= self::tolerancia($declarado, $cantidadItems)) {
            return;
        }

        $errores[] = sprintf(
            '[%d] %s declarado %s y el detalle da %s (diferencia %s).',
            $codigo,
            $campo,
            number_format($declarado, 2, ',', '.'),
            number_format($calculado, 2, ',', '.'),
            number_format($dif, 2, ',', '.'),
        );
    }

    /**
     * @param  array<string, mixed>  $req
     * @return list<array<string, mixed>>
     */
    private static function itemsDelRequest(array $req): array
    {
        $items = $req['arrayItems']['item'] ?? [];
        if ($items === []) {
            return [];
        }

        return array_is_list($items) ? $items : [$items];
    }

    /**
     * @param  array<string, mixed>  $req
     * @return array<int, float>
     */
    private static function subtotalesIvaPorCodigo(array $req): array
    {
        $subtotales = $req['arraySubtotalesIVA']['subtotalIVA'] ?? [];
        if ($subtotales === []) {
            return [];
        }
        $subtotales = array_is_list($subtotales) ? $subtotales : [$subtotales];

        $out = [];
        foreach ($subtotales as $subtotal) {
            $codigo = (int) ($subtotal['codigo'] ?? 0);
            $out[$codigo] = round(($out[$codigo] ?? 0) + (float) ($subtotal['importe'] ?? 0), 2);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $req
     */
    private static function sumaSubtotalesIva(array $req): float
    {
        return round(array_sum(self::subtotalesIvaPorCodigo($req)), 2);
    }
}
