<?php

namespace App\Support\Contable\LibroIvaDigital;

/**
 * Validaciones RG 4597 / diseño de registros ARCA, previas a exportar.
 *
 * @see https://www.arca.gob.ar/iva/documentos/Libro-IVA-Digital-Especificaciones.pdf
 * @see https://www.arca.gob.ar/iva/documentos/libro-iva-digital-diseno-registros.pdf
 */
final class LibroIvaDigitalValidacionSupport
{
    private const MAX_AVISOS = 20;

    /** @var list<string> */
    private const ALICUOTAS_VALIDAS = ['0003', '0004', '0005', '0006', '0008', '0009'];

    /** @var list<string> */
    private const CODIGOS_OPERACION = [' ', '0', 'A', 'C', 'D', 'E', 'N', 'T', 'X', 'Z'];

    /** @var list<string> */
    private const TIPOS_PV_CERO = ['033', '099', '331', '332'];

    /**
     * @param  array<string, mixed>  $resultado
     * @return list<string>
     */
    public static function validar(array $resultado): array
    {
        $avisos = [];

        self::validarLongitudes($resultado, $avisos);
        self::validarVentasCbteYAlicuotas($resultado, $avisos);
        self::validarComprasCbte($resultado, $avisos);
        self::validarTotalesIvaSimple($resultado, $avisos);
        self::validarActividades($resultado, $avisos);

        return $avisos;
    }

    /**
     * @param  list<string>  $avisos
     */
    private static function validarLongitudes(array $resultado, array &$avisos): void
    {
        $muestras = [
            ['ventas.ventas_cbte', 266, 'VENTAS_CBTE'],
            ['ventas.ventas_alicuotas', 62, 'VENTAS_ALICUOTAS'],
            ['compras.compras_cbte', 325, 'COMPRAS_CBTE'],
            ['compras.compras_alicuotas', 84, 'COMPRAS_ALICUOTAS'],
            ['anulados.ventas', 44, 'VENTAS_ANULADOS'],
            ['anulados.compras', 44, 'COMPRAS_ANULADOS'],
            ['importaciones.importacion_bienes_alicuotas', 50, 'IMPORT_BIENES_ALIC'],
            ['importaciones.importacion_servicios', 211, 'IMPORT_SERVICIOS'],
        ];

        foreach ($muestras as [$path, $len, $label]) {
            $contenido = (string) data_get($resultado, $path, '');
            if ($contenido === '') {
                continue;
            }
            foreach (self::lineas($contenido) as $i => $linea) {
                if (strlen($linea) !== $len) {
                    $avisos[] = "Registro {$label} línea ".($i + 1).": longitud {$len} esperada, obtuvo ".strlen($linea).'.';
                    break;
                }
            }
        }
    }

