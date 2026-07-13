<?php

namespace App\Support\Contable;

use App\Models\Caja\RendicionEstacionamientoCaja;
use Illuminate\Support\Collection;

/**
 * Agrega cobrado de rendiciones estacionamiento por cuenta de caja (medio).
 */
final class CierreRendicionEstacionamientoMediosCobroSupport
{
    /**
     * @param  iterable<int, RendicionEstacionamientoCaja>  $rendiciones
     * @return array<int, array{cuentacaja_id: int, codigo: string, nombre: string, label: string, total: float}>
     *         keyed by cuentacaja_id
     */
    public static function agregarDesdeRendiciones(iterable $rendiciones): array
    {
        /** @var array<int, array{cuentacaja_id: int, codigo: string, nombre: string, label: string, total: float}> $out */
        $out = [];

        foreach ($rendiciones as $rendicion) {
            $rendicion->loadMissing(['movimientos.cuentacaja:id,codigo,nombre']);
            foreach ($rendicion->movimientos ?? [] as $mov) {
                $ccId = (int) ($mov->cuentacaja_id ?? 0);
                if ($ccId <= 0) {
                    continue;
                }
                $monto = round((float) ($mov->monto ?? 0), 2);
                if (! isset($out[$ccId])) {
                    $codigo = trim((string) ($mov->cuentacaja?->codigo ?? ''));
                    $nombre = trim((string) ($mov->cuentacaja?->nombre ?? ''));
                    $label = self::etiqueta($codigo, $nombre, $ccId);
                    $out[$ccId] = [
                        'cuentacaja_id' => $ccId,
                        'codigo' => $codigo,
                        'nombre' => $nombre,
                        'label' => $label,
                        'total' => 0.0,
                    ];
                }
                $out[$ccId]['total'] = round($out[$ccId]['total'] + $monto, 2);
            }
        }

        uasort($out, static function (array $a, array $b): int {
            $cmp = strcmp($a['codigo'], $b['codigo']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp($a['nombre'], $b['nombre']);
        });

        return $out;
    }

    /**
     * Columnas de medios presentes en el listado (unión ordenada).
     *
     * @param  list<array<int, array{cuentacaja_id: int, codigo: string, nombre: string, label: string, total: float}>>  $agregadosPorFila
     * @return list<array{cuentacaja_id: int, codigo: string, nombre: string, label: string}>
     */
    public static function columnasDesdeAgregados(array $agregadosPorFila): array
    {
        /** @var array<int, array{cuentacaja_id: int, codigo: string, nombre: string, label: string}> $cols */
        $cols = [];
        foreach ($agregadosPorFila as $agregado) {
            foreach ($agregado as $ccId => $medio) {
                $ccId = (int) $ccId;
                if ($ccId <= 0 || isset($cols[$ccId])) {
                    continue;
                }
                $cols[$ccId] = [
                    'cuentacaja_id' => $ccId,
                    'codigo' => (string) ($medio['codigo'] ?? ''),
                    'nombre' => (string) ($medio['nombre'] ?? ''),
                    'label' => (string) ($medio['label'] ?? self::etiqueta(
                        (string) ($medio['codigo'] ?? ''),
                        (string) ($medio['nombre'] ?? ''),
                        $ccId,
                    )),
                    'label_corto' => self::etiquetaCorta(
                        (string) ($medio['codigo'] ?? ''),
                        (string) ($medio['nombre'] ?? ''),
                        $ccId,
                    ),
                    'label_descripcion' => self::etiquetaDescripcion(
                        (string) ($medio['codigo'] ?? ''),
                        (string) ($medio['nombre'] ?? ''),
                        $ccId,
                    ),
                ];
            }
        }

        uasort($cols, static function (array $a, array $b): int {
            $cmp = strcmp($a['codigo'], $b['codigo']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp($a['nombre'], $b['nombre']);
        });

        return array_values($cols);
    }

    /**
     * @param  array<int, array{total?: float}>  $agregado
     */
    public static function montoDe(array $agregado, int $cuentacajaId): float
    {
        return round((float) ($agregado[$cuentacajaId]['total'] ?? 0), 2);
    }

    /**
     * @param  Collection<int, mixed>|iterable<int, mixed>  $filas
     * @return list<array{cuentacaja_id: int, codigo: string, nombre: string, label: string}>
     */
    public static function columnasDesdeFilasExport($filas, bool $vistaPorTurno): array
    {
        $agregados = [];
        if ($vistaPorTurno) {
            foreach ($filas as $row) {
                if ($row instanceof RendicionEstacionamientoCaja) {
                    $agregados[] = self::agregarDesdeRendiciones([$row]);
                }
            }
        } else {
            foreach ($filas as $grupo) {
                if (is_array($grupo) && isset($grupo['medios_cobro']) && is_array($grupo['medios_cobro'])) {
                    $agregados[] = $grupo['medios_cobro'];
                } elseif (is_array($grupo) && isset($grupo['rendiciones'])) {
                    $agregados[] = self::agregarDesdeRendiciones($grupo['rendiciones']);
                }
            }
        }

        return self::columnasDesdeAgregados($agregados);
    }

    public static function etiqueta(string $codigo, string $nombre, int $cuentacajaId): string
    {
        if ($codigo !== '' && $nombre !== '') {
            return $codigo.' — '.$nombre;
        }
        if ($codigo !== '') {
            return $codigo;
        }
        if ($nombre !== '') {
            return $nombre;
        }

        return '#'.$cuentacajaId;
    }

    /**
     * Descripción de medio para encabezado de columna (sin código).
     */
    public static function etiquetaDescripcion(string $codigo, string $nombre, int $cuentacajaId): string
    {
        $nombre = trim($nombre);
        if ($nombre !== '') {
            return $nombre;
        }
        if ($codigo !== '') {
            return $codigo;
        }

        return 'Cuenta #'.$cuentacajaId;
    }

    /**
     * Encabezado de columna corto (una línea) para Excel/PDF densos.
     */
    public static function etiquetaCorta(string $codigo, string $nombre, int $cuentacajaId): string
    {
        if ($codigo !== '') {
            return $codigo;
        }
        if ($nombre !== '') {
            return mb_strlen($nombre) > 18 ? mb_substr($nombre, 0, 17).'…' : $nombre;
        }

        return '#'.$cuentacajaId;
    }

    /**
     * Columna Excel a partir de índice 0 = A.
     */
    public static function columnaExcel(int $index0): string
    {
        $index0 = max(0, $index0);
        $letter = '';
        $n = $index0;
        do {
            $letter = chr(65 + ($n % 26)).$letter;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $letter;
    }
}
