<?php

namespace App\Support\Stock;

use Illuminate\Support\Facades\File;

/**
 * Resolución de unidades de medida por nombre/abreviatura (sinónimos) y por código SIFAB.
 *
 * Una unidad del ERP guarda un solo código externo en `unidadmedida.codigo`. Si el importador
 * trae un código que no está, se busca por el nombre que SIFAB le da a ese código
 * (seed `database/data/sifab_maestros_interforming.json`).
 */
final class UnidadmedidaSifabSupport
{
    /** Cada grupo agrupa nombre, plural y abreviatura de la misma unidad. La clave es el primer elemento. */
    private const GRUPOS = [
        ['MINUTO', 'MINUTOS', 'MIN'],
        ['HORA', 'HORAS', 'HS'],
        ['LITRO', 'LITROS', 'LT'],
        ['KILO', 'KILOS', 'KG'],
        ['GRAMO', 'GRAMOS', 'GR'],
        ['METRO CUBICO', 'METROS CUBICOS', 'M3'],
        ['METRO', 'METROS', 'MT'],
        ['TONELADA', 'TONELADAS', 'TN'],
        ['UNIDAD', 'UNIDADES', 'UN'],
    ];

    /** @var array<string, array{0: string, 1: string}>|null */
    private static ?array $nombresSifab = null;

    public static function claveCanonica(?string $texto): ?string
    {
        $norm = self::normalizar($texto);
        if ($norm === '') {
            return null;
        }
        foreach (self::GRUPOS as $grupo) {
            if (in_array($norm, $grupo, true)) {
                return $grupo[0];
            }
        }

        return null;
    }

    /**
     * @param  iterable<object>  $unidades  objetos con id, nombre, abreviatura
     */
    public static function resolverIdPorNombre(iterable $unidades, ?string $nombre, ?string $abreviatura): ?int
    {
        $claves = array_values(array_filter([
            self::claveCanonica($nombre),
            self::claveCanonica($abreviatura),
        ]));
        $normales = array_values(array_filter([
            self::normalizar($nombre),
            self::normalizar($abreviatura),
        ]));

        $mejor = null;
        foreach ($unidades as $u) {
            $coincide = false;
            $clavesU = array_filter([
                self::claveCanonica($u->nombre ?? null),
                self::claveCanonica($u->abreviatura ?? null),
            ]);
            if ($claves !== [] && array_intersect($claves, $clavesU) !== []) {
                $coincide = true;
            } elseif (in_array(self::normalizar($u->nombre ?? null), $normales, true)
                && self::normalizar($u->nombre ?? null) !== '') {
                $coincide = true;
            }
            if ($coincide && ($mejor === null || (int) $u->id < $mejor)) {
                $mejor = (int) $u->id;
            }
        }

        return $mejor;
    }

    /**
     * Unidad para un código SIFAB que no está en `unidadmedida.codigo`: busca por el nombre SIFAB del código.
     *
     * @param  iterable<object>  $unidades
     */
    public static function resolverIdPorCodigoSifab(string $codigo, iterable $unidades): ?int
    {
        $meta = self::nombresSifab()[trim($codigo)] ?? null;
        if ($meta === null) {
            return null;
        }

        return self::resolverIdPorNombre($unidades, $meta[0], $meta[1]);
    }

    /**
     * @return array<string, array{0: string, 1: string}> codigo => [descripcion, descripcion corta]
     */
    public static function nombresSifab(): array
    {
        if (self::$nombresSifab !== null) {
            return self::$nombresSifab;
        }
        self::$nombresSifab = [];
        $path = database_path('data/sifab_maestros_interforming.json');
        if (! File::isFile($path)) {
            return self::$nombresSifab;
        }
        $data = json_decode(File::get($path), true);
        foreach (($data['unidadmedida'] ?? []) as $row) {
            $codigo = trim((string) ($row['codigoItem'] ?? ''));
            if ($codigo === '') {
                continue;
            }
            self::$nombresSifab[$codigo] = [
                (string) ($row['descripcionItem'] ?? ''),
                (string) ($row['descripcionItemCorta'] ?? ''),
            ];
        }

        return self::$nombresSifab;
    }

    private static function normalizar(?string $texto): string
    {
        $t = mb_strtoupper(trim((string) $texto));

        return strtr($t, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U']);
    }
}
