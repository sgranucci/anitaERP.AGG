<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Caja\InterbankingArchivoPagoFormatoSupport;

/**
 * Carga masiva de solicitudes de retiro (Anita p-cargasolret.c).
 *
 * El CSV (BETSUR o DRO) trae las mismas columnas: operación, monto, estado,
 * nombre, apellido, DNI, CUIT, CBU y banco. Monto en cero no se graba.
 * Banco que empieza con "PROBABLE CVU" sale a frances.csv; el resto, a
 * solretiro.txt (cabecera *U* + renglones *M*, mismo layout que Interbanking).
 */
final class SolicitudRetiroCrownSupport
{
    public const OBSERVACION = 'Solicitudes de retiro';

    public const PREFIJO_CVU = 'PROBABLE CVU';

    /** Código de concepto del archivo Francés, fijo en p-cargasolret.c. */
    public const CONCEPTO_FRANCES = 'VAR';

    /**
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     omitidas: list<array{linea:int, motivo:string}>,
     *     interbanking: string,
     *     frances: string,
     *     cantidad_datos: int,
     *     cantidad_interbanking: int,
     *     cantidad_frances: int,
     *     cantidad_no_autorizadas: int,
     *     total_interbanking: float,
     *     total_frances: float
     * }
     */
    public static function procesar(string $csv, string $cbuOrigen, int $fechaYmd, int $secuencia): array
    {
        $csv = self::normalizarTexto($csv);
        $delimitador = self::delimitador($csv);
        $filas = [];
        $omitidas = [];
        $interbanking = [];
        $francesLineas = [];
        $cantidadDatos = 0;
        $noAutorizadas = 0;
        $lineaNro = 0;

        foreach (preg_split("/\r\n|\n|\r/", $csv) ?: [] as $linea) {
            $lineaNro++;
            if (trim($linea) === '') {
                continue;
            }

            $campos = str_getcsv($linea, $delimitador);
            $rawMonto = (string) ($campos[1] ?? '');
            if (preg_match('/\d/', $rawMonto) !== 1) {
                continue;
            }

            $cantidadDatos++;
            $monto = self::parseMonto($rawMonto);
            if (abs($monto) < 0.005) {
                $omitidas[] = ['linea' => $lineaNro, 'motivo' => 'Monto en cero'];

                continue;
            }

            $cbu = CbuSupport::normalizar((string) ($campos[7] ?? ''));
            if (strlen($cbu) !== 22) {
                $omitidas[] = [
                    'linea' => $lineaNro,
                    'motivo' => 'CBU destino de '.strlen($cbu).' dígitos (tienen que ser 22)',
                ];

                continue;
            }

            $banco = trim((string) ($campos[8] ?? ''));
            $esCvu = strncmp($banco, self::PREFIJO_CVU, strlen(self::PREFIJO_CVU)) === 0;
            $nombreOriginal = trim((string) ($campos[3] ?? ''));
            $apellidoOriginal = trim((string) ($campos[4] ?? ''));
            $nombreArchivo = self::limpiarNombre($nombreOriginal, $apellidoOriginal);
            $cuitOriginal = (string) ($campos[6] ?? '');
            $cuit = self::cuitArchivo($cuitOriginal);
            $estado = trim((string) ($campos[2] ?? ''));
            if ($estado !== '' && strcasecmp($estado, 'Autorizado') !== 0) {
                $noAutorizadas++;
            }

            $destino = $esCvu ? 'frances' : 'interbanking';
            $filas[] = [
                'linea' => $lineaNro,
                'operacion' => trim((string) ($campos[0] ?? '')),
                'nombre' => trim($nombreOriginal.' '.$apellidoOriginal),
                'nombre_archivo' => $nombreArchivo,
                'cuit' => $cuit,
                'cbu' => $cbu,
                'banco' => $banco,
                'monto' => round($monto, 2),
                'destino' => $destino,
                'estado' => $estado,
            ];

            if ($esCvu) {
                $francesLineas[] = self::lineaFrances($nombreArchivo, $cbu, $monto, $cuit);
            } else {
                $interbanking[] = ['cbu' => $cbu, 'importe' => round($monto, 2)];
            }
        }

        $archivoInterbanking = $interbanking === []
            ? ''
            : InterbankingArchivoPagoFormatoSupport::generarArchivo(
                $cbuOrigen,
                $fechaYmd,
                $secuencia,
                self::OBSERVACION,
                $interbanking
            );

        $totalIb = round(array_sum(array_column($interbanking, 'importe')), 2);
        $totalFr = round(array_sum(array_map(
            static fn (array $fila): float => $fila['destino'] === 'frances' ? (float) $fila['monto'] : 0.0,
            $filas
        )), 2);

        return [
            'filas' => $filas,
            'omitidas' => $omitidas,
            'interbanking' => $archivoInterbanking,
            'frances' => implode('', $francesLineas),
            'cantidad_datos' => $cantidadDatos,
            'cantidad_interbanking' => count($interbanking),
            'cantidad_frances' => count($francesLineas),
            'cantidad_no_autorizadas' => $noAutorizadas,
            'total_interbanking' => $totalIb,
            'total_frances' => $totalFr,
        ];
    }

