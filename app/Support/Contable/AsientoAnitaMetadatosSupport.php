<?php

declare(strict_types=1);

namespace App\Support\Contable;

final class AsientoAnitaMetadatosSupport
{
    public const ORIGEN_CTAMOV = 'ctamov';

    public const ORIGEN_CTAMOV_RESUMEN = 'ctamov_resumen';

    public const ORIGEN_SUBDIARIO = 'subdiario';

    public const ORIGEN_SUBHIST = 'subhist';

    /**
     * Observación generada por AnitaAsientoImportService:
     * "[SUBH] C COM X 1 159056" o "B COM X 1 156408".
     *
     * @return array{origen: string, sistema: string, tipo: string, letra: string, sucursal: int, nro: int}
     */
    public static function desdeObservacion(string $observacion): array
    {
        $observacion = trim($observacion);
        $origen = self::origenDesdeObservacion($observacion);
        $vacio = [
            'origen' => $origen,
            'sistema' => 'B',
            'tipo' => '',
            'letra' => ' ',
            'sucursal' => 0,
            'nro' => 0,
        ];

        $texto = trim((string) preg_replace(
            '/^\[(?:SUBH|SUBD|SUBHIST|SUBDIARIO|RESU)\]\s*/i',
            '',
            $observacion,
        ));
        if ($texto === '') {
            return $vacio;
        }

        $partes = preg_split('/\s+/', $texto) ?: [];
        $vacio['sistema'] = strtoupper(trim((string) ($partes[0] ?? 'B'))) ?: 'B';
        if (count($partes) < 3) {
            return $vacio;
        }

        // [RESU] agrega tipo de asiento entre sistema y comprobante.
        if ($origen === self::ORIGEN_CTAMOV_RESUMEN && count($partes) >= 5) {
            array_splice($partes, 1, 1);
        }

        $tipo = strtoupper(trim((string) ($partes[1] ?? '')));
        $nro = (int) array_pop($partes);
        $sucursal = 0;
        if (count($partes) > 2 && preg_match('/^\d+$/', (string) end($partes)) === 1) {
            $sucursal = (int) array_pop($partes);
        }
        $letra = count($partes) > 2 ? strtoupper(trim((string) array_pop($partes))) : ' ';

        if ($tipo === '' || $sucursal < 0 || $nro <= 0) {
            return $vacio;
        }

        return [
            'origen' => $origen,
            'sistema' => $vacio['sistema'],
            'tipo' => $tipo,
            'letra' => $letra !== '' ? $letra : ' ',
            'sucursal' => $sucursal,
            'nro' => $nro,
        ];
    }

    /**
     * Comprobante para la columna Tip del mayor.
     *
     * Una observación libre («ERASUN, NORMA NELIDA - Cobranza Nro. 77722») no es
     * un comprobante: el segundo término es el nombre del cliente y no debe
     * ocupar Tip. Sí valen el subdiario («T COB A 1 77384») y el asiento nativo
     * de ventas («FAC A 00012 82997»), donde el tipo es FAC/NCD/COB.
     *
     * @return array{origen: string, sistema: string, tipo: string, letra: string, sucursal: int, nro: int}
     */
    public static function comprobanteVisible(string $observacion): array
    {
        $parseado = self::desdeObservacion($observacion);
        $vacio = [
            'origen' => $parseado['origen'],
            'sistema' => preg_match('/^[A-Z]$/', (string) $parseado['sistema']) === 1
                ? (string) $parseado['sistema']
                : '',
            'tipo' => '',
            'letra' => ' ',
            'sucursal' => 0,
            'nro' => 0,
        ];

        $texto = trim((string) preg_replace(
            '/^\[(?:SUBH|SUBD|SUBHIST|SUBDIARIO|RESU)\]\s*/i',
            '',
            trim($observacion),
        ));
        if ($parseado['origen'] === self::ORIGEN_CTAMOV_RESUMEN) {
            $partesResu = preg_split('/\s+/', $texto) ?: [];
            if (count($partesResu) >= 5) {
                array_splice($partesResu, 1, 1);
                $texto = implode(' ', $partesResu);
            }
        }

        $partes = preg_split('/\s+/', $texto) ?: [];
        if (count($partes) < 2) {
            return $vacio;
        }

        $primero = strtoupper($partes[0]);
        $segundo = strtoupper($partes[1]);

        if (preg_match('/^[A-Z]$/', $primero) === 1
            && preg_match('/^[A-Z]{3}$/', $segundo) === 1
            && (int) $parseado['nro'] > 0
            && preg_match('/^[A-Z]{3}$/', (string) $parseado['tipo']) === 1) {
            $parseado['sistema'] = $primero;

            return $parseado;
        }

        $ultimo = (string) ($partes[count($partes) - 1] ?? '');
        if (preg_match('/^[A-Z]{3}$/', $primero) === 1
            && preg_match('/^[A-Z]$/', $segundo) === 1
            && preg_match('/^\d+$/', $ultimo) === 1) {
            $sucursal = 0;
            if (count($partes) >= 4 && preg_match('/^\d+$/', (string) $partes[2]) === 1) {
                $sucursal = (int) $partes[2];
            }

            return [
                'origen' => $parseado['origen'],
                'sistema' => '',
                'tipo' => $primero,
                'letra' => $segundo,
                'sucursal' => $sucursal,
                'nro' => (int) $ultimo,
            ];
        }

        return $vacio;
    }

    public static function origenDesdeObservacion(string $observacion): string
    {
        $normalizada = strtoupper(trim($observacion));
        if (str_starts_with($normalizada, '[SUBH]') || str_starts_with($normalizada, '[SUBHIST]')) {
            return self::ORIGEN_SUBHIST;
        }
        if (str_starts_with($normalizada, '[SUBD]') || str_starts_with($normalizada, '[SUBDIARIO]')) {
            return self::ORIGEN_SUBDIARIO;
        }
        if (str_starts_with($normalizada, '[RESU]')) {
            return self::ORIGEN_CTAMOV_RESUMEN;
        }

        return self::ORIGEN_CTAMOV;
    }

    public static function esDetalle(string $origen): bool
    {
        return in_array($origen, [self::ORIGEN_SUBDIARIO, self::ORIGEN_SUBHIST], true);
    }

    public static function claveAsiento(int $empresa, int $fechaYmd, int $numeroAsiento): string
    {
        return $empresa.'|'.$fechaYmd.'|'.$numeroAsiento;
    }
}
