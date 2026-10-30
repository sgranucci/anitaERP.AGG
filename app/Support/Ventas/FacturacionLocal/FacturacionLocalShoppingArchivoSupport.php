<?php

declare(strict_types=1);

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Ventas\Venta;
use App\Support\Ventas\IvaVentas\IvaVentasDesgloseSupport;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Archivo de ventas del shopping (VMall): TRANCOMP_DDMMAAAA.TXT.
 * Cada registro mide 100 caracteres y termina en CR/LF.
 *
 * Línea C: resumen del comprobante. Línea P: un medio de pago (1 a 5).
 * El prefijo de 65 caracteres (local, contrato, caja, fecha, comprobante, documento)
 * se repite en C y en P para identificar el ticket.
 */
final class FacturacionLocalShoppingArchivoSupport
{
    /**
     * @param  iterable<int, Venta>  $ventas
     * @param  array<int, list<array{nombre:string,es_tarjeta:bool,monto:float}>>  $pagosPorVenta
     * @return array{contenido:string,comprobantes:int,lineas:int,advertencias:list<string>}
     */
    public static function armar(
        iterable $ventas,
        array $pagosPorVenta,
        string $nroLocal,
        string $nroContrato,
        string $nroCaja,
    ): array {
        $lineas = [];
        $advertencias = [];
        $comprobantes = 0;

        foreach ($ventas as $venta) {
            $comprobantes++;
            $bloque = self::lineasComprobante($venta, $pagosPorVenta[(int) $venta->id] ?? [], $nroLocal, $nroContrato, $nroCaja);
            foreach ($bloque['lineas'] as $linea) {
                if (strlen($linea) !== 100) {
                    throw new InvalidArgumentException(
                        'La línea del comprobante '.$venta->codigo.' no mide 100 caracteres ('.strlen($linea).').'
                    );
                }
                $lineas[] = $linea;
            }
            foreach ($bloque['advertencias'] as $aviso) {
                $advertencias[] = $aviso;
            }
        }

        $contenido = $lineas === [] ? '' : implode("\r\n", $lineas)."\r\n";

        return [
            'contenido' => $contenido,
            'comprobantes' => $comprobantes,
            'lineas' => count($lineas),
            'advertencias' => $advertencias,
        ];
    }

    public static function nombreArchivo(string $fechaYmd): string
    {
        $fecha = Carbon::parse($fechaYmd);

        return 'TRANCOMP_'.$fecha->format('dmY').'.TXT';
    }

    /**
     * @param  list<array{nombre:string,es_tarjeta:bool,monto:float}>  $pagos
     * @return array{lineas:list<string>,advertencias:list<string>}
     */
    private static function lineasComprobante(
        Venta $venta,
        array $pagos,
        string $nroLocal,
        string $nroContrato,
        string $nroCaja,
    ): array {
        [$neto, $iva, $otros] = self::importes($venta);
        $target = round($neto + $iva + $otros, 2);
        $esNc = self::esNotaCredito($venta);
        $prefijo = self::prefijo($venta, $nroLocal, $nroContrato, $nroCaja);
        $advertencias = [];

        $medios = self::mediosDePago($pagos, $target, $esNc, (string) $venta->codigo, $advertencias);

        $lineas = [
            $prefijo.'C'.'0000'.self::importe($neto).self::importe($iva).self::importe($otros).str_repeat(' ', 3),
        ];
        foreach ($medios as $medio) {
            $lineas[] = $prefijo
                .'P'
                .$medio['tipo']
                .self::descripcion($medio['descripcion'])
                .self::importe($medio['monto']);
        }

        return ['lineas' => $lineas, 'advertencias' => $advertencias];
    }

    private static function prefijo(Venta $venta, string $nroLocal, string $nroContrato, string $nroCaja): string
    {
        $cuando = $venta->created_at instanceof Carbon
            ? $venta->created_at->timezone((string) config('app.timezone'))
            : Carbon::parse((string) ($venta->created_at ?: $venta->fecha))->timezone((string) config('app.timezone'));

        $pv = ((int) preg_replace('/\D+/', '', (string) ($venta->puntoventas->codigo ?? '0'))) % 10000;
        $numero = ((int) $venta->numerocomprobante) % 100000000;

        $prefijo = 'L'
            .self::ceros($nroLocal, 4)
            .str_repeat(' ', 5)
            .self::ceros($nroContrato, 10)
            .self::ceros($nroCaja, 2)
            .$cuando->format('YmdHis')
            .self::letra($venta)
            .(self::esNotaCredito($venta) ? 'C' : 'D')
            .self::ceros((string) $pv, 4)
            .self::ceros((string) $numero, 8)
            .(IvaVentasDesgloseSupport::esAnulada($venta) ? 'A' : 'N')
            .'00'
            .self::documento($venta);

        if (strlen($prefijo) !== 65) {
            throw new InvalidArgumentException(
                'El prefijo del comprobante '.$venta->codigo.' no mide 65 caracteres ('.strlen($prefijo).').'
            );
        }

        return $prefijo;
    }

