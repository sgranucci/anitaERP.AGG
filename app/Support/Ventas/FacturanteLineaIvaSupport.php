<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Configuracion\Impuesto;

/**
 * Facturante manda el precio unitario neto (gravado).
 * La emisión del local graba el precio con IVA y, aparte, gravado / exento / IVA.
 * La letra solo cambia la impresión.
 */
final class FacturanteLineaIvaSupport
{
    /**
     * Si la suma de renglones es el neto (total / 1,21 o / 1,105), pasa el unitario a precio con IVA.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    public static function precioUnitarioConIva(array $lineas, float $totalComprobante): array
    {
        $total = abs($totalComprobante);
        $suma = self::sumaImportes($lineas);
        if ($suma <= 0.0001 || $total <= 0.0001) {
            return $lineas;
        }
        if (abs($total / $suma - 1) < 0.005) {
            return $lineas;
        }

        $factor = self::factorIva($total / $suma);
        if ($factor === null) {
            return $lineas;
        }

        $ultima = null;
        foreach ($lineas as $i => $item) {
            if (self::tasaLinea($item) <= 0) {
                continue;
            }
            $lineas[$i]['precio'] = round((float) $item['precio'] * $factor, 2);
            $lineas[$i]['incluyeimpuesto'] = '1';
            if (isset($lineas[$i]['medidas'][0]) && is_array($lineas[$i]['medidas'][0])) {
                $lineas[$i]['medidas'][0]['precio'] = $lineas[$i]['precio'];
            }
            $ultima = $i;
        }

        if ($ultima !== null) {
            $dif = round($total - self::sumaImportes($lineas), 2);
            $cant = abs((float) ($lineas[$ultima]['cantidad'] ?? 0));
            if (abs($dif) >= 0.01 && abs($dif) <= 0.05 && $cant > 0.0001) {
                $lineas[$ultima]['precio'] = round((float) $lineas[$ultima]['precio'] + ($dif / $cant), 2);
                if (isset($lineas[$ultima]['medidas'][0]) && is_array($lineas[$ultima]['medidas'][0])) {
                    $lineas[$ultima]['medidas'][0]['precio'] = $lineas[$ultima]['precio'];
                }
            }
        }

        return $lineas;
    }

    /**
     * Arma el pie igual que una factura emitida en el local y conserva percepciones que ya venían.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @param  list<array<string, mixed>>  $conceptosActuales
     * @return list<array<string, mixed>>
     */
    public static function conceptosComoEmisionLocal(array $lineas, array $conceptosActuales): array
    {
        $porTasa = [];
        $subtotal = 0.0;
        foreach ($lineas as $item) {
            $importe = round(self::importeLinea($item), 2);
            if (abs($importe) < 0.0001) {
                continue;
            }
            $tasa = self::tasaLinea($item);
            $impuestoId = (int) ($item['impuesto_id'] ?? 0);
            if ($tasa <= 0) {
                $clave = '0';
                $gravado = $importe;
                $iva = 0.0;
            } else {
                $clave = number_format($tasa, 3, '.', '');
                $iva = round($importe - ($importe / (1 + ($tasa / 100))), 2);
                $gravado = round($importe - $iva, 2);
            }
            if (! isset($porTasa[$clave])) {
                $porTasa[$clave] = [
                    'tasa' => $tasa,
                    'impuesto_id' => $impuestoId > 0 ? $impuestoId : null,
                    'gravado' => 0.0,
                    'iva' => 0.0,
                ];
            }
            $porTasa[$clave]['gravado'] += $gravado;
            $porTasa[$clave]['iva'] += $iva;
            $subtotal += $gravado;
        }

        $conceptos = [[
            'concepto' => 'Subtotal',
            'tasa' => 0,
            'importe' => round($subtotal, 2),
            'baseimponible' => 0,
        ]];

        $total = 0.0;
        foreach ($porTasa as $fila) {
            $gravado = round($fila['gravado'], 2);
            $tasaTxt = number_format((float) $fila['tasa'], 3, '.', '');
            if ((float) $fila['tasa'] <= 0) {
                $conceptos[] = [
                    'concepto' => 'Exento',
                    'tasa' => 0,
                    'importe' => $gravado,
                    'baseimponible' => 0,
                    'impuesto_id' => $fila['impuesto_id'],
                ];
            } else {
                $conceptos[] = [
                    'concepto' => 'Gravado al '.$tasaTxt.'%',
                    'tasa' => (float) $fila['tasa'],
                    'importe' => $gravado,
                    'baseimponible' => 0,
                    'impuesto_id' => $fila['impuesto_id'],
                ];
                $iva = round($fila['iva'], 2);
                $conceptos[] = [
                    'concepto' => 'Iva '.$tasaTxt.'%',
                    'tasa' => (float) $fila['tasa'],
                    'importe' => $iva,
                    'baseimponible' => $gravado,
                    'impuesto_id' => $fila['impuesto_id'],
                ];
                $total += $iva;
            }
            $total += $gravado;
        }

        foreach (self::percepciones($conceptosActuales) as $percepcion) {
            $conceptos[] = $percepcion;
            $total += (float) ($percepcion['importe'] ?? 0);
        }

        $conceptos[] = [
            'concepto' => 'Total',
            'tasa' => 0,
            'importe' => round($total, 2),
            'baseimponible' => 0,
        ];

        return $conceptos;
    }

    public static function sumaImportes(array $lineas): float
    {
        $suma = 0.0;
        foreach ($lineas as $item) {
            $suma += self::importeLinea($item);
        }

        return round($suma, 2);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function importeLinea(array $item): float
    {
        $cant = abs((float) ($item['cantidad'] ?? 0));
        $precio = (float) ($item['precio'] ?? 0);
        $dto = (float) ($item['descuento'] ?? 0);
        $factor = ($dto > 0 && $dto < 100) ? (1 - ($dto / 100)) : 1.0;

        return $cant * $precio * $factor;
    }

    /**
     * @param  list<array<string, mixed>>  $conceptos
     * @return list<array<string, mixed>>
     */
    private static function percepciones(array $conceptos): array
    {
        $out = [];
        foreach ($conceptos as $concepto) {
            $nombre = (string) ($concepto['concepto'] ?? '');
            if ($nombre === '' || self::esIvaOTotal($nombre)) {
                continue;
            }
            if (stripos($nombre, 'perc') === false && stripos($nombre, 'ingreso') === false) {
                continue;
            }
            $out[] = $concepto;
        }

        return $out;
    }

    private static function esIvaOTotal(string $nombre): bool
    {
        return in_array($nombre, ['Subtotal', 'Total', 'Exento'], true)
            || str_starts_with($nombre, 'Gravado')
            || str_starts_with($nombre, 'Descuento')
            || (stripos($nombre, 'iva') !== false && stripos($nombre, 'perc') === false);
    }

    private static function factorIva(float $ratio): ?float
    {
        if (abs($ratio - 1.21) < 0.002) {
            return 1.21;
        }
        if (abs($ratio - 1.105) < 0.002) {
            return 1.105;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function tasaLinea(array $item): float
    {
        $id = (int) ($item['impuesto_id'] ?? 0);
        if ($id <= 0) {
            return 21.0;
        }
        $valor = Impuesto::query()->whereKey($id)->value('valor');

        return $valor === null ? 21.0 : (float) $valor;
    }
}
