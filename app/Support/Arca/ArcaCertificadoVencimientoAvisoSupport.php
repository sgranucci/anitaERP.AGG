<?php

namespace App\Support\Arca;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Selección de certificados ARCA a avisar: desde N días antes del vencimiento,
 * día por medio (también después de vencidos, hasta que se renueven).
 */
final class ArcaCertificadoVencimientoAvisoSupport
{
    /**
     * Días de calendario en Argentina entre hoy y el vencimiento.
     * Negativo si ya venció. 0 si vence hoy.
     */
    public static function diasCalendario(?int $validToTs, ?DateTimeImmutable $hoy = null): ?int
    {
        if ($validToTs === null || $validToTs <= 0) {
            return null;
        }

        $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
        $hoy = ($hoy ?? new DateTimeImmutable('today', $tz))->setTimezone($tz)->setTime(0, 0);
        $vence = (new DateTimeImmutable('@'.$validToTs))->setTimezone($tz)->setTime(0, 0);

        return (int) $hoy->diff($vence)->format('%r%a');
    }

    /**
     * true el día en que entran a la ventana y cada $cada días, inclusive vencidos.
     */
    public static function correspondeAvisar(int $dias, int $diasAntes, int $cada): bool
    {
        if ($dias > $diasAntes) {
            return false;
        }

        $cada = max(1, $cada);
        $offset = $diasAntes - $dias;

        return $offset % $cada === 0;
    }

    /**
     * @param  list<array<string, mixed>>  $inventario
     * @return list<array{empresa:string,servicios:string,alias:string,cuit:string,vence:string,dias:int,estado:string}>
     */
    public static function seleccionar(
        array $inventario,
        int $diasAntes,
        int $cada,
        ?DateTimeImmutable $hoy = null,
    ): array {
        /** @var array<string, array{empresa:string,servicios:list<string>,alias:string,cuit:string,vence:string,dias:int}> $grupos */
        $grupos = [];

        foreach ($inventario as $fila) {
            if (! is_array($fila) || empty($fila['existe_cert'])) {
                continue;
            }

            $dias = self::diasCalendario(
                isset($fila['valid_to_ts']) ? (int) $fila['valid_to_ts'] : null,
                $hoy,
            );
            if ($dias === null || ! self::correspondeAvisar($dias, $diasAntes, $cada)) {
                continue;
            }

            $clave = trim((string) ($fila['fingerprint_sha256'] ?? ''));
            if ($clave === '') {
                $clave = (string) ($fila['cert_path'] ?? '');
            }
            if ($clave === '') {
                continue;
            }

            $servicio = trim((string) ($fila['etiqueta'] ?? $fila['servicio'] ?? ''));
            if (! isset($grupos[$clave])) {
                $empresa = trim((string) ($fila['empresa_nombre'] ?? ''));
                if ($empresa === '' && ! empty($fila['empresa_id'])) {
                    $empresa = 'Empresa '.(int) $fila['empresa_id'];
                }
                $grupos[$clave] = [
                    'empresa' => $empresa !== '' ? $empresa : '—',
                    'servicios' => [],
                    'alias' => trim((string) ($fila['alias'] ?? '')),
                    'cuit' => trim((string) ($fila['cuit'] ?? '')),
                    'vence' => trim((string) ($fila['valid_to'] ?? '')),
                    'dias' => $dias,
                    'vence_ts' => isset($fila['valid_to_ts']) ? (int) $fila['valid_to_ts'] : null,
                ];
            }
            if ($servicio !== '' && ! in_array($servicio, $grupos[$clave]['servicios'], true)) {
                $grupos[$clave]['servicios'][] = $servicio;
            }
        }

        $salida = [];
        foreach ($grupos as $grupo) {
            $dias = $grupo['dias'];
            $salida[] = [
                'empresa' => $grupo['empresa'],
                'servicios' => $grupo['servicios'] !== [] ? implode(', ', $grupo['servicios']) : '—',
                'alias' => $grupo['alias'] !== '' ? $grupo['alias'] : '—',
                'cuit' => $grupo['cuit'] !== '' ? $grupo['cuit'] : '—',
                'vence' => $grupo['vence'] !== '' ? $grupo['vence'] : '—',
                'dias' => $dias,
                'estado' => self::estado($dias, $grupo['vence_ts'], $hoy),
            ];
        }

        usort($salida, static function (array $a, array $b): int {
            return $a['dias'] <=> $b['dias'] ?: strcmp($a['empresa'], $b['empresa']);
        });

        return $salida;
    }

    public static function estado(int $dias, ?int $validToTs = null, ?DateTimeImmutable $referencia = null): string
    {
        if ($dias < 0) {
            $n = abs($dias);

            return 'VENCIDO hace '.$n.' día'.($n === 1 ? '' : 's');
        }
        if ($dias === 0) {
            $ahora = $referencia ?? new DateTimeImmutable('now');
            if ($validToTs !== null && $validToTs <= $ahora->getTimestamp()) {
                return 'venció hoy';
            }

            return 'vence hoy';
        }

        return 'vence en '.$dias.' día'.($dias === 1 ? '' : 's');
    }

    /**
     * @param  list<array{empresa:string,servicios:string,alias:string,cuit:string,vence:string,dias:int,estado:string}>  $filas
     */
    public static function formatearLista(array $filas): string
    {
        if ($filas === []) {
            return 'Sin certificados en la ventana de aviso.';
        }

        $lineas = [];
        foreach ($filas as $fila) {
            $lineas[] = $fila['empresa']
                .' | '.$fila['servicios']
                .' | alias '.$fila['alias']
                .' | CUIT '.$fila['cuit']
                .' | vence '.$fila['vence']
                .' | '.$fila['estado'];
        }

        return implode("\n", $lineas);
    }
}