    private static function esNotaCredito(Venta $venta): bool
    {
        return strtoupper((string) ($venta->tipotransacciones->operacion ?? '')) === 'C';
    }

    private static function letra(Venta $venta): string
    {
        $letra = strtoupper(trim((string) ($venta->condicionivas->letra ?? '')));
        if (in_array($letra, ['A', 'B', 'C'], true)) {
            return $letra;
        }
        if (preg_match('/\b([ABC])-/', (string) $venta->codigo, $coincide) === 1) {
            return $coincide[1];
        }

        return 'B';
    }

    private static function documento(Venta $venta): string
    {
        $digitos = preg_replace('/\D+/', '', (string) ($venta->nroinscripcion ?? '')) ?? '';
        if ($digitos === '' || ltrim($digitos, '0') === '') {
            return '000000000000';
        }

        return str_pad(substr($digitos, -12), 12, '0', STR_PAD_LEFT);
    }

    /**
     * Neto sin IVA, IVA y otros impuestos. Siempre en positivo: el tipo C/D indica la nota de crédito.
     *
     * @return array{0:float,1:float,2:float}
     */
    private static function importes(Venta $venta): array
    {
        $gravado = 0.0;
        $exento = 0.0;
        $noGravado = 0.0;
        $iva = 0.0;
        $otros = 0.0;

        foreach ($venta->venta_impuestos as $imp) {
            $concepto = trim((string) $imp->concepto);
            $importe = abs((float) $imp->importe);
            if (IvaVentasDesgloseSupport::esTotalIva($concepto)) {
                $iva += $importe;
                continue;
            }
            if (stripos($concepto, 'Total') === 0 || stripos($concepto, 'Subtotal') === 0) {
                continue;
            }
            if (stripos($concepto, 'No Gravado') !== false || stripos($concepto, 'No gravado') !== false) {
                $noGravado += $importe;
                continue;
            }
            if (stripos($concepto, 'Exento') !== false) {
                $exento += $importe;
                continue;
            }
            if (stripos($concepto, 'Impuesto Interno') !== false || stripos($concepto, 'Imp. interno') !== false) {
                $otros += $importe;
                continue;
            }
            if (stripos($concepto, 'Percepcion IVA') !== false || stripos($concepto, 'Perc. IVA') !== false) {
                $iva += $importe;
                continue;
            }
            if (stripos($concepto, 'Perc.') !== false
                || stripos($concepto, 'Percepcion') !== false
                || stripos($concepto, 'Sellado') !== false) {
                $otros += $importe;
                continue;
            }
            if (stripos($concepto, 'Gravado') !== false) {
                $gravado += $importe;
                continue;
            }
            if (stripos($concepto, 'Iva ') !== false || stripos($concepto, 'IVA') === 0) {
                $iva += $importe;
            }
        }

        $neto = round($gravado + $exento + $noGravado, 2);
        $iva = round($iva, 2);
        $otros = round($otros, 2);
        $total = round(abs((float) $venta->total), 2);
        if ($total <= 0) {
            $total = round($neto + $iva + $otros, 2);
        }
        if (abs(round($neto + $iva + $otros, 2) - $total) > 0.05) {
            $neto = round($total - $iva - $otros, 2);
            if ($neto < 0) {
                $neto = $total;
                $iva = 0.0;
                $otros = 0.0;
            }
        }

        return [round(max(0, $neto), 2), round(max(0, $iva), 2), round(max(0, $otros), 2)];
    }