    public static function normalizarTexto(string $contenido): string
    {
        if (str_starts_with($contenido, "\xEF\xBB\xBF")) {
            $contenido = substr($contenido, 3);
        }
        if ($contenido !== '' && ! mb_check_encoding($contenido, 'UTF-8')) {
            $convertido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
            if (is_string($convertido) && $convertido !== '') {
                $contenido = $convertido;
            }
        }

        return $contenido;
    }

    /**
     * Misma limpieza que p-cargasolret: lo que no está entre '0' y 'z' pasa a espacio.
     * Así "Ibañez" queda "Iba ez" y el CSV del Francés no trae acentos.
     */
    public static function limpiarNombre(string $nombre, string $apellido): string
    {
        $completo = $nombre.' '.$apellido;
        $out = '';
        $len = mb_strlen($completo, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($completo, $i, 1, 'UTF-8');
            $orden = mb_ord($ch, 'UTF-8');
            $out .= ($orden >= 48 && $orden <= 122) ? $ch : ' ';
        }

        return $out;
    }

    /**
     * CUIT de 11 dígitos. Con guiones ("23-16880450-4") coincide con el recorte
     * de p-cargasolret (%2.2s + 8 desde el 4.º carácter + el dígito verificador).
     */
    public static function cuitArchivo(string $cuit): string
    {
        $digitos = preg_replace('/\D+/', '', $cuit) ?? '';
        if (strlen($digitos) === 11) {
            return $digitos;
        }

        $crudo = rtrim($cuit, "\r\n");

        return substr($crudo, 0, 2).substr($crudo, 3, 8).substr($crudo, 12, 1);
    }

    public static function parseMonto(string $raw): float
    {
        $s = str_replace(['"', '$', ' '], '', $raw);
        $tieneComa = str_contains($s, ',');
        $tienePunto = str_contains($s, '.');
        if ($tieneComa && $tienePunto) {
            if (strrpos($s, ',') > strrpos($s, '.')) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        } elseif ($tieneComa) {
            $s = str_replace(',', '.', $s);
        }

        return (float) $s;
    }

    public static function lineaFrances(string $nombre, string $cbu, float $monto, string $cuit): string
    {
        return sprintf(
            "%s,%s,%013.2f,%s,%s\r\n",
            $nombre,
            $cbu,
            $monto,
            self::CONCEPTO_FRANCES,
            $cuit
        );
    }

    private static function delimitador(string $csv): string
    {
        $primera = strtok($csv, "\n") ?: $csv;
        $primera = rtrim($primera, "\r");

        return substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';
    }
}
