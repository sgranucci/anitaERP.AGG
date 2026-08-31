<?php

namespace App\Support\Sueldos\Lsd;

/**
 * Columnas del registro 04 (F.931) y su equivalencia con el formato SIAP 99 de Anita.
 * No confundir con lsd_subsistemas (flags del TXT de conceptos, pos. 168-186).
 */
class LsdBases04Support
{
    /** @var list<string> */
    public const CLAVES_IMPORTE = [
        'aporte_adicional_os',
        'contrib_adicional_os',
        'rem_maternidad',
        'rem_bruta',
        'base_1',
        'base_2',
        'base_3',
        'base_4',
        'base_5',
        'base_6',
        'base_7',
        'base_8',
        'base_9',
        'base_dif_ap_ss',
        'base_dif_co_ss',
        'base_10',
        'importe_detraer',
    ];

    /** @var list<string> */
    public const CLAVES_CANTIDAD = [
        'dias_trabajados',
        'horas_trabajadas',
        'adherentes',
    ];

    /** @var array<string, string> */
    public const ETIQUETAS = [
        'dias_trabajados' => 'Días trabajados (reg. 04)',
        'horas_trabajadas' => 'Horas trabajadas',
        'adherentes' => 'Adherentes OS',
        'aporte_adicional_os' => 'Aporte adic. OS',
        'contrib_adicional_os' => 'Contrib. adic. OS',
        'rem_maternidad' => 'Rem. maternidad ANSeS',
        'rem_bruta' => 'Remuneración bruta',
        'base_1' => 'Base imponible 1 (SIPA)',
        'base_2' => 'Base imponible 2',
        'base_3' => 'Base imponible 3',
        'base_4' => 'Base imponible 4',
        'base_5' => 'Base imponible 5',
        'base_6' => 'Base imponible 6',
        'base_7' => 'Base imponible 7',
        'base_8' => 'Base imponible 8 (LRT)',
        'base_9' => 'Base imponible 9',
        'base_dif_ap_ss' => 'Base dif. aportes SS',
        'base_dif_co_ss' => 'Base dif. contrib. SS',
        'base_10' => 'Base imponible 10',
        'importe_detraer' => 'Importe a detraer',
    ];

    /**
     * Campo siapcam del formato 99 → clave LSD.
     * Omite cónyuge/hijos (parentezco, no son conceptos de haberes).
     *
     * @var array<int, string>
     */
    public const ANITA99_CAMPO = [
        22 => 'dias_trabajados',
        23 => 'horas_trabajadas',
        27 => 'adherentes',
        28 => 'aporte_adicional_os',
        29 => 'contrib_adicional_os',
        33 => 'rem_maternidad',
        34 => 'rem_bruta',
        35 => 'base_1',
        36 => 'base_2',
        37 => 'base_3',
        38 => 'base_4',
        39 => 'base_5',
        40 => 'base_6',
        41 => 'base_7',
        42 => 'base_8',
        43 => 'base_9',
        44 => 'base_dif_ap_ss',
        45 => 'base_dif_co_ss',
        46 => 'base_10',
        47 => 'importe_detraer',
    ];

    /** @return list<string> */
    public static function claves(): array
    {
        return array_merge(self::CLAVES_CANTIDAD, self::CLAVES_IMPORTE);
    }

    /**
     * @param  array<string, mixed>|null  $flags
     * @return array<string, int>
     */
    public static function normalizar(?array $flags): array
    {
        $out = [];
        if (! is_array($flags)) {
            return $out;
        }
        foreach (self::claves() as $k) {
            if (! array_key_exists($k, $flags)) {
                continue;
            }
            $s = (int) $flags[$k];
            if ($s === 1 || $s === -1) {
                $out[$k] = $s;
            }
        }

        return $out;
    }

    public static function tieneMapeo(?array $flags): bool
    {
        return self::normalizar($flags) !== [];
    }

    public static function claveDesdeCampoAnita(int $nroCampo): ?string
    {
        return self::ANITA99_CAMPO[$nroCampo] ?? null;
    }

    public static function signoAnita(?string $signo): int
    {
        return trim((string) $signo) === '-' ? -1 : 1;
    }

    public static function esCantidad(string $clave): bool
    {
        return in_array($clave, self::CLAVES_CANTIDAD, true);
    }

    /**
     * Agrupa filas siapcon (formato 99) en flags por código de concepto ERP/Anita.
     *
     * @param  iterable<int, object|array<string, mixed>>  $filas
     * @return array<int, array<string, int>>
     */
    public static function mapearDesdeSiapcon(iterable $filas, int $formato = 99): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $row = is_array($fila) ? $fila : (array) $fila;
            $fmt = (int) ($row['siapcn_formato'] ?? $row['formato'] ?? 0);
            if ($fmt !== $formato) {
                continue;
            }
            $codigo = (int) ($row['siapcn_concepto'] ?? $row['concepto'] ?? 0);
            $campo = (int) ($row['siapcn_nro_campo'] ?? $row['nro_campo'] ?? 0);
            $clave = self::claveDesdeCampoAnita($campo);
            if ($codigo <= 0 || $clave === null) {
                continue;
            }
            $out[$codigo][$clave] = self::signoAnita($row['siapcn_signo'] ?? $row['signo'] ?? '+');
        }
        foreach ($out as $codigo => $flags) {
            $out[$codigo] = self::normalizar($flags);
        }

        return $out;
    }

    public static function texto(?array $flags): string
    {
        $n = self::normalizar($flags);
        if ($n === []) {
            return '—';
        }
        $parts = [];
        foreach ($n as $k => $s) {
            $parts[] = ($s < 0 ? '-' : '+').$k;
        }

        return implode(', ', $parts);
    }
}
