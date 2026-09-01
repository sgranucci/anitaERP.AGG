<?php

declare(strict_types=1);

namespace App\Support\Ventas;

/**
 * Mapeo tipo Anita (ven_tipo + ven_letra) → código AFIP para presentación CAEA.
 */
final class ArcaCaeaAnitaTipoAfipSupport
{
    /**
     * Tipos Anita habituales en PV CAEA (Bierzo / frigorífico).
     *
     * @return list<array{tipo_anita:string, letra:string, tipo_afip:int, etiqueta:string}>
     */
    public static function catalogoPresentacion(): array
    {
        return [
            ['tipo_anita' => 'FAC', 'letra' => 'A', 'tipo_afip' => 1, 'etiqueta' => 'FAC A (1)'],
            ['tipo_anita' => 'FAC', 'letra' => 'B', 'tipo_afip' => 6, 'etiqueta' => 'FAC B (6)'],
            ['tipo_anita' => 'NDR', 'letra' => 'A', 'tipo_afip' => 2, 'etiqueta' => 'NDR A (2)'],
            ['tipo_anita' => 'NDP', 'letra' => 'A', 'tipo_afip' => 2, 'etiqueta' => 'NDP A (2)'],
            ['tipo_anita' => 'NDE', 'letra' => 'A', 'tipo_afip' => 2, 'etiqueta' => 'NDE A (2)'],
            ['tipo_anita' => 'NDE', 'letra' => 'B', 'tipo_afip' => 7, 'etiqueta' => 'NDE B (7)'],
            ['tipo_anita' => 'NCD', 'letra' => 'A', 'tipo_afip' => 3, 'etiqueta' => 'NCD A (3)'],
            ['tipo_anita' => 'NCG', 'letra' => 'A', 'tipo_afip' => 3, 'etiqueta' => 'NCG A (3)'],
            ['tipo_anita' => 'NCP', 'letra' => 'A', 'tipo_afip' => 3, 'etiqueta' => 'NCP A (3)'],
            ['tipo_anita' => 'NCE', 'letra' => 'A', 'tipo_afip' => 3, 'etiqueta' => 'NCE A (3)'],
            ['tipo_anita' => 'NCE', 'letra' => 'B', 'tipo_afip' => 8, 'etiqueta' => 'NCE B (8)'],
            ['tipo_anita' => 'FCE', 'letra' => 'A', 'tipo_afip' => 201, 'etiqueta' => 'FCE A (201)'],
            ['tipo_anita' => 'FCE', 'letra' => 'B', 'tipo_afip' => 206, 'etiqueta' => 'FCE B (206)'],
        ];
    }

    /**
     * Catálogo filtrado por t_comp.tcomp_subdiar = V (IVA-ventas).
     *
     * @return list<array{tipo_anita:string, letra:string, tipo_afip:int, etiqueta:string}>
     */
    public static function catalogoPresentacionIvaVentas(): array
    {
        $out = [];
        $seen = [];
        foreach (self::catalogoPresentacion() as $item) {
            if (! ArcaCaeaAnitaIvaVentasSupport::vaAlSubdiarioIvaVentas($item['tipo_anita'])) {
                continue;
            }
            $clave = $item['tipo_anita'].'|'.$item['letra'].'|'.$item['tipo_afip'];
            if (isset($seen[$clave])) {
                continue;
            }
            $seen[$clave] = true;
            $out[] = $item;
        }

        foreach (ArcaCaeaAnitaIvaVentasSupport::tiposQueVanAlIvaVentas() as $tipoAnita) {
            foreach (['A', 'B', 'C'] as $letra) {
                $tipoAfip = self::tipoAfipDesdeAnita($tipoAnita, $letra);
                if ($tipoAfip <= 0) {
                    continue;
                }
                $clave = $tipoAnita.'|'.$letra.'|'.$tipoAfip;
                if (isset($seen[$clave])) {
                    continue;
                }
                $seen[$clave] = true;
                $out[] = [
                    'tipo_anita' => $tipoAnita,
                    'letra' => $letra,
                    'tipo_afip' => $tipoAfip,
                    'etiqueta' => $tipoAnita.' '.$letra.' ('.$tipoAfip.')',
                ];
            }
        }

        return $out;
    }

