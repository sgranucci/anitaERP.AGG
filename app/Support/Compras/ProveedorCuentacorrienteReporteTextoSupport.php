<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Listado\CoincidenciaFlexibleTexto;

/**
 * Filtro de texto sobre filas ya armadas del reporte CC proveedores (estilo índices CRUD).
 * Conserva el bloque completo de cada proveedor que coincida.
 */
final class ProveedorCuentacorrienteReporteTextoSupport
{
    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function aplicar(array $resultado, array $filtros): array
    {
        if (! ProveedorCuentacorrienteReporteFiltros::tieneFiltrosTextoAplicados($filtros)) {
            return $resultado;
        }

        $filas = $resultado['filas'] ?? [];
        if (! is_array($filas) || $filas === []) {
            return $resultado;
        }

        $filasFiltradas = self::filtrarFilas($filas, $filtros);
        $resultado['filas'] = $filasFiltradas;
        $resultado['totales'] = self::recalcularTotales(
            $filasFiltradas,
            $resultado['totales'] ?? [],
            (string) ($filtros['modo'] ?? ProveedorCuentacorrienteReporteFiltros::MODO_DEUDA)
        );
        $resultado['stats'] = self::recalcularStats($filasFiltradas);

        if (! empty($resultado['secciones']) && is_array($resultado['secciones'])) {
            $secciones = [];
            foreach ($resultado['secciones'] as $seccion) {
                $filasSeccion = self::filtrarFilas($seccion['filas'] ?? [], $filtros);
                if ($filasSeccion === []) {
                    continue;
                }
                $seccion['filas'] = $filasSeccion;
                $seccion['totales'] = self::recalcularTotales(
                    $filasSeccion,
                    $seccion['totales'] ?? [],
                    (string) ($filtros['modo'] ?? ProveedorCuentacorrienteReporteFiltros::MODO_DEUDA)
                );
                $seccion['stats'] = self::recalcularStats($filasSeccion);
                $secciones[] = $seccion;
            }
            $resultado['secciones'] = $secciones;
        }

        if ($filasFiltradas === [] && empty($resultado['advertencias'])) {
            $resultado['advertencias'] = ['Sin filas para el filtro de texto indicado.'];
        } elseif ($filasFiltradas === []) {
            $resultado['advertencias'][] = 'Sin filas para el filtro de texto indicado.';
        }

        return $resultado;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public static function filtrarFilas(array $filas, array $filtros): array
    {
        $proveedorIds = [];
        foreach ($filas as $fila) {
            if (! self::filaCoincide($fila, $filtros)) {
                continue;
            }
            $proveedorId = (int) ($fila['proveedor_id'] ?? 0);
            if ($proveedorId > 0) {
                $proveedorIds[$proveedorId] = true;
            }
        }

        if ($proveedorIds === []) {
            return [];
        }

        $out = [];
        $i = 0;
        $n = count($filas);
        while ($i < $n) {
            $fila = $filas[$i];
            $tipo = (string) ($fila['tipo'] ?? '');

            if ($tipo === 'header_empresa') {
                $j = $i + 1;
                $bloque = [$fila];
                $incluyeMatch = false;
                while ($j < $n && ($filas[$j]['tipo'] ?? '') !== 'header_empresa') {
                    $bloque[] = $filas[$j];
                    $pid = (int) ($filas[$j]['proveedor_id'] ?? 0);
                    if ($pid > 0 && isset($proveedorIds[$pid])) {
                        $incluyeMatch = true;
                    }
                    $j++;
                }
                if ($incluyeMatch) {
                    foreach ($bloque as $idx => $rowBloque) {
                        if ($idx === 0) {
                            $out[] = $rowBloque;
                            continue;
                        }
                        $pid = (int) ($rowBloque['proveedor_id'] ?? 0);
                        if ($pid > 0 && isset($proveedorIds[$pid])) {
                            $out[] = $rowBloque;
                        }
                    }
                }
                $i = $j;
                continue;
            }

            $pid = (int) ($fila['proveedor_id'] ?? 0);
            if ($pid > 0 && isset($proveedorIds[$pid])) {
                $out[] = $fila;
            }
            $i++;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, mixed>  $filtros
     */
    public static function filaCoincide(array $fila, array $filtros): bool
    {
        $operador = (string) ($filtros['filtro_operador'] ?? 'contiene');
        $valor = trim((string) ($filtros['filtro_valor'] ?? ''));
        $modo = (string) ($filtros['filtro_modo'] ?? ProveedorCuentacorrienteReporteFiltros::FILTRO_MODO_TODOS);

        if ($operador !== 'vacio' && $valor === '') {
            return true;
        }

        $campos = $modo === ProveedorCuentacorrienteReporteFiltros::FILTRO_MODO_CAMPO
            ? [((string) ($filtros['filtro_campo'] ?? 'proveedor_nombre'))]
            : array_keys(ProveedorCuentacorrienteReporteFiltros::CAMPOS_TEXTO);

        foreach ($campos as $campo) {
            if (! isset(ProveedorCuentacorrienteReporteFiltros::CAMPOS_TEXTO[$campo])) {
                continue;
            }
            $prop = ProveedorCuentacorrienteReporteFiltros::CAMPOS_TEXTO[$campo]['prop'];
            $texto = self::valorComoTexto($fila[$prop] ?? null);
            if (self::textoCoincide($texto, $operador, $valor, $campo)) {
                return true;
            }
        }

        return false;
    }

    private static function valorComoTexto(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }
        if (is_float($valor) || is_int($valor)) {
            return (string) $valor;
        }

        return trim((string) $valor);
    }

    private static function textoCoincide(string $texto, string $operador, string $valor, string $campo): bool
    {
        if ($operador === 'vacio') {
            return $texto === '';
        }

        $hay = mb_strtolower($texto);
        $bus = mb_strtolower($valor);

        return match ($operador) {
            'igual' => $hay === $bus,
            'distinto' => $hay !== $bus,
            'empieza' => $bus === '' || str_starts_with($hay, $bus),
            'termina' => $bus === '' || str_ends_with($hay, $bus),
            default => self::contieneFlexible($hay, $bus, $campo),
        };
    }

    private static function contieneFlexible(string $haystack, string $needle, string $campo): bool
    {
        if ($needle === '') {
            return true;
        }
        if (mb_strpos($haystack, $needle) !== false) {
            return true;
        }

        if (! in_array($campo, ProveedorCuentacorrienteReporteFiltros::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
            return false;
        }

        $min = $campo === 'proveedor_codigo'
            ? CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO
            : CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT;
        if (mb_strlen($needle) < $min) {
            return false;
        }

        $pref = mb_substr($needle, 0, 3);
        $longitudSufijo = mb_strlen($needle) >= 8 ? 5 : 4;
        $suf = mb_substr($needle, -$longitudSufijo);
        if ($pref === '' || $suf === '' || $pref === $suf) {
            return false;
        }

        return mb_strpos($haystack, $pref) !== false && mb_strpos($haystack, $suf) !== false;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $totalesBase
     * @return array<string, mixed>
     */
    private static function recalcularTotales(array $filas, array $totalesBase, string $modo): array
    {
        $debe = 0.0;
        $haber = 0.0;
        $pendiente = 0.0;
        $saldosPorMoneda = [];

        foreach ($filas as $fila) {
            if (($fila['tipo'] ?? '') !== 'total_proveedor') {
                continue;
            }
            $debe += (float) ($fila['debe'] ?? 0);
            $haber += (float) ($fila['haber'] ?? 0);
            $pendiente += (float) ($fila['saldo_pendiente'] ?? 0);
            foreach ($fila['saldos_por_moneda'] ?? [] as $monedaId => $monto) {
                $saldosPorMoneda[(int) $monedaId] = round(
                    ($saldosPorMoneda[(int) $monedaId] ?? 0) + (float) $monto,
                    2
                );
            }
        }

        return [
            'debe' => round($debe, 2),
            'haber' => round($haber, 2),
            'pendiente' => round($pendiente, 2),
            'saldos_por_moneda' => $saldosPorMoneda !== []
                ? $saldosPorMoneda
                : ($totalesBase['saldos_por_moneda'] ?? []),
            'abreviatura' => $totalesBase['abreviatura'] ?? '',
            'modo' => $modo,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{proveedores: int, movimientos: int, aplicaciones: int}
     */
    private static function recalcularStats(array $filas): array
    {
        $proveedores = [];
        $movimientos = 0;
        $aplicaciones = 0;
        foreach ($filas as $fila) {
            $tipo = (string) ($fila['tipo'] ?? '');
            $pid = (int) ($fila['proveedor_id'] ?? 0);
            if ($pid > 0) {
                $proveedores[$pid] = true;
            }
            if ($tipo === 'movimiento') {
                $movimientos++;
            } elseif ($tipo === 'aplicacion') {
                $aplicaciones++;
            }
        }

        return [
            'proveedores' => count($proveedores),
            'movimientos' => $movimientos,
            'aplicaciones' => $aplicaciones,
        ];
    }
}