    /**
     * @param  list<string>  $avisos
     */
    private static function validarVentasCbteYAlicuotas(array $resultado, array &$avisos): void
    {
        $cbteTxt = (string) data_get($resultado, 'ventas.ventas_cbte', '');
        $alicTxt = (string) data_get($resultado, 'ventas.ventas_alicuotas', '');
        $cbteCount = (int) ($resultado['ventas']['resumen']['comprobantes'] ?? 0);
        $alicCount = (int) ($resultado['ventas']['resumen']['alicuotas'] ?? 0);

        if ($cbteCount === 0 && ($resultado['compras']['resumen']['comprobantes'] ?? 0) === 0) {
            $avisos[] = 'Sin movimientos de ventas ni compras en el período (verifique «Con/Sin movimientos» en ARCA).';
        }
        if ($cbteTxt === '') {
            return;
        }

        $alicuotasPorClave = [];
        foreach (self::lineas($alicTxt) as $i => $linea) {
            if (strlen($linea) < 62) {
                continue;
            }
            $clave = self::claveAlicuotaVentas($linea);
            $alicuotasPorClave[$clave][] = [
                'linea' => $i + 1,
                'neto' => LibroIvaDigitalFormatoSupport::parseImporte15(substr($linea, 28, 15)),
                'codigo' => substr($linea, 43, 4),
                'iva' => LibroIvaDigitalFormatoSupport::parseImporte15(substr($linea, 47, 15)),
            ];
        }

        $pesTipoCambioMal = 0;
        $cantAlicCero = 0;
        $desbalance = 0;
        $alicFueraTabla = 0;
        $sinMatchAlic = 0;
        $ejemplos = [];

        foreach (self::lineas($cbteTxt) as $i => $linea) {
            if (count($avisos) >= self::MAX_AVISOS) {
                break;
            }
            if (strlen($linea) < 266) {
                continue;
            }

            $nroLinea = $i + 1;
            $tipo = substr($linea, 8, 3);
            $pv = (int) substr($linea, 11, 5);
            $nro = ltrim(substr($linea, 16, 20), '0');
            $hasta = ltrim(substr($linea, 36, 20), '0');
            $moneda = rtrim(substr($linea, 228, 3));
            $tipoCambio = LibroIvaDigitalFormatoSupport::parseTipoCambio10(substr($linea, 231, 10));
            $cantAlic = (int) substr($linea, 241, 1);
            $codOp = substr($linea, 242, 1);
            $total = LibroIvaDigitalFormatoSupport::parseImporte15(substr($linea, 108, 15));
            $noIntegra = LibroIvaDigitalFormatoSupport::parseImporte15(substr($linea, 123, 15));
            $percNocat = LibroIvaDigitalFormatoSupport::parseImporte15(substr($linea, 138, 15));
            $exentas = LibroIvaDigitalFormatoSupport::parseImporte15(substr($linea, 153, 15));
            $percNac = LibroIvaDigitalFormatoSupport::parseImporte15(substr($linea, 168, 15));
            $iibb = LibroIvaDigitalFormatoSupport::parseImporte15(substr($linea, 183, 15));
            $mun = LibroIvaDigitalFormatoSupport::parseImporte15(substr($linea, 198, 15));
            $internos = LibroIvaDigitalFormatoSupport::parseImporte15(substr($linea, 213, 15));
            $otros = LibroIvaDigitalFormatoSupport::parseImporte15(substr($linea, 243, 15));

            if ($moneda === 'PES' && abs($tipoCambio - 1.0) > 0.000001) {
                $pesTipoCambioMal++;
                if ($pesTipoCambioMal === 1) {
                    $ejemplos[] = "VENTAS_CBTE línea {$nroLinea}: moneda PES con tipo de cambio {$tipoCambio} (ARCA exige 1).";
                }
            }
            if ($moneda !== 'PES' && $moneda !== '' && abs($tipoCambio - 1.0) < 0.000001) {
                self::agregar($avisos, "VENTAS_CBTE línea {$nroLinea}: moneda {$moneda} con tipo de cambio 1 (debe ser la cotización de la operación).");
            }

            $esTipoC = in_array($tipo, LibroIvaDigitalVentasAlicuotaSupport::TIPOS_SIN_ALICUOTA, true);
            if (! $esTipoC && $cantAlic < 1) {
                $cantAlicCero++;
                if ($cantAlicCero === 1) {
                    $ejemplos[] = "VENTAS_CBTE línea {$nroLinea}: cantidad de alícuotas menor a 1 (tipo {$tipo}).";
                }
            }

            if (! in_array($codOp, self::CODIGOS_OPERACION, true)) {
                self::agregar($avisos, "VENTAS_CBTE línea {$nroLinea}: código de operación '{$codOp}' no está en la tabla ARCA.");
            }

            if (! in_array($tipo, self::TIPOS_PV_CERO, true) && ($pv < 1 || $pv > 9997)) {
                self::agregar($avisos, "VENTAS_CBTE línea {$nroLinea}: punto de venta {$pv} fuera de 00001-09997.");
            }

            $nroInt = (int) ($nro === '' ? '0' : $nro);
            $hastaInt = (int) ($hasta === '' ? '0' : $hasta);
            if ($hastaInt < $nroInt) {
                self::agregar($avisos, "VENTAS_CBTE línea {$nroLinea}: número hasta menor que número desde.");
            }

            $clave = $tipo.'|'.substr($linea, 11, 5).'|'.substr($linea, 16, 20);
            $filasAlic = $alicuotasPorClave[$clave] ?? [];
            if (count($filasAlic) !== $cantAlic) {
                $sinMatchAlic++;
                if ($sinMatchAlic === 1) {
                    $ejemplos[] = "VENTAS_CBTE línea {$nroLinea}: declara {$cantAlic} alícuota(s) y el archivo de alícuotas tiene ".count($filasAlic).'.';
                }
            }

            $netoIva = 0.0;
            foreach ($filasAlic as $fila) {
                if (! in_array($fila['codigo'], self::ALICUOTAS_VALIDAS, true)) {
                    $alicFueraTabla++;
                }
                $netoIva += $fila['neto'] + $fila['iva'];
                if (abs($fila['neto']) + 0.009 < abs($fila['iva']) && $fila['codigo'] !== '0003') {
                    self::agregar($avisos, "VENTAS_ALICUOTAS línea {$fila['linea']}: IVA mayor que neto gravado.");
                }
            }

            $suma = $noIntegra + $percNocat + $exentas + $percNac + $iibb + $mun + $internos + $otros + $netoIva;
            if (abs($suma - $total) > 0.05) {
                $desbalance++;
                if ($desbalance === 1) {
                    $ejemplos[] = sprintf(
                        'VENTAS_CBTE línea %d: total %.2f distinto de la suma de componentes %.2f.',
                        $nroLinea,
                        $total,
                        $suma,
                    );
                }
            }
        }

        if ($pesTipoCambioMal > 0) {
            self::agregar($avisos, $ejemplos[0].($pesTipoCambioMal > 1 ? " Hay {$pesTipoCambioMal} registros." : ''));
        }
        if ($cantAlicCero > 0) {
            foreach ($ejemplos as $ej) {
                if (str_contains($ej, 'alícuotas menor')) {
                    self::agregar($avisos, $ej.($cantAlicCero > 1 ? " Hay {$cantAlicCero} registros." : ''));
                    break;
                }
            }
        }
        if ($sinMatchAlic > 0) {
            foreach ($ejemplos as $ej) {
                if (str_contains($ej, 'archivo de alícuotas')) {
                    self::agregar($avisos, $ej.($sinMatchAlic > 1 ? " Hay {$sinMatchAlic} registros." : ''));
                    break;
                }
            }
        }
        if ($alicFueraTabla > 0) {
            self::agregar($avisos, "Hay {$alicFueraTabla} alícuota(s) con código fuera de la tabla ARCA (0003/0004/0005/0006/0008/0009).");
        }
        if ($desbalance > 0) {
            foreach ($ejemplos as $ej) {
                if (str_contains($ej, 'suma de componentes')) {
                    self::agregar($avisos, $ej.($desbalance > 1 ? " Hay {$desbalance} registros." : ''));
                    break;
                }
            }
        }

        $alicResumen = $alicCount;
        $cbteConAlic = (int) ($resultado['ventas']['resumen']['comprobantes_con_alicuotas'] ?? 0);
        if ($cbteConAlic > 0 && $alicResumen === 0) {
            $avisos[] = 'Hay comprobantes de venta con IVA discriminado pero el archivo de alícuotas está vacío.';
        }
    }