    /**
     * Tipos Anita IVA-ventas que numeran en el mismo tipo AFIP (ej. NCP/NCD/NCG → 3).
     *
     * @return list<string>
     */
    public static function tiposAnitaParaTipoAfip(int $tipoAfip): array
    {
        $letra = self::letraDesdeTipoAfip($tipoAfip);
        if ($letra === '') {
            return [];
        }

        $candidatos = [
            'FAC', 'FAU', 'FCE',
            'NDR', 'NDE', 'NDB', 'NDT', 'NDP', 'NDA', 'NDJ', 'NDI',
            'NCD', 'NCG', 'NCP', 'NCE', 'NCL', 'NCR', 'NCA', 'NCJ', 'NCI',
        ];
        $out = [];
        foreach ($candidatos as $tipoAnita) {
            if (self::tipoAfipDesdeAnita($tipoAnita, $letra) !== $tipoAfip) {
                continue;
            }
            if (! ArcaCaeaAnitaIvaVentasSupport::vaAlSubdiarioIvaVentas($tipoAnita)) {
                continue;
            }
            $out[] = $tipoAnita;
        }

        return $out;
    }

    public static function letraDesdeTipoAfip(int $tipoAfip): string
    {
        return match ($tipoAfip) {
            1, 2, 3, 51, 201, 202, 203 => 'A',
            6, 7, 8, 206, 207, 208 => 'B',
            11, 12, 13, 211 => 'C',
            default => '',
        };
    }

    public static function tipoAfipDesdeAnita(string $tipoAnita, string $letra): int
    {
        $tipo = strtoupper(trim($tipoAnita));
        $letra = strtoupper(trim($letra));

        return match ($tipo) {
            'FCE' => match ($letra) {
                'A' => 201,
                'B' => 206,
                'C' => 211,
                default => 0,
            },
            'FAC', 'FA', 'FAU' => match ($letra) {
                'A' => 1,
                'B' => 6,
                'C' => 11,
                'M' => 51,
                default => 0,
            },
            'NDE', 'ND', 'NDR', 'NDB', 'NDT', 'NDP', 'NDA', 'NDJ', 'NDI' => match ($letra) {
                'A' => 2,
                'B' => 7,
                'C' => 12,
                default => 0,
            },
            'NCE', 'NC', 'NCD', 'NCG', 'NCP', 'NCL', 'NCR', 'NCA', 'NCJ', 'NCI' => match ($letra) {
                'A' => 3,
                'B' => 8,
                'C' => 13,
                default => 0,
            },
            default => ctype_digit($tipo) ? (int) $tipo : 0,
        };
    }

    /**
     * @return array{tipo_anita:string, letra:string}|null
     */
    public static function anitaDesdeTipoAfip(int $tipoAfip): ?array
    {
        return match ($tipoAfip) {
            1 => ['tipo_anita' => 'FAC', 'letra' => 'A'],
            6 => ['tipo_anita' => 'FAC', 'letra' => 'B'],
            11 => ['tipo_anita' => 'FAC', 'letra' => 'C'],
            2 => ['tipo_anita' => 'NDR', 'letra' => 'A'],
            7 => ['tipo_anita' => 'NDE', 'letra' => 'B'],
            3 => ['tipo_anita' => 'NCD', 'letra' => 'A'],
            8 => ['tipo_anita' => 'NCE', 'letra' => 'B'],
            201 => ['tipo_anita' => 'FCE', 'letra' => 'A'],
            206 => ['tipo_anita' => 'FCE', 'letra' => 'B'],
            211 => ['tipo_anita' => 'FCE', 'letra' => 'C'],
            202 => ['tipo_anita' => 'NDE', 'letra' => 'A'],
            207 => ['tipo_anita' => 'NDE', 'letra' => 'B'],
            203 => ['tipo_anita' => 'NCE', 'letra' => 'A'],
            208 => ['tipo_anita' => 'NCE', 'letra' => 'B'],
            default => null,
        };
    }

    public static function esFce(int $tipoAfip): bool
    {
        return in_array($tipoAfip, [201, 202, 203, 206, 207, 208, 211], true);
    }
}