    /**
     * @param  list<array{nombre:string,es_tarjeta:bool,monto:float}>  $pagos
     * @param  list<string>  $advertencias
     * @return list<array{tipo:string,descripcion:string,monto:float}>
     */
    private static function mediosDePago(array $pagos, float $target, bool $esNc, string $codigo, array &$advertencias): array
    {
        $agrupados = [];
        foreach ($pagos as $pago) {
            $monto = round((float) ($pago['monto'] ?? 0), 2);
            if ($monto <= 0.009) {
                continue;
            }
            $nombre = trim((string) ($pago['nombre'] ?? ''));
            $clave = $nombre.'|'.((bool) ($pago['es_tarjeta'] ?? false) ? '1' : '0');
            if (! isset($agrupados[$clave])) {
                $agrupados[$clave] = [
                    'tipo' => self::tipoMedio($nombre, (bool) ($pago['es_tarjeta'] ?? false)),
                    'descripcion' => $nombre !== '' ? $nombre : 'MEDIO',
                    'monto' => 0.0,
                ];
            }
            $agrupados[$clave]['monto'] = round($agrupados[$clave]['monto'] + $monto, 2);
        }

        $medios = array_values($agrupados);
        usort($medios, static function (array $a, array $b): int {
            $orden = ['E' => 0, 'T' => 1, 'O' => 2];

            return [$orden[$a['tipo']] ?? 9, $a['descripcion']] <=> [$orden[$b['tipo']] ?? 9, $b['descripcion']];
        });

        if ($medios === []) {
            $medios[] = [
                'tipo' => 'O',
                'descripcion' => $esNc ? 'NOTA DE CREDITO' : 'SIN MEDIO',
                'monto' => $target,
            ];
            if ($target > 0.009) {
                $advertencias[] = $codigo.': sin medios de pago en caja; se informó el total como '
                    .($esNc ? 'nota de crédito' : 'otro').'.';
            }
        }

        if (count($medios) > 5) {
            $extra = array_slice($medios, 4);
            $medios = array_slice($medios, 0, 4);
            $montoExtra = round(array_sum(array_column($extra, 'monto')), 2);
            $medios[] = ['tipo' => 'O', 'descripcion' => 'OTROS', 'monto' => $montoExtra];
            $advertencias[] = $codigo.': más de 5 medios; el resto se agrupó en OTROS.';
        }

        $suma = round(array_sum(array_column($medios, 'monto')), 2);
        $diff = round($target - $suma, 2);
        if ($diff > 0.02) {
            if (count($medios) >= 5) {
                $ultimo = count($medios) - 1;
                $medios[$ultimo]['monto'] = round($medios[$ultimo]['monto'] + $diff, 2);
                $advertencias[] = $codigo.': los medios no cubren el total; se sumó la diferencia al último medio.';
            } else {
                $medios[] = [
                    'tipo' => 'O',
                    'descripcion' => 'DIFERENCIA',
                    'monto' => $diff,
                ];
                $advertencias[] = $codigo.': los medios no cubren el total; se agregó diferencia de '
                    .number_format($diff, 2, '.', '').'.';
            }
        } elseif ($diff < -0.02 && $medios !== []) {
            $idx = 0;
            foreach ($medios as $i => $medio) {
                if ($medio['monto'] > $medios[$idx]['monto']) {
                    $idx = $i;
                }
            }
            $ajustado = round($medios[$idx]['monto'] + $diff, 2);
            if ($ajustado < 0) {
                throw new InvalidArgumentException('Los medios de pago de '.$codigo.' superan el total del comprobante.');
            }
            $medios[$idx]['monto'] = $ajustado;
            $advertencias[] = $codigo.': los medios superan el total; se ajustó el mayor.';
        } elseif (abs($diff) > 0.001 && $medios !== []) {
            $ultimo = count($medios) - 1;
            $medios[$ultimo]['monto'] = round($medios[$ultimo]['monto'] + $diff, 2);
        }

        return $medios;
    }

    private static function tipoMedio(string $nombre, bool $esTarjeta): string
    {
        if ($esTarjeta) {
            return 'T';
        }
        $texto = self::ascii($nombre);
        if (preg_match('/\b(EFECTIVO|FONDO FIJO|CAJA)\b/', $texto) === 1) {
            return 'E';
        }

        return 'O';
    }

    private static function descripcion(string $texto): string
    {
        $texto = self::ascii($texto);
        if (strlen($texto) > 24) {
            $texto = substr($texto, 0, 24);
        }

        return str_pad($texto, 24, ' ', STR_PAD_RIGHT);
    }

    private static function ascii(string $texto): string
    {
        $texto = trim($texto);
        $convertido = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if ($convertido === false) {
            $convertido = $texto;
        }
        $convertido = strtoupper($convertido);
        $convertido = preg_replace('/[^A-Z0-9 .+\-\/]/', ' ', $convertido) ?? '';
        $convertido = preg_replace('/\s+/', ' ', trim($convertido)) ?? '';

        return $convertido;
    }

    private static function importe(float $valor): string
    {
        $valor = round(abs($valor), 2);
        if ($valor >= 1000000) {
            throw new InvalidArgumentException(
                'Hay un importe de '.number_format($valor, 2, '.', '').' que no entra en el formato de 6 enteros del shopping.'
            );
        }

        return sprintf('%09.2f', $valor);
    }

    private static function ceros(string $valor, int $largo): string
    {
        $digitos = preg_replace('/\D+/', '', $valor) ?? '';
        if (strlen($digitos) > $largo) {
            $digitos = substr($digitos, -$largo);
        }

        return str_pad($digitos, $largo, '0', STR_PAD_LEFT);
    }
}