    /**
     * @param  list<string>  $avisos
     */
    private static function validarComprasCbte(array $resultado, array &$avisos): void
    {
        $cbteTxt = (string) data_get($resultado, 'compras.compras_cbte', '');
        if ($cbteTxt === '') {
            return;
        }

        $pesTipoCambioMal = 0;
        $primera = null;
        foreach (self::lineas($cbteTxt) as $i => $linea) {
            if (strlen($linea) < 238) {
                continue;
            }
            $moneda = rtrim(substr($linea, 224, 3));
            $tipoCambio = LibroIvaDigitalFormatoSupport::parseTipoCambio10(substr($linea, 227, 10));
            $tipo = substr($linea, 8, 3);
            $cantAlic = (int) substr($linea, 237, 1);
            $esTipoC = in_array($tipo, LibroIvaDigitalVentasAlicuotaSupport::TIPOS_SIN_ALICUOTA, true);

            if ($moneda === 'PES' && abs($tipoCambio - 1.0) > 0.000001) {
                $pesTipoCambioMal++;
                $primera ??= $i + 1;
            }
            if ($esTipoC && $cantAlic !== 0) {
                self::agregar($avisos, 'COMPRAS_CBTE línea '.($i + 1).": comprobante C debe informar cantidad de alícuotas 0 (tiene {$cantAlic}).");
            }
            if (! $esTipoC && $cantAlic < 1) {
                self::agregar($avisos, 'COMPRAS_CBTE línea '.($i + 1).": tipo {$tipo} debe informar alícuotas IVA (tiene {$cantAlic}).");
            }
        }
        if ($pesTipoCambioMal > 0) {
            self::agregar(
                $avisos,
                "COMPRAS_CBTE línea {$primera}: moneda PES con tipo de cambio distinto de 1."
                .($pesTipoCambioMal > 1 ? " Hay {$pesTipoCambioMal} registros." : ''),
            );
        }
    }

    /**
     * @param  list<string>  $avisos
     */
    private static function validarTotalesIvaSimple(array $resultado, array &$avisos): void
    {
        $totalIvaVentas = (float) ($resultado['ventas']['resumen']['total_iva'] ?? 0);
        $totalIvaSimple = (float) ($resultado['iva_simple']['resumen']['total_iva_debito'] ?? 0);

        if ($totalIvaVentas > 0 && $totalIvaSimple > 0) {
            $diff = abs($totalIvaVentas - $totalIvaSimple);
            if ($diff > max(1.0, $totalIvaVentas * 0.02)) {
                $avisos[] = sprintf(
                    'IVA débito ventas (%.2f) difiere del CSV IVA Simple (%.2f) en más del 2%%.',
                    $totalIvaVentas,
                    $totalIvaSimple,
                );
            }
        }
    }

    /**
     * @param  list<string>  $avisos
     */
    private static function validarActividades(array $resultado, array &$avisos): void
    {
        $sinActividad = (int) ($resultado['iva_simple']['resumen']['sin_actividad_arca'] ?? 0);
        if ($sinActividad > 0) {
            $avisos[] = "Hay {$sinActividad} agrupación(es) IVA Simple sin código de actividad ARCA (000000). Revise PV y ventas.";
        }
    }

    /**
     * @param  list<string>  $avisos
     */
    private static function agregar(array &$avisos, string $mensaje): void
    {
        if (count($avisos) >= self::MAX_AVISOS) {
            return;
        }
        $avisos[] = $mensaje;
    }

    /**
     * @return list<string>
     */
    private static function lineas(string $contenido): array
    {
        $contenido = rtrim($contenido, "\r\n");
        if ($contenido === '') {
            return [];
        }

        return preg_split('/\r\n|\n|\r/', $contenido) ?: [];
    }

    private static function claveAlicuotaVentas(string $linea): string
    {
        return substr($linea, 0, 3).'|'.substr($linea, 3, 5).'|'.substr($linea, 8, 20);
    }
}
